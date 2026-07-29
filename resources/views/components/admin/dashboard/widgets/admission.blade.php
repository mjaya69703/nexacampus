@props([
    'stats' => [],
    'subWidgets' => ['stat_card', 'trend_chart', 'top_schools', 'top_programs', 'status_funnel', 'exam_schedule', 'recent_table', 'quota_progress'],
])

@activecan('admission-application.viewAny')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    {{-- Header --}}
    <div class="card-header border-bottom p-3 p-md-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fas fa-user-plus fs-4"></i>
            </div>
            <div>
                <h4 class="card-title fw-bold mb-0 text-dark">Modul PMB &amp; Penerimaan Mahasiswa Baru</h4>
                <span class="text-muted small">Analitik pendaftar, tren pendaftaran, demografi sekolah asal, sebaran prodi, dan status seleksi.</span>
            </div>
        </div>
        <a href="{{ route('admin.admission.admission-applications.index') }}" class="btn btn-outline-primary rounded-pill fw-bold px-3 py-2">
            Kelola PMB <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Stat Cards --}}
        @if(in_array('stat_card', $subWidgets))
            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-4 col-xl-2.4">
                    <x-admin.dashboard.widgets.stat-card
                        label="Total Pendaftar"
                        :value="$stats['admission_applicants_count'] ?? 0"
                        icon="fas fa-users"
                        color="primary"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-2.4">
                    <x-admin.dashboard.widgets.stat-card
                        label="Verifikasi Pending"
                        :value="$stats['admission_pending_verification_count'] ?? 0"
                        icon="fas fa-clock"
                        color="warning"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-2.4">
                    <x-admin.dashboard.widgets.stat-card
                        label="Diterima / Lolos"
                        :value="$stats['admission_accepted_count'] ?? 0"
                        icon="fas fa-check-circle"
                        color="success"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-2.4">
                    <x-admin.dashboard.widgets.stat-card
                        label="Konversi NIM"
                        :value="$stats['admission_converted_count'] ?? 0"
                        icon="fas fa-id-card"
                        color="info"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-2.4">
                    <x-admin.dashboard.widgets.stat-card
                        label="Gelombang Aktif"
                        :value="$stats['admission_active_periods_count'] ?? 0"
                        icon="fas fa-calendar-check"
                        color="purple"
                    />
                </div>
            </div>
        @endif

        {{-- Trend Chart --}}
        @if(in_array('trend_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="pmbTrendChart"
                title="Tren Pendaftaran PMB per Bulan"
                subtitle="Grafik jumlah pendaftar baru dari waktu ke waktu."
                type="area"
                height="280"
                badge="Visual Analytics"
                badgeColor="primary"
            />
        @endif

        {{-- Analytics Grid --}}
        <div class="row g-3">
            {{-- Top Schools --}}
            @if(in_array('top_schools', $subWidgets))
                <div class="col-lg-6 col-xl-4">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-school me-2 text-primary"></i>Top Asal Sekolah (SMA/SMK)</h5>
                                <span class="badge bg-primary-lt text-primary">Demografi</span>
                            </div>
                            @if(isset($stats['top_schools']) && count($stats['top_schools']) > 0)
                                <div class="divide-y">
                                    @foreach($stats['top_schools'] as $school)
                                        <div class="py-2">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-semibold text-dark small text-truncate" style="max-width: 220px;" title="{{ $school['name'] }}">
                                                    <i class="fas fa-graduation-cap me-1 text-muted"></i>{{ $school['name'] }}
                                                </span>
                                                <span class="badge bg-light text-dark fw-bold">{{ number_format($school['count']) }} Siswa</span>
                                            </div>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-primary" style="width: {{ $school['percentage'] }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small text-center py-4">Belum ada data sekolah asal pendaftar.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Top Programs Donut --}}
            @if(in_array('top_programs', $subWidgets))
                <div class="col-lg-6 col-xl-4">
                    <x-admin.dashboard.widgets.chart-container
                        id="pmbProgramChart"
                        title="Sebaran Program Studi"
                        type="donut"
                        height="240"
                        badge="Donut Chart"
                        badgeColor="info"
                    />
                </div>
            @endif

            {{-- Status Funnel --}}
            @if(in_array('status_funnel', $subWidgets))
                <div class="col-lg-12 col-xl-4">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-filter me-2 text-success"></i>Funnel Seleksi PMB</h5>
                                <span class="badge bg-success-lt text-success">Pipeline</span>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <div class="p-2.5 rounded-3 bg-light d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-secondary rounded-circle p-1.5"><i class="fas fa-file-export"></i></span>
                                        <span class="small fw-semibold">Draft / Registrasi Awal</span>
                                    </div>
                                    <span class="fw-bold text-dark">{{ number_format($stats['admission_status_counts']['draft'] ?? 0) }}</span>
                                </div>
                                <div class="p-2.5 rounded-3 bg-light d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-warning rounded-circle p-1.5"><i class="fas fa-clock"></i></span>
                                        <span class="small fw-semibold">Submit / Menunggu Verifikasi</span>
                                    </div>
                                    <span class="fw-bold text-warning">{{ number_format($stats['admission_status_counts']['submitted'] ?? 0) }}</span>
                                </div>
                                <div class="p-2.5 rounded-3 bg-light d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success rounded-circle p-1.5"><i class="fas fa-check"></i></span>
                                        <span class="small fw-semibold">Diterima (Accepted)</span>
                                    </div>
                                    <span class="fw-bold text-success">{{ number_format($stats['admission_status_counts']['accepted'] ?? 0) }}</span>
                                </div>
                                <div class="p-2.5 rounded-3 bg-light d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-info rounded-circle p-1.5"><i class="fas fa-id-card"></i></span>
                                        <span class="small fw-semibold">Terkonversi Mahasiswa (NIM)</span>
                                    </div>
                                    <span class="fw-bold text-info">{{ number_format($stats['admission_status_counts']['converted'] ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Recent Table --}}
            @if(in_array('recent_table', $subWidgets))
                <div class="col-lg-8">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-list me-2 text-primary"></i>Pendaftar Terbaru</h5>
                                <span class="badge bg-primary-lt text-primary">5 Terakhir</span>
                            </div>
                            @if(isset($stats['recent_applicants']) && count($stats['recent_applicants']) > 0)
                                <div class="table-responsive">
                                    <table class="table table-vcenter table-sm card-table">
                                        <thead>
                                            <tr>
                                                <th>No. Reg</th>
                                                <th>Nama Lengkap</th>
                                                <th>Sekolah Asal</th>
                                                <th>Prodi Pilihan</th>
                                                <th>Status</th>
                                                <th class="text-end">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($stats['recent_applicants'] as $applicant)
                                                <tr>
                                                    <td class="fw-semibold text-primary">{{ $applicant['application_number'] }}</td>
                                                    <td class="fw-bold text-dark">{{ $applicant['name'] }}</td>
                                                    <td class="small text-secondary">{{ $applicant['high_school'] }}</td>
                                                    <td>{{ $applicant['study_program'] }}</td>
                                                    <td>
                                                        <span class="badge {{ $applicant['status_badge_class'] }}">{{ $applicant['status_label'] }}</span>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="{{ route('admin.admission.admission-applications.show', ['id' => $applicant['id']]) }}" class="btn btn-ghost-primary px-3 py-1">Detail</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted small text-center py-4">Belum ada data pendaftar terbaru.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Exam Schedule --}}
            @if(in_array('exam_schedule', $subWidgets))
                <div class="col-lg-4">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-laptop-code me-2 text-info"></i>Seleksi &amp; Ujian CBT</h5>
                                <a href="{{ route('admin.admission.admission-exam-schedules.index') }}" class="small text-primary text-decoration-none fw-bold">Kelola</a>
                            </div>
                            <div class="p-3 bg-light bg-opacity-75 rounded-4 mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-semibold text-dark small">Total Sesi Ujian</span>
                                    <span class="badge bg-info-lt text-info fw-bold">{{ number_format($stats['admission_exam_schedules_count'] ?? 0) }} Sesi</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-semibold text-dark small">Rata-rata Score Seleksi</span>
                                    <span class="badge bg-success-lt text-success fw-bold">{{ number_format($stats['admission_avg_score'] ?? 0, 1) }} / 100</span>
                                </div>
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-info-circle me-1 text-info"></i>Sesi ujian CBT aktif dapat dipantau langsung dari halaman pengawas.
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Quota Progress --}}
        @if(in_array('quota_progress', $subWidgets))
            <div class="card border rounded-4 mt-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-simple me-2 text-purple"></i>Progress Kuota PMB per Prodi</h5>
                        <span class="badge bg-purple-lt text-purple">Quota Tracker</span>
                    </div>
                    @if(isset($stats['quota_progress']) && count($stats['quota_progress']) > 0)
                        @foreach($stats['quota_progress'] as $quota)
                            <x-admin.dashboard.widgets.progress-bar
                                :current="$quota['filled']"
                                :total="$quota['capacity']"
                                :label="$quota['program_name']"
                                :batchName="$quota['filled'] . '/' . $quota['capacity']"
                                color="auto"
                            />
                        @endforeach
                    @else
                        <div class="text-muted small text-center py-4">Data kuota belum tersedia untuk periode ini.</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Trend Chart
    var trendElement = document.getElementById('pmbTrendChart');
    if (trendElement && typeof ApexCharts !== 'undefined') {
        var trendSeriesData = {!! json_encode(array_values($stats['pmb_monthly_trend'] ?? [12, 25, 42, 68, 95, 120, 150])) !!};
        var trendCategories = {!! json_encode(array_keys($stats['pmb_monthly_trend'] ?? ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'])) !!};

        var trendOptions = {
            chart: { type: 'area', height: 280, toolbar: { show: false }, sparkline: { enabled: false } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
            series: [{ name: 'Pendaftar Baru', data: trendSeriesData }],
            xaxis: { categories: trendCategories, labels: { style: { colors: '#64748b' } } },
            colors: ['#206bc4'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(trendElement, trendOptions).render();
    }

    // Donut Chart
    var programElement = document.getElementById('pmbProgramChart');
    if (programElement && typeof ApexCharts !== 'undefined') {
        var programData = {!! json_encode($stats['top_programs'] ?? []) !!};
        var programLabels = programData.map(function(item) { return item.name; });
        var programSeries = programData.map(function(item) { return item.count; });
        if (programSeries.length === 0) {
            programLabels = ['Teknik Informatika', 'Sistem Informasi', 'Manajemen', 'Akuntansi', 'Lainnya'];
            programSeries = [45, 30, 20, 15, 10];
        }

        var programOptions = {
            chart: { type: 'donut', height: 240 },
            labels: programLabels,
            series: programSeries,
            legend: { position: 'bottom', fontSize: '11px' },
            colors: ['#206bc4', '#4299e1', '#0ca678', '#f59f00', '#d63939'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(programElement, programOptions).render();
    }
});
</script>
@endactivecan
