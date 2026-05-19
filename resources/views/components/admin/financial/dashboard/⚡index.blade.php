<?php

use App\Models\Financial\FinancialHold;
use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\InvoiceSchedule;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\StudentScholarship;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public array $invoiceStatusRows = [];

    public array $invoiceTypeRows = [];

    public array $chartSummary = [];

    public array $revenueTrendRows = [];

    public array $agingRows = [];

    public array $paymentMethodRows = [];

    public array $programOutstandingRows = [];

    public $recentPayments;

    public $overdueInvoices;

    public $dueSchedules;

    public $activeHolds;

    public function mount(): void
    {
        $this->stats = [
            'verified_payments' => (float) Payment::where('status', 'verified')->sum('amount'),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'outstanding' => (float) StudentInvoice::whereNotIn('status', ['draft', 'cancelled', 'paid'])->sum('outstanding_amount'),
            'overdue_invoices' => StudentInvoice::where('status', 'overdue')->count(),
            'active_holds' => FinancialHold::where('status', 'active')->count(),
            'due_schedules' => InvoiceSchedule::where('status', 'pending')->where('is_active', true)->where('publish_at', '<=', now())->count(),
            'student_credits' => (float) StudentCreditBalance::sum('balance'),
            'active_scholarships' => StudentScholarship::where('status', 'active')->count(),
            'net_adjustments' => (float) InvoiceAdjustment::sum('amount'),
        ];

        $this->invoiceStatusRows = StudentInvoice::query()
            ->selectRaw('status, COUNT(*) as invoice_count, SUM(total_amount) as total_amount, SUM(outstanding_amount) as outstanding_amount')
            ->groupBy('status')
            ->orderByRaw("FIELD(status, 'draft', 'issued', 'partially_paid', 'overdue', 'paid', 'cancelled')")
            ->get()
            ->map(fn (StudentInvoice $row) => [
                'status' => str($row->status)->replace('_', ' ')->title()->toString(),
                'invoice_count' => (int) $row->invoice_count,
                'total_amount' => (float) $row->total_amount,
                'outstanding_amount' => (float) $row->outstanding_amount,
            ])
            ->toArray();

        $this->invoiceTypeRows = StudentInvoice::query()
            ->selectRaw('invoice_type, COUNT(*) as invoice_count, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(outstanding_amount) as outstanding_amount')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy('invoice_type')
            ->orderByDesc('outstanding_amount')
            ->limit(6)
            ->get()
            ->map(fn (StudentInvoice $row) => [
                'invoice_type' => str($row->invoice_type)->replace('_', ' ')->title()->toString(),
                'invoice_count' => (int) $row->invoice_count,
                'total_amount' => (float) $row->total_amount,
                'paid_amount' => (float) $row->paid_amount,
                'outstanding_amount' => (float) $row->outstanding_amount,
            ])
            ->toArray();

        $totalBilled = collect($this->invoiceTypeRows)->sum('total_amount');
        $totalPaid = collect($this->invoiceTypeRows)->sum('paid_amount');
        $totalOutstanding = collect($this->invoiceTypeRows)->sum('outstanding_amount');
        $maxTypeOutstanding = max(1, (float) collect($this->invoiceTypeRows)->max('outstanding_amount'));
        $maxStatusCount = max(1, (int) collect($this->invoiceStatusRows)->max('invoice_count'));

        $this->chartSummary = [
            'total_billed' => (float) $totalBilled,
            'total_paid' => (float) $totalPaid,
            'total_outstanding' => (float) $totalOutstanding,
            'paid_percent' => $totalBilled > 0 ? min(100, round(($totalPaid / $totalBilled) * 100)) : 0,
            'outstanding_percent' => $totalBilled > 0 ? min(100, round(($totalOutstanding / $totalBilled) * 100)) : 0,
            'max_type_outstanding' => $maxTypeOutstanding,
            'max_status_count' => $maxStatusCount,
        ];

        $this->revenueTrendRows = $this->revenueTrendRows();
        $this->agingRows = $this->agingRows();
        $this->paymentMethodRows = $this->paymentMethodRows();
        $this->programOutstandingRows = $this->programOutstandingRows();

        $this->recentPayments = Payment::query()
            ->with(['invoice', 'studentProfile.user'])
            ->latest()
            ->limit(6)
            ->get();

        $this->overdueInvoices = StudentInvoice::query()
            ->with(['studentProfile.user'])
            ->where('status', 'overdue')
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        $this->dueSchedules = InvoiceSchedule::query()
            ->with('academicYear')
            ->where('status', 'pending')
            ->where('is_active', true)
            ->where('publish_at', '<=', now())
            ->orderBy('publish_at')
            ->limit(5)
            ->get();

        $this->activeHolds = FinancialHold::query()
            ->with(['studentProfile.user', 'invoice'])
            ->where('status', 'active')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Financial Dashboard',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function statusClass(?string $status): string
    {
        return match ($status) {
            'verified', 'paid', 'completed' => 'bg-green-lt text-green',
            'pending', 'issued', 'partially_paid' => 'bg-yellow-lt text-yellow',
            'overdue', 'rejected', 'failed' => 'bg-red-lt text-red',
            'cancelled' => 'bg-secondary-lt text-secondary',
            default => 'bg-blue-lt text-blue',
        };
    }

    private function revenueTrendRows(): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));

        $payments = Payment::query()
            ->where('status', 'verified')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $months->first()->copy()->startOfMonth())
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->format('Y-m'));

        return $months
            ->map(fn (Carbon $month) => [
                'label' => $month->format('M Y'),
                'amount' => (float) ($payments->get($month->format('Y-m'), collect())->sum('amount')),
            ])
            ->values()
            ->all();
    }

    private function agingRows(): array
    {
        $buckets = [
            ['label' => 'Current', 'min' => null, 'max' => 0, 'amount' => 0.0],
            ['label' => '1-7 days', 'min' => 1, 'max' => 7, 'amount' => 0.0],
            ['label' => '8-30 days', 'min' => 8, 'max' => 30, 'amount' => 0.0],
            ['label' => '31-60 days', 'min' => 31, 'max' => 60, 'amount' => 0.0],
            ['label' => '> 60 days', 'min' => 61, 'max' => null, 'amount' => 0.0],
        ];

        StudentInvoice::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'paid'])
            ->where('outstanding_amount', '>', 0)
            ->get(['due_date', 'outstanding_amount'])
            ->each(function (StudentInvoice $invoice) use (&$buckets): void {
                $days = $invoice->due_date?->startOfDay()->diffInDays(now()->startOfDay(), false) ?? 0;

                foreach ($buckets as $index => $bucket) {
                    $min = $bucket['min'];
                    $max = $bucket['max'];

                    if (($min === null || $days >= $min) && ($max === null || $days <= $max)) {
                        $buckets[$index]['amount'] += (float) $invoice->outstanding_amount;

                        break;
                    }
                }
            });

        return $buckets;
    }

    private function paymentMethodRows(): array
    {
        return Payment::query()
            ->where('status', 'verified')
            ->selectRaw('payment_method, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (Payment $payment) => [
                'label' => str($payment->payment_method)->replace('_', ' ')->title()->toString(),
                'amount' => (float) $payment->total_amount,
            ])
            ->values()
            ->all();
    }

    private function programOutstandingRows(): array
    {
        return StudentInvoice::query()
            ->with('studentProfile.studyProgram')
            ->whereNotIn('status', ['draft', 'cancelled', 'paid'])
            ->where('outstanding_amount', '>', 0)
            ->get()
            ->groupBy(fn (StudentInvoice $invoice) => $invoice->studentProfile?->studyProgram?->name ?? 'Unassigned')
            ->map(fn (Collection $invoices, string $program) => [
                'label' => $program,
                'amount' => (float) $invoices->sum('outstanding_amount'),
            ])
            ->sortByDesc('amount')
            ->take(6)
            ->values()
            ->all();
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
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.11);
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
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
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

        .hero-icon {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            flex-shrink: 0;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 0.8rem;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            font-size: 0.85rem;
            color: white;
        }

        .stat-card {
            padding: 1.35rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-label {
            font-size: 0.82rem;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .stat-value {
            font-size: 1.45rem;
            font-weight: 800;
            color: #111827;
            line-height: 1.15;
            word-break: break-word;
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
            flex-shrink: 0;
        }

        .soft-list-item {
            padding: 1rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #eef2ff;
            transition: all 0.25s ease;
        }

        .soft-list-item:hover {
            transform: translateX(4px);
            border-color: #c7d2fe;
            box-shadow: 0 8px 22px rgba(102, 126, 234, 0.12);
        }

        .compact-table th,
        .compact-table td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
        }

        .empty-soft {
            padding: 2rem 1rem;
            border-radius: 16px;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }

        .chart-card {
            min-height: 100%;
        }

        .chart-track {
            height: 12px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .chart-bar {
            height: 100%;
            border-radius: inherit;
            min-width: 4px;
        }

        .chart-row {
            display: grid;
            grid-template-columns: minmax(90px, 130px) 1fr auto;
            gap: 0.9rem;
            align-items: center;
        }

        .chart-row-label {
            font-weight: 700;
            color: #1f2937;
            overflow-wrap: anywhere;
        }

        .chart-row-value {
            min-width: 120px;
            text-align: right;
            font-weight: 800;
        }

        .chart-stack {
            display: flex;
            height: 22px;
            border-radius: 999px;
            overflow: hidden;
            background: #e5e7eb;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.04);
        }

        .chart-stack-segment {
            height: 100%;
            min-width: 4px;
        }

        .apex-chart-box {
            min-height: 300px;
        }

        .apex-chart-box-sm {
            min-height: 255px;
        }

        @media (max-width: 575.98px) {
            .chart-row {
                grid-template-columns: 1fr;
                gap: 0.45rem;
            }

            .chart-row-value {
                text-align: left;
            }
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient mb-4" style="color: white;">
        <div class="card-body p-4 p-lg-5 hero-content">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3">
                        <div class="hero-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Financial Operations</div>
                            <h1 class="h2 mb-2" style="font-weight: 800;">Financial Dashboard</h1>
                            <div style="opacity: 0.9; margin-bottom: 1rem;">
                                Pantau invoice, pembayaran, overdue, hold, schedule, beasiswa, dan credit balance dari satu halaman.
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="info-badge"><i class="fas fa-receipt me-2"></i>{{ number_format($stats['pending_payments']) }} pending payment</span>
                                <span class="info-badge"><i class="fas fa-triangle-exclamation me-2"></i>{{ number_format($stats['overdue_invoices']) }} overdue</span>
                                <span class="info-badge"><i class="fas fa-clock me-2"></i>{{ number_format($stats['due_schedules']) }} due schedule</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 14px; padding: 1rem;">
                        <div style="font-size: 0.82rem; opacity: 0.86; margin-bottom: 0.45rem;">Outstanding balance</div>
                        <div style="font-size: 2rem; font-weight: 800; line-height: 1.1;">{{ $this->money($stats['outstanding']) }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="{{ route('admin.financial.student-invoices.create') }}" class="btn btn-light btn-sm">
                                <i class="fas fa-file-invoice me-1"></i> Create Invoice
                            </a>
                            <a href="{{ route('admin.financial.invoice-schedules.create') }}" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-clock me-1"></i> Schedule
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="stat-label">Verified Payments</div>
                        <div class="stat-value">{{ $this->money($stats['verified_payments']) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div>
                        <div class="stat-label">Outstanding</div>
                        <div class="stat-value">{{ $this->money($stats['outstanding']) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('admin.financial.payments.index') }}" class="stat-card d-block text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <div class="stat-label">Pending Payments</div>
                        <div class="stat-value">{{ number_format($stats['pending_payments']) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('admin.financial.student-invoices.index') }}" class="stat-card d-block text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%);">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <div class="stat-label">Overdue Invoices</div>
                        <div class="stat-value">{{ number_format($stats['overdue_invoices']) }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('admin.financial.financial-holds.index') }}" class="stat-card d-block text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <div class="stat-label">Active Holds</div>
                        <div class="stat-value">{{ number_format($stats['active_holds']) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('admin.financial.invoice-schedules.index') }}" class="stat-card d-block text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-label">Due Schedules</div>
                        <div class="stat-value">{{ number_format($stats['due_schedules']) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('admin.financial.student-credits.index') }}" class="stat-card d-block text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <div>
                        <div class="stat-label">Student Credits</div>
                        <div class="stat-value">{{ $this->money($stats['student_credits']) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('admin.financial.student-scholarships.index') }}" class="stat-card d-block text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                        <i class="fas fa-award"></i>
                    </div>
                    <div>
                        <div class="stat-label">Active Scholarships</div>
                        <div class="stat-value">{{ number_format($stats['active_scholarships']) }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card modern-card chart-card">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-chart-line"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Revenue Trend</h3>
                            <div class="small text-secondary">Penerimaan terverifikasi dalam 6 bulan terakhir</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="financial-revenue-trend-chart" class="apex-chart-box"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card modern-card chart-card">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-chart-pie"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Payment Methods</h3>
                            <div class="small text-secondary">Komposisi metode pembayaran terverifikasi</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="financial-payment-method-chart" class="apex-chart-box-sm"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card modern-card chart-card">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-chart-column"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Outstanding Aging</h3>
                            <div class="small text-secondary">Umur piutang berdasarkan due date</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="financial-aging-chart" class="apex-chart-box"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card modern-card chart-card">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-building-columns"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Outstanding By Program</h3>
                            <div class="small text-secondary">Program studi dengan piutang terbesar</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="financial-program-outstanding-chart" class="apex-chart-box"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card modern-card chart-card">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-chart-pie"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Collection Health</h3>
                            <div class="small text-secondary">Perbandingan paid dan outstanding dari invoice terbit</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Total Billed</span>
                            <span class="fw-bold">{{ $this->money($chartSummary['total_billed']) }}</span>
                        </div>
                        <div class="chart-stack">
                            <div class="chart-stack-segment" style="width: {{ $chartSummary['paid_percent'] }}%; background: linear-gradient(135deg, #10b981 0%, #059669 100%);"></div>
                            <div class="chart-stack-segment" style="width: {{ $chartSummary['outstanding_percent'] }}%; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);"></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="soft-list-item">
                                <div class="text-secondary small mb-1">Paid</div>
                                <div class="fw-bold text-success">{{ $this->money($chartSummary['total_paid']) }}</div>
                                <div class="small text-secondary mt-1">{{ $chartSummary['paid_percent'] }}%</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="soft-list-item">
                                <div class="text-secondary small mb-1">Outstanding</div>
                                <div class="fw-bold text-danger">{{ $this->money($chartSummary['total_outstanding']) }}</div>
                                <div class="small text-secondary mt-1">{{ $chartSummary['outstanding_percent'] }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-secondary mt-3">Paid + outstanding dihitung dari invoice non-draft/non-cancelled.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card modern-card chart-card">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-chart-bar"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Outstanding By Invoice Type</h3>
                            <div class="small text-secondary">Tipe invoice yang paling perlu perhatian finance</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($invoiceTypeRows as $row)
                            @php
                                $width = $chartSummary['max_type_outstanding'] > 0
                                    ? max(4, round(($row['outstanding_amount'] / $chartSummary['max_type_outstanding']) * 100))
                                    : 0;
                            @endphp
                            <div class="chart-row">
                                <div class="chart-row-label">{{ $row['invoice_type'] }}</div>
                                <div class="chart-track">
                                    <div class="chart-bar" style="width: {{ $width }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                                </div>
                                <div class="chart-row-value {{ $row['outstanding_amount'] > 0 ? 'text-danger' : 'text-success' }}">{{ $this->money($row['outstanding_amount']) }}</div>
                            </div>
                        @empty
                            <div class="empty-soft">Belum ada data invoice untuk chart.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card modern-card h-100">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-chart-column"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Invoice Status Summary</h3>
                            <div class="small text-secondary">Distribusi status dan nominal outstanding</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter compact-table mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Invoices</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoiceStatusRows as $row)
                                <tr>
                                    <td class="fw-bold">{{ $row['status'] }}</td>
                                    <td class="text-end">{{ number_format($row['invoice_count']) }}</td>
                                    <td class="text-end">{{ $this->money($row['total_amount']) }}</td>
                                    <td class="text-end fw-bold {{ $row['outstanding_amount'] > 0 ? 'text-danger' : 'text-success' }}">{{ $this->money($row['outstanding_amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="empty-soft">Belum ada invoice.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card modern-card h-100">
                <div class="card-header section-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-calendar-check"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Due Schedules</h3>
                            <div class="small text-secondary">Jadwal invoice yang siap diproses</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.invoice-schedules.index') }}" class="btn btn-sm btn-outline-primary">Open</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($dueSchedules as $schedule)
                            <div class="soft-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $schedule->name }}</div>
                                        <div class="text-secondary small mt-1">{{ $schedule->academicYear?->name ?? '-' }} / Semester {{ $schedule->semester ?? '-' }}</div>
                                    </div>
                                    <span class="badge bg-yellow-lt text-yellow align-self-start">{{ $schedule->publish_at?->format('d M H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Tidak ada schedule yang due.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card modern-card h-100">
                <div class="card-header section-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Overdue Invoices</h3>
                            <div class="small text-secondary">Tagihan yang perlu follow-up</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-sm btn-outline-danger">Open</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($overdueInvoices as $invoice)
                            <div class="soft-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $invoice->invoice_number }}</div>
                                        <div class="text-secondary small mt-1">{{ $invoice->studentProfile?->user?->name ?? '-' }} / Due {{ $invoice->due_date?->format('d M Y') }}</div>
                                    </div>
                                    <div class="text-end fw-bold text-danger">{{ $this->money($invoice->outstanding_amount) }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Tidak ada overdue invoice.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card modern-card h-100">
                <div class="card-header section-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-receipt"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Recent Payments</h3>
                            <div class="small text-secondary">Pembayaran terbaru dari mahasiswa</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.payments.index') }}" class="btn btn-sm btn-outline-primary">Open</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($recentPayments as $payment)
                            <div class="soft-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $payment->payment_number }}</div>
                                        <div class="text-secondary small mt-1">{{ $payment->studentProfile?->user?->name ?? '-' }} / {{ $payment->invoice?->invoice_number ?? '-' }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold">{{ $this->money($payment->amount) }}</div>
                                        <span class="badge {{ $this->statusClass($payment->status) }}">{{ str($payment->status)->title()->toString() }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Belum ada pembayaran.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card modern-card h-100">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-layer-group"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Invoice Type Health</h3>
                            <div class="small text-secondary">Tipe invoice dengan sisa tagihan terbesar</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter compact-table mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th class="text-end">Invoices</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoiceTypeRows as $row)
                                <tr>
                                    <td class="fw-bold">{{ $row['invoice_type'] }}</td>
                                    <td class="text-end">{{ number_format($row['invoice_count']) }}</td>
                                    <td class="text-end text-success">{{ $this->money($row['paid_amount']) }}</td>
                                    <td class="text-end fw-bold {{ $row['outstanding_amount'] > 0 ? 'text-danger' : 'text-success' }}">{{ $this->money($row['outstanding_amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="empty-soft">Belum ada data.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card modern-card h-100">
                <div class="card-header section-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-lock"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Active Holds</h3>
                            <div class="small text-secondary">Akses akademik yang sedang tertahan</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.financial-holds.index') }}" class="btn btn-sm btn-outline-primary">Open</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($activeHolds as $hold)
                            <div class="soft-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $hold->studentProfile?->user?->name ?? '-' }}</div>
                                        <div class="text-secondary small mt-1">{{ str($hold->hold_type)->replace('_', ' ')->title()->toString() }} / {{ $hold->invoice?->invoice_number ?? '-' }}</div>
                                    </div>
                                    <span class="badge bg-red-lt text-red align-self-start">Active</span>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Tidak ada active hold.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const money = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            });

            const chartColors = {
                primary: '#667eea',
                secondary: '#764ba2',
                success: '#10b981',
                danger: '#ef4444',
                warning: '#f59e0b',
                info: '#06b6d4',
            };

            const renderChart = (selector, options) => {
                const element = document.querySelector(selector);

                if (! element || typeof ApexCharts === 'undefined') {
                    return;
                }

                new ApexCharts(element, options).render();
            };

            const revenueTrend = @js($revenueTrendRows);
            const paymentMethods = @js($paymentMethodRows);
            const agingRows = @js($agingRows);
            const programOutstanding = @js($programOutstandingRows);

            renderChart('#financial-revenue-trend-chart', {
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                },
                series: [{
                    name: 'Revenue',
                    data: revenueTrend.map(row => row.amount),
                }],
                xaxis: {
                    categories: revenueTrend.map(row => row.label),
                    labels: { style: { colors: '#64748b' } },
                },
                yaxis: {
                    labels: {
                        formatter: value => money.format(value),
                        style: { colors: '#64748b' },
                    },
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                colors: [chartColors.primary],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.04,
                        stops: [0, 90, 100],
                    },
                },
                tooltip: {
                    y: { formatter: value => money.format(value) },
                },
                grid: {
                    borderColor: '#eef2ff',
                    strokeDashArray: 4,
                },
            });

            renderChart('#financial-payment-method-chart', {
                chart: {
                    type: 'donut',
                    height: 255,
                    fontFamily: 'Inter, sans-serif',
                },
                labels: paymentMethods.length ? paymentMethods.map(row => row.label) : ['No Payment'],
                series: paymentMethods.length ? paymentMethods.map(row => row.amount) : [1],
                colors: [chartColors.primary, chartColors.success, chartColors.warning, chartColors.info, chartColors.danger],
                legend: {
                    position: 'bottom',
                    fontSize: '12px',
                },
                dataLabels: {
                    formatter: value => `${Math.round(value)}%`,
                },
                tooltip: {
                    y: {
                        formatter: value => paymentMethods.length ? money.format(value) : money.format(0),
                    },
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Verified',
                                    formatter: () => money.format(paymentMethods.reduce((total, row) => total + Number(row.amount || 0), 0)),
                                },
                            },
                        },
                    },
                },
            });

            renderChart('#financial-aging-chart', {
                chart: {
                    type: 'bar',
                    height: 300,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                },
                series: [{
                    name: 'Outstanding',
                    data: agingRows.map(row => row.amount),
                }],
                xaxis: {
                    categories: agingRows.map(row => row.label),
                    labels: { style: { colors: '#64748b' } },
                },
                yaxis: {
                    labels: {
                        formatter: value => money.format(value),
                        style: { colors: '#64748b' },
                    },
                },
                plotOptions: {
                    bar: {
                        borderRadius: 8,
                        columnWidth: '48%',
                    },
                },
                dataLabels: { enabled: false },
                colors: [chartColors.danger],
                tooltip: {
                    y: { formatter: value => money.format(value) },
                },
                grid: {
                    borderColor: '#eef2ff',
                    strokeDashArray: 4,
                },
            });

            renderChart('#financial-program-outstanding-chart', {
                chart: {
                    type: 'bar',
                    height: 300,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                },
                series: [{
                    name: 'Outstanding',
                    data: programOutstanding.map(row => row.amount),
                }],
                xaxis: {
                    categories: programOutstanding.map(row => row.label),
                    labels: { style: { colors: '#64748b' } },
                },
                yaxis: {
                    labels: {
                        formatter: value => money.format(value),
                        style: { colors: '#64748b' },
                    },
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 8,
                        barHeight: '52%',
                    },
                },
                dataLabels: { enabled: false },
                colors: [chartColors.secondary],
                tooltip: {
                    y: { formatter: value => money.format(value) },
                },
                grid: {
                    borderColor: '#eef2ff',
                    strokeDashArray: 4,
                },
            });
        });
    </script>
@endpush
