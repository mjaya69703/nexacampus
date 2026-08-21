<?php

use App\Support\ActivePermission;
use App\Support\Dashboard\DashboardService;
use Livewire\Component;

new class extends Component
{
    public array $config = [];
    public array $stats = [];

    public bool $loadError = false;

    public function mount(): void
    {
        if (! ActivePermission::check('student-invoice.viewAny') && ! ActivePermission::check('payment.viewAny')) {
            return;
        }

        try {
            $this->stats = app(DashboardService::class)->financial();
        } catch (Throwable $e) {
            report($e);
            $this->loadError = true;
        }
    }

    public function placeholder()
    {
        return view('components.admin.dashboard.widget-skeleton');
    }

    public function render()
    {
        if ($this->loadError) {
            return $this->view('components.admin.dashboard.module-error');
        }

        return $this->view();
    }
};
?>

@php
    $on = fn (string $key): bool => boolval($config[$key] ?? true);
@endphp

<div class="card rounded-4 mb-4 overflow-hidden">
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="app-module-icon bg-success-lt text-success"><i class="fas fa-wallet"></i></span>
            <div>
                <h4 class="fw-bold mb-0">Modul Keuangan &amp; SPP/UKT</h4>
                <span class="text-muted small">Pendapatan, tunggakan, dan pengajuan cicilan.</span>
            </div>
        </div>
        <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-outline-success rounded-pill px-3 py-2">
            Kelola Keuangan <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        @if($on('stat_card'))
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Pembayaran Terkonfirmasi"
                        value="Rp {{ number_format(($stats['paid_sum'] ?? 0) / 1000000, 1) }} M"
                        icon="fas fa-money-check-dollar"
                        color="success"
                    />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Tagihan Belum Lunas" value="{{ number_format($stats['unpaid_invoices_count'] ?? 0) }}" icon="fas fa-receipt" color="warning" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Pengajuan Cicilan" value="{{ number_format($stats['pending_installments_count'] ?? 0) }}" icon="fas fa-file-invoice-dollar" color="info" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Financial Hold Aktif" value="{{ number_format($stats['holds_count'] ?? 0) }}" icon="fas fa-lock" color="danger" />
                </div>
            </div>
        @endif

        @if($on('revenue_chart') || $on('invoice_status_chart'))
            <div class="row g-3 mb-4">
                @if($on('revenue_chart'))
                    <div class="col-lg-8">
                        <x-admin.dashboard.widgets.chart-container
                            id="finRevenueChart"
                            title="Tren Penerimaan SPP/UKT"
                            subtitle="Total pembayaran terverifikasi per bulan (Juta Rp)."
                            type="bar"
                            :series="$stats['monthly_revenue']['data'] ?? []"
                            :categories="$stats['monthly_revenue']['labels'] ?? []"
                            height="240"
                            badge="Revenue"
                            badgeColor="success"
                        />
                    </div>
                @endif
                @if($on('invoice_status_chart'))
                    <div class="col-lg-{{ $on('revenue_chart') ? '4' : '12' }}">
                        <x-admin.dashboard.widgets.chart-container
                            id="finStatusChart"
                            title="Status Tagihan"
                            type="donut"
                            :series="$stats['invoice_status_breakdown']['series'] ?? []"
                            :labels="$stats['invoice_status_breakdown']['labels'] ?? []"
                            height="240"
                            badge="Donut"
                            badgeColor="warning"
                        />
                    </div>
                @endif
            </div>
        @endif

        @if($on('arrears_table') || $on('scholarship_stats'))
            <div class="row g-3 mb-4">
                @if($on('arrears_table'))
                    <div class="col-lg-6">
                        <div class="card border rounded-4 h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Tunggakan Tertinggi</h5>
                                    <span class="badge bg-danger-lt text-danger">Prioritas Penagihan</span>
                                </div>
                                @if(count($stats['top_arrears'] ?? []) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-vcenter table-sm card-table">
                                            <thead>
                                                <tr><th>Mahasiswa</th><th>Total Tunggakan</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach(($stats['top_arrears'] ?? []) as $arrear)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold">{{ $arrear['student_name'] }}</div>
                                                            <div class="text-muted small">{{ $arrear['nim'] }}</div>
                                                        </td>
                                                        <td class="fw-bold text-danger text-end">Rp {{ number_format($arrear['amount']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small text-center py-4 mb-0">Tidak ada tunggakan signifikan.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if($on('scholarship_stats'))
                    <div class="col-lg-{{ $on('arrears_table') ? '6' : '12' }}">
                        <div class="card border rounded-4 h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0"><i class="fas fa-award me-2 text-info"></i>Penerima Beasiswa</h5>
                                    <span class="badge bg-info-lt text-info">Ringkasan</span>
                                </div>
                                @if(count($stats['scholarships'] ?? []) > 0)
                                    <div class="divide-y">
                                        @foreach(($stats['scholarships'] ?? []) as $scholarship)
                                            <div class="py-2 d-flex justify-content-between align-items-center">
                                                <span class="fw-semibold"><i class="fas fa-medal me-2 text-warning"></i>{{ $scholarship['name'] }}</span>
                                                <span class="badge bg-secondary-lt text-secondary fw-bold">{{ number_format($scholarship['count']) }} Mahasiswa</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted small text-center py-4 mb-0">Data beasiswa belum tersedia.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if($on('recent_payments'))
            <div class="card border rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-clock-rotate-left me-2 text-success"></i>Pembayaran Terbaru</h5>
                        <a href="{{ route('admin.financial.payments.index') }}" class="small text-decoration-none">Lihat Semua</a>
                    </div>
                    @if(count($stats['recent_payments'] ?? []) > 0)
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>No. Transaksi</th>
                                        <th>Mahasiswa</th>
                                        <th>Jumlah Bayar</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($stats['recent_payments'] ?? []) as $payment)
                                        <tr>
                                            <td class="fw-semibold text-success">{{ $payment['reference_number'] }}</td>
                                            <td>{{ $payment['student_name'] }}</td>
                                            <td class="fw-bold">Rp {{ number_format($payment['amount']) }}</td>
                                            <td>{{ $payment['paid_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted small text-center py-4 mb-0">Belum ada pembayaran terverifikasi.</p>
                    @endif
                </div>
            </div>
        @endif

        @if($on('overdue_alert'))
            @if(($stats['overdue_count'] ?? 0) > 0)
                <x-admin.dashboard.widgets.alert-banner
                    type="danger"
                    title="{{ $stats['overdue_count'] ?? 0 }} Tagihan Jatuh Tempo"
                    message="Total tunggakan jatuh tempo: Rp {{ number_format($stats['overdue_amount'] ?? 0, 0, ',', '.') }}"
                />
            @else
                <x-admin.dashboard.widgets.alert-banner type="success" message="Semua tagihan dalam kondisi aman, tidak ada yang jatuh tempo." />
            @endif
        @endif

        @if($on('payment_method_chart'))
            <div class="row g-3">
                <div class="col-lg-6">
                    <x-admin.dashboard.widgets.chart-container
                        id="finPaymentMethodChart"
                        title="Metode Pembayaran"
                        subtitle="Breakdown transaksi terverifikasi."
                        type="donut"
                        :series="array_values($stats['payment_methods'] ?? [])"
                        :labels="array_keys($stats['payment_methods'] ?? [])"
                        height="220"
                        badge="Breakdown"
                        badgeColor="info"
                    />
                </div>
            </div>
        @endif
    </div>
</div>
