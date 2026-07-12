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

<div>
    <x-alert />

    <x-admin.academic.header
        title="Buat Registrasi Mahasiswa Baru"
        description="Daftarkan status akademik dan her-registrasi mahasiswa pada tahun akademik dan semester tertentu."
        icon="user-check"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    @if (! $activePeriod)
        <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 p-4 d-flex align-items-center gap-3" role="alert">
            <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                <i class="fa fa-exclamation-triangle fs-5"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">Periode Registrasi Tidak Aktif</h6>
                <div class="small">Tidak ada periode registrasi yang sedang berjalan saat ini. {{ $isSuperuser ? 'Anda dapat tetap membuat registrasi dengan catatan wajib diisi.' : 'Registrasi di luar periode aktif hanya dapat dilakukan oleh Superuser.' }}</div>
            </div>
        </div>
    @endif

    @if (! $canCreate)
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 p-4 d-flex align-items-center gap-3" role="alert">
            <div class="bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                <i class="fa fa-ban fs-5"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">Akses Ditolak</h6>
                <div class="small">Anda tidak memiliki izin untuk membuat registrasi di luar periode terdaftar. Hubungi administrator apabila diperlukan.</div>
            </div>
        </div>
    @endif

    <form wire:submit.prevent="save">
        <fieldset @if (! $canCreate) disabled @endif>
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-user-graduate fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Formulir Registrasi Mahasiswa</h4>
                                <div class="text-muted small">Cari profil mahasiswa, pilih tahun akademik, serta tentukan status akademiknya.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Mahasiswa <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
                                        <input 
                                            type="text" 
                                            class="form-control border-start-0 ps-0" 
                                            wire:model.live="searchStudent" 
                                            placeholder="Cari berdasarkan nama, NIM, atau email mahasiswa..."
                                        >
                                    </div>
                                    @if($searchStudent && count($studentProfiles) > 0)
                                        <div class="dropdown-menu show w-100 shadow-lg border-0 rounded-3 p-2 mt-1" style="position: static; max-height: 260px; overflow-y: auto;">
                                            @foreach($studentProfiles as $profile)
                                                <button 
                                                    type="button" 
                                                    class="dropdown-item rounded-2 p-2 mb-1 text-start d-flex align-items-center justify-content-between"
                                                    wire:click="$set('studentProfileId', {{ $profile->id }}); $set('searchStudent', '{{ $profile->user?->name }} ({{ $profile->nim }})')"
                                                >
                                                    <div>
                                                        <div class="fw-bold text-dark">{{ $profile->user?->name }}</div>
                                                        <small class="text-muted">NIM: {{ $profile->nim }} | {{ $profile->studyProgram?->name }}</small>
                                                    </div>
                                                    <span class="badge bg-light text-dark border">Pilih</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @if($studentProfileId)
                                    <div class="mt-2 alert alert-info border-0 rounded-3 p-3 mb-0 d-flex align-items-center gap-2">
                                        @php
                                            $selected = \App\Models\Academic\StudentProfile::find($studentProfileId);
                                        @endphp
                                        <i class="fa fa-check-circle text-info fs-5"></i>
                                        <div>
                                            <strong>Terpilih:</strong> {{ $selected?->user?->name }} (NIM: {{ $selected?->nim }}) | <span class="text-muted">{{ $selected?->studyProgram?->name }}</span>
                                        </div>
                                    </div>
                                @endif
                                @error('studentProfileId') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tahun Akademik <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="academicYearId" required>
                                    <option value="">Pilih Tahun Akademik</option>
                                    @foreach($academicYears as $year)
                                        <option value="{{ $year->id }}">{{ $year->name }}</option>
                                    @endforeach
                                </select>
                                @error('academicYearId') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Semester Ke</label>
                                <input type="number" class="form-control" min="1" max="14" wire:model="semesterNo" placeholder="Contoh: 3">
                                @error('semesterNo') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Akademik <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="academicStatus" required>
                                    <option value="Aktif">Aktif</option>
                                    <option value="Cuti">Cuti</option>
                                    <option value="Nonaktif">Nonaktif</option>
                                    <option value="Lulus">Lulus</option>
                                    <option value="Drop Out">Drop Out</option>
                                    <option value="Keluar">Keluar</option>
                                </select>
                                @error('academicStatus') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Registrasi <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="registrationStatus" required>
                                    <option value="Draft">Draft (Belum Disubmit)</option>
                                    <option value="Submitted">Submitted (Menunggu Approval)</option>
                                    <option value="Approved">Approved (Disetujui)</option>
                                    <option value="Rejected">Rejected (Ditolak)</option>
                                    <option value="Cancelled">Cancelled (Dibatalkan)</option>
                                </select>
                                @error('registrationStatus') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan Tambahan @if (! $activePeriod)<span class="text-danger">*</span> <small class="text-muted fw-normal">(Wajib diluar periode)</small>@else<small class="text-muted fw-normal">(Opsional)</small>@endif</label>
                                <textarea class="form-control" wire:model="notes" rows="3" placeholder="Tulis catatan mengenai status her-registrasi mahasiswa..."></textarea>
                                @error('notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-sliders fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-1 text-dark">Status & Aksi</h5>
                                <div class="text-muted small">Atur keaktifan dan simpan data.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label fw-semibold" for="isActive">Registrasi Aktif</label>
                            <div class="text-muted small mt-1">Jika nonaktif, mahasiswa tidak dapat melakukan pengisian KRS untuk periode ini.</div>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                <i class="fa fa-times me-1"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm" @if (! $canCreate) disabled @endif>
                                <i class="fa fa-save me-1"></i> Simpan Registrasi
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-light">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-2 text-dark"><i class="fa fa-info-circle text-primary me-2"></i>Pedoman Registrasi</h6>
                        <ul class="text-muted small mb-0 ps-3">
                            <li class="mb-1"><strong>Aktif:</strong> Mahasiswa berhak mengisi Kartu Rencana Studi (KRS) semester berjalan.</li>
                            <li class="mb-1"><strong>Cuti:</strong> Mahasiswa mengambil cuti resmi; tidak dikenakan beban studi.</li>
                            <li><strong>Di Luar Periode:</strong> Pembuatan registrasi saat periode ditutup memerlukan otorisasi Superuser dan catatan penjelasan yang valid.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        </fieldset>
    </form>
</div>
