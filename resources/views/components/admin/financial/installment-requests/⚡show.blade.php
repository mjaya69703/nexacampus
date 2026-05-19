<?php

use App\Models\Financial\InvoiceInstallmentRequest;
use App\Support\ActivePermission;
use App\Support\Financial\InstallmentApprovalService;
use Livewire\Component;

new class extends Component
{
    public InvoiceInstallmentRequest $requestModel;

    public ?string $financeNotes = null;

    public function mount($id): void
    {
        $this->requestModel = InvoiceInstallmentRequest::with([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'reviewedBy',
            'installments',
        ])->findOrFail($id);
    }

    public function approve(InstallmentApprovalService $approvalService): void
    {
        abort_unless(ActivePermission::check('installment-request.update'), 403);

        try {
            $this->requestModel = $approvalService->approve($this->requestModel, auth()->id(), $this->financeNotes);
            session()->flash('success', 'Pengajuan cicilan berhasil disetujui.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadRequest();
    }

    public function reject(InstallmentApprovalService $approvalService): void
    {
        abort_unless(ActivePermission::check('installment-request.update'), 403);

        try {
            $this->requestModel = $approvalService->reject($this->requestModel, auth()->id(), $this->financeNotes);
            session()->flash('success', 'Pengajuan cicilan berhasil ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadRequest();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Installment Request Detail',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function reloadRequest(): void
    {
        $this->requestModel->refresh()->load([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'reviewedBy',
            'installments',
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
                    <h3 class="card-title mb-0">{{ $requestModel->invoice?->invoice_number }}</h3>
                    <small class="text-muted">Pengajuan cicilan {{ $requestModel->requested_tenor }}x</small>
                </div>
                <a href="{{ route('admin.financial.installment-requests.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">Mahasiswa</small>
                        <div class="h6 mb-0">{{ $requestModel->studentProfile?->user?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">NIM</small>
                        <div class="h6 mb-0">{{ $requestModel->studentProfile?->nim ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Tenor</small>
                        <div class="h6 mb-0">{{ $requestModel->requested_tenor }} kali</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Status</small>
                        <div class="h6 mb-0">{{ str($requestModel->status)->title() }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Fee</small>
                        <div class="h6 mb-0">{{ $this->money($requestModel->requested_fee_amount) }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Simulated Total</small>
                        <div class="h6 mb-0">{{ $this->money($requestModel->simulated_total_amount) }}</div>
                    </div>
                    @if($requestModel->student_reason)
                        <div class="col-12">
                            <small class="text-muted">Student Reason</small>
                            <div class="alert alert-light border mb-0">{{ $requestModel->student_reason }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Simulation</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Due Date</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Fee</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($requestModel->simulation_snapshot['installments'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['installment_no'] }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}</td>
                                <td class="text-end">{{ $this->money($row['amount']) }}</td>
                                <td class="text-end">{{ $this->money($row['fee_amount'] ?? 0) }}</td>
                                <td class="text-end">{{ $this->money($row['total_amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Decision</h5>
            </div>
            <div class="card-body">
                @if($requestModel->status === 'submitted')
                    <div class="mb-3">
                        <label class="form-label">Finance Notes</label>
                        <textarea wire:model="financeNotes" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" wire:click="approve">
                            <i class="fas fa-check me-1"></i> Approve
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="reject">
                            <i class="fas fa-times me-1"></i> Reject
                        </button>
                    </div>
                @else
                    <div class="mb-3">
                        <small class="text-muted">Reviewed By</small>
                        <div class="h6 mb-0">{{ $requestModel->reviewedBy?->name ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Reviewed At</small>
                        <div class="h6 mb-0">{{ $requestModel->reviewed_at?->format('d F Y H:i') ?? '-' }}</div>
                    </div>
                    <div>
                        <small class="text-muted">Notes</small>
                        <div>{{ $requestModel->finance_notes ?: '-' }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
