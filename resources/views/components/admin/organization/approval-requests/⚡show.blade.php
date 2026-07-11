<?php

use App\Models\Organization\ApprovalRequest;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\Organization\ApprovalEngine;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public ApprovalRequest $requestModel;
    public string $actionNotes = '';

    public function mount(int $id): void
    {
        $this->requestModel = ApprovalRequest::with([
            'template',
            'requester',
            'approvable',
            'steps.actedBy',
            'steps.organizationalPosition',
            'steps.workUnit',
            'actions.user',
        ])->findOrFail($id);
    }

    public function approve(ApprovalEngine $engine): void
    {
        if ($this->requiresDedicatedReview()) {
            session()->flash('error', 'Keputusan harus diproses dari halaman review modul agar data operasionalnya lengkap.');

            return;
        }

        try {
            $this->requestModel = $engine->approve($this->requestModel, auth()->user(), $this->actionNotes)
                ->load(['template', 'requester', 'steps.actedBy', 'steps.organizationalPosition', 'steps.workUnit', 'actions.user']);
            $this->actionNotes = '';
            session()->flash('success', 'Step approval berhasil disetujui.');
        } catch (ValidationException $exception) {
            session()->flash('error', collect($exception->errors())->flatten()->first());
        }
    }

    public function reject(ApprovalEngine $engine): void
    {
        if ($this->requiresDedicatedReview()) {
            session()->flash('error', 'Keputusan harus diproses dari halaman review modul agar data operasionalnya lengkap.');

            return;
        }

        try {
            $this->requestModel = $engine->reject($this->requestModel, auth()->user(), $this->actionNotes)
                ->load(['template', 'requester', 'steps.actedBy', 'steps.organizationalPosition', 'steps.workUnit', 'actions.user']);
            $this->actionNotes = '';
            session()->flash('success', 'Request approval berhasil ditolak.');
        } catch (ValidationException $exception) {
            session()->flash('error', collect($exception->errors())->flatten()->first());
        }
    }

    public function cancel(ApprovalEngine $engine): void
    {
        if ($this->requiresDedicatedReview()) {
            session()->flash('error', 'Perubahan workflow harus diproses dari halaman review modul.');

            return;
        }

        try {
            $this->requestModel = $engine->cancel($this->requestModel, auth()->user(), $this->actionNotes)
                ->load(['template', 'requester', 'steps.actedBy', 'steps.organizationalPosition', 'steps.workUnit', 'actions.user']);
            $this->actionNotes = '';
            session()->flash('success', 'Request approval berhasil dibatalkan.');
        } catch (ValidationException $exception) {
            session()->flash('error', collect($exception->errors())->flatten()->first());
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Detail Approval',
        ]);
    }

    public function canAct(): bool
    {
        if ($this->requiresDedicatedReview()) {
            return false;
        }

        $step = $this->requestModel->currentStep();

        return $step && app(ApprovalEngine::class)->canUserActOnStep(auth()->user(), $step);
    }

    public function requiresDedicatedReview(): bool
    {
        return $this->requestModel->approvable instanceof StudentLeaveApplication
            || $this->requestModel->approvable instanceof StudentTransferRequest
            || $this->requestModel->approvable instanceof GraduationApplication;
    }

    public function dedicatedReviewUrl(): ?string
    {
        return match (true) {
            $this->requestModel->approvable instanceof StudentLeaveApplication => route(
                'admin.student-services.leave-applications.show',
                ['id' => $this->requestModel->approvable_id],
            ),
            $this->requestModel->approvable instanceof StudentTransferRequest => route(
                'admin.student-services.transfer-requests.show',
                ['id' => $this->requestModel->approvable_id],
            ),
            $this->requestModel->approvable instanceof GraduationApplication => route(
                'admin.student-services.graduation-applications.show',
                ['id' => $this->requestModel->approvable_id],
            ),
            default => null,
        };
    }

    public function dedicatedReviewLabel(): string
    {
        return match (true) {
            $this->requestModel->approvable instanceof StudentLeaveApplication => 'Buka Review Cuti',
            $this->requestModel->approvable instanceof StudentTransferRequest => 'Buka Review Pindah',
            $this->requestModel->approvable instanceof GraduationApplication => 'Buka Review Yudisium',
            default => 'Buka Detail Modul',
        };
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'cancelled', 'skipped' => 'bg-secondary',
            'current', 'in_progress' => 'bg-warning text-dark',
            default => 'bg-muted',
        };
    }

    public function approverLabel($step): string
    {
        return match ($step->approver_type) {
            'role' => 'Role: '.($step->approver_role ?: '-'),
            'permission' => 'Permission: '.($step->approver_permission ?: '-'),
            'position' => 'Jabatan: '.($step->organizationalPosition?->name ?: '-'),
            'work_unit' => 'Unit: '.($step->workUnit?->name ?: '-'),
            'user' => 'User: '.($step->approverUser?->name ?: '-'),
            default => str($step->approver_type)->title(),
        };
    }
};
?>

