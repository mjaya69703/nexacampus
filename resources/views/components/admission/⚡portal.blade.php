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
        return route('admission.documents.preview', [
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
            <div class="col-lg-11">
                <div class="admission-hero mb-4">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker">Applicant Portal</div>
                            <h1 class="admission-title">{{ $application->full_name }}</h1>
                            <p class="admission-subtitle mb-3">
                                Track your admission status, keep documents complete, and follow every review update from one place.
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('admission.status') }}" class="btn btn-light">
                                    <i class="fas fa-magnifying-glass me-2"></i> Check Another Application
                                </a>
                                <a href="{{ route('admission.apply') }}" class="btn btn-outline-light">
                                    <i class="fas fa-plus me-2"></i> New Application
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="text-white-50 small">Application Number</div>
                                        <div class="h3 text-white mb-0">{{ $application->application_number }}</div>
                                    </div>
                                    <span class="badge bg-white text-primary">{{ str($application->status)->replace('_', ' ')->title() }}</span>
                                </div>
                                <div class="progress progress-sm bg-white bg-opacity-25 mb-2">
                                    <div class="progress-bar bg-white" style="width: {{ $stats['completion'] }}%"></div>
                                </div>
                                <div class="d-flex justify-content-between text-white-50 small">
                                    <span>Required docs completion</span>
                                    <span>{{ $stats['completion'] }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <x-alert />

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="admission-stat">
                            <i class="fas fa-clipboard-check"></i>
                            <span>{{ str($application->status)->replace('_', ' ')->title() }}</span>
                            <small>Current Status</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="admission-stat">
                            <i class="fas fa-file-circle-check"></i>
                            <span>{{ $stats['verified_documents'] }}/{{ $stats['uploaded_documents'] }}</span>
                            <small>Verified Documents</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="admission-stat">
                            <i class="fas fa-star"></i>
                            <span>{{ $application->final_score ?? '-' }}</span>
                            <small>Final Score</small>
                        </div>
                    </div>
                </div>

                <div class="admission-shortcuts mb-4">
                    <a href="#documents">
                        <i class="fas fa-folder-open"></i>
                        <span>Manage documents</span>
                    </a>
                    <a href="#selection">
                        <i class="fas fa-calendar-check"></i>
                        <span>Selection schedule</span>
                    </a>
                    <a href="#timeline">
                        <i class="fas fa-timeline"></i>
                        <span>View status history</span>
                    </a>
                </div>

                <div class="admission-card mb-3">
                    <div class="admission-card-header">
                        <div>
                            <div class="section-kicker">Profile</div>
                            <h3>Application Summary</h3>
                        </div>
                        <span>{{ $application->period?->name }}</span>
                    </div>
                    <div class="admission-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-muted">Full Name</small>
                                <div class="h6 mb-0">{{ $application->full_name }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Admission Period</small>
                                <div class="h6 mb-0">{{ $application->period?->name }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Email</small>
                                <div class="h6 mb-0">{{ $application->email }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Phone</small>
                                <div class="h6 mb-0">{{ $application->phone }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Study Program</small>
                                <div class="h6 mb-0">{{ $application->studyProgram?->name ?? '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Class Type</small>
                                <div class="h6 mb-0">{{ ucfirst($application->class_type ?? '-') }}</div>
                            </div>
                            @if($application->converted_at)
                                <div class="col-md-6">
                                    <small class="text-muted">Student ID / NIM</small>
                                    <div class="h6 mb-0">{{ $application->user?->studentProfile?->nim ?? '-' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Converted At</small>
                                    <div class="h6 mb-0">{{ $application->converted_at?->format('d F Y H:i') }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="admission-card mb-3" id="documents">
                    <div class="admission-card-header">
                        <div>
                            <div class="section-kicker">Checklist</div>
                            <h3>Documents</h3>
                        </div>
                        <span>{{ $stats['completion'] }}% complete</span>
                    </div>
                    <div class="admission-card-body">
                        @foreach ($requirements as $requirement)
                            @php($document = $this->documentFor($requirement['document_type']))
                            <div class="document-row" wire:key="portal-doc-{{ $requirement['id'] }}">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ $requirement['label'] }} @if($requirement['is_required']) <span class="text-danger">*</span> @endif</div>
                                        @if($document)
                                            <small class="text-muted">{{ $document->file_name }} - {{ number_format($document->file_size / 1024, 1) }} KB</small>
                                        @else
                                            <small class="text-muted">Not uploaded yet</small>
                                        @endif
                                    </div>
                                    <div>
                                        @if($document)
                                            <span class="badge @if($document->verification_status === 'verified') bg-success @elseif($document->verification_status === 'rejected') bg-danger @else bg-warning text-dark @endif">
                                                {{ ucfirst($document->verification_status) }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">Missing</span>
                                        @endif
                                    </div>
                                </div>

                                @if($document?->verification_notes)
                                    <div class="alert alert-light border mt-2 mb-0">{{ $document->verification_notes }}</div>
                                @endif

                                @if($document)
                                    @if($this->isImageDocument($document))
                                        <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="document-preview mt-3">
                                            <img src="{{ $this->documentPreviewUrl($document) }}" alt="{{ $document->file_name }}">
                                        </a>
                                    @elseif($this->canPreviewDocument($document))
                                        <div class="pdf-preview mt-3">
                                            <iframe src="{{ $this->documentPreviewUrl($document) }}" title="{{ $document->file_name }}"></iframe>
                                        </div>
                                    @endif
                                    <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="fas fa-eye me-1"></i> Open Current File
                                    </a>
                                @endif

                                @if(! $document || $document->verification_status !== 'verified')
                                    <div class="row g-2 align-items-end mt-2">
                                        <div class="col-md-8">
                                            <input type="file" class="form-control" wire:model="documentUploads.{{ $requirement['document_type'] }}">
                                            <small class="text-muted">Max {{ $requirement['max_size_kb'] ?? 2048 }} KB. Allowed: {{ $requirement['allowed_extensions'] ?: 'pdf,jpg,jpeg,png' }}</small>
                                            @error('documentUploads.'.$requirement['document_type']) <div class="text-danger">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <button class="btn btn-primary w-100" wire:click="uploadDocument({{ $requirement['id'] }})" wire:loading.attr="disabled" wire:target="uploadDocument({{ $requirement['id'] }}),documentUploads.{{ $requirement['document_type'] }}">
                                                <span wire:loading.remove wire:target="uploadDocument({{ $requirement['id'] }}),documentUploads.{{ $requirement['document_type'] }}">Upload</span>
                                                <span wire:loading wire:target="uploadDocument({{ $requirement['id'] }}),documentUploads.{{ $requirement['document_type'] }}">Uploading...</span>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="admission-card mb-3" id="selection">
                    <div class="admission-card-header">
                        <div>
                            <div class="section-kicker">Selection</div>
                            <h3>Exam & Interview Schedule</h3>
                        </div>
                        <span>{{ $stats['assigned_sessions'] }} sessions</span>
                    </div>
                    <div class="admission-card-body">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                @forelse($application->examParticipants as $participant)
                                    <div class="document-row">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold">{{ $participant->schedule?->title }}</div>
                                                <small class="text-muted">
                                                    {{ str($participant->schedule?->exam_type)->replace('_', ' ')->title() }}
                                                    - {{ $participant->schedule?->exam_date?->format('d F Y') }}
                                                    at {{ $participant->schedule?->exam_time?->format('H:i') }}
                                                </small>
                                            </div>
                                            <span class="badge @if($participant->attendance_status === 'present') bg-success @elseif($participant->attendance_status === 'absent') bg-danger @else bg-primary @endif">
                                                {{ ucfirst($participant->attendance_status) }}
                                            </span>
                                        </div>
                                        <div class="mt-3 row g-2">
                                            <div class="col-md-6">
                                                <small class="text-muted">Venue</small>
                                                <div class="fw-semibold">{{ $participant->schedule?->venue ?? 'Online / TBA' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">Meeting Link</small>
                                                <div>
                                                    @if($participant->schedule?->meeting_link)
                                                        <a href="{{ $participant->schedule->meeting_link }}" target="_blank">Open link</a>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted">No exam or interview schedule assigned yet.</div>
                                @endforelse
                            </div>
                            <div class="col-lg-5">
                                <div class="document-row h-100">
                                    <div class="fw-semibold mb-3">Score Summary</div>
                                    @forelse($application->scores as $score)
                                        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                            <div>
                                                <div class="fw-semibold">{{ str($score->score_type)->replace('_', ' ')->title() }}</div>
                                                <small class="text-muted">Weight {{ $score->weight }}</small>
                                            </div>
                                            <span class="badge bg-blue-lt text-blue">{{ $score->score }}</span>
                                        </div>
                                    @empty
                                        <div class="text-muted">Scores are not published yet.</div>
                                    @endforelse
                                    <div class="mt-3 pt-3 border-top">
                                        <small class="text-muted">Final Score</small>
                                        <div class="h3 mb-0">{{ $application->final_score ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admission-card" id="timeline">
                    <div class="admission-card-header">
                        <div>
                            <div class="section-kicker">Timeline</div>
                            <h3>Status History</h3>
                        </div>
                        <span>{{ $stats['timeline_items'] }} updates</span>
                    </div>
                    <div class="admission-card-body">
                        @forelse ($application->statusHistories->sortByDesc('created_at') as $history)
                            <div class="timeline-row">
                                <div class="timeline-dot"></div>
                                <div>
                                    <div class="fw-semibold">{{ $history->from_status ?: 'new' }} -> {{ $history->to_status }}</div>
                                    <small class="text-muted">{{ $history->created_at?->format('d F Y H:i') }}</small>
                                    @if($history->notes)
                                        <div class="mt-1">{{ $history->notes }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">No history yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .admission-public {
        background: linear-gradient(180deg, #f7f3ff 0%, #ffffff 42%, #f8fafc 100%);
        min-height: calc(100vh - 120px);
    }

    .admission-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 58%, #16a3b8 100%);
        border-radius: 20px;
        color: #fff;
        padding: 2rem;
        box-shadow: 0 22px 55px rgba(102, 126, 234, 0.16);
    }

    .admission-kicker,
    .section-kicker {
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .admission-kicker {
        color: rgba(255, 255, 255, .72);
    }

    .admission-title {
        font-size: clamp(2rem, 4vw, 3.4rem);
        line-height: 1.05;
        margin: .5rem 0 1rem;
        letter-spacing: 0;
    }

    .admission-subtitle {
        color: rgba(255, 255, 255, .82);
        max-width: 42rem;
    }

    .admission-hero-panel {
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 16px;
        padding: 1.25rem;
        backdrop-filter: blur(10px);
    }

    .admission-stat,
    .admission-card,
    .admission-shortcuts {
        background: #fff;
        border: 1px solid rgba(102, 126, 234, .12);
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .06);
    }

    .admission-stat {
        padding: 1rem;
        min-height: 116px;
    }

    .admission-stat i {
        color: #667eea;
        font-size: 1.15rem;
        margin-bottom: .8rem;
    }

    .admission-stat span {
        display: block;
        font-size: 1.35rem;
        font-weight: 800;
        color: #1f2937;
        line-height: 1.2;
    }

    .admission-stat small {
        color: #64748b;
        font-weight: 600;
    }

    .admission-shortcuts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
    }

    .admission-shortcuts a {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: 1rem;
        color: #334155;
        text-decoration: none;
        border-right: 1px solid #edf2f7;
        font-weight: 700;
    }

    .admission-shortcuts a:last-child {
        border-right: 0;
    }

    .admission-shortcuts i,
    .section-kicker {
        color: #667eea;
    }

    .admission-card-header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid #edf2f7;
    }

    .admission-card-header h3 {
        margin: 0;
        font-size: 1.12rem;
    }

    .admission-card-header > span {
        color: #64748b;
        font-weight: 600;
        font-size: .875rem;
    }

    .admission-card-body {
        padding: 1.25rem;
    }

    .document-row {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #f8fafc;
    }

    .document-preview {
        display: block;
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background: #fff;
        max-height: 260px;
    }

    .document-preview img {
        width: 100%;
        max-height: 260px;
        object-fit: contain;
        display: block;
    }

    .pdf-preview {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        height: 320px;
        background: #fff;
    }

    .pdf-preview iframe {
        width: 100%;
        height: 100%;
        border: 0;
    }

    .timeline-row {
        display: grid;
        grid-template-columns: 18px 1fr;
        gap: .75rem;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid #edf2f7;
    }

    .timeline-dot {
        width: 12px;
        height: 12px;
        margin-top: .35rem;
        border-radius: 999px;
        background: #667eea;
        box-shadow: 0 0 0 4px #eee8ff;
    }

    @media (max-width: 767.98px) {
        .admission-hero {
            padding: 1.5rem;
        }

        .admission-shortcuts {
            grid-template-columns: 1fr;
        }

        .admission-shortcuts a {
            border-right: 0;
            border-bottom: 1px solid #edf2f7;
        }

        .admission-card-header {
            flex-direction: column;
        }
    }
</style>
