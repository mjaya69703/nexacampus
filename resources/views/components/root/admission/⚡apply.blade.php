<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Mail\AdmissionApplicationSubmitted;
use App\Support\Admission\AdmissionNumberService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?AdmissionPeriod $period = null;

    public array $form = [];

    public array $faculties = [];

    public array $studyPrograms = [];

    public array $requirements = [];

    public array $documentUploads = [];

    public ?string $submittedApplicationNumber = null;

    public ?string $submittedAccessToken = null;

    public array $stats = [];

    public function mount(): void
    {
        $this->period = AdmissionPeriod::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->whereDate('opens_at', '<=', now())
            ->whereDate('closes_at', '>=', now())
            ->orderByDesc('opens_at')
            ->first();

        $this->form = [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'birth_date' => '',
            'gender' => 'male',
            'address' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'high_school_name' => '',
            'high_school_major' => '',
            'high_school_graduation_year' => now()->year,
            'faculty_id' => '',
            'study_program_id' => '',
            'class_type' => 'regular',
        ];

        $this->faculties = Faculty::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $this->loadStudyPrograms();
        $this->loadRequirements();
        $this->loadStats();
    }

    public function updatedForm($value, string $key): void
    {
        if ($key === 'faculty_id') {
            $this->form['study_program_id'] = '';
            $this->loadStudyPrograms();
        }
    }

    public function loadStudyPrograms(): void
    {
        $this->studyPrograms = StudyProgram::query()
            ->where('is_active', true)
            ->when($this->form['faculty_id'] ?? null, fn ($query, $facultyId) => $query->where('faculty_id', $facultyId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function submit(AdmissionNumberService $numberService): void
    {
        abort_unless($this->period, 404);

        $rules = [
            'form.full_name' => 'required|string|max:255',
            'form.email' => 'required|email|max:255',
            'form.phone' => 'required|string|max:40',
            'form.birth_date' => 'required|date',
            'form.gender' => 'required|in:male,female',
            'form.address' => 'required|string',
            'form.emergency_contact_name' => 'required|string|max:255',
            'form.emergency_contact_phone' => 'required|string|max:40',
            'form.high_school_name' => 'required|string|max:255',
            'form.high_school_major' => 'required|string|max:255',
            'form.high_school_graduation_year' => 'required|integer|min:1980|max:'.(now()->year + 1),
            'form.faculty_id' => 'nullable|exists:faculties,id',
            'form.study_program_id' => 'nullable|exists:study_programs,id',
            'form.class_type' => 'nullable|in:regular,evening,weekend',
        ];

        foreach ($this->requirements as $requirement) {
            $key = 'documentUploads.'.$requirement['document_type'];
            $rule = ($requirement['is_required'] ? 'required' : 'nullable').'|file';
            $rules[$key] = $rule;
        }

        $validated = $this->validate($rules);
        $documentFiles = [];

        foreach ($this->requirements as $requirement) {
            $type = $requirement['document_type'];
            $file = $this->documentUploads[$type] ?? null;

            if (! $file) {
                continue;
            }

            $uploadError = $this->validateDocumentUpload($type, $file, $requirement);

            if ($uploadError) {
                $this->addError('documentUploads.'.$type, $uploadError[0]);
                return;
            }

            $documentFiles[$type] = [
                'file' => $file,
                'name' => $file->getClientOriginalName(),
                'size' => $this->uploadedFileSize($file),
            ];
        }

        $application = AdmissionApplication::create([
            ...$validated['form'],
            'admission_period_id' => $this->period->id,
            'application_number' => $numberService->generate($this->period),
            'access_token' => $numberService->token(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $application->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'submitted',
            'notes' => 'Application submitted from public form.',
            'changed_by' => null,
        ]);

        foreach ($this->requirements as $requirement) {
            $filePayload = $documentFiles[$requirement['document_type']] ?? null;

            if (! $filePayload) {
                continue;
            }

            $file = $filePayload['file'];
            $path = $file->store('admission/'.$application->application_number, 'public');

            $application->documents()->create([
                'document_requirement_id' => $requirement['id'],
                'document_type' => $requirement['document_type'],
                'file_path' => $path,
                'file_name' => $filePayload['name'],
                'file_size' => $filePayload['size'],
                'verification_status' => 'pending',
            ]);
        }

        $portalUrl = route('root.admission.portal', [
            'applicationNumber' => $application->application_number,
            'token' => $application->access_token,
        ]);

        try {
            Mail::to($application->email)->send(
                new AdmissionApplicationSubmitted($application->load(['period', 'studyProgram']), $portalUrl),
            );
        } catch (Throwable $exception) {
            Log::warning('Admission application email failed.', [
                'application_id' => $application->id,
                'email' => $application->email,
                'message' => $exception->getMessage(),
            ]);
        }

        $this->submittedApplicationNumber = $application->application_number;
        $this->submittedAccessToken = $application->access_token;
        $this->reset('documentUploads');
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'Apply',
        ]);
    }

    private function loadRequirements(): void
    {
        $this->requirements = $this->period
            ? $this->period->documentRequirements()
                ->orderBy('sort_order')
                ->get(['id', 'document_type', 'label', 'is_required', 'allowed_extensions', 'max_size_kb'])
                ->toArray()
            : [];
    }

    private function loadStats(): void
    {
        $this->stats = [
            'active_periods' => AdmissionPeriod::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->count(),
            'study_programs' => StudyProgram::query()
                ->where('is_active', true)
                ->count(),
            'requirements' => count($this->requirements),
            'days_left' => $this->period ? max(0, now()->startOfDay()->diffInDays($this->period->closes_at, false)) : 0,
        ];
    }

    private function uploadedFileSize($file): int
    {
        try {
            return (int) $file->getSize();
        } catch (Throwable $exception) {
            Log::warning('Admission temporary upload size unavailable.', [
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    private function validateDocumentUpload(string $type, $file, array $requirement): ?array
    {
        $fileSize = $this->uploadedFileSize($file);
        $maxBytes = ((int) ($requirement['max_size_kb'] ?: 2048)) * 1024;

        if ($fileSize <= 0) {
            return ['File belum selesai diunggah. Tunggu sebentar lalu submit lagi.'];
        }

        if ($fileSize > $maxBytes) {
            return ['Ukuran file melebihi batas '.number_format($maxBytes / 1024).' KB.'];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->allowedExtensions($requirement['allowed_extensions'] ?? null);

        if (! in_array($extension, $allowedExtensions, true)) {
            return ['Tipe file tidak diizinkan. Format yang diperbolehkan: '.implode(', ', $allowedExtensions).'.'];
        }

        $allowedMimeTypes = $this->allowedMimeTypes($allowedExtensions);
        $mimeType = $file->getMimeType();

        if ($mimeType && ! in_array($mimeType, $allowedMimeTypes, true)) {
            return ['Isi file tidak sesuai format yang diperbolehkan.'];
        }

        return null;
    }

    private function allowedExtensions(?string $extensions): array
    {
        $allowed = collect(explode(',', $extensions ?: 'pdf,jpg,jpeg,png'))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $safeAllowList = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        return array_values(array_intersect($allowed, $safeAllowList)) ?: ['pdf', 'jpg', 'jpeg', 'png'];
    }

    private function allowedMimeTypes(array $extensions): array
    {
        $map = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];

        return collect($extensions)
            ->flatMap(fn (string $extension) => $map[$extension] ?? [])
            ->unique()
            ->values()
            ->all();
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">
                {{-- Hero Banner --}}
                <div class="admission-hero mb-4">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Penerimaan Mahasiswa Baru NexaCampus</span>
                            </div>
                            <h1 class="admission-title mb-3">Mulai Langkah Akademik Anda Menuju Masa Depan Cemerlang.</h1>
                            <p class="admission-subtitle mb-4">
                                Portal pendaftaran terintegrasi. Lengkapi data diri, pilih program studi favorit, dan unggah berkas persyaratan dengan alur panduan interaktif yang cepat & transparan.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.admission.status') }}" class="btn btn-light btn px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-magnifying-glass text-primary"></i> Cek Status Pendaftaran
                                </a>
                                <a href="{{ route('auth.signin-index') }}" class="btn btn-outline-light btn px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-right-to-bracket"></i> Login Portal
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase tracking-wider">Gelombang Aktif</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ $period?->name ?? 'Tidak Ada Gelombang Aktif' }}</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold shadow-sm">Portal Resmi</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $stats['study_programs'] ?? 0 }}</span>
                                            <small class="text-white-50">Program Studi</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-warning">{{ $stats['days_left'] ?? 0 }}</span>
                                            <small class="text-white-50">Hari Sisa</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <x-alert />

                @if ($submittedApplicationNumber)
                    {{-- Submitted Success View --}}
                    <div class="admission-card admission-success py-4 my-4 shadow-sm border-0">
                        <div class="success-icon mx-auto mb-3 shadow">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <h2 class="fw-bolder mb-2 text-gradient">Pendaftaran Berhasil Dikirim!</h2>
                        <p class="text-muted mb-4 fs-5">Nomor Pendaftaran resmi Anda adalah:</p>
                        <div class="application-number-badge mb-4 mx-auto shadow-sm">
                            {{ $submittedApplicationNumber }}
                        </div>
                        <div class="alert alert-info border-0 bg-info bg-opacity-10 rounded-3 max-w-lg mx-auto mb-4 p-3 text-start d-flex align-items-center gap-3">
                            <i class="fas fa-circle-info fs-4 text-info flex-shrink-0"></i>
                            <div class="small">
                                Simpan nomor pendaftaran ini dan periksa email Anda (<strong class="text-body">{{ $form['email'] ?? '' }}</strong>) untuk melihat tautan akses portal verifikasi berkas dan jadwal ujian seleksi.
                            </div>
                        </div>
                        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                            <a href="{{ route('root.admission.portal', ['applicationNumber' => $submittedApplicationNumber, 'token' => $submittedAccessToken]) }}" class="btn btn-primary btn px-4 fw-bold shadow">
                                <i class="fas fa-user-check me-2"></i> Buka Portal Pendaftar
                            </a>
                            <a href="{{ route('root.admission.status') }}" class="btn btn-outline-primary btn px-4 fw-bold">
                                <i class="fas fa-magnifying-glass me-2"></i> Lacak Status Pendaftaran
                            </a>
                        </div>
                    </div>
                @elseif (! $period)
                    {{-- No Active Period View --}}
                    <div class="admission-card admission-empty py-4 my-4 text-center shadow-sm border-0">
                        <div class="empty-icon mx-auto mb-3 shadow">
                            <i class="fas fa-calendar-xmark"></i>
                        </div>
                        <h2 class="fw-bolder mb-2">Pendaftaran Belum Dibuka</h2>
                        <p class="text-muted max-w-md mx-auto mb-4 fs-6">
                            Saat ini belum ada gelombang penerimaan mahasiswa baru yang aktif. Silakan kembali lagi nanti atau periksa status pendaftaran Anda yang sudah ada sebelumnya.
                        </p>
                        <a href="{{ route('root.admission.status') }}" class="btn btn-primary btn px-4 fw-bold shadow-sm">
                            <i class="fas fa-magnifying-glass me-2"></i> Cek Aplikasi Saya
                        </a>
                    </div>
                @else
                    {{-- Quick Stats Row --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 col-6">
                            <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                                <div class="stat-icon-wrap bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <span class="stat-num">{{ $stats['active_periods'] }}</span>
                                    <small class="text-muted fw-semibold">Gelombang Aktif</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                                <div class="stat-icon-wrap bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <span class="stat-num">{{ $stats['study_programs'] }}</span>
                                    <small class="text-muted fw-semibold">Program Studi</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                                <div class="stat-icon-wrap bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-file-shield"></i>
                                </div>
                                <div>
                                    <span class="stat-num">{{ $stats['requirements'] }}</span>
                                    <small class="text-muted fw-semibold">Berkas Persyaratan</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                                <div class="stat-icon-wrap bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div>
                                    <span class="stat-num">{{ $stats['days_left'] }}</span>
                                    <small class="text-muted fw-semibold">Hari Sisa Pendaftaran</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Navigation Shortcuts --}}
                    <div class="admission-shortcuts mb-4 shadow-sm border-0 rounded-4">
                        <a href="{{ route('root.admission.status') }}" class="shortcut-link">
                            <div class="shortcut-icon"><i class="fas fa-location-dot"></i></div>
                            <div>
                                <div class="fw-bold text-body">Lacak Pendaftaran</div>
                                <small class="text-muted">Lihat progres seleksi</small>
                            </div>
                        </a>
                        <a href="#program" class="shortcut-link">
                            <div class="shortcut-icon"><i class="fas fa-building-columns"></i></div>
                            <div>
                                <div class="fw-bold text-body">Pilih Program Studi</div>
                                <small class="text-muted">Daftar fakultas & jurusan</small>
                            </div>
                        </a>
                        <a href="#documents" class="shortcut-link">
                            <div class="shortcut-icon"><i class="fas fa-folder-open"></i></div>
                            <div>
                                <div class="fw-bold text-body">Checklist Dokumen</div>
                                <small class="text-muted">Persyaratan & lampiran</small>
                            </div>
                        </a>
                    </div>

                    {{-- Step 1: Identity Card --}}
                    <div class="admission-card mb-4 shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="admission-card-header d-flex align-items-center justify-content-between p-4 bg-surface border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="step-badge">1</div>
                                <div>
                                    <h3 class="mb-0 fw-bolder">Identitas Calon Mahasiswa</h3>
                                    <small class="text-muted">Lengkapi data pribadi dan kontak darurat</small>
                                </div>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                                <i class="fas fa-calendar me-1"></i> {{ $period->opens_at?->format('d M') }} - {{ $period->closes_at?->format('d M Y') }}
                            </span>
                        </div>
                        <div class="admission-card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase">Nama Lengkap (Sesuai Ijazah) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control modern-input" wire:model.defer="form.full_name" placeholder="Contoh: Ahmad Rizki Pratama">
                                    @error('form.full_name') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small text-uppercase">Email Aktif <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control form-control modern-input" wire:model.defer="form.email" placeholder="email@domain.com">
                                    @error('form.email') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small text-uppercase">Nomor WhatsApp / HP <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control modern-input" wire:model.defer="form.phone" placeholder="081234567890">
                                    @error('form.phone') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-uppercase">Tanggal Lahir <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control modern-input" wire:model.defer="form.birth_date">
                                    @error('form.birth_date') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-uppercase">Jenis Kelamin <span class="text-danger">*</span></label>
                                    <select class="form-select form-select modern-input" wire:model.defer="form.gender">
                                        <option value="male">Laki-laki</option>
                                        <option value="female">Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-uppercase">Kelas Tujuan <span class="text-danger">*</span></label>
                                    <select class="form-select form-select modern-input" wire:model.defer="form.class_type">
                                        <option value="regular">Kelas Reguler Pagi</option>
                                        <option value="evening">Kelas Karyawan / Malam</option>
                                        <option value="weekend">Kelas Akhir Pekan / Ekstensi</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-uppercase">Alamat Lengkap Domisili <span class="text-danger">*</span></label>
                                    <textarea class="form-control modern-input" rows="3" wire:model.defer="form.address" placeholder="Sebutkan jalan, nomor rumah, RT/RW, kelurahan, kecamatan, dan kota/kabupaten"></textarea>
                                    @error('form.address') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Program & Education Card --}}
                    <div class="admission-card mb-4 shadow-sm border-0 rounded-4 overflow-hidden" id="program">
                        <div class="admission-card-header d-flex align-items-center justify-content-between p-4 bg-surface border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="step-badge">2</div>
                                <div>
                                    <h3 class="mb-0 fw-bolder">Pilihan Program & Riwayat Pendidikan</h3>
                                    <small class="text-muted">Pilih fakultas, program studi, dan asal sekolah</small>
                                </div>
                            </div>
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold">
                                <i class="fas fa-graduation-cap me-1"></i> {{ count($studyPrograms) }} Program Pilihan
                            </span>
                        </div>
                        <div class="admission-card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase">Fakultas Pilihan</label>
                                    <select class="form-select form-select modern-input" wire:model.live="form.faculty_id">
                                        <option value="">-- Pilih Fakultas --</option>
                                        @foreach ($faculties as $faculty)
                                            <option value="{{ $faculty['id'] }}">{{ $faculty['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase">Program Studi / Jurusan</label>
                                    <select class="form-select form-select modern-input" wire:model.defer="form.study_program_id">
                                        <option value="">-- Pilih Program Studi --</option>
                                        @foreach ($studyPrograms as $studyProgram)
                                            <option value="{{ $studyProgram['id'] }}">{{ $studyProgram['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.study_program_id') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-uppercase">Asal Sekolah (SMA/SMK/MA) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control modern-input" wire:model.defer="form.high_school_name" placeholder="Contoh: SMAN 1 Jakarta">
                                    @error('form.high_school_name') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-uppercase">Jurusan Asal <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control modern-input" wire:model.defer="form.high_school_major" placeholder="Contoh: IPA / Rekayasa Perangkat Lunak">
                                    @error('form.high_school_major') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-uppercase">Tahun Lulus <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control modern-input" wire:model.defer="form.high_school_graduation_year" placeholder="{{ now()->year }}">
                                    @error('form.high_school_graduation_year') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase">Nama Kontak Darurat (Orang Tua/Wali) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control modern-input" wire:model.defer="form.emergency_contact_name" placeholder="Nama Orang Tua / Wali">
                                    @error('form.emergency_contact_name') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase">No. HP Kontak Darurat <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control modern-input" wire:model.defer="form.emergency_contact_phone" placeholder="0812xxxxxx">
                                    @error('form.emergency_contact_phone') <span class="text-danger small mt-1 d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: Documents Card --}}
                    <div class="admission-card mb-5 shadow-sm border-0 rounded-4 overflow-hidden" id="documents">
                        <div class="admission-card-header d-flex align-items-center justify-content-between p-4 bg-surface border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="step-badge">3</div>
                                <div>
                                    <h3 class="mb-0 fw-bolder">Unggah Berkas Persyaratan</h3>
                                    <small class="text-muted">Pastikan scan atau foto dokumen terlihat jelas</small>
                                </div>
                            </div>
                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill fw-bold">
                                <i class="fas fa-file-invoice me-1"></i> {{ count($requirements) }} Item Persyaratan
                            </span>
                        </div>
                        <div class="admission-card-body p-4">
                            <div class="row g-4">
                                @foreach ($requirements as $requirement)
                                    <div class="col-md-6" wire:key="doc-{{ $requirement['id'] }}">
                                        <div class="document-upload p-4 rounded-4 h-100 d-flex flex-column justify-content-between shadow-sm">
                                            <div>
                                                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                                    <div class="fw-bolder fs-6 text-body">{{ $requirement['label'] }}</div>
                                                    @if($requirement['is_required'])
                                                        <span class="badge bg-danger px-2 py-1 rounded-pill">Wajib Diunggah</span>
                                                    @else
                                                        <span class="badge bg-secondary px-2 py-1 rounded-pill">Opsional</span>
                                                    @endif
                                                </div>
                                                <input type="file" class="form-control modern-input" wire:model="documentUploads.{{ $requirement['document_type'] }}">
                                                <small class="text-muted mt-2 d-block">
                                                    <i class="fas fa-circle-info me-1 text-primary"></i> Maks. {{ $requirement['max_size_kb'] ?? 2048 }} KB. Format: {{ strtoupper($requirement['allowed_extensions'] ?: 'pdf,jpg,png') }}
                                                </small>
                                            </div>
                                            @error('documentUploads.'.$requirement['document_type']) 
                                                <div class="text-danger small mt-2 fw-semibold"><i class="fas fa-exclamation-triangle me-1"></i>{{ $message }}</div> 
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-5 pt-4 border-top">
                                <div class="text-muted small">
                                    <i class="fas fa-shield-halved text-success me-1"></i> Data dan dokumen yang Anda kirimkan dijamin kerahasiaannya dan hanya digunakan untuk seleksi akademik.
                                </div>
                                <button class="btn btn-primary btn px-4 py-3 fw-bold rounded-pill shadow d-flex align-items-center gap-2 transition-transform" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,documentUploads">
                                    <i class="fas fa-paper-plane" wire:loading.remove></i>
                                    <span wire:loading.remove>Kirim Pendaftaran Sekarang</span>
                                    <span wire:loading><i class="fas fa-spinner fa-spin me-2"></i>Mengunggah & Memproses...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>


