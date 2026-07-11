<?php

use App\Models\StudentService\ServiceLetterRequest;
use App\Support\ActivePermission;
use App\Support\StudentService\ServiceLetterRequestService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ServiceLetterRequest $request;

    public ?string $adminNotes = null;

    public string $issueMethod = 'auto_generate';

    public ?TemporaryUploadedFile $finalFile = null;

    public function mount($id): void
    {
        $this->request = ServiceLetterRequest::with([
            'letterType',
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'issuedBy',
            'approvalRequest.steps.actedBy',
        ])->findOrFail($id);

        $this->adminNotes = $this->request->admin_notes;
        $this->issueMethod = $this->request->letterType->fulfillment_mode === 'manual_upload'
            ? 'manual_upload'
            : 'auto_generate';
    }

    public function markUnderReview(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'under_review', $this->adminNotes, auth()->id());
        session()->flash('success', 'Request ditandai under review.');
        $this->reload();
    }

    public function approve(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        try {
            $this->request = $service->approve($this->request, $this->adminNotes, auth()->id());
            session()->flash('success', 'Request berhasil diapprove.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function requestRevision(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'revision_requested', $this->adminNotes, auth()->id());
        session()->flash('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        $this->reload();
    }

    public function reject(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);
        try {
            $this->request = $service->reject($this->request, $this->adminNotes, auth()->id());
            session()->flash('success', 'Request ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function issue(ServiceLetterRequestService $service): void
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);

        $this->validate([
            'issueMethod' => ['required', Rule::in(['auto_generate', 'manual_upload'])],
            'finalFile' => [$this->issueMethod === 'manual_upload' ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            $this->request = $service->issue($this->request, $this->issueMethod, $this->finalFile, auth()->id());
            $this->finalFile = null;
            session()->flash('success', 'Surat berhasil diterbitkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Detail Pengajuan Surat',
        ]);
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'issued' => 'bg-success',
            'approved' => 'bg-info',
            'under_review' => 'bg-primary',
            'revision_requested', 'in_approval' => 'bg-warning text-dark',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Approval',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function reload(): void
    {
        $this->request->refresh()->load([
            'letterType',
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'issuedBy',
            'approvalRequest.steps.actedBy',
        ]);
    }
};
?>

<div class="row">
    <div class="col-lg-8">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">{{ $request->request_number }}</h3>
                    <small class="text-muted">{{ $request->letterType?->name }}</small>
                </div>
                <a href="{{ route('admin.student-services.letter-requests.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted">Status</small>
                        <div><span class="badge {{ $this->statusBadge($request->status) }}">{{ $this->statusLabel($request->status) }}</span></div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Mahasiswa</small>
                        <div class="h6 mb-0">{{ $request->studentProfile?->user?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">NIM</small>
                        <div class="h6 mb-0">{{ $request->studentProfile?->nim ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Program Studi</small>
                        <div>{{ $request->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Submitted</small>
                        <div>{{ $request->created_at?->format('d M Y H:i') }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Mode</small>
                        <div>{{ str($request->letterType?->fulfillment_mode)->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Purpose</small>
                        <div>{{ $request->purpose }}</div>
                    </div>
                    @if ($request->request_data)
                        <div class="col-12">
                            <small class="text-muted">Request Data</small>
                            <div class="border rounded p-3 bg-light">
                                @foreach ($request->request_data as $key => $value)
                                    <div class="d-flex justify-content-between border-bottom py-1">
                                        <span>{{ str($key)->replace('_', ' ')->title() }}</span>
                                        <strong>{{ is_array($value) ? json_encode($value) : $value }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($request->attachment_path)
                        <div class="col-12">
                            <a href="{{ $this->fileUrl($request->attachment_path) }}" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-paperclip me-1"></i> Preview Attachment
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Status History</h3>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($request->histories as $history)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $this->statusLabel($history->to_status) }}</strong>
                            <span class="text-muted">{{ $history->created_at?->format('d M Y H:i') }}</span>
                        </div>
                        <div class="text-muted small">{{ $history->notes ?: '-' }}</div>
                        <div class="text-muted small">By {{ $history->changedBy?->name ?? 'System' }}</div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">Belum ada history.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Review Actions</h3>
            </div>
            <div class="card-body">
                <label class="form-label">Admin Notes</label>
                <textarea wire:model="adminNotes" class="form-control mb-3" rows="4" placeholder="Catatan untuk mahasiswa"></textarea>

                <div class="d-grid gap-2">
                    <button wire:click="markUnderReview" class="btn btn-outline-primary" @disabled(! in_array($request->status, ['submitted', 'revision_requested'], true))>
                        <i class="fas fa-search me-1"></i> Mark Under Review
                    </button>
                    <button wire:click="approve" class="btn btn-success" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                        <i class="fas fa-check me-1"></i> Approve
                    </button>
                    <button wire:click="requestRevision" class="btn btn-warning" @disabled(in_array($request->status, ['issued', 'rejected', 'cancelled'], true))>
                        <i class="fas fa-rotate-left me-1"></i> Minta Perbaikan
                    </button>
                    <button wire:click="reject" class="btn btn-danger" @disabled(in_array($request->status, ['issued', 'rejected', 'cancelled'], true))>
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Issue Letter</h3>
            </div>
            <div class="card-body">
                @if ($request->isDownloadable())
                    <a href="{{ route('admin.student-services.letter-requests.download', ['request' => $request->id]) }}" target="_blank" class="btn btn-success w-100 mb-3">
                        <i class="fas fa-download me-1"></i> Download Issued Letter
                    </a>
                @endif

                <label class="form-label">Issue Method</label>
                <select wire:model.live="issueMethod" class="form-select mb-3">
                    @if (in_array($request->letterType?->fulfillment_mode, ['auto_generate', 'hybrid'], true))
                        <option value="auto_generate">Auto Generate PDF</option>
                    @endif
                    @if (in_array($request->letterType?->fulfillment_mode, ['manual_upload', 'hybrid'], true))
                        <option value="manual_upload">Manual Upload</option>
                    @endif
                </select>

                @if ($issueMethod === 'manual_upload')
                    <label class="form-label">Final File</label>
                    <input type="file" wire:model="finalFile" class="form-control mb-2">
                    @error('finalFile') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                @endif

                <button wire:click="issue" class="btn btn-primary w-100" @disabled($request->status !== 'approved')>
                    <i class="fas fa-paper-plane me-1"></i> Issue Letter
                </button>
            </div>
        </div>
    </div>
</div>
