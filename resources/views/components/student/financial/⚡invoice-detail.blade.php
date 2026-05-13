<?php

use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InstallmentSimulationService;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Financial\PaymentProcessingService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public StudentInvoice $invoice;

    public ?float $paymentAmount = null;

    public string $paymentMethod = 'bank_transfer';

    public ?string $transactionReference = null;

    public ?string $paymentNotes = null;

    public $paymentProof;

    public int $installmentTenor = 3;

    public ?string $installmentReason = null;

    public array $installmentSimulation = [];

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;

        abort_unless($studentProfile, 404);

        $this->invoice = StudentInvoice::query()
            ->with(['academicYear', 'items', 'payments.verifiedBy', 'installments', 'installmentRequests.reviewedBy'])
            ->where('student_profile_id', $studentProfile->id)
            ->where('status', '!=', 'draft')
            ->findOrFail($id);

        app(InvoiceStatusService::class)->refresh($this->invoice);
        app(PaymentProcessingService::class)->refreshInstallmentOverdue($this->invoice);
        $this->reloadInvoice();
        $this->paymentAmount = $this->defaultPaymentAmount();
        $this->simulateInstallment();
    }

    public function submitPayment(PaymentProcessingService $paymentService): void
    {
        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:1'],
            'paymentMethod' => ['required', 'in:bank_transfer,cash,credit_card,e_wallet'],
            'transactionReference' => ['nullable', 'string', 'max:100'],
            'paymentNotes' => ['nullable', 'string', 'max:1000'],
            'paymentProof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ]);

        try {
            $path = $this->paymentProof->store('financial/payment-proofs', 'public');

            $paymentService->submitProof(
                invoice: $this->invoice,
                amount: (float) $this->paymentAmount,
                proofPath: $path,
                paymentMethod: $this->paymentMethod,
                transactionReference: $this->transactionReference,
                notes: $this->paymentNotes,
                submittedBy: auth()->id(),
            );

            $this->reset(['transactionReference', 'paymentNotes', 'paymentProof']);
            session()->flash('success', 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi finance.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reloadInvoice();
        $this->paymentAmount = $this->defaultPaymentAmount();
    }

    public function updatedInstallmentTenor(): void
    {
        $this->simulateInstallment();
    }

    public function simulateInstallment(): void
    {
        $this->installmentSimulation = [];

        if (! $this->canRequestInstallment()) {
            return;
        }

        try {
            $this->installmentSimulation = app(InstallmentSimulationService::class)
                ->simulate($this->invoice, $this->installmentTenor);
        } catch (\Throwable) {
            $this->installmentSimulation = [];
        }
    }

    public function submitInstallmentRequest(): void
    {
        $this->validate([
            'installmentTenor' => ['required', 'integer', 'min:2', 'max:12'],
            'installmentReason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->canRequestInstallment()) {
            session()->flash('error', 'Invoice ini tidak tersedia untuk pengajuan cicilan.');

            return;
        }

        $simulation = app(InstallmentSimulationService::class)->simulate($this->invoice, $this->installmentTenor);

        $this->invoice->installmentRequests()->create([
            'student_profile_id' => $this->invoice->student_profile_id,
            'requested_tenor' => $this->installmentTenor,
            'requested_fee_amount' => $simulation['fee_amount'],
            'simulated_total_amount' => $simulation['total_amount'],
            'simulation_snapshot' => $simulation,
            'status' => 'submitted',
            'student_reason' => $this->installmentReason,
        ]);

        $this->reset('installmentReason');
        $this->reloadInvoice();
        $this->simulateInstallment();
        session()->flash('success', 'Pengajuan cicilan berhasil dikirim dan menunggu approval finance.');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Invoice Detail',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function paidPercent(): int
    {
        $total = (float) $this->invoice->total_amount;

        if ($total <= 0) {
            return 0;
        }

        return min(100, (int) round(((float) $this->invoice->paid_amount / $total) * 100));
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-lt text-green',
            'partially_paid' => 'bg-blue-lt text-blue',
            'overdue' => 'bg-red-lt text-red',
            'cancelled' => 'bg-secondary-lt text-secondary',
            'issued' => 'bg-indigo-lt text-indigo',
            'verified', 'approved' => 'bg-green-lt text-green',
            'pending', 'submitted' => 'bg-yellow-lt text-yellow',
            'rejected', 'failed' => 'bg-red-lt text-red',
            default => 'bg-yellow-lt text-yellow',
        };
    }

    public function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    public function typeLabel(?string $type): string
    {
        return str($type ?: 'invoice')->replace('_', ' ')->title()->toString();
    }

    public function canRequestInstallment(): bool
    {
        return ! in_array($this->invoice->status, ['paid', 'cancelled'], true)
            && (float) $this->invoice->outstanding_amount > 0
            && $this->invoice->installments->isEmpty()
            && ! $this->invoice->installmentRequests->contains('status', 'submitted');
    }

    private function defaultPaymentAmount(): float
    {
        $nextInstallment = $this->invoice->installments
            ->whereNotIn('status', ['paid'])
            ->sortBy('installment_no')
            ->first();

        if ($nextInstallment) {
            return max(0, (float) $nextInstallment->amount + (float) $nextInstallment->fee_amount - (float) $nextInstallment->paid_amount);
        }

        return (float) $this->invoice->outstanding_amount;
    }

    private function reloadInvoice(): void
    {
        $this->invoice->refresh()->load([
            'academicYear',
            'items',
            'payments.verifiedBy',
            'installments',
            'installmentRequests.reviewedBy',
        ]);
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
        }

        .modern-card:hover {
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.12);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            font-size: 0.85rem;
            color: white;
        }

        .hero-icon {
            width: 76px;
            height: 76px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
            flex-shrink: 0;
        }

        .hero-payment-panel {
            background: rgba(255,255,255,0.16);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.24);
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: 0 18px 45px rgba(31, 41, 55, 0.18);
        }

        .hero-payment-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: 0;
        }

        .hero-mini-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding-top: 0.85rem;
            margin-top: 0.85rem;
            border-top: 1px solid rgba(255,255,255,0.2);
            font-size: 0.82rem;
            color: rgba(255,255,255,0.84);
        }

        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .material-card {
            border-radius: 16px;
            padding: 1.25rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .material-card:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem 1.25rem;
        }

        .section-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 8px 18px rgba(102, 126, 234, 0.28);
        }

        .invoice-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .invoice-line-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            flex-shrink: 0;
        }

        .amount-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            background: #eef2ff;
            color: #4338ca;
            font-weight: 800;
            white-space: nowrap;
        }

        .timeline-item {
            position: relative;
            padding-left: 2.15rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.52rem;
            top: 2.1rem;
            bottom: -1rem;
            width: 2px;
            background: #e5e7eb;
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .timeline-dot {
            position: absolute;
            left: 0;
            top: 0.35rem;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            background: #667eea;
            box-shadow: 0 0 0 5px #eef2ff;
        }

        .payment-card {
            border-radius: 16px;
            padding: 1rem;
            background: white;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .action-panel {
            border-radius: 18px;
            overflow: hidden;
            background: white;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .action-panel-header {
            padding: 1rem;
            background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
            border-bottom: 1px solid #e5e7eb;
        }

        .action-panel-payment .action-panel-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .action-panel-soft {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 14px;
            padding: 0.9rem;
        }

        .simulation-row {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            align-items: center;
            padding: 0.8rem;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .empty-state-mini {
            border-radius: 16px;
            padding: 1.2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px dashed #cbd5e1;
            text-align: center;
            color: #64748b;
        }

        .invoice-progress {
            height: 10px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .invoice-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(135deg, #10b981 0%, #22c55e 100%);
        }

        .hero-progress {
            background: rgba(255,255,255,0.24);
        }

        .hero-progress .invoice-progress-bar {
            background: linear-gradient(135deg, #fef3c7 0%, #ffffff 100%);
        }

        .action-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            border: none;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .action-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .action-btn-soft {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
        }

        @media (max-width: 767.98px) {
            .hero-payment-value {
                font-size: 1.55rem;
            }

            .invoice-line {
                align-items: flex-start;
                flex-direction: column;
            }

            .amount-pill {
                width: 100%;
            }
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient mb-4 text-white">
        <div class="card-body p-4 p-lg-5 hero-content">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3">
                        <div class="hero-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Detail Invoice</div>
                            <h1 class="h2 mb-2" style="font-weight: 700;">{{ $invoice->invoice_number }}</h1>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="info-badge"><i class="fas fa-circle-info me-2"></i>{{ $this->statusLabel($invoice->status) }}</span>
                                <span class="info-badge"><i class="fas fa-tag me-2"></i>{{ $this->typeLabel($invoice->invoice_type) }}</span>
                                @if($invoice->academicYear)
                                    <span class="info-badge"><i class="fas fa-calendar me-2"></i>{{ $invoice->academicYear?->name }}</span>
                                @endif
                                @if($invoice->semester)
                                    <span class="info-badge"><i class="fas fa-layer-group me-2"></i>Semester {{ $invoice->semester }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="hero-payment-panel">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div style="font-size: 0.82rem; opacity: 0.88;">Sisa Pembayaran</div>
                                <div class="hero-payment-value">{{ $this->money($invoice->outstanding_amount) }}</div>
                            </div>
                            <span class="info-badge" style="background: rgba(255,255,255,0.18);">{{ $this->paidPercent() }}%</span>
                        </div>
                        <div class="invoice-progress hero-progress">
                            <div class="invoice-progress-bar" style="width: {{ $this->paidPercent() }}%;"></div>
                        </div>
                        <div class="hero-mini-row">
                            <span>Paid {{ $this->money($invoice->paid_amount) }}</span>
                            <span>Due {{ $invoice->due_date?->format('d M Y') ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="{{ route('student.financial.invoices') }}" class="action-btn" style="background: rgba(255,255,255,0.92); color: #4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Invoice
                </a>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #3b82f6;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Total Invoice</div>
                        <div style="font-size: 1.35rem; font-weight: 700; color: #1f2937;">{{ $this->money($invoice->total_amount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #10b981;">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Sudah Dibayar</div>
                        <div style="font-size: 1.35rem; font-weight: 700; color: #1f2937;">{{ $this->money($invoice->paid_amount) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #ef4444;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Outstanding</div>
                        <div class="{{ (float) $invoice->outstanding_amount > 0 ? 'text-danger' : 'text-success' }}" style="font-size: 1.35rem; font-weight: 700;">
                            {{ $this->money($invoice->outstanding_amount) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card modern-card mb-4">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-list-check"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Rincian Tagihan</h3>
                            <div class="small text-secondary">Snapshot item saat invoice diterbitkan</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @foreach ($invoice->items->sortBy('sort_order') as $item)
                            <div class="material-card">
                                <div class="invoice-line">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="invoice-line-icon">
                                            <i class="fas {{ $item->item_type === 'discount' ? 'fa-percent' : ($item->item_type === 'penalty' ? 'fa-triangle-exclamation' : 'fa-receipt') }}"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold" style="color: #111827;">{{ $item->description }}</div>
                                            <div class="text-secondary small mt-1">{{ str($item->item_type)->replace('_', ' ')->title() }}</div>
                                        </div>
                                    </div>
                                    <div class="amount-pill">{{ $this->money($item->amount) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="material-card mt-4" style="background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%); border-color: #c7d2fe;">
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <div class="fw-bold">Total Invoice</div>
                                <div class="text-secondary small">Nominal final yang perlu diselesaikan</div>
                            </div>
                            <div class="h3 mb-0 text-end" style="font-weight: 800; color: #4338ca;">{{ $this->money($invoice->total_amount) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if($invoice->notes)
                <div class="card modern-card">
                    <div class="card-body p-4">
                        <div class="fw-bold mb-2"><i class="fas fa-note-sticky me-2 text-primary"></i>Catatan Invoice</div>
                        <div class="text-secondary">{{ $invoice->notes }}</div>
                    </div>
                </div>
            @endif

            @if($invoice->installments->isNotEmpty())
                <div class="card modern-card mt-4">
                    <div class="card-header section-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="fas fa-calendar-check"></i></span>
                            <div>
                                <h3 class="card-title mb-0" style="font-weight: 700;">Jadwal Cicilan</h3>
                                <div class="small text-secondary">Bayar sesuai cicilan yang sudah disetujui finance</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-3">
                            @foreach($invoice->installments->sortBy('installment_no') as $installment)
                                @php
                                    $installmentTotal = (float) $installment->amount + (float) $installment->fee_amount;
                                    $installmentOutstanding = max(0, $installmentTotal - (float) $installment->paid_amount);
                                @endphp
                                <div class="material-card">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-bold" style="color: #111827;">Cicilan {{ $installment->installment_no }}</div>
                                            <div class="text-secondary small mt-1">Jatuh tempo {{ $installment->due_date?->format('d M Y') }}</div>
                                            <span class="badge {{ $this->statusClass($installment->status) }} mt-2">{{ $this->statusLabel($installment->status) }}</span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold">{{ $this->money($installmentTotal) }}</div>
                                            <div class="text-secondary small">Sisa {{ $this->money($installmentOutstanding) }}</div>
                                        </div>
                                    </div>
                                    <div class="invoice-progress mt-3">
                                        <div class="invoice-progress-bar" style="width: {{ $installmentTotal > 0 ? min(100, round(((float) $installment->paid_amount / $installmentTotal) * 100)) : 0 }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if($invoice->payments->isNotEmpty())
                <div class="card modern-card mt-4">
                    <div class="card-header section-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="fas fa-receipt"></i></span>
                            <div>
                                <h3 class="card-title mb-0" style="font-weight: 700;">Riwayat Pembayaran</h3>
                                <div class="small text-secondary">Bukti bayar dan status verifikasi finance</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-3">
                            @foreach($invoice->payments->sortByDesc('created_at') as $payment)
                                <div class="payment-card">
                                    <div class="timeline-item">
                                        <span class="timeline-dot"></span>
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="fw-bold" style="color: #111827;">{{ $payment->payment_number }}</div>
                                                <div class="text-secondary small mt-1">{{ $payment->paid_at?->format('d M Y H:i') }} / {{ str($payment->payment_method)->replace('_', ' ')->title() }}</div>
                                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                                    <span class="badge {{ $this->statusClass($payment->status) }}">{{ $this->statusLabel($payment->status) }}</span>
                                                    @if($payment->status === 'verified')
                                                        <a href="{{ route('student.financial.payments.receipt', ['payment' => $payment->id]) }}" target="_blank" class="badge bg-indigo-lt text-indigo text-decoration-none">
                                                            <i class="fas fa-file-pdf me-1"></i>Receipt PDF
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold">{{ $this->money($payment->amount) }}</div>
                                                <div class="text-secondary small">{{ $payment->verifiedBy?->name ? 'By '.$payment->verifiedBy?->name : 'Menunggu finance' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="card modern-card mt-4">
                    <div class="card-body p-4">
                        <div class="empty-state-mini">
                            <i class="fas fa-wallet mb-2" style="font-size: 1.8rem; color: #667eea;"></i>
                            <div class="fw-bold text-dark">Belum ada pembayaran</div>
                            <div class="small">Upload bukti bayar dari panel aksi di samping kanan.</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card modern-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0" style="color: #1f2937;"><i class="fas fa-info-circle me-2 text-primary"></i>Status</h6>
                        <span class="badge {{ $this->statusClass($invoice->status) }} px-3 py-2">{{ $this->statusLabel($invoice->status) }}</span>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small text-secondary mb-2">
                            <span>Progress Pembayaran</span>
                            <span class="fw-bold">{{ $this->paidPercent() }}%</span>
                        </div>
                        <div class="invoice-progress">
                            <div class="invoice-progress-bar" style="width: {{ $this->paidPercent() }}%;"></div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background: #f8fafc;">
                            <div class="small text-secondary">Terbit</div>
                            <div class="small fw-bold">{{ $invoice->issued_at?->format('d M Y') ?? $invoice->created_at?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background: #f8fafc;">
                            <div class="small text-secondary">Jatuh Tempo</div>
                            <div class="small fw-bold {{ (float) $invoice->outstanding_amount > 0 && $invoice->due_date && $invoice->due_date->isPast() ? 'text-danger' : '' }}">
                                {{ $invoice->due_date?->format('d M Y') ?? '-' }}
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background: #f8fafc;">
                            <div class="small text-secondary">Metode Bayar</div>
                            <div class="small fw-bold">{{ $invoice->installments->isNotEmpty() ? 'Cicilan' : 'Normal' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if(! in_array($invoice->status, ['paid', 'cancelled'], true))
                <div class="action-panel action-panel-payment mb-4">
                    <div class="action-panel-header">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 48px; height: 48px; background: rgba(255,255,255,0.18); border-radius: 14px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-upload"></i>
                            </div>
                            <div>
                                <div class="fw-bold">Kirim Pembayaran</div>
                                <div style="font-size: 0.82rem; opacity: 0.86;">Upload bukti bayar untuk diverifikasi finance</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-3">
                        @if($invoice->installments->isNotEmpty())
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>Nominal default mengikuti cicilan terdekat. Kamu bisa bayar beberapa cicilan sekaligus dengan menaikkan nominal.
                            </div>
                        @endif

                        <form wire:submit="submitPayment" class="d-grid gap-3">
                            <div>
                                <label class="form-label small fw-semibold">Nominal Bayar</label>
                                <input type="number" min="1" step="1" wire:model="paymentAmount" class="form-control">
                                @error('paymentAmount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label small fw-semibold">Metode</label>
                                <select wire:model="paymentMethod" class="form-select">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cash">Cash</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="e_wallet">E-Wallet</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label small fw-semibold">Reference</label>
                                <input type="text" wire:model="transactionReference" class="form-control" placeholder="Nomor transfer">
                            </div>
                            <div>
                                <label class="form-label small fw-semibold">Bukti Bayar <span class="text-danger">*</span></label>
                                <input type="file" wire:model="paymentProof" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                @error('paymentProof') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="form-label small fw-semibold">Catatan (opsional)</label>
                                <textarea wire:model="paymentNotes" class="form-control" rows="2" placeholder="Catatan untuk finance"></textarea>
                            </div>
                            <button type="submit" class="action-btn action-btn-primary justify-content-center">
                                <i class="fas fa-upload"></i> Kirim Bukti Bayar
                            </button>
                        </form>

                        <div class="action-panel-soft text-center mt-3">
                            <button type="button" class="btn btn-light w-100" disabled style="cursor: not-allowed;">
                                <i class="fas fa-credit-card me-1"></i> Bayar Otomatis
                            </button>
                            <div class="text-secondary small mt-2">Gateway belum diaktifkan</div>
                        </div>
                    </div>
                </div>

                <div class="action-panel">
                    <div class="action-panel-header">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #1e40af;">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <div class="fw-bold" style="color: #1f2937;">Ajukan Cicilan</div>
                                <div class="small text-secondary">
                                    @if($invoice->installments->isNotEmpty())
                                        Cicilan aktif
                                    @elseif($invoice->installmentRequests->contains('status', 'submitted'))
                                        Menunggu approval
                                    @else
                                        Simulasi dulu, kirim kalau cocok
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-3">
                        @if($invoice->installments->isNotEmpty())
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle me-2"></i>Pengajuan cicilan sudah disetujui.
                            </div>
                        @elseif($invoice->installmentRequests->contains('status', 'submitted'))
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-clock me-2"></i>Menunggu approval finance.
                            </div>
                        @else
                            <form wire:submit="submitInstallmentRequest" class="d-grid gap-3">
                                <div>
                                    <label class="form-label small fw-semibold">Tenor Cicilan</label>
                                    <select wire:model.live="installmentTenor" class="form-select">
                                        @foreach([2, 3, 4, 5, 6, 8, 10, 12] as $tenor)
                                            <option value="{{ $tenor }}">{{ $tenor }} kali</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if(! empty($installmentSimulation))
                                    <div class="action-panel-soft">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="fw-bold small">Simulasi</div>
                                            <span class="badge bg-blue-lt text-blue">{{ $installmentTenor }}x</span>
                                        </div>
                                        <div class="d-grid gap-2">
                                            @foreach($installmentSimulation['installments'] as $row)
                                                <div class="simulation-row">
                                                    <div>
                                                        <div class="small fw-bold">Cicilan {{ $row['installment_no'] }}</div>
                                                        <div class="text-secondary small">{{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}</div>
                                                    </div>
                                                    <div class="small fw-bold">{{ $this->money($row['total_amount']) }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <label class="form-label small fw-semibold">Alasan (opsional)</label>
                                    <textarea wire:model="installmentReason" class="form-control" rows="2" placeholder="Ceritakan kebutuhan cicilan"></textarea>
                                </div>
                                <button type="submit" class="action-btn action-btn-soft justify-content-center">
                                    <i class="fas fa-calendar-check"></i> Ajukan Cicilan
                                </button>
                            </form>
                        @endif

                        @if($invoice->installmentRequests->where('status', 'rejected')->isNotEmpty())
                            <div class="text-secondary small mt-3">
                                <i class="fas fa-info-circle me-1"></i>Pengajuan sebelumnya ditolak. Kamu masih bisa mengajukan ulang dengan tenor berbeda.
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card modern-card">
                    <div class="card-body p-4">
                        <div class="empty-state-mini">
                            <i class="fas fa-circle-check mb-2" style="font-size: 1.8rem; color: #10b981;"></i>
                            <div class="fw-bold text-dark">Invoice selesai</div>
                            <div class="small">Tidak ada aksi pembayaran yang diperlukan.</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
