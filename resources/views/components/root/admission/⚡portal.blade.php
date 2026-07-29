<?php

use App\Models\Admission\AdmissionApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public AdmissionApplication $application;

    public array $requirements = [];

    public array $documentUploads = [];

    public array $stats = [];

    public function mount(string $applicationNumber, string $token): void
    {
        $this->application = AdmissionApplication::query()
            ->with([
                'period.documentRequirements',
                'faculty',
                'studyProgram',
                'documents.requirement',
                'examParticipants.schedule',
                'scores.schedule',
                'user.studentProfile',
                'statusHistories.changedBy',
            ])
            ->where('application_number', $applicationNumber)
            ->where('access_token', $token)
            ->firstOrFail();

        $this->requirements = $this->application->period?->documentRequirements()
            ->orderBy('sort_order')
            ->get(['id', 'document_type', 'label', 'is_required', 'allowed_extensions', 'max_size_kb'])
            ->toArray() ?? [];

        $this->refreshStats();
    }

    public function uploadDocument(int $requirementId): void
    {
        $requirement = collect($this->requirements)->firstWhere('id', $requirementId);
        abort_unless($requirement, 404);

        $key = $requirement['document_type'];

        $this->validate([
            'documentUploads.'.$key => 'required|file',
        ]);

        $file = $this->documentUploads[$key];
        $uploadError = $this->validateDocumentUpload($key, $file, $requirement);

        if ($uploadError) {
            $this->addError('documentUploads.'.$key, $uploadError[0]);
            return;
        }

        $fileSize = $this->uploadedFileSize($file);
        $fileName = $file->getClientOriginalName();
        $existing = $this->application->documents()
            ->where('document_requirement_id', $requirementId)
            ->first();

        if ($existing && $existing->file_path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $path = $file->store('admission/'.$this->application->application_number, 'public');

        $this->application->documents()->updateOrCreate(
            ['document_requirement_id' => $requirementId],
            [
                'document_type' => $key,
                'file_path' => $path,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'verification_status' => 'pending',
                'verification_notes' => null,
                'verified_by' => null,
                'verified_at' => null,
            ],
        );

        session()->flash('success', 'Document uploaded successfully.');
        $this->documentUploads[$key] = null;
        $this->application->refresh()->load([
            'period.documentRequirements',
            'faculty',
            'studyProgram',
            'documents.requirement',
            'examParticipants.schedule',
            'scores.schedule',
            'statusHistories.changedBy',
        ]);
        $this->refreshStats();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'Applicant Portal',
        ]);
    }

    public function documentFor(string $documentType)
    {
        return $this->application->documents->firstWhere('document_type', $documentType);
    }

    public function documentPreviewUrl($document): string
    {
        return route('root.admission.documents.preview', [
            'applicationNumber' => $this->application->application_number,
            'token' => $this->application->access_token,
            'document' => $document->id,
        ]);
    }

    public function isImageDocument($document): bool
    {
        return in_array(strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function canPreviewDocument($document): bool
    {
        return $this->isImageDocument($document)
            || strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function uploadedFileSize($file): int
    {
        try {
            return (int) $file->getSize();
        } catch (Throwable $exception) {
            Log::warning('Admission portal temporary upload size unavailable.', [
                'application_id' => $this->application->id,
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
            return ['File belum selesai diunggah. Tunggu sebentar lalu upload lagi.'];
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

    private function refreshStats(): void
    {
        $requiredTypes = collect($this->requirements)->where('is_required', true)->pluck('document_type');
        $documents = $this->application->documents;
        $uploadedRequired = $documents->whereIn('document_type', $requiredTypes)->count();
        $requiredCount = max(1, $requiredTypes->count());
        $verifiedCount = $documents->where('verification_status', 'verified')->count();

        $this->stats = [
            'required_documents' => $requiredTypes->count(),
            'uploaded_documents' => $documents->count(),
            'verified_documents' => $verifiedCount,
            'completion' => min(100, (int) round(($uploadedRequired / $requiredCount) * 100)),
            'timeline_items' => $this->application->statusHistories->count(),
            'assigned_sessions' => $this->application->examParticipants->count(),
        ];
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
                                <span>Portal Pendaftar Aktif</span>
                            </div>
                            <h1 class="admission-title mb-3">Selamat Datang, {{ $application->full_name }}!</h1>
                            <p class="admission-subtitle mb-4">
                                Lacak progres seleksi, unggah kelengkapan berkas, pantau jadwal ujian, dan lihat pengumuman resmi langsung dari dashboard terpadu ini.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.admission.status') }}" class="btn btn-light btn px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-right-from-bracket text-primary"></i> Keluar Portal
                                </a>
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-outline-light btn px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-plus"></i> Pendaftaran Lainnya
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase tracking-wider">Nomor Pendaftaran</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ $application->application_number }}</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold shadow-sm">{{ str($application->status)->replace('_', ' ')->title() }}</span>
                                </div>
                                <div class="mb-2 d-flex justify-content-between align-items-center text-white-50 small fw-bold">
                                    <span>Kelengkapan Berkas Wajib</span>
                                    <span>{{ $stats['completion'] }}%</span>
                                </div>
                                <div class="progress progress-sm bg-white bg-opacity-25" style="height: 10px; border-radius: 10px;">
                                    <div class="progress-bar bg-white progress-bar-striped progress-bar-animated" style="width: {{ $stats['completion'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <x-alert />

                {{-- Stats Row --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0 h-100">
                            <div class="stat-icon-wrap bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div>
                                <span class="stat-num">{{ str($application->status)->replace('_', ' ')->title() }}</span>
                                <small class="text-muted fw-semibold">Status Saat Ini</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0 h-100">
                            <div class="stat-icon-wrap bg-success bg-opacity-10 text-success">
                                <i class="fas fa-file-circle-check"></i>
                            </div>
                            <div>
                                <span class="stat-num">{{ $stats['verified_documents'] }} / {{ $stats['uploaded_documents'] }}</span>
                                <small class="text-muted fw-semibold">Dokumen Terverifikasi</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0 h-100">
                            <div class="stat-icon-wrap bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <span class="stat-num">{{ $application->final_score ?? '-' }}</span>
                                <small class="text-muted fw-semibold">Skor Akhir / Kelulusan</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Navigation Shortcuts --}}
                <div class="admission-shortcuts mb-4 shadow-sm border-0 rounded-4">
                    <a href="#documents" class="shortcut-link">
                        <div class="shortcut-icon"><i class="fas fa-folder-open"></i></div>
                        <div>
                            <div class="fw-bold text-body">Manajemen Berkas</div>
                            <small class="text-muted">Kelola dokumen pendaftaran</small>
                        </div>
                    </a>
                    <a href="#selection" class="shortcut-link">
                        <div class="shortcut-icon"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="fw-bold text-body">Jadwal Seleksi</div>
                            <small class="text-muted">Ujian & Wawancara</small>
                        </div>
                    </a>
                    <a href="#timeline" class="shortcut-link">
                        <div class="shortcut-icon"><i class="fas fa-timeline"></i></div>
                        <div>
                            <div class="fw-bold text-body">Riwayat Status</div>
                            <small class="text-muted">Lacak historis progres</small>
                        </div>
                    </a>
                </div>

                <div class="row g-4 align-items-start">
                    <div class="col-lg-8">
                        {{-- Checklist Documents --}}
                        <div class="admission-card mb-4 shadow-sm border-0 rounded-4 overflow-hidden" id="documents">
                            <div class="admission-card-header d-flex align-items-center justify-content-between p-4 bg-surface border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="step-badge"><i class="fas fa-folder-open"></i></div>
                                    <div>
                                        <h3 class="mb-0 fw-bolder">Kelengkapan Berkas Persyaratan</h3>
                                        <small class="text-muted">Pastikan dokumen terverifikasi (Verified)</small>
                                    </div>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                                    Progres: {{ $stats['completion'] }}%
                                </span>
                            </div>
                            <div class="admission-card-body p-4">
                                <div class="row g-4">
                                    @foreach ($requirements as $requirement)
                                        @php($document = $this->documentFor($requirement['document_type']))
                                        <div class="col-12" wire:key="portal-doc-{{ $requirement['id'] }}">
                                            <div class="document-row p-4 rounded-4 shadow-sm position-relative overflow-hidden {{ $document && $document->verification_status === 'verified' ? 'border-success' : '' }}">
                                                @if($document && $document->verification_status === 'verified')
                                                    <div class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bottom-start shadow-sm fw-bold small">
                                                        <i class="fas fa-check me-1"></i> Terverifikasi
                                                    </div>
                                                @endif
                                                
                                                <div class="row g-3 align-items-start">
                                                    <div class="col-md-6">
                                                        <div class="d-flex align-items-center gap-2 mb-2">
                                                            <div class="fw-bolder text-body">{{ $requirement['label'] }}</div>
                                                            @if($requirement['is_required'])
                                                                <span class="badge bg-danger px-2 py-1 rounded-pill">Wajib</span>
                                                            @endif
                                                        </div>
                                                        
                                                        @if($document)
                                                            <div class="d-flex align-items-center gap-2 text-muted small mb-2">
                                                                <i class="fas fa-file-lines text-primary"></i> 
                                                                <span class="text-truncate" style="max-width: 200px;">{{ $document->file_name }}</span> 
                                                                <span>({{ number_format($document->file_size / 1024, 1) }} KB)</span>
                                                            </div>
                                                            <div>
                                                                @if($document->verification_status === 'verified')
                                                                    <span class="badge bg-success px-2 py-1 rounded-pill"><i class="fas fa-check me-1"></i> Verified</span>
                                                                @elseif($document->verification_status === 'rejected')
                                                                    <span class="badge bg-danger px-2 py-1 rounded-pill"><i class="fas fa-times me-1"></i> Ditolak (Perbaiki)</span>
                                                                @else
                                                                    <span class="badge bg-warning text-body px-2 py-1 rounded-pill"><i class="fas fa-clock me-1"></i> Menunggu Verifikasi</span>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="text-muted small"><i class="fas fa-exclamation-circle text-warning me-1"></i> Belum diunggah</div>
                                                        @endif
                                                        
                                                        @if($document?->verification_notes)
                                                            <div class="alert alert-danger bg-danger bg-opacity-10 border-0 mt-3 mb-0 small rounded-3 p-2 d-flex gap-2">
                                                                <i class="fas fa-triangle-exclamation text-danger flex-shrink-0 mt-1"></i>
                                                                <div>
                                                                    <strong class="text-danger d-block">Catatan Verifikator:</strong>
                                                                    <span class="text-danger">{{ $document->verification_notes }}</span>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="col-md-6">
                                                        @if($document)
                                                            @if($this->isImageDocument($document))
                                                                <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="document-preview shadow-sm d-block mb-2">
                                                                    <img src="{{ $this->documentPreviewUrl($document) }}" alt="{{ $document->file_name }}">
                                                                </a>
                                                            @elseif($this->canPreviewDocument($document))
                                                                <div class="pdf-preview shadow-sm mb-2">
                                                                    <iframe src="{{ $this->documentPreviewUrl($document) }}" title="{{ $document->file_name }}"></iframe>
                                                                </div>
                                                            @endif
                                                            <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 rounded-pill px-3">
                                                                <i class="fas fa-eye"></i> Buka File
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>

                                                @if(! $document || $document->verification_status !== 'verified')
                                                    <div class="row g-2 align-items-center mt-3 pt-3 border-top border-light">
                                                        <div class="col-md-8">
                                                            <input type="file" class="form-control modern-input" wire:model="documentUploads.{{ $requirement['document_type'] }}">
                                                            <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle text-primary me-1"></i> Maks. {{ $requirement['max_size_kb'] ?? 2048 }} KB. ({{ strtoupper($requirement['allowed_extensions'] ?: 'pdf,jpg,png') }})</small>
                                                            @error('documentUploads.'.$requirement['document_type']) <div class="text-danger small mt-1 fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-md-4">
                                                            <button class="btn btn-primary w-100 rounded-pill fw-bold" wire:click="uploadDocument({{ $requirement['id'] }})" wire:loading.attr="disabled" wire:target="uploadDocument({{ $requirement['id'] }}),documentUploads.{{ $requirement['document_type'] }}">
                                                                <span wire:loading.remove wire:target="uploadDocument({{ $requirement['id'] }}),documentUploads.{{ $requirement['document_type'] }}"><i class="fas fa-cloud-arrow-up me-1"></i> Unggah File</span>
                                                                <span wire:loading wire:target="uploadDocument({{ $requirement['id'] }}),documentUploads.{{ $requirement['document_type'] }}"><i class="fas fa-spinner fa-spin me-1"></i> Memproses...</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Exam & Interview Schedules --}}
                        <div class="admission-card mb-4 shadow-sm border-0 rounded-4 overflow-hidden" id="selection">
                            <div class="admission-card-header d-flex align-items-center justify-content-between p-4 bg-surface border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="step-badge bg-info"><i class="fas fa-calendar-check"></i></div>
                                    <div>
                                        <h3 class="mb-0 fw-bolder">Jadwal Ujian & Wawancara</h3>
                                        <small class="text-muted">Detail jadwal tahapan seleksi</small>
                                    </div>
                                </div>
                                <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill fw-bold">
                                    {{ $stats['assigned_sessions'] }} Sesi Ditugaskan
                                </span>
                            </div>
                            <div class="admission-card-body p-4">
                                <div class="row g-4">
                                    @forelse($application->examParticipants as $participant)
                                        <div class="col-12">
                                            <div class="document-row p-4 rounded-4 shadow-sm h-100 d-flex flex-column justify-content-center border-start border-4 border-primary">
                                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                                    <div>
                                                        <div class="fw-bolder text-body fs-5 mb-1">{{ $participant->schedule?->title }}</div>
                                                        <div class="text-muted small d-flex align-items-center gap-2 mb-2">
                                                            <span class="badge bg-secondary">{{ str($participant->schedule?->exam_type)->replace('_', ' ')->title() }}</span>
                                                            <span><i class="fas fa-calendar me-1"></i> {{ $participant->schedule?->exam_date?->format('d F Y') }}</span>
                                                            <span><i class="fas fa-clock me-1"></i> {{ $participant->schedule?->exam_time?->format('H:i') }}</span>
                                                        </div>
                                                    </div>
                                                    <span class="badge @if($participant->attendance_status === 'present') bg-success @elseif($participant->attendance_status === 'absent') bg-danger @else bg-primary @endif px-3 py-2 rounded-pill fw-bold shadow-sm">
                                                        Kehadiran: {{ ucfirst($participant->attendance_status) }}
                                                    </span>
                                                </div>
                                                <div class="mt-3 p-3 bg-light rounded-3 d-flex flex-wrap gap-4">
                                                    <div>
                                                        <div class="small fw-bold text-uppercase text-muted mb-1">Lokasi Ujian / Ruang</div>
                                                        <div class="fw-semibold text-body"><i class="fas fa-location-dot text-danger me-1"></i> {{ $participant->schedule?->venue ?? 'Online / TBA' }}</div>
                                                    </div>
                                                    <div>
                                                        <div class="small fw-bold text-uppercase text-muted mb-1">Tautan Virtual Meeting</div>
                                                        <div>
                                                            @if($participant->schedule?->meeting_link)
                                                                <a href="{{ $participant->schedule->meeting_link }}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm"><i class="fas fa-video me-1"></i> Buka Ruang Virtual</a>
                                                            @else
                                                                <span class="text-muted fst-italic">Belum Tersedia</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12 text-center py-4">
                                            <div class="text-muted"><i class="fas fa-calendar-xmark fs-2 mb-3 text-secondary"></i><br>Belum ada jadwal ujian atau wawancara yang ditetapkan untuk Anda.</div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        {{-- Application Summary Profile --}}
                        <div class="admission-card mb-4 shadow-sm border-0 rounded-4 overflow-hidden">
                            <div class="admission-card-header p-4 bg-surface border-bottom">
                                <h3 class="mb-0 fw-bolder d-flex align-items-center gap-2">
                                    <i class="fas fa-id-badge text-primary"></i> Profil Pendaftaran
                                </h3>
                            </div>
                            <div class="admission-card-body p-4">
                                <div class="mb-3">
                                    <small class="text-muted fw-bold text-uppercase">Nama Lengkap</small>
                                    <div class="h6 mb-0 text-body fw-bolder">{{ $application->full_name }}</div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted fw-bold text-uppercase">Gelombang</small>
                                    <div class="h6 mb-0 text-body">{{ $application->period?->name }}</div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted fw-bold text-uppercase">Program Studi / Kelas</small>
                                    <div class="h6 mb-0 text-body fw-semibold">{{ $application->studyProgram?->name ?? '-' }} ({{ ucfirst($application->class_type ?? '-') }})</div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted fw-bold text-uppercase">Email / No. HP</small>
                                    <div class="h6 mb-0 text-body">{{ $application->email }}<br>{{ $application->phone }}</div>
                                </div>
                                @if($application->converted_at)
                                    <div class="p-3 bg-success bg-opacity-10 rounded-3 mt-4 border border-success border-opacity-25">
                                        <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold">
                                            <i class="fas fa-check-circle"></i> Pendaftaran Diterima
                                        </div>
                                        <small class="text-success fw-bold text-uppercase d-block mb-1">Nomor Induk Mahasiswa (NIM)</small>
                                        <div class="h4 mb-0 text-success fw-bolder">{{ $application->user?->studentProfile?->nim ?? '-' }}</div>
                                        <div class="small mt-2 text-success">Dikonversi pada: {{ $application->converted_at?->format('d M Y') }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Final Scores --}}
                        <div class="admission-card mb-4 shadow-sm border-0 rounded-4 overflow-hidden">
                            <div class="admission-card-header p-4 bg-surface border-bottom">
                                <h3 class="mb-0 fw-bolder d-flex align-items-center gap-2">
                                    <i class="fas fa-star text-warning"></i> Penilaian Kelulusan
                                </h3>
                            </div>
                            <div class="admission-card-body p-4">
                                @forelse($application->scores as $score)
                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-light">
                                        <div>
                                            <div class="fw-bold text-body">{{ str($score->score_type)->replace('_', ' ')->title() }}</div>
                                            <small class="text-muted">Bobot: {{ $score->weight }}</small>
                                        </div>
                                        <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill shadow-sm">{{ $score->score }}</span>
                                    </div>
                                @empty
                                    <div class="text-muted text-center py-3">Skor ujian dan penilaian belum dipublikasikan oleh panitia.</div>
                                @endforelse
                                <div class="mt-4 p-3 bg-primary bg-opacity-10 rounded-3 text-center border border-primary border-opacity-25">
                                    <small class="text-primary fw-bold text-uppercase">Skor Akhir / Final</small>
                                    <div class="h2 mb-0 text-primary fw-bolder">{{ $application->final_score ?? '-' }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Status Timeline --}}
                        <div class="admission-card shadow-sm border-0 rounded-4 overflow-hidden" id="timeline">
                            <div class="admission-card-header p-4 bg-surface border-bottom d-flex justify-content-between align-items-center">
                                <h3 class="mb-0 fw-bolder d-flex align-items-center gap-2">
                                    <i class="fas fa-timeline text-info"></i> Riwayat Status
                                </h3>
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2">{{ $stats['timeline_items'] }}</span>
                            </div>
                            <div class="admission-card-body p-4">
                                @forelse ($application->statusHistories->sortByDesc('created_at') as $history)
                                    <div class="timeline-row">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-content">
                                            <div class="fw-bold text-body d-flex align-items-center flex-wrap gap-2">
                                                <span class="badge bg-light text-muted border">{{ $history->from_status ?: 'new' }}</span>
                                                <i class="fas fa-arrow-right text-muted small"></i>
                                                <span class="badge bg-primary">{{ $history->to_status }}</span>
                                            </div>
                                            <small class="text-muted d-block mt-1 mb-2"><i class="fas fa-clock me-1"></i> {{ $history->created_at?->format('d M Y, H:i') }}</small>
                                            @if($history->notes)
                                                <div class="p-2 bg-light rounded-3 small text-muted border fst-italic">"{{ $history->notes }}"</div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted text-center py-3">Belum ada riwayat perubahan status.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


