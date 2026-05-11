<?php

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionStatusService;
use Livewire\Component;

new class extends Component
{
    public AdmissionApplication $application;

    public array $reviewForm = [
        'status' => 'submitted',
        'review_notes' => '',
        'final_score' => null,
    ];

    public array $documentNotes = [];

    public function mount($id): void
    {
        $this->loadApplication($id);

        $this->reviewForm = [
            'status' => $this->application->status,
            'review_notes' => $this->application->review_notes ?? '',
            'final_score' => $this->application->final_score,
        ];
    }

    public function updateReview(AdmissionStatusService $statusService): void
    {
        abort_unless(ActivePermission::check('admission-application.update'), 403);

        $validated = $this->validate([
            'reviewForm.status' => 'required|in:submitted,under_review,accepted,rejected,waitlisted',
            'reviewForm.review_notes' => 'nullable|string',
            'reviewForm.final_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $this->application->update([
            'final_score' => $validated['reviewForm']['final_score'],
            'review_notes' => $validated['reviewForm']['review_notes'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        if ($validated['reviewForm']['status'] !== $this->application->status) {
            $statusService->change(
                $this->application,
                $validated['reviewForm']['status'],
                $validated['reviewForm']['review_notes'] ?: null,
                auth()->id(),
            );
        }

        session()->flash('success', 'Review aplikasi berhasil diperbarui.');
        $this->loadApplication($this->application->id);
    }

    public function verifyDocument(int $documentId, string $status): void
    {
        abort_unless(ActivePermission::check('admission-application.update'), 403);
        abort_unless(in_array($status, ['verified', 'rejected', 'pending'], true), 422);

        $document = $this->application->documents()->whereKey($documentId)->firstOrFail();

        $document->update([
            'verification_status' => $status,
            'verification_notes' => $this->documentNotes[$documentId] ?? null,
            'verified_by' => auth()->id(),
            'verified_at' => $status === 'pending' ? null : now(),
        ]);

        session()->flash('success', 'Status dokumen berhasil diperbarui.');
        $this->loadApplication($this->application->id);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'accepted', 'verified' => 'bg-green-lt text-green',
            'under_review', 'pending', 'waitlisted' => 'bg-yellow-lt text-yellow',
            'rejected' => 'bg-red-lt text-red',
            default => 'bg-blue-lt text-blue',
        };
    }

    public function documentPreviewUrl($document): string
    {
        return route('admin.admission.documents.preview', ['document' => $document->id]);
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

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Admission Application Detail',
        ]);
    }

    private function loadApplication($id): void
    {
        $this->application = AdmissionApplication::with([
            'period.academicYear',
            'period.documentRequirements',
            'faculty',
            'studyProgram',
            'documents.requirement',
            'documents.verifiedBy',
            'statusHistories.changedBy',
            'reviewedBy',
        ])->findOrFail($id);

        $this->documentNotes = $this->application->documents
            ->mapWithKeys(fn (AdmissionDocument $document) => [$document->id => $document->verification_notes])
            ->toArray();
    }
};
?>

