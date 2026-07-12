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
            'approved' => 'bg-success text-white',
            'rejected' => 'bg-danger text-white',
            'cancelled', 'skipped' => 'bg-secondary text-white',
            'current', 'in_progress' => 'bg-warning text-dark',
            default => 'bg-muted text-white',
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

    <x-admin.organization.header
        title="{{ $requestModel->subject }}"
        description="Referensi: {{ $requestModel->reference ?: 'Tanpa referensi' }}"
        icon="file-signature"
    >
        <a href="{{ route('admin.organization.approval-requests.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Permohonan</div>
                        <span class="badge {{ $this->statusBadge($requestModel->status) }} rounded-pill px-2 py-1 mt-1">{{ str($requestModel->status)->replace('_', ' ')->title() }}</span>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Template</div>
                        <div class="fw-bold text-white">{{ $requestModel->template?->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pemohon</div>
                        <div class="fw-bold text-white">{{ $requestModel->requester?->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-alt fs-6 text-white"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Waktu Submit</div>
                        <div class="fw-bold text-white">{{ $requestModel->submitted_at?->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    @if ($requestModel->notes)
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa fa-comment-dots fs-6"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Catatan Pengaju</h5>
                </div>
                <div class="p-3 bg-light rounded-3 border-start border-4 border-primary text-secondary mb-0">
                    {{ $requestModel->notes }}
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-bars-progress fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Timeline & Langkah Approval</h4>
                            <div class="text-muted small">Riwayat dan status verifikasi di setiap tingkatan persetujuan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="list-group list-group-flush border-top">
                        @foreach ($requestModel->steps as $step)
                            <div class="list-group-item px-0 py-3 border-bottom">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="badge {{ $this->statusBadge($step->status) }} rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        {{ $step->step_order }}
                                    </span>
                                    <div class="flex-fill">
                                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-1">
                                            <div>
                                                <h6 class="fw-bold text-dark mb-0 fs-6">{{ $step->name }}</h6>
                                                <span class="small text-muted">{{ $this->approverLabel($step) }}</span>
                                            </div>
                                            <span class="badge {{ $this->statusBadge($step->status) }} rounded-pill px-3 py-1">{{ str($step->status)->title() }}</span>
                                        </div>
                                        @if ($step->actedBy || $step->acted_at || $step->notes)
                                            <div class="mt-2 p-2 bg-light rounded-3 small text-secondary">
                                                <div class="fw-medium text-dark">
                                                    <i class="fas fa-user-check me-1 text-primary"></i> {{ $step->actedBy?->name ?? 'System' }}
                                                    @if ($step->acted_at)
                                                        <span class="text-muted fw-normal">({{ $step->acted_at->format('d M Y H:i') }})</span>
                                                    @endif
                                                </div>
                                                @if ($step->notes)
                                                    <div class="mt-1 text-dark border-top pt-1">{{ $step->notes }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-clipboard-check fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Proses Keputusan</h4>
                            <div class="text-muted small">Lakukan persetujuan atau penolakan langkah saat ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 pt-3">
                    @if ($this->requiresDedicatedReview())
                        <div class="alert alert-info rounded-3 small mb-3">
                            <i class="fas fa-info-circle me-1"></i> Pengajuan ini membutuhkan data review khusus modul. Proses keputusan dari halaman detail agar biaya, evaluasi, atau checklist tidak terlewat.
                        </div>
                        <a href="{{ $this->dedicatedReviewUrl() }}" class="btn btn-primary rounded-pill shadow-sm w-100 fw-medium d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="fa fa-arrow-up-right-from-square"></i> <span>{{ $this->dedicatedReviewLabel() }}</span>
                        </a>
                    @elseif (in_array($requestModel->status, ['submitted', 'in_progress'], true))
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Catatan Tindakan (Opsional)</label>
                            <textarea class="form-control rounded-3" rows="3" wire:model.defer="actionNotes" placeholder="Tuliskan alasan persetujuan atau penolakan..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success rounded-pill shadow-sm fw-medium d-inline-flex align-items-center justify-content-center gap-2" wire:click="approve" @disabled(! $this->canAct())>
                                <i class="fa fa-check"></i> <span>Setujui Langkah Ini</span>
                            </button>
                            <button type="button" class="btn btn-danger rounded-pill shadow-sm fw-medium d-inline-flex align-items-center justify-content-center gap-2" wire:click="reject" @disabled(! $this->canAct())>
                                <i class="fa fa-times"></i> <span>Tolak Permohonan</span>
                            </button>
                            @activecan('approval-request.update')
                                <button type="button" class="btn btn-outline-secondary rounded-pill mt-1 d-inline-flex align-items-center justify-content-center gap-2" wire:click="cancel">
                                    <i class="fa fa-ban"></i> <span>Batalkan Request</span>
                                </button>
                            @endactivecan
                        </div>

                        @unless ($this->canAct())
                            <div class="alert alert-warning rounded-3 small mt-3 mb-0">
                                <i class="fas fa-exclamation-triangle me-1"></i> Anda tidak menjadi approver untuk step yang sedang berjalan saat ini.
                            </div>
                        @endunless
                    @else
                        <div class="text-center py-4 text-muted">
                            <div class="fs-1 text-success mb-2"><i class="fas fa-check-circle"></i></div>
                            <h6 class="fw-bold text-dark">Permohonan Telah Selesai</h6>
                            <p class="small text-muted mb-0">Tidak ada tindakan lanjutan yang diperlukan untuk status saat ini.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-clock-rotate-left fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Riwayat Sistem</h4>
                            <div class="text-muted small">Catatan jejak aktivitas.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="list-group list-group-flush border-top">
                        @forelse ($requestModel->actions as $action)
                            <div class="list-group-item px-0 py-2 border-bottom">
                                <div class="fw-semibold text-dark">{{ str($action->action)->title() }}</div>
                                <div class="small text-muted">
                                    <i class="fas fa-user me-1"></i> {{ $action->user?->name ?? 'System' }} &bull; {{ $action->created_at->format('d M Y H:i') }}
                                </div>
                                @if ($action->notes)
                                    <div class="small mt-1 p-2 bg-light rounded text-secondary">{{ $action->notes }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-3 text-muted small">Belum ada riwayat sistem.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
