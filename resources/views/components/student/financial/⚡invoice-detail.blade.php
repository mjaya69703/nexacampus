<?php

use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InvoiceStatusService;
use Livewire\Component;

new class extends Component
{
    public StudentInvoice $invoice;

    public function mount($id): void
    {
        $studentProfile = auth()->user()?->studentProfile;

        abort_unless($studentProfile, 404);

        $this->invoice = StudentInvoice::query()
            ->with(['academicYear', 'items'])
            ->where('student_profile_id', $studentProfile->id)
            ->where('status', '!=', 'draft')
            ->findOrFail($id);

        app(InvoiceStatusService::class)->refresh($this->invoice);
        $this->invoice->refresh()->load(['academicYear', 'items']);
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
    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient mb-4" style="color: white;">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3">
                        <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
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
                    <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Sisa Pembayaran</div>
                        <div style="font-size: 2rem; font-weight: 700;">{{ $this->money($invoice->outstanding_amount) }}</div>
                        <div style="font-size: 0.82rem; opacity: 0.82; margin-top: 0.35rem;">
                            Jatuh tempo {{ $invoice->due_date?->format('d M Y') ?? '-' }}
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
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-list-check" style="font-size: 1.3rem; color: #667eea;"></i>
                        <h3 class="card-title mb-0" style="font-weight: 700;">Rincian Tagihan</h3>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @foreach ($invoice->items->sortBy('sort_order') as $item)
                            <div class="material-card">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-bold" style="color: #111827;">{{ $item->description }}</div>
                                        <div class="text-secondary small mt-1">{{ str($item->item_type)->replace('_', ' ')->title() }}</div>
                                    </div>
                                    <div class="fw-bold text-end">{{ $this->money($item->amount) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top: 2px solid #e2e8f0;">
                        <div class="fw-bold">Total</div>
                        <div class="h3 mb-0" style="font-weight: 700;">{{ $this->money($invoice->total_amount) }}</div>
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
        </div>

        <div class="col-lg-4">
            <div class="card modern-card mb-4">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700;">Status Pembayaran</h3>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between small text-secondary mb-2">
                        <span>Progress</span>
                        <span>{{ $this->paidPercent() }}%</span>
                    </div>
                    <div class="invoice-progress mb-4">
                        <div class="invoice-progress-bar" style="width: {{ $this->paidPercent() }}%;"></div>
                    </div>

                    <div class="d-grid gap-3">
                        <div>
                            <div class="text-secondary small">Status</div>
                            <span class="badge {{ $this->statusClass($invoice->status) }} px-3 py-2">{{ $this->statusLabel($invoice->status) }}</span>
                        </div>
                        <div>
                            <div class="text-secondary small">Tanggal Terbit</div>
                            <div class="fw-bold">{{ $invoice->issued_at?->format('d M Y') ?? $invoice->created_at?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-secondary small">Jatuh Tempo</div>
                            <div class="fw-bold">{{ $invoice->due_date?->format('d F Y') ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if(! in_array($invoice->status, ['paid', 'cancelled'], true))
                <div class="card modern-card" id="payment-actions">
                    <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                        <h3 class="card-title mb-0" style="font-weight: 700;">Aksi Pembayaran</h3>
                    </div>
                    <div class="card-body p-4 d-grid gap-2">
                        <button type="button" class="action-btn justify-content-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; cursor: not-allowed;" disabled>
                            <i class="fas fa-upload"></i> Bayar Manual
                        </button>
                        <button type="button" class="action-btn justify-content-center" style="background: #e5e7eb; color: #9ca3af; cursor: not-allowed;" disabled>
                            <i class="fas fa-credit-card"></i> Bayar Otomatis
                        </button>
                        <div class="text-secondary small mt-2">
                            Upload bukti bayar dan payment gateway akan aktif di phase payment processing.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
