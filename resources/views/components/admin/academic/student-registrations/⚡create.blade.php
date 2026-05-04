<?php

use Livewire\Component;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudentRegistration;
use Illuminate\Validation\Rule;

new class extends Component {
    public $studentProfileId = '';
    public $academicYearId = '';
    public $semesterNo = '';
    public $academicStatus = 'Aktif';
    public $registrationStatus = 'Draft';
    public $notes = '';
    public $isActive = true;
    public $studentProfiles = [];
    public $academicYears = [];
    public $searchStudent = '';
    public $activePeriod = null;
    public $isSuperuser = false;
    public $canCreate = true;

    public function mount(): void
    {
        $this->academicYears = AcademicYear::orderByDesc('created_at')->get();
        $this->loadStudentProfiles();
        
        // Check active academic period
        $this->activePeriod = AcademicPeriod::where('is_active', true)->first();
        
        // Check if user is superuser
        $this->isSuperuser = auth()->user()?->hasRole('superuser');
        
        // Determine if can create
        if (! $this->activePeriod && ! $this->isSuperuser) {
            $this->canCreate = false;
        }
    }

    public function loadStudentProfiles(): void
    {
        $query = StudentProfile::with('user', 'studyProgram');

        if ($this->searchStudent) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($subQ) {
                    $subQ->where('first_name', 'like', "%{$this->searchStudent}%")
                        ->orWhere('last_name', 'like', "%{$this->searchStudent}%")
                        ->orWhere('email', 'like', "%{$this->searchStudent}%");
                })->orWhere('nim', 'like', "%{$this->searchStudent}%");
            });
        }

        $this->studentProfiles = $query->limit(20)->get();
    }

    public function updatedSearchStudent(): void
    {
        $this->loadStudentProfiles();
    }

    public function save(): void
    {
        $rules = [
            'studentProfileId' => ['required', 'exists:student_profiles,id', Rule::unique('student_registrations', 'student_profile_id')
                ->where('academic_year_id', $this->academicYearId)
                ->whereNull('deleted_at')],
            'academicYearId' => 'required|exists:academic_years,id',
            'semesterNo' => 'nullable|integer|min:1|max:14',
            'academicStatus' => 'required|in:Aktif,Cuti,Nonaktif,Lulus,Drop Out,Keluar',
            'registrationStatus' => 'required|in:Draft,Submitted,Approved,Rejected,Cancelled',
            'isActive' => 'nullable|boolean',
        ];

        // Notes wajib jika tidak ada active period (create di luar period)
        if (! $this->activePeriod) {
            $rules['notes'] = 'required|string|min:10|max:500';
        } else {
            $rules['notes'] = 'nullable|string|max:500';
        }

        $this->validate($rules, [
            'studentProfileId.unique' => 'Mahasiswa ini sudah terdaftar untuk tahun akademik yang dipilih.',
            'notes.required' => 'Catatan wajib diisi karena registrasi dilakukan di luar periode terdaftar.',
            'notes.min' => 'Catatan minimal 10 karakter.',
        ]);

        try {
            StudentRegistration::create([
                'student_profile_id' => $this->studentProfileId,
                'academic_year_id' => $this->academicYearId,
                'semester_no' => $this->semesterNo ?: null,
                'academic_status' => $this->academicStatus,
                'registration_status' => $this->registrationStatus,
                'notes' => $this->notes ?: null,
                'is_active' => $this->isActive,
                'created_by' => auth()->id(),
            ]);

            session()->flash('success', 'Registrasi mahasiswa berhasil dibuat!');
            $this->redirect(route('admin.academic.student-registrations.index'));
        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.academic.student-registrations.index'));
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Buat Registrasi Mahasiswa Baru',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Registrasi Mahasiswa Baru</h5>
            </div>
            <div class="card-body">
                @if (! $activePeriod)
                    <div class="alert alert-warning mb-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Periode Registrasi Tidak Aktif</strong>
                        <br>
                        <small>Tidak ada periode registrasi yang sedang berjalan saat ini. {{ $isSuperuser ? 'Anda dapat tetap membuat registrasi dengan catatan wajib.' : 'Registrasi hanya dapat dilakukan oleh Superuser.' }}</small>
                    </div>
                @endif

                @if (! $canCreate)
                    <div class="alert alert-danger mb-3" role="alert">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Akses Ditolak</strong>
                        <br>
                        <small>Anda tidak memiliki izin untuk membuat registrasi di luar periode terdaftar. Hubungi administrator jika diperlukan.</small>
                    </div>
                @endif

                <form wire:submit.prevent="save" @if (! $canCreate) disabled @endif>
                    <fieldset @if (! $canCreate) disabled @endif>
                    <div class="mb-3">
                        <label class="form-label">Mahasiswa <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input 
                                type="text" 
                                class="form-control" 
                                wire:model.live="searchStudent" 
                                placeholder="Cari berdasarkan nama atau NIM..."
                            >
                            @if($searchStudent && count($studentProfiles) > 0)
                                <div class="dropdown-menu show w-100" style="position: static; margin-top: 2px;">
                                    @foreach($studentProfiles as $profile)
                                        <button 
                                            type="button" 
                                            class="dropdown-item text-start"
                                            wire:click="$set('studentProfileId', {{ $profile->id }}); $set('searchStudent', '{{ $profile->user?->name }} ({{ $profile->nim }})')"
                                        >
                                            <div><strong>{{ $profile->user?->name }}</strong></div>
                                            <small class="text-muted">NIM: {{ $profile->nim }} | {{ $profile->studyProgram?->name }}</small>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @if($studentProfileId)
                            <div class="mt-2 alert alert-info mb-0">
                                @php
                                    $selected = \App\Models\Academic\StudentProfile::find($studentProfileId);
                                @endphp
                                <strong>✓ Terpilih:</strong> {{ $selected?->user?->name }} ({{ $selected?->nim }}) - {{ $selected?->studyProgram?->name }}
                            </div>
                        @endif
                        @error('studentProfileId') <span class="text-danger d-block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tahun Akademik <span class="text-danger">*</span></label>
                        <select class="form-select" wire:model="academicYearId" required>
                            <option value="">Pilih Tahun Akademik</option>
                            @foreach($academicYears as $year)
                                <option value="{{ $year->id }}">{{ $year->name }}</option>
                            @endforeach
                        </select>
                        @error('academicYearId') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semester</label>
                            <input type="number" class="form-control" min="1" max="14" wire:model="semesterNo" placeholder="Opsional">
                            @error('semesterNo') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Akademik <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="academicStatus" required>
                                <option value="Aktif">Aktif</option>
                                <option value="Cuti">Cuti</option>
                                <option value="Nonaktif">Nonaktif</option>
                                <option value="Lulus">Lulus</option>
                                <option value="Drop Out">Drop Out</option>
                                <option value="Keluar">Keluar</option>
                            </select>
                            @error('academicStatus') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Registrasi <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="registrationStatus" required>
                                <option value="Draft">Draft (Belum Disubmit)</option>
                                <option value="Submitted">Submitted (Menunggu Approval)</option>
                                <option value="Approved">Approved (Disetujui)</option>
                                <option value="Rejected">Rejected (Ditolak)</option>
                                <option value="Cancelled">Cancelled (Dibatalkan)</option>
                            </select>
                            @error('registrationStatus') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model="isActive" checked>
                                <span class="form-check-label">Registrasi Aktif</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan @if (! $activePeriod)<span class="text-danger">*</span> <small class="text-muted">(Wajib diluar periode)</small>@else<small class="text-muted">(Opsional)</small>@endif</label>
                        <textarea class="form-control" wire:model="notes" rows="3" placeholder="Catatan tambahan"></textarea>
                        @error('notes') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary" @if (! $canCreate) disabled @endif>
                            <i class="fas fa-save me-1"></i> Buat Registrasi
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="cancel">
                            <i class="fas fa-times me-1"></i> Batal
                        </button>
                    </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div>
