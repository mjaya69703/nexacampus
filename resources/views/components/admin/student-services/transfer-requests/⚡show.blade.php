<?php

use App\Models\StudentService\StudentTransferRequest;
use App\Support\ActivePermission;
use App\Support\StudentService\StudentTransferRequestService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public StudentTransferRequest $request;

    public array $evaluation = [
        'admin_notes' => '',
        'academic_evaluation_notes' => '',
        'credit_mapping_notes' => '',
        'finance_notes' => '',
        'recommended_semester' => null,
    ];

    public $transferFeeAmount = 0;

    public ?string $transferFeeDueDate = null;

    public function mount($id): void
    {
        $this->request = StudentTransferRequest::with([
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'fromStudyProgram.faculty',
            'toStudyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'appliedBy',
            'transferFeeInvoice',
            'approvalRequest.steps.actedBy',
        ])->findOrFail($id);

        $this->evaluation = [
            'admin_notes' => $this->request->admin_notes ?? '',
            'academic_evaluation_notes' => $this->request->academic_evaluation_notes ?? '',
            'credit_mapping_notes' => $this->request->credit_mapping_notes ?? '',
            'finance_notes' => $this->request->finance_notes ?? '',
            'recommended_semester' => $this->request->recommended_semester,
        ];
        $this->transferFeeAmount = (float) $this->request->transfer_fee_amount;
        $this->transferFeeDueDate = $this->request->transfer_fee_due_date?->format('Y-m-d') ?? now()->addDays(7)->toDateString();
    }

    public function markUnderReview(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'under_review', $this->evaluation['admin_notes'] ?: null, auth()->id());
        session()->flash('success', 'Pengajuan transfer ditandai under review.');
        $this->reload();
    }

    public function approve(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        $validated = $this->validate([
            'evaluation.admin_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.academic_evaluation_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.credit_mapping_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.finance_notes' => ['nullable', 'string', 'max:3000'],
            'evaluation.recommended_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'transferFeeAmount' => ['required', 'numeric', 'min:0'],
            'transferFeeDueDate' => ['nullable', 'date'],
        ]);

        if ((float) $this->transferFeeAmount > 0 && ! $this->transferFeeDueDate) {
            session()->flash('error', 'Due date wajib diisi jika transfer dikenakan biaya.');

            return;
        }

        try {
            $this->request = $service->approve(
                $this->request,
                $validated['evaluation'],
                auth()->id(),
                (float) $this->transferFeeAmount,
                $this->transferFeeDueDate,
            );

            session()->flash(
                'success',
                (float) $this->transferFeeAmount > 0
                    ? 'Pengajuan transfer diapprove dan invoice biaya transfer diterbitkan.'
                    : 'Pengajuan transfer berhasil diapprove.',
            );
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function requestCorrection(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);
        $this->request = $service->setStatus($this->request, 'revision_requested', $this->evaluation['admin_notes'] ?: null, auth()->id());
        session()->flash('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        $this->reload();
    }

    public function reject(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);
        try {
            $this->request = $service->reject($this->request, $this->evaluation['admin_notes'] ?: null, auth()->id());
            session()->flash('success', 'Pengajuan transfer ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
        $this->reload();
    }

    public function applyTransfer(StudentTransferRequestService $service): void
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        try {
            $this->request = $service->applyTransfer($this->request, auth()->id(), $this->evaluation['admin_notes'] ?: null);
            session()->flash('success', 'Transfer mahasiswa berhasil diterapkan.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Detail Pengajuan Pindah',
        ]);
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'applied' => 'bg-success',
            'approved' => 'bg-info',
            'approved_pending_payment', 'revision_requested', 'in_approval' => 'bg-warning text-dark',
            'under_review' => 'bg-primary',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Approval',
            'approved_pending_payment' => 'Menunggu Pembayaran',
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
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'fromStudyProgram.faculty',
            'toStudyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'appliedBy',
            'transferFeeInvoice',
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
                    <small class="text-muted">Pengajuan Pindah Internal</small>
                </div>
                <a href="{{ route('admin.student-services.transfer-requests.index') }}" class="btn btn-secondary">
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
                    <div class="col-md-6">
                        <small class="text-muted">{{ $request->transfer_type === 'class_type' ? 'Kelas Saat Ini' : 'From Program' }}</small>
                        <div>{{ $request->transfer_type === 'class_type' ? ucfirst($request->from_class_type ?? '-') : ($request->fromStudyProgram?->name ?? '-') }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ $request->transfer_type === 'class_type' ? 'Kelas Tujuan' : 'To Program' }}</small>
                        <div>{{ $request->transfer_type === 'class_type' ? ucfirst($request->to_class_type ?? '-') : ($request->toStudyProgram?->name ?? '-') }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Transfer Type</small>
                        <div>{{ str($request->transfer_type)->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Current Semester</small>
                        <div>{{ $request->current_semester ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Recommended Semester</small>
                        <div>{{ $request->recommended_semester ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Transfer Fee</small>
                        <div>Rp {{ number_format((float) $request->transfer_fee_amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Reason</small>
                        <div>{{ $request->reason }}</div>
                    </div>
                    @if ($request->student_notes)
                        <div class="col-12">
                            <small class="text-muted">Student Notes</small>
                            <div>{{ $request->student_notes }}</div>
                        </div>
                    @endif
                    @if ($request->transferFeeInvoice)
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                Invoice transfer:
                                <a href="{{ route('admin.financial.student-invoices.show', ['id' => $request->transferFeeInvoice->id]) }}">
                                    {{ $request->transferFeeInvoice->invoice_number }}
                                </a>
                                <span class="badge bg-light text-dark">{{ $request->transferFeeInvoice->status }}</span>
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

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Evaluation Notes</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Recommended Semester</label>
                        <input type="number" min="1" max="14" class="form-control" wire:model="evaluation.recommended_semester">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Transfer Fee</label>
                        <input type="number" min="0" step="0.01" class="form-control" wire:model="transferFeeAmount" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fee Due Date</label>
                        <input type="date" class="form-control" wire:model="transferFeeDueDate" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Admin Notes</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.admin_notes"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Academic Evaluation Notes</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.academic_evaluation_notes"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Credit Mapping Notes</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.credit_mapping_notes"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Finance Notes</label>
                        <textarea class="form-control" rows="3" wire:model="evaluation.finance_notes"></textarea>
                    </div>
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
            <div class="card-body d-grid gap-2">
                <button wire:click="markUnderReview" class="btn btn-outline-primary" @disabled(! in_array($request->status, ['submitted', 'revision_requested'], true))>
                    <i class="fas fa-search me-1"></i> Mark Under Review
                </button>
                <button wire:click="approve" class="btn btn-success" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    <i class="fas fa-check me-1"></i> Approve
                </button>
                <button wire:click="requestCorrection" class="btn btn-warning" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    <i class="fas fa-rotate-left me-1"></i> Minta Perbaikan
                </button>
                <button wire:click="reject" class="btn btn-danger" @disabled(! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true))>
                    <i class="fas fa-times me-1"></i> Reject
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Terapkan Pindah</h3>
            </div>
            <div class="card-body d-grid gap-2">
                <button wire:click="applyTransfer" class="btn btn-primary" @disabled(! in_array($request->status, ['approved', 'approved_pending_payment'], true) || ($request->transferFeeInvoice && $request->transferFeeInvoice->status !== 'paid'))>
                    <i class="fas fa-right-left me-1"></i> Terapkan Pindah
                </button>
                <div class="alert alert-info mb-0">
                    Terapkan pindah akan mengubah program studi mahasiswa ke target program. Jika ada invoice pindah, invoice harus lunas dulu.
                </div>
            </div>
        </div>
    </div>
</div>
