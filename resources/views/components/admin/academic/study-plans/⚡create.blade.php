<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Support\Notifications\NotificationDispatchService;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public $studentProfileId = '';
    public $academicYearId = '';
    public $studentRegistrationId = '';
    public $semesterNo = '';
    public $status = 'Draft';
    public $notes = '';

    public $studentProfiles = [];
    public $academicYears = [];
    public $studentRegistrations = [];
    public $searchStudent = '';

    public function mount(): void
    {
        $this->academicYears = AcademicYear::orderByDesc('created_at')->get();
        $this->loadStudentProfiles();
    }

    public function loadStudentProfiles(): void
    {
        $query = StudentProfile::with('user', 'studyProgram');

        if (trim($this->searchStudent) !== '') {
            $keyword = trim($this->searchStudent);
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($subQ) use ($keyword) {
                    $subQ->where('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                })->orWhere('nim', 'like', "%{$keyword}%");
            });
        }

        $this->studentProfiles = $query->limit(15)->get();
    }

    public function updatedSearchStudent(): void
    {
        $this->loadStudentProfiles();
    }

    public function selectStudent(int $id): void
    {
        $this->studentProfileId = $id;
        $profile = StudentProfile::with('user', 'studyProgram')->find($id);
        $this->searchStudent = $profile ? ($profile->user?->name . ' (' . $profile->nim . ')') : '';
        $this->loadStudentRegistrations();
    }

    public function clearStudent(): void
    {
        $this->studentProfileId = '';
        $this->searchStudent = '';
        $this->loadStudentRegistrations();
    }

    public function updatedStudentProfileId(): void
    {
        $this->loadStudentRegistrations();
    }

    public function updatedAcademicYearId(): void
    {
        $this->loadStudentRegistrations();
    }

    public function loadStudentRegistrations(): void
    {
        if (! $this->studentProfileId || ! $this->academicYearId) {
            $this->studentRegistrations = [];
            $this->studentRegistrationId = '';

            return;
        }

        $this->studentRegistrations = StudentRegistration::query()
            ->where('student_profile_id', $this->studentProfileId)
            ->where('academic_year_id', $this->academicYearId)
            ->orderByDesc('created_at')
            ->get();

        if ($this->studentRegistrations->isEmpty()) {
            $this->studentRegistrationId = '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'studentProfileId' => [
                'required',
                'exists:student_profiles,id',
                Rule::unique('study_plans', 'student_profile_id')
                    ->where('academic_year_id', $this->academicYearId)
                    ->whereNull('deleted_at'),
            ],
            'academicYearId' => 'required|exists:academic_years,id',
            'studentRegistrationId' => 'nullable|exists:student_registrations,id',
            'semesterNo' => 'nullable|integer|min:1|max:14',
            'status' => 'required|in:Draft,Submitted,Approved,Rejected,Cancelled',
            'notes' => 'nullable|string',
        ], [
            'studentProfileId.unique' => 'KRS untuk mahasiswa dan tahun akademik ini sudah ada.',
        ]);

        try {
            $studyPlan = StudyPlan::create([
                'student_profile_id' => $this->studentProfileId,
                'academic_year_id' => $this->academicYearId,
                'student_registration_id' => $this->studentRegistrationId ?: null,
                'semester_no' => $this->semesterNo ?: null,
                'status' => $this->status,
                'notes' => $this->notes ?: null,
                'submitted_at' => $this->status === 'Submitted' ? now() : null,
                'approved_at' => $this->status === 'Approved' ? now() : null,
                'approved_by' => $this->status === 'Approved' ? auth()->id() : null,
                'created_by' => auth()->id(),
            ]);

            if (in_array($studyPlan->status, ['Submitted', 'Approved', 'Rejected', 'Cancelled'], true)) {
                app(NotificationDispatchService::class)->studyPlanStatusUpdated($studyPlan, $studyPlan->notes);
            }

            session()->flash('success', 'Header KRS berhasil dibuat. Silakan lengkapi detail mata kuliah.');

            $this->redirectRoute('admin.academic.study-plans.edit', ['id' => $studyPlan->id]);
        } catch (\Throwable $th) {
            session()->flash('error', 'Terjadi kesalahan: ' . $th->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.study-plans.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Buat KRS Baru',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Buat Kartu Rencana Studi (KRS)"
        description="Buat rancangan studi mahasiswa pada tahun akademik dan semester tertentu untuk pengisian mata kuliah."
        icon="book-open"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    <form wire:submit.prevent="save">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-file-alt fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Formulir Header KRS</h4>
                                <div class="text-muted small">Pilih mahasiswa dan periode akademik untuk membuat kerangka rancangan studi.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Mahasiswa <span class="text-danger">*</span></label>
                                @if($studentProfileId)
                                    @php
                                        $selected = \App\Models\Academic\StudentProfile::with('user', 'studyProgram')->find($studentProfileId);
                                    @endphp
                                    <div class="alert alert-info border-0 rounded-3 p-3 mb-0 d-flex align-items-center justify-content-between shadow-sm">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fa fa-user-check fs-5"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-6">{{ $selected?->user?->name ?? 'Mahasiswa' }} ({{ $selected?->nim ?? '-' }})</div>
                                                <div class="small text-muted">{{ $selected?->studyProgram?->name ?? '-' }} | Angkatan {{ $selected?->entry_year ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold" wire:click="clearStudent">
                                            <i class="fa fa-times me-1"></i> Ganti Mahasiswa
                                        </button>
                                    </div>
                                    <input type="hidden" wire:model="studentProfileId">
                                @else
                                    <div class="position-relative">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
                                            <input 
                                                type="text" 
                                                class="form-control border-start-0 ps-0" 
                                                wire:model.live.debounce.300ms="searchStudent" 
                                                placeholder="Ketik nama mahasiswa, NIM, atau email untuk mencari..."
                                            >
                                        </div>
                                        @if(count($studentProfiles) > 0)
                                            <div class="list-group shadow-lg border rounded-3 overflow-hidden mt-1 bg-white" style="max-height: 280px; overflow-y: auto;">
                                                @foreach($studentProfiles as $profile)
                                                    <button 
                                                        type="button" 
                                                        class="list-group-item list-group-item-action p-3 text-start d-flex align-items-center justify-content-between border-bottom"
                                                        wire:click="selectStudent({{ $profile->id }})"
                                                    >
                                                        <div>
                                                            <div class="fw-bold text-dark">{{ $profile->user?->name }}</div>
                                                            <div class="small text-muted"><i class="fa fa-id-card me-1 text-primary"></i> NIM: {{ $profile->nim }} | {{ $profile->studyProgram?->name }}</div>
                                                        </div>
                                                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-3 py-1 fw-semibold border border-primary border-opacity-25">Pilih <i class="fa fa-check ms-1"></i></span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="p-3 text-center border rounded-3 bg-light text-muted mt-1 small">
                                                <i class="fa fa-info-circle me-1"></i> Tidak ditemukan mahasiswa dengan kata kunci tersebut.
                                            </div>
                                        @endif
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
                                <label class="form-label fw-semibold">Registrasi Mahasiswa Terkait</label>
                                <select class="form-select" wire:model="studentRegistrationId">
                                    <option value="">Pilih Registration (Opsional)</option>
                                    @foreach($studentRegistrations as $registration)
                                        <option value="{{ $registration->id }}">
                                            #{{ $registration->id }} - {{ $registration->registration_status }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('studentRegistrationId') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Semester Ke</label>
                                <input type="number" min="1" max="14" class="form-control" wire:model="semesterNo" placeholder="Contoh: 3">
                                @error('semesterNo') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status Awal KRS <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="status" required>
                                    <option value="Draft">Draft (Belum Disubmit)</option>
                                    <option value="Submitted">Submitted (Menunggu Persetujuan)</option>
                                    <option value="Approved">Approved (Disetujui)</option>
                                    <option value="Rejected">Rejected (Ditolak)</option>
                                    <option value="Cancelled">Cancelled (Dibatalkan)</option>
                                </select>
                                @error('status') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan</label>
                                <textarea class="form-control" rows="3" wire:model="notes" placeholder="Tuliskan catatan atau keterangan tambahan apabila ada..."></textarea>
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
                                <h5 class="card-title fw-bold mb-1 text-dark">Simpan & Lanjutkan</h5>
                                <div class="text-muted small">Buat kerangka untuk mengisi kelas.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">Setelah menyimpan header KRS, Anda akan langsung diarahkan ke halaman pengelolaan detail mata kuliah/kelas yang akan diambil oleh mahasiswa.</p>
                        <div class="d-flex justify-content-end gap-2 border-top pt-3">
                            <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                <i class="fa fa-times me-1"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                                <i class="fa fa-save me-1"></i> Buat & Isi Matkul
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-light">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-2 text-dark"><i class="fa fa-info-circle text-primary me-2"></i>Panduan Pengisian KRS</h6>
                        <ul class="text-muted small mb-0 ps-3">
                            <li class="mb-1">Pastikan mahasiswa telah berstatus <strong>Aktif</strong> pada data registrasi semester.</li>
                            <li class="mb-1">Batas maksimal pengambilan SKS akan disesuaikan dengan Indeks Prestasi Semester (IPS) sebelumnya.</li>
                            <li>Status <strong>Draft</strong> memungkinkan mahasiswa untuk mengubah daftar mata kuliah secara mandiri.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