@push('styles')
<style>
    .modern-card { border-radius:20px;border:none;box-shadow:0 4px 20px rgba(0,0,0,.08);background:white; }
    .hero-gradient { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);position:relative;overflow:hidden;color:white; }
    .hero-gradient::before { content:'';position:absolute;top:-50%;right:-20%;width:520px;height:520px;background:radial-gradient(circle,rgba(255,255,255,.16) 0%,transparent 68%); }
    .stat-card { border-radius:16px;padding:1.2rem;background:white;box-shadow:0 2px 12px rgba(0,0,0,.06);height:100%; }
    .stat-icon { width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.1rem;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
    .info-tile { border-radius:14px;background:#f8fafc;border:1px solid #eef2f7;padding:1rem;height:100%; }
    .info-tile small { color:#64748b;font-weight:700;display:block;margin-bottom:.25rem; }
    .document-card { border-radius:16px;background:#f8fafc;border:2px solid transparent;padding:1rem;margin-bottom:1rem;transition:all .2s ease; }
    .document-card:hover { border-color:#667eea;background:#f5f0ff;box-shadow:0 8px 24px rgba(102,66,244,.08); }
    .document-preview { display:block;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;background:white;max-height:320px; }
    .document-preview img { display:block;width:100%;max-height:320px;object-fit:contain; }
    .pdf-preview { height:360px;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;background:white; }
    .pdf-preview iframe { width:100%;height:100%;border:0; }
    .timeline-row { display:grid;grid-template-columns:18px 1fr;gap:.75rem;padding-bottom:1rem;margin-bottom:1rem;border-bottom:1px solid #edf2f7; }
    .timeline-dot { width:12px;height:12px;margin-top:.35rem;border-radius:999px;background:#667eea;box-shadow:0 0 0 4px #eef2ff; }
    .soft-input { border-radius:12px;border:2px solid #e2e8f0; }
</style>
@endpush

<div>
    <x-alert />

    <div class="modern-card hero-gradient p-4 mb-4">
        <div class="position-relative" style="z-index:1;">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2" style="opacity:.9;font-weight:700;">
                        <i class="fas fa-file-signature"></i>
                        <span>Admission Application</span>
                    </div>
                    <h1 class="mb-1" style="font-weight:800;font-size:2rem;">{{ $application->full_name }}</h1>
                    <p class="mb-0" style="opacity:.9;">{{ $application->application_number }} - {{ $application->period?->name }}</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admission.portal', ['applicationNumber' => $application->application_number, 'token' => $application->access_token]) }}" target="_blank" class="btn btn-light">
                        <i class="fas fa-arrow-up-right-from-square me-1"></i> Applicant Portal
                    </a>
                    <a href="{{ route('admin.admission.admission-applications.index') }}" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div>
                        <div class="h4 mb-0">{{ str($application->status)->replace('_', ' ')->title() }}</div>
                        <small class="text-muted">Status</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fas fa-file-circle-check"></i></div>
                    <div>
                        <div class="h4 mb-0">{{ $application->documents->where('verification_status', 'verified')->count() }}/{{ $application->documents->count() }}</div>
                        <small class="text-muted">Verified Docs</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div>
                        <div class="h4 mb-0">{{ $application->final_score ?? '-' }}</div>
                        <small class="text-muted">Final Score</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="h4 mb-0">{{ $application->submitted_at?->format('d M') ?? '-' }}</div>
                        <small class="text-muted">Submitted</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="modern-card p-4 mb-4">
                <h4 class="mb-3" style="font-weight:800;color:#1e293b;"><i class="fas fa-user me-2" style="color:#667eea;"></i>Applicant Profile</h4>
                <div class="row g-3">
                    <div class="col-md-6"><div class="info-tile"><small>Email</small><div class="fw-bold">{{ $application->email }}</div></div></div>
                    <div class="col-md-6"><div class="info-tile"><small>Phone</small><div class="fw-bold">{{ $application->phone }}</div></div></div>
                    <div class="col-md-6"><div class="info-tile"><small>Birth Date / Gender</small><div class="fw-bold">{{ $application->birth_date?->format('d F Y') }} / {{ ucfirst($application->gender) }}</div></div></div>
                    <div class="col-md-6"><div class="info-tile"><small>Emergency Contact</small><div class="fw-bold">{{ $application->emergency_contact_name }} - {{ $application->emergency_contact_phone }}</div></div></div>
                    <div class="col-md-6"><div class="info-tile"><small>Academic Year</small><div class="fw-bold">{{ $application->period?->academicYear?->name ?? $application->period?->academic_year ?? '-' }}</div></div></div>
                    <div class="col-md-6"><div class="info-tile"><small>Program</small><div class="fw-bold">{{ $application->studyProgram?->name ?? '-' }}</div></div></div>
                    <div class="col-12"><div class="info-tile"><small>Address</small><div>{{ $application->address }}</div></div></div>
                </div>
            </div>

            <div class="modern-card p-4 mb-4">
                <h4 class="mb-3" style="font-weight:800;color:#1e293b;"><i class="fas fa-school me-2" style="color:#667eea;"></i>Education Background</h4>
                <div class="row g-3">
                    <div class="col-md-4"><div class="info-tile"><small>High School</small><div class="fw-bold">{{ $application->high_school_name }}</div></div></div>
                    <div class="col-md-4"><div class="info-tile"><small>Major</small><div class="fw-bold">{{ $application->high_school_major }}</div></div></div>
                    <div class="col-md-4"><div class="info-tile"><small>Graduation Year</small><div class="fw-bold">{{ $application->high_school_graduation_year }}</div></div></div>
                </div>
            </div>

            <div class="modern-card p-4 mb-4">
                <h4 class="mb-3" style="font-weight:800;color:#1e293b;"><i class="fas fa-folder-open me-2" style="color:#667eea;"></i>Documents</h4>
                @forelse ($application->documents as $document)
                    <div class="document-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="fw-bold">{{ $document->requirement?->label ?? $document->document_type }}</div>
                                <small class="text-muted">{{ $document->file_name }} - {{ number_format($document->file_size / 1024, 1) }} KB</small>
                            </div>
                            <span class="badge {{ $this->statusBadgeClass($document->verification_status) }}">{{ ucfirst($document->verification_status) }}</span>
                        </div>

                        @if($this->isImageDocument($document))
                            <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="document-preview mb-3">
                                <img src="{{ $this->documentPreviewUrl($document) }}" alt="{{ $document->file_name }}">
                            </a>
                        @elseif($this->canPreviewDocument($document))
                            <div class="pdf-preview mb-3">
                                <iframe src="{{ $this->documentPreviewUrl($document) }}" title="{{ $document->file_name }}"></iframe>
                            </div>
                        @endif

                        <textarea class="form-control soft-input" rows="2" placeholder="Verification notes" wire:model.defer="documentNotes.{{ $document->id }}"></textarea>
                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <a href="{{ $this->documentPreviewUrl($document) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> Open
                            </a>
                            <button class="btn btn-success btn-sm" wire:click="verifyDocument({{ $document->id }}, 'verified')">
                                <i class="fas fa-check me-1"></i> Verify
                            </button>
                            <button class="btn btn-danger btn-sm" wire:click="verifyDocument({{ $document->id }}, 'rejected')">
                                <i class="fas fa-times me-1"></i> Reject
                            </button>
                            <button class="btn btn-secondary btn-sm" wire:click="verifyDocument({{ $document->id }}, 'pending')">Reset</button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                        <div class="mt-3 fw-semibold">Belum ada dokumen yang diunggah.</div>
                    </div>
                @endforelse
            </div>

            <div class="modern-card p-4">
                <h4 class="mb-3" style="font-weight:800;color:#1e293b;"><i class="fas fa-timeline me-2" style="color:#667eea;"></i>Status History</h4>
                @forelse ($application->statusHistories->sortByDesc('created_at') as $history)
                    <div class="timeline-row">
                        <div class="timeline-dot"></div>
                        <div>
                            <div class="fw-bold">{{ $history->from_status ?: 'new' }} -> {{ $history->to_status }}</div>
                            <small class="text-muted">{{ $history->created_at?->format('d F Y H:i') }} by {{ $history->changedBy?->name ?? 'System' }}</small>
                            @if($history->notes)<div class="mt-1">{{ $history->notes }}</div>@endif
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Belum ada riwayat status.</div>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="modern-card p-4 mb-4">
                <h4 class="mb-3" style="font-weight:800;color:#1e293b;"><i class="fas fa-pen-to-square me-2" style="color:#667eea;"></i>Review</h4>
                <div class="mb-3">
                    <label class="form-label fw-bold">Status</label>
                    <select class="form-control soft-input" wire:model.defer="reviewForm.status">
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                        <option value="waitlisted">Waitlisted</option>
                    </select>
                    @error('reviewForm.status') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Final Score</label>
                    <input type="number" step="0.01" class="form-control soft-input" wire:model.defer="reviewForm.final_score">
                    @error('reviewForm.final_score') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Review Notes</label>
                    <textarea class="form-control soft-input" rows="5" wire:model.defer="reviewForm.review_notes"></textarea>
                    @error('reviewForm.review_notes') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                @activecan('admission-application.update')
                    <button class="btn btn-primary w-100" wire:click="updateReview">
                        <i class="fas fa-save me-1"></i> Save Review
                    </button>
                @endactivecan
            </div>

            <div class="modern-card p-4">
                <h4 class="mb-2" style="font-weight:800;color:#1e293b;"><i class="fas fa-link me-2" style="color:#667eea;"></i>Applicant Portal</h4>
                <p class="text-muted small">Tokenized link untuk applicant mengecek status dan memperbarui dokumen.</p>
                <a href="{{ route('admission.portal', ['applicationNumber' => $application->application_number, 'token' => $application->access_token]) }}" target="_blank" class="btn btn-outline-primary w-100">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i> Open Portal
                </a>
            </div>
        </div>
    </div>
</div>
