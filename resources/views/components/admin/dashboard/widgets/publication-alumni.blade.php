@props([
    'stats' => [],
    'subWidgets' => ['stat_card', 'tracer_study_chart', 'job_board_stats', 'tracer_study_response_rate', 'alumni_employment_chart'],
])

@activecan('announcement.viewAny')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    {{-- Header --}}
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-teal bg-opacity-10 text-teal rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fas fa-bullhorn fs-4"></i>
            </div>
            <div>
                <h4 class="card-title fw-bold mb-0 text-dark">Modul Publikasi &amp; Portal Alumni</h4>
                <span class="text-muted small">Ringkasan pengumuman kampus, FAQ publik, dan tracer study karir alumni.</span>
            </div>
        </div>
        <a href="{{ route('admin.publication.announcements.index') }}" class="btn btn-outline-teal rounded-pill fw-bold px-3 py-2">
            Kelola Publikasi <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Stat Cards --}}
        @if(in_array('stat_card', $subWidgets))
            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Pengumuman Aktif"
                        :value="$stats['pub_announcements_count'] ?? 0"
                        icon="fas fa-bullhorn"
                        color="teal"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Item FAQ Publik"
                        :value="$stats['pub_faqs_count'] ?? 0"
                        icon="fas fa-circle-question"
                        color="primary"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Alumni Terdaftar"
                        :value="$stats['pub_alumni_count'] ?? 0"
                        icon="fas fa-user-graduate"
                        color="success"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Lowongan Kerja Aktif"
                        :value="$stats['pub_jobs_count'] ?? 0"
                        icon="fas fa-briefcase"
                        color="warning"
                    />
                </div>
            </div>
        @endif

        {{-- Tracer Study Chart --}}
        @if(in_array('tracer_study_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="alumniTracerChart"
                title="Tracer Study Masa Tunggu Kerja Alumni"
                subtitle="Lama waktu tunggu alumni mendapatkan pekerjaan pertama."
                type="bar"
                height="240"
                badge="Alumni Career"
                badgeColor="teal"
            />
        @endif

        {{-- Job Board & Response Rate Row --}}
        <div class="row g-3">
            {{-- Job Board Stats --}}
            @if(in_array('job_board_stats', $subWidgets))
                <div class="col-lg-6">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-building me-2 text-primary"></i>Lowongan Kerja Terbaru</h5>
                                <a href="#" class="small text-primary text-decoration-none fw-bold">Lihat Semua</a>
                            </div>
                            @if(isset($stats['recent_jobs']) && count($stats['recent_jobs']) > 0)
                                <div class="divide-y">
                                    @foreach($stats['recent_jobs'] as $job)
                                        <div class="py-2 d-flex justify-content-between align-items-center">
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark">{{ $job['position'] }}</span>
                                                <span class="small text-muted">{{ $job['company'] }} <i class="fas fa-map-marker-alt ms-1 text-danger"></i> {{ $job['location'] }}</span>
                                            </div>
                                            <span class="badge bg-light text-dark">{{ $job['type'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small text-center py-4">Belum ada lowongan kerja baru.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tracer Study Response Rate --}}
            @if(in_array('tracer_study_response_rate', $subWidgets))
                <div class="col-lg-6">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-line me-2 text-info"></i>Response Rate Tracer Study</h5>
                                <span class="badge bg-info-lt text-info">Engagement</span>
                            </div>
                            <x-admin.dashboard.widgets.progress-bar
                                :current="$stats['tracer_study_responded'] ?? 0"
                                :total="$stats['tracer_study_total_sent'] ?? 0"
                                label="Alumni Merespons"
                                :batchName="'Kampanye: ' . ($stats['tracer_study_campaign_name'] ?? 'Tidak ada kampanye aktif')"
                                color="auto"
                            />
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Alumni Employment Chart --}}
        @if(in_array('alumni_employment_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="alumniEmploymentChart"
                title="Status Pekerjaan Alumni"
                subtitle="Sebaran status pekerjaan alumni."
                type="donut"
                height="220"
                badge="Employment"
                badgeColor="success"
            />
        @endif
    </div>
</div>

<script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Tracer Chart
    var tracerElement = document.getElementById('alumniTracerChart');
    if (tracerElement && typeof ApexCharts !== 'undefined') {
        var tracerOptions = {
            chart: { type: 'bar', height: 240, toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '40%', distributed: true } },
            dataLabels: { enabled: true },
            series: [{ name: 'Persentase Alumni', data: [58, 26, 11, 5] }],
            xaxis: { categories: ['< 3 Bulan', '3 - 6 Bulan', '6 - 12 Bulan', '> 12 Bulan'], labels: { style: { colors: '#64748b' } } },
            colors: ['#0ca678', '#206bc4', '#f59f00', '#d63939'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(tracerElement, tracerOptions).render();
    }

    // Employment Chart
    var empElement = document.getElementById('alumniEmploymentChart');
    if (empElement && typeof ApexCharts !== 'undefined') {
        var empOptions = {
            chart: { type: 'donut', height: 220 },
            labels: {!! json_encode(array_keys($stats['alumni_employment_status'] ?? ['Bekerja' => 65, 'Wirausaha' => 15, 'Studi Lanjut' => 12, 'Belum Bekerja' => 8])) !!},
            series: {!! json_encode(array_values($stats['alumni_employment_status'] ?? [65, 15, 12, 8])) !!},
            legend: { position: 'bottom', fontSize: '11px' },
            colors: ['#0ca678', '#206bc4', '#f59f00', '#d63939'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(empElement, empOptions).render();
    }
});
</script>
@endactivecan
