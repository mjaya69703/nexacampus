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
                'status' => match($row->status) {
                    'draft' => 'Draft Belum Terbit',
                    'issued' => 'Aktif / Belum Bayar',
                    'partially_paid' => 'Cicilan Sebagian',
                    'overdue' => 'Jatuh Tempo (Overdue)',
                    'paid' => 'Lunas (Paid)',
                    'cancelled' => 'Dibatalkan',
                    default => str($row->status)->replace('_', ' ')->title()->toString()
                },
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
                'invoice_type' => match($row->invoice_type) {
                    'tuition' => 'SPP / Uang Kuliah',
                    'registration' => 'Biaya Pendaftaran',
                    'exam' => 'Biaya Ujian Akhir',
                    default => str($row->invoice_type)->replace('_', ' ')->title()->toString()
                },
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
            'menus' => 'Keuangan',
            'pages' => 'Dashboard Operasional Keuangan',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
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
            ['label' => 'Belum Jatuh Tempo', 'min' => null, 'max' => 0, 'amount' => 0.0],
            ['label' => '1-7 Hari', 'min' => 1, 'max' => 7, 'amount' => 0.0],
            ['label' => '8-30 Hari', 'min' => 8, 'max' => 30, 'amount' => 0.0],
            ['label' => '31-60 Hari', 'min' => 31, 'max' => 60, 'amount' => 0.0],
            ['label' => '> 60 Hari', 'min' => 61, 'max' => null, 'amount' => 0.0],
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
                'label' => match($payment->payment_method) {
                    'bank_transfer' => 'Transfer Bank',
                    'virtual_account' => 'Virtual Account',
                    'credit_card' => 'Kartu Kredit',
                    'cash' => 'Tunai / Kasir',
                    default => str($payment->payment_method)->replace('_', ' ')->title()->toString()
                },
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
            ->groupBy(fn (StudentInvoice $invoice) => $invoice->studentProfile?->studyProgram?->name ?? 'Tidak Terikat Prodi')
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

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Dashboard Operasional Keuangan"
        description="Pantau tagihan, verifikasi pembayaran, piutang tertunggak, pemblokiran akademik, jadwal terbit, dan beasiswa dari satu pusat kendali."
        icon="wallet"
    >
        <div class="d-flex flex-wrap gap-2">
            @activecan('student-invoice.create')
                <a href="{{ route('admin.financial.student-invoices.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-file-invoice"></i> <span>Buat Tagihan</span>
                </a>
            @endactivecan
            @activecan('invoice-schedule.create')
                <a href="{{ route('admin.financial.invoice-schedules.create') }}" class="btn btn-sm btn-outline-light fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-clock"></i> <span>Buat Jadwal</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-receipt fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Verifikasi</div>
                        <div class="fw-bold">{{ number_format($stats['pending_payments']) }} Pembayaran</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-triangle-exclamation fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Lewat Jatuh Tempo</div>
                        <div class="fw-bold">{{ number_format($stats['overdue_invoices']) }} Tagihan</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jadwal Siap Terbit</div>
                        <div class="fw-bold">{{ number_format($stats['due_schedules']) }} Jadwal</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-coins fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Piutang (Outstanding)</div>
                        <div class="fw-bold">{{ $this->money($stats['outstanding']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Terverifikasi</div>
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
                        <div class="stat-label">Total Tunggakan</div>
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
                        <div class="stat-label">Menunggu Verifikasi</div>
                        <div class="stat-value">{{ number_format($stats['pending_payments']) }} Pembayaran</div>
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
                        <div class="stat-label">Tagihan Overdue</div>
                        <div class="stat-value">{{ number_format($stats['overdue_invoices']) }} Tagihan</div>
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
                        <div class="stat-label">Blokir Akademik Aktif</div>
                        <div class="stat-value">{{ number_format($stats['active_holds']) }} Mahasiswa</div>
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
                        <div class="stat-label">Jadwal Siap Eksekusi</div>
                        <div class="stat-value">{{ number_format($stats['due_schedules']) }} Jadwal</div>
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
                        <div class="stat-label">Total Saldo Deposit</div>
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
                        <div class="stat-label">Beasiswa Aktif</div>
                        <div class="stat-value">{{ number_format($stats['active_scholarships']) }} Penerima</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Tren Penerimaan (Revenue Trend)</h3>
                            <div class="small text-secondary">Total pembayaran terverifikasi dalam 6 bulan terakhir</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Metode Pembayaran</h3>
                            <div class="small text-secondary">Komposisi kanal pembayaran terverifikasi</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Analisis Umur Piutang (Outstanding Aging)</h3>
                            <div class="small text-secondary">Keterlambatan bayar berdasarkan tanggal jatuh tempo</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Tunggakan Per Program Studi</h3>
                            <div class="small text-secondary">Program studi dengan total piutang terbesar</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Kesehatan Penagihan (Collection Health)</h3>
                            <div class="small text-secondary">Rasio penerimaan vs tunggakan dari tagihan terbit</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Total Diterbitkan (Billed)</span>
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
                                <div class="text-secondary small mb-1">Sudah Dibayar (Paid)</div>
                                <div class="fw-bold text-success">{{ $this->money($chartSummary['total_paid']) }}</div>
                                <div class="small text-secondary mt-1">{{ $chartSummary['paid_percent'] }}%</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="soft-list-item">
                                <div class="text-secondary small mb-1">Sisa Tunggakan</div>
                                <div class="fw-bold text-danger">{{ $this->money($chartSummary['total_outstanding']) }}</div>
                                <div class="small text-secondary mt-1">{{ $chartSummary['outstanding_percent'] }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-secondary mt-3">Diperhitungkan dari tagihan berstatus aktif dan belum lunas.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card modern-card chart-card">
                <div class="card-header section-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon"><i class="fas fa-chart-bar"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Tunggakan Berdasarkan Jenis Tagihan</h3>
                            <div class="small text-secondary">Kategori tagihan yang memerlukan prioritas penagihan</div>
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
                            <div class="empty-soft">Belum ada data tagihan untuk ditampilkan.</div>
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
                        <span class="section-icon"><i class="fas fa-layer-group"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight: 700;">Rekapitulasi Status Tagihan</h3>
                            <div class="small text-secondary">Distribusi jumlah dan nominal tagihan mahasiswa</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter compact-table mb-0">
                        <thead>
                            <tr>
                                <th>Status Tagihan</th>
                                <th class="text-end">Jumlah</th>
                                <th class="text-end">Total Nominal</th>
                                <th class="text-end">Sisa Tunggakan</th>
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
                                <tr><td colspan="4"><div class="empty-soft">Belum ada data tagihan.</div></td></tr>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Jadwal Terbit Tertunda / Siap Eksekusi</h3>
                            <div class="small text-secondary">Jadwal penerbitan tagihan yang sudah memasuki waktu terbit</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.invoice-schedules.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Buka</a>
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
                                    <span class="badge bg-yellow-lt text-yellow align-self-start rounded-pill px-2 py-1">{{ $schedule->publish_at?->format('d M, H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Semua jadwal sudah dieksekusi tepat waktu.</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Tagihan Jatuh Tempo (Overdue)</h3>
                            <div class="small text-secondary">Tagihan mahasiswa yang memerlukan tindak lanjut</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-sm btn-outline-danger rounded-pill px-3">Buka</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($overdueInvoices as $invoice)
                            <div class="soft-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $invoice->invoice_number }}</div>
                                        <div class="text-secondary small mt-1">{{ $invoice->studentProfile?->user?->name ?? '-' }} • Tempo: {{ $invoice->due_date?->format('d M Y') }}</div>
                                    </div>
                                    <div class="text-end fw-bold text-danger">{{ $this->money($invoice->outstanding_amount) }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Tidak ada tagihan yang jatuh tempo.</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Pembayaran Masuk Terbaru</h3>
                            <div class="small text-secondary">Histori transaksi pembayaran dari mahasiswa</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.payments.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Buka</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($recentPayments as $payment)
                            <div class="soft-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $payment->payment_number }}</div>
                                        <div class="text-secondary small mt-1">{{ $payment->studentProfile?->user?->name ?? '-' }} • {{ $payment->invoice?->invoice_number ?? '-' }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold">{{ $this->money($payment->amount) }}</div>
                                        <span class="badge {{ $this->statusClass($payment->status) }} rounded-pill px-2 py-1 fs-8">{{ match($payment->status) {
                                            'verified' => 'Terverifikasi',
                                            'pending' => 'Menunggu Review',
                                            'rejected' => 'Ditolak',
                                            default => str($payment->status)->title()->toString()
                                        } }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Belum ada transaksi pembayaran masuk.</div>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Rincian Jenis Tagihan & Sisa Tunggakan</h3>
                            <div class="small text-secondary">Komposisi penerimaan dan tunggakan per jenis tagihan</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter compact-table mb-0">
                        <thead>
                            <tr>
                                <th>Jenis Tagihan</th>
                                <th class="text-end">Jumlah</th>
                                <th class="text-end">Sudah Dibayar</th>
                                <th class="text-end">Sisa Tunggakan</th>
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
                                <tr><td colspan="4"><div class="empty-soft">Belum ada data jenis tagihan.</div></td></tr>
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
                            <h3 class="card-title mb-0" style="font-weight: 700;">Daftar Blokir Akademik Aktif</h3>
                            <div class="small text-secondary">Mahasiswa yang tertahan layanan akademiknya</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.financial.financial-holds.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Buka</a>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        @forelse($activeHolds as $hold)
                            <div class="soft-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold">{{ $hold->studentProfile?->user?->name ?? '-' }}</div>
                                        <div class="text-secondary small mt-1">{{ match($hold->hold_type) {
                                            'registration' => 'Pendaftaran Ulang / KRS',
                                            'study_plan' => 'Rencana Studi',
                                            'exam_card' => 'Kartu Ujian Akhir',
                                            default => str($hold->hold_type)->replace('_', ' ')->title()->toString()
                                        } }} • {{ $hold->invoice?->invoice_number ?? '-' }}</div>
                                    </div>
                                    <span class="badge bg-red-lt text-red align-self-start rounded-pill px-2 py-1 fs-8">Diblokir</span>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft">Tidak ada mahasiswa yang diblokir saat ini.</div>
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
                    name: 'Penerimaan',
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
                labels: paymentMethods.length ? paymentMethods.map(row => row.label) : ['Belum Ada Pembayaran'],
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
                                    label: 'Terverifikasi',
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
                    name: 'Tunggakan',
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
                    name: 'Tunggakan',
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