<div>
    <x-alert />
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title mb-0">{{ $requestModel->subject }}</h3>
                        <small class="text-muted">{{ $requestModel->reference ?: 'Tanpa referensi' }}</small>
                    </div>
                    <a href="{{ route('admin.organization.approval-requests.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="subheader">Status</div>
                            <span class="badge {{ $this->statusBadge($requestModel->status) }}">{{ str($requestModel->status)->replace('_', ' ')->title() }}</span>
                        </div>
                        <div class="col-md-3">
                            <div class="subheader">Template</div>
                            <div>{{ $requestModel->template?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="subheader">Requester</div>
                            <div>{{ $requestModel->requester?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="subheader">Submit</div>
                            <div>{{ $requestModel->submitted_at?->format('d M Y H:i') ?? '-' }}</div>
                        </div>
                    </div>

                    @if ($requestModel->notes)
                        <div class="mt-4">
                            <div class="subheader">Catatan</div>
                            <div class="border rounded p-3 bg-light">{{ $requestModel->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Timeline Step</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($requestModel->steps as $step)
                        <div class="list-group-item">
                            <div class="d-flex gap-3">
                                <span class="avatar {{ $this->statusBadge($step->status) }}">{{ $step->step_order }}</span>
                                <div class="flex-fill">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="fw-bold">{{ $step->name }}</div>
                                            <div class="text-secondary">{{ $this->approverLabel($step) }}</div>
                                        </div>
                                        <span class="badge {{ $this->statusBadge($step->status) }}">{{ str($step->status)->title() }}</span>
                                    </div>
                                    @if ($step->actedBy || $step->acted_at || $step->notes)
                                        <div class="mt-2 small text-secondary">
                                            {{ $step->actedBy?->name ?? 'System' }}
                                            {{ $step->acted_at ? ' - '.$step->acted_at->format('d M Y H:i') : '' }}
                                        </div>
                                        @if ($step->notes)
                                            <div class="mt-2 border rounded p-2 bg-light">{{ $step->notes }}</div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Aksi</h3>
                </div>
                <div class="card-body">
                    @if ($this->requiresDedicatedReview())
                        <div class="alert alert-info">
                            Pengajuan ini membutuhkan data review khusus modul. Proses keputusan dari halaman detail agar biaya, evaluasi, atau checklist tidak terlewat.
                        </div>
                        <a href="{{ $this->dedicatedReviewUrl() }}" class="btn btn-primary w-100">
                            <i class="fas fa-arrow-up-right-from-square me-2"></i>{{ $this->dedicatedReviewLabel() }}
                        </a>
                    @elseif (in_array($requestModel->status, ['submitted', 'in_progress'], true))
                        <textarea class="form-control mb-3" rows="4" wire:model.defer="actionNotes" placeholder="Catatan aksi"></textarea>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success" wire:click="approve" @disabled(! $this->canAct())>
                                <i class="fas fa-check me-2"></i> Approve Step
                            </button>
                            <button type="button" class="btn btn-danger" wire:click="reject" @disabled(! $this->canAct())>
                                <i class="fas fa-times me-2"></i> Reject
                            </button>
                            @activecan('approval-request.update')
                                <button type="button" class="btn btn-outline-secondary" wire:click="cancel">
                                    <i class="fas fa-ban me-2"></i> Batalkan Request
                                </button>
                            @endactivecan
                        </div>

                        @unless ($this->canAct())
                            <div class="alert alert-info mt-3 mb-0">
                                Anda tidak menjadi approver untuk step yang sedang berjalan.
                            </div>
                        @endunless
                    @else
                        <div class="empty">
                            <div class="empty-icon"><i class="fas fa-circle-check"></i></div>
                            <p class="empty-title">Request selesai</p>
                            <p class="empty-subtitle text-secondary">Tidak ada aksi lanjutan untuk status ini.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Riwayat Aksi</h3>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($requestModel->actions as $action)
                        <div class="list-group-item">
                            <div class="fw-semibold">{{ str($action->action)->title() }}</div>
                            <div class="small text-secondary">
                                {{ $action->user?->name ?? 'System' }} - {{ $action->created_at->format('d M Y H:i') }}
                            </div>
                            @if ($action->notes)
                                <div class="small mt-1">{{ $action->notes }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">Belum ada riwayat aksi.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
