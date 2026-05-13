<?php

use App\Models\Financial\Payment;
use App\Support\ActivePermission;
use App\Support\Financial\PaymentProcessingService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public Payment $payment;

    public ?string $verificationNotes = null;

    public function mount($id): void
    {
        $this->payment = Payment::with([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'installment',
            'submittedBy',
            'verifiedBy',
        ])->findOrFail($id);
    }

    public function verify(PaymentProcessingService $paymentService): void
    {
        abort_unless(ActivePermission::check('payment.update'), 403);

        try {
            $this->payment = $paymentService->verify($this->payment, auth()->id(), $this->verificationNotes);
            session()->flash('success', 'Payment berhasil diverifikasi.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadPayment();
    }

    public function reject(PaymentProcessingService $paymentService): void
    {
        abort_unless(ActivePermission::check('payment.update'), 403);

        try {
            $this->payment = $paymentService->reject($this->payment, auth()->id(), $this->verificationNotes);
            session()->flash('success', 'Payment berhasil ditolak.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadPayment();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Payment Detail',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function proofUrl(): ?string
    {
        if (! $this->payment->proof_file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->payment->proof_file_path);
    }

    public function proofExtension(): ?string
    {
        if (! $this->payment->proof_file_path) {
            return null;
        }

        return strtolower(pathinfo($this->payment->proof_file_path, PATHINFO_EXTENSION));
    }

    public function isProofImage(): bool
    {
        return in_array($this->proofExtension(), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    public function isProofPdf(): bool
    {
        return $this->proofExtension() === 'pdf';
    }

    private function reloadPayment(): void
    {
        $this->payment->refresh()->load([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'installment',
            'submittedBy',
            'verifiedBy',
        ]);
    }
};
?>

@push('styles')
    <style>
        .proof-preview-shell {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #f8fafc;
        }

        .proof-preview-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #fff;
        }

        .proof-preview-image {
            display: block;
            width: 100%;
            max-height: 680px;
            object-fit: contain;
            background: #111827;
        }

        .proof-preview-pdf {
            display: block;
            width: 100%;
            min-height: 680px;
            border: 0;
            background: #fff;
        }
    </style>
@endpush

<div class="row">
    <div class="col-lg-8">
        <x-alert />

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">{{ $payment->payment_number }}</h3>
                    <small class="text-muted">Payment proof verification</small>
                </div>
                <a href="{{ route('admin.financial.payments.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">Mahasiswa</small>
                        <div class="h6 mb-0">{{ $payment->studentProfile?->user?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">NIM</small>
                        <div class="h6 mb-0">{{ $payment->studentProfile?->nim ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Invoice</small>
                        <div class="h6 mb-0">{{ $payment->invoice?->invoice_number ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Target</small>
                        <div class="h6 mb-0">{{ $payment->installment ? 'Cicilan '.$payment->installment->installment_no : 'Invoice' }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Amount</small>
                        <div class="h5 mb-0">{{ $this->money($payment->amount) }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Status</small>
                        <div class="h6 mb-0">{{ str($payment->status)->title() }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Method</small>
                        <div class="h6 mb-0">{{ str($payment->payment_method)->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Reference</small>
                        <div class="h6 mb-0">{{ $payment->transaction_reference ?: '-' }}</div>
                    </div>
                    @if($payment->notes)
                        <div class="col-12">
                            <small class="text-muted">Student Notes</small>
                            <div class="alert alert-light border mb-0">{{ $payment->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Proof File</h5>
            </div>
            <div class="card-body">
                @if($this->proofUrl())
                    <div class="proof-preview-shell">
                        <div class="proof-preview-toolbar">
                            <div>
                                <div class="fw-bold">Bukti Pembayaran</div>
                                <div class="text-muted small">{{ strtoupper($this->proofExtension() ?? 'FILE') }} preview</div>
                            </div>
                            <a href="{{ $this->proofUrl() }}" target="_blank" class="btn btn-primary btn-sm">
                                <i class="fas fa-up-right-from-square me-1"></i> Open
                            </a>
                        </div>

                        @if($this->isProofImage())
                            <a href="{{ $this->proofUrl() }}" target="_blank">
                                <img src="{{ $this->proofUrl() }}" alt="Bukti pembayaran {{ $payment->payment_number }}" class="proof-preview-image">
                            </a>
                        @elseif($this->isProofPdf())
                            <iframe src="{{ $this->proofUrl() }}#toolbar=1&navpanes=0" class="proof-preview-pdf" title="Bukti pembayaran {{ $payment->payment_number }}"></iframe>
                        @else
                            <div class="p-4 text-center">
                                <i class="fas fa-file-lines text-muted mb-2" style="font-size: 2rem;"></i>
                                <div class="fw-bold">Preview tidak tersedia untuk tipe file ini.</div>
                                <div class="text-muted small">Gunakan tombol Open untuk melihat file.</div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-muted">Tidak ada bukti pembayaran.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Verification</h5>
            </div>
            <div class="card-body">
                @if($payment->status === 'pending')
                    <div class="mb-3">
                        <label class="form-label">Verification Notes</label>
                        <textarea wire:model="verificationNotes" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" wire:click="verify">
                            <i class="fas fa-check me-1"></i> Verify Payment
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="reject">
                            <i class="fas fa-times me-1"></i> Reject Payment
                        </button>
                    </div>
                @else
                    @if($payment->status === 'verified')
                        <a href="{{ route('admin.financial.payments.receipt', ['payment' => $payment->id]) }}" target="_blank" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-file-pdf me-1"></i> Open Receipt PDF
                        </a>
                    @endif
                    <div class="mb-3">
                        <small class="text-muted">Verified By</small>
                        <div class="h6 mb-0">{{ $payment->verifiedBy?->name ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Verified At</small>
                        <div class="h6 mb-0">{{ $payment->verified_at?->format('d F Y H:i') ?? '-' }}</div>
                    </div>
                    <div>
                        <small class="text-muted">Notes</small>
                        <div>{{ $payment->verification_notes ?: '-' }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
