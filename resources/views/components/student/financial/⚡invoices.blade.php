<?php

use App\Models\Financial\StudentInvoice;
use App\Support\Financial\InvoiceStatusService;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;

    public Collection $invoices;

    public array $summary = [
        'total_invoices' => 0,
        'outstanding' => 0,
        'overdue' => 0,
        'paid' => 0,
    ];

    public function mount(): void
    {
        $studentProfile = auth()->user()?->studentProfile;

        $this->invoices = collect();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;

        StudentInvoice::query()
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->get()
            ->each(fn (StudentInvoice $invoice) => app(InvoiceStatusService::class)->refresh($invoice));

        $this->invoices = StudentInvoice::query()
            ->with(['academicYear', 'items'])
            ->where('student_profile_id', $studentProfile->id)
            ->where('status', '!=', 'draft')
            ->orderByRaw("FIELD(status, 'overdue', 'partially_paid', 'issued', 'paid', 'cancelled')")
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        $this->summary = [
            'total_invoices' => $this->invoices->count(),
            'outstanding' => (float) $this->invoices
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->sum('outstanding_amount'),
            'overdue' => $this->invoices->where('status', 'overdue')->count(),
            'paid' => $this->invoices->where('status', 'paid')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'My Invoices',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function paidPercent(StudentInvoice $invoice): int
    {
        $total = (float) $invoice->total_amount;

        if ($total <= 0) {
            return 0;
        }

        return min(100, (int) round(((float) $invoice->paid_amount / $total) * 100));
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
            border: 2px solid transparent;
            border-radius: 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .material-card:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-color: #6366f1;
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
        }

        .invoice-metric {
            border-radius: 12px;
            background: rgba(255,255,255,0.82);
            padding: 0.85rem;
            height: 100%;
        }

        .invoice-progress {
            height: 8px;
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

        [data-bs-theme=dark] .modern-card,
        body[data-bs-theme=dark] .modern-card,
        [data-bs-theme=dark] .stat-card,
        body[data-bs-theme=dark] .stat-card,
        [data-bs-theme=dark] .material-card,
        body[data-bs-theme=dark] .material-card,
        [data-bs-theme=dark] .invoice-metric,
        body[data-bs-theme=dark] .invoice-metric {
            background: rgba(43, 28, 67, 0.85) !important;
            border-color: rgba(167, 139, 255, 0.22) !important;
            color: #f3edff !important;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum terhubung.</div>
    @else
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Ruang Keuangan Mahasiswa</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">Tagihan & Pembayaran</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">Pantau invoice kuliah, deadline pembayaran, dan status pelunasan.</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-badge">
                                        <i class="fas fa-file-invoice me-2"></i>{{ $summary['total_invoices'] }} invoice
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-triangle-exclamation me-2"></i>{{ $summary['overdue'] }} overdue
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-circle-check me-2"></i>{{ $summary['paid'] }} lunas
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                            <div style="font-size: 0.82rem; opacity: 0.86; margin-bottom: 0.45rem;">Total belum dibayar</div>
                            <div style="font-size: 2rem; font-weight: 700;">{{ $this->money($summary['outstanding']) }}</div>
                            <div style="font-size: 0.82rem; opacity: 0.82; margin-top: 0.35rem;">
                                {{ $summary['overdue'] }} invoice lewat jatuh tempo
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #3b82f6;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Total Invoice</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['total_invoices'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #ef4444;">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Overdue</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['overdue'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #10b981;">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Lunas</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['paid'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #8b5cf6;">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Outstanding</div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">{{ $this->money($summary['outstanding']) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card modern-card">
            <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-list-check" style="font-size: 1.3rem; color: #667eea;"></i>
                        <h3 class="card-title mb-0" style="font-weight: 700;">Daftar Invoice</h3>
                    </div>
                    <div>
                        <div class="text-secondary small">Invoice terbit dari admin akan muncul di sini setelah dipublish.</div>
                    </div>
                    <span class="badge bg-indigo-lt text-indigo px-3 py-2">{{ $summary['total_invoices'] }} invoice</span>
                </div>
            </div>

            <div class="card-body p-4">
                @forelse ($invoices as $invoice)
                    <div class="material-card mb-3">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="badge {{ $this->statusClass($invoice->status) }} px-3 py-2">{{ $this->statusLabel($invoice->status) }}</span>
                                    <span class="badge bg-secondary-lt text-secondary px-3 py-2">{{ $this->typeLabel($invoice->invoice_type) }}</span>
                                </div>
                                <h3 class="h4 mb-1" style="font-weight: 800; color: #111827;">{{ $invoice->invoice_number }}</h3>
                                <div class="text-secondary">
                                    @if($invoice->academicYear)
                                        <i class="fas fa-calendar me-1"></i>{{ $invoice->academicYear?->name }}
                                    @endif
                                    @if($invoice->semester)
                                        <span class="mx-2">/</span> Semester {{ $invoice->semester }}
                                    @endif
                                </div>
                            </div>

                            <a href="{{ route('student.financial.invoices.show', ['id' => $invoice->id]) }}" class="action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <i class="fas fa-eye me-1"></i> Detail
                            </a>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="invoice-metric">
                                    <div class="text-secondary small mb-1">Total</div>
                                    <div class="fw-bold">{{ $this->money($invoice->total_amount) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="invoice-metric">
                                    <div class="text-secondary small mb-1">Terbayar</div>
                                    <div class="fw-bold text-success">{{ $this->money($invoice->paid_amount) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="invoice-metric">
                                    <div class="text-secondary small mb-1">Sisa</div>
                                    <div class="fw-bold {{ (float) $invoice->outstanding_amount > 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $this->money($invoice->outstanding_amount) }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="invoice-metric">
                                    <div class="text-secondary small mb-1">Jatuh Tempo</div>
                                    <div class="fw-bold">{{ $invoice->due_date?->format('d M Y') ?? '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="d-flex justify-content-between small text-secondary mb-2">
                                <span>Progress pembayaran</span>
                                <span>{{ $this->paidPercent($invoice) }}%</span>
                            </div>
                            <div class="invoice-progress">
                                <div class="invoice-progress-bar" style="width: {{ $this->paidPercent($invoice) }}%;"></div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @if(! in_array($invoice->status, ['paid', 'cancelled'], true))
                                <a href="{{ route('student.financial.invoices.show', ['id' => $invoice->id]) }}#payment-actions" class="action-btn" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af;">
                                    <i class="fas fa-upload me-1"></i> Bayar Manual
                                </a>
                                <button type="button" class="action-btn" style="background: #e5e7eb; color: #9ca3af; cursor: not-allowed;" disabled>
                                    <i class="fas fa-credit-card me-1"></i> Bayar Otomatis
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <i class="fas fa-file-circle-check" style="font-size: 4rem; color: #cbd5e1;"></i>
                        <div class="mt-3 fw-bold" style="color: #334155;">Belum ada tagihan.</div>
                        <div class="text-secondary small mt-1">Invoice yang sudah dipublish oleh admin akan tampil di halaman ini.</div>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
