@props([
    'stats' => [],
    'subWidgets' => ['stat_card', 'revenue_chart', 'invoice_status_chart', 'installment_table', 'recent_payments', 'arrears_table', 'scholarship_stats', 'overdue_alert', 'payment_method_chart'],
])

@activecan('student-invoice.viewAny')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    {{-- Header --}}
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fas fa-wallet fs-4"></i>
            </div>
            <div>
                <h4 class="card-title fw-bold mb-0 text-dark">Modul Keuangan &amp; SPP/UKT</h4>
                <span class="text-muted small">Analitik pendapatan, penerimaan pembayaran, tren UKT, dan pengajuan cicilan.</span>
            </div>
        </div>
        <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-outline-success rounded-pill fw-bold px-3 py-2">
            Kelola Keuangan <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Stat Cards --}}
        @if(in_array('stat_card', $subWidgets))
            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Pembayaran Terkonfirmasi"
                        :value="number_format(($stats['financial_paid_sum'] ?? 0) / 1000000, 1)"
                        suffix="M"
                        prefix="Rp "
                        icon="fas fa-money-check-dollar"
                        color="success"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Tagihan Belum Lunas"
                        :value="$stats['financial_unpaid_invoices_count'] ?? 0"
                        icon="fas fa-receipt"
                        color="warning"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Pengajuan Cicilan"
                        :value="$stats['financial_pending_installments_count'] ?? 0"
                        icon="fas fa-file-invoice-dollar"
                        color="info"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Financial Hold Aktif"
                        :value="$stats['financial_holds_count'] ?? 0"
                        icon="fas fa-lock"
                        color="danger"
                    />
                </div>
            </div>
        @endif

        {{-- Charts Row --}}
        <div class="row g-3 mb-4">
            @if(in_array('revenue_chart', $subWidgets))
                <div class="col-lg-8">
                    <x-admin.dashboard.widgets.chart-container
                        id="financialRevenueChart"
                        title="Tren Penerimaan SPP / UKT"
                        subtitle="Grafik akumulasi pembayaran masuk per bulan."
                        type="bar"
                        height="260"
                        badge="Revenue Trend"
                        badgeColor="success"
                    />
                </div>
            @endif

            @if(in_array('invoice_status_chart', $subWidgets))
                <div class="col-lg-4">
                    <x-admin.dashboard.widgets.chart-container
                        id="financialStatusChart"
                        title="Status Tagihan"
                        type="donut"
                        height="240"
                        badge="Donut"
                        badgeColor="warning"
                    />
                </div>
            @endif
        </div>

        {{-- Tables Row --}}
        <div class="row g-3">
            {{-- Arrears Table --}}
            @if(in_array('arrears_table', $subWidgets))
                <div class="col-lg-6">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Tunggakan Tertinggi</h5>
                                <span class="badge bg-danger-lt text-danger">Prioritas Penagihan</span>
                            </div>
                            @if(isset($stats['top_arrears']) && count($stats['top_arrears']) > 0)
                                <div class="table-responsive">
                                    <table class="table table-vcenter table-sm card-table">
                                        <thead>
                                            <tr>
                                                <th>Mahasiswa</th>
                                                <th>Total Tunggakan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($stats['top_arrears'] as $arrear)
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold text-dark">{{ $arrear['student_name'] }}</div>
                                                        <div class="text-muted small">{{ $arrear['nim'] }}</div>
                                                    </td>
                                                    <td class="fw-bold text-danger text-end">Rp {{ number_format($arrear['amount']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted small text-center py-4">Tidak ada data tunggakan signifikan.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Scholarship Stats --}}
            @if(in_array('scholarship_stats', $subWidgets))
                <div class="col-lg-6">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-award me-2 text-info"></i>Penerima Beasiswa</h5>
                                <span class="badge bg-info-lt text-info">Ringkasan</span>
                            </div>
                            @if(isset($stats['scholarships']) && count($stats['scholarships']) > 0)
                                <div class="divide-y">
                                    @foreach($stats['scholarships'] as $scholarship)
                                        <div class="py-2 d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-info rounded-circle p-1.5"><i class="fas fa-medal"></i></span>
                                                <span class="fw-semibold text-dark">{{ $scholarship['name'] }}</span>
                                            </div>
                                            <span class="badge bg-light text-dark fw-bold">{{ number_format($scholarship['count']) }} Mahasiswa</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small text-center py-4">Data beasiswa belum tersedia.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Recent Payments Table --}}
        @if(in_array('installment_table', $subWidgets) && isset($stats['recent_payments']) && count($stats['recent_payments']) > 0)
            <div class="card border rounded-4 mt-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-history me-2 text-success"></i>Pembayaran Terbaru</h5>
                        <span class="badge bg-success-lt text-success">5 Terakhir</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-sm card-table">
                            <thead>
                                <tr>
                                    <th>No. Transaksi</th>
                                    <th>Mahasiswa</th>
                                    <th>Jumlah Bayar</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['recent_payments'] as $payment)
                                    <tr>
                                        <td class="fw-semibold text-success">{{ $payment['reference_number'] }}</td>
                                        <td>{{ $payment['student_name'] }}</td>
                                        <td class="fw-bold">Rp {{ number_format($payment['amount']) }}</td>
                                        <td>{{ $payment['paid_at'] }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.financial.payments.index') }}" class="btn btn-ghost-success px-3 py-1">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Overdue Alert --}}
        @if(in_array('overdue_alert', $subWidgets))
            @php
                $overdueCount = $stats['financial_overdue_count'] ?? 0;
                $overdueAmount = $stats['financial_overdue_amount'] ?? 0;
            @endphp
            @if($overdueCount > 0)
                <x-admin.dashboard.widgets.alert-banner
                    type="danger"
                    :title="$overdueCount . ' Tagihan Jatuh Tempo!'"
                    :message="'Total tunggakan jatuh tempo: Rp ' . number_format($overdueAmount, 0, ',', '.')"
                />
            @else
                <x-admin.dashboard.widgets.alert-banner
                    type="success"
                    message="Tidak ada tagihan yang jatuh tempo hari ini. 🎉"
                />
            @endif
        @endif

        {{-- Payment Method Chart --}}
        @if(in_array('payment_method_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="paymentMethodChart"
                title="Metode Pembayaran"
                subtitle="Breakdown metode pembayaran yang digunakan mahasiswa."
                type="pie"
                height="220"
                badge="Breakdown"
                badgeColor="info"
            />
        @endif
    </div>
</div>

<script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Revenue Chart
    var revElement = document.getElementById('financialRevenueChart');
    if (revElement && typeof ApexCharts !== 'undefined') {
        var revData = {!! json_encode(array_values($stats['financial_monthly_revenue'] ?? [85, 120, 145, 210, 340, 480])) !!};
        var revCategories = {!! json_encode(array_keys($stats['financial_monthly_revenue'] ?? ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'])) !!};

        var revOptions = {
            chart: { type: 'bar', height: 260, toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            dataLabels: { enabled: false },
            series: [{ name: 'Pendapatan (Juta Rp)', data: revData }],
            xaxis: { categories: revCategories, labels: { style: { colors: '#64748b' } } },
            colors: ['#2fb344'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(revElement, revOptions).render();
    }

    // Status Chart
    var statusElement = document.getElementById('financialStatusChart');
    if (statusElement && typeof ApexCharts !== 'undefined') {
        var statusSeries = {!! json_encode($stats['financial_status_breakdown'] ?? [65, 20, 10, 5]) !!};

        var statusOptions = {
            chart: { type: 'donut', height: 240 },
            labels: ['Lunas', 'Belum Lunas', 'Cicilan', 'Financial Hold'],
            series: statusSeries,
            legend: { position: 'bottom', fontSize: '11px' },
            colors: ['#2fb344', '#f59f00', '#4299e1', '#d63939'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(statusElement, statusOptions).render();
    }

    // Payment Method Chart
    var pmElement = document.getElementById('paymentMethodChart');
    if (pmElement && typeof ApexCharts !== 'undefined') {
        var pmOptions = {
            chart: { type: 'pie', height: 220 },
            labels: {!! json_encode(array_keys($stats['payment_methods'] ?? ['Bank Transfer' => 40, 'Virtual Account' => 35, 'E-Wallet' => 15, 'Tunai' => 10])) !!},
            series: {!! json_encode(array_values($stats['payment_methods'] ?? [40, 35, 15, 10])) !!},
            legend: { position: 'bottom', fontSize: '11px' },
            colors: ['#206bc4', '#0ca678', '#f59f00', '#d63939'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(pmElement, pmOptions).render();
    }
});
</script>
@endactivecan
