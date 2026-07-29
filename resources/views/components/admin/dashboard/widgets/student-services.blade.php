@props([
    'stats' => [],
    'subWidgets' => ['stat_card', 'services_types_chart', 'pending_requests_table', 'graduation_progress', 'complaint_resolution_chart'],
])

@activecan('service-letter-request.viewAny')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    {{-- Header --}}
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fas fa-hands-helping fs-4"></i>
            </div>
            <div>
                <h4 class="card-title fw-bold mb-0 text-dark">Modul Layanan Mahasiswa</h4>
                <span class="text-muted small">Ringkasan pengajuan surat keterangan, cuti, dan pengaduan.</span>
            </div>
        </div>
        <a href="{{ route('admin.student-services.letter-requests.index') }}" class="btn btn-outline-warning rounded-pill fw-bold px-3 py-2">
            Proses Layanan <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Stat Cards --}}
        @if(in_array('stat_card', $subWidgets))
            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Surat Masuk Perlu Process"
                        :value="$stats['services_pending_letters_count'] ?? 0"
                        icon="fas fa-file-signature"
                        color="warning"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Pengajuan Cuti Studi"
                        :value="$stats['services_pending_leave_count'] ?? 0"
                        icon="fas fa-plane-departure"
                        color="info"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Pengaduan Mahasiswa"
                        :value="$stats['services_pending_complaints_count'] ?? 0"
                        icon="fas fa-comments"
                        color="danger"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Peserta Yudisium Batch"
                        :value="$stats['services_graduation_applicants_count'] ?? 0"
                        icon="fas fa-user-graduate"
                        color="success"
                    />
                </div>
            </div>
        @endif

        {{-- Services Types Chart --}}
        @if(in_array('services_types_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="studentServiceLetterChart"
                title="Permohonan Surat Terbanyak"
                subtitle="Sebaran jenis surat permohonan mahasiswa."
                type="bar"
                height="240"
                badge="Letter Analytics"
                badgeColor="warning"
            />
        @endif

        {{-- Pending Requests Table --}}
        @if(in_array('pending_requests_table', $subWidgets))
            <div class="card border rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-clock me-2 text-primary"></i>Layanan Menunggu Persetujuan</h5>
                        <span class="badge bg-primary-lt text-primary">Pending</span>
                    </div>
                    @if(isset($stats['pending_requests']) && count($stats['pending_requests']) > 0)
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>Mahasiswa</th>
                                        <th>Jenis Layanan</th>
                                        <th>Tanggal Pengajuan</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['pending_requests'] as $request)
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $request['student_name'] }}</div>
                                                <div class="text-muted small">{{ $request['nim'] }}</div>
                                            </td>
                                            <td>{{ $request['service_type'] }}</td>
                                            <td class="small">{{ $request['requested_at'] }}</td>
                                            <td class="text-end">
                                                <a href="#" class="btn btn-ghost-primary px-3 py-1">Review</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted small text-center py-4">Semua permohonan layanan sudah diproses.</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Graduation Progress --}}
        @if(in_array('graduation_progress', $subWidgets))
            <div class="card border rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-user-graduate me-2 text-success"></i>Progress Yudisium Batch Aktif</h5>
                        <span class="badge bg-success-lt text-success">Graduation</span>
                    </div>
                    <x-admin.dashboard.widgets.progress-bar
                        :current="$stats['graduation_eligible'] ?? 0"
                        :total="$stats['graduation_total'] ?? 0"
                        label="Mahasiswa Eligible"
                        :batchName="'Batch: ' . ($stats['graduation_batch_name'] ?? 'Belum ada batch aktif')"
                        color="auto"
                    />
                </div>
            </div>
        @endif

        {{-- Complaint Resolution Chart --}}
        @if(in_array('complaint_resolution_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="complaintStatusChart"
                title="Status Pengaduan Mahasiswa"
                subtitle="Sebaran status pengaduan."
                type="donut"
                height="220"
                badge="Complaints"
                badgeColor="danger"
            />
        @endif
    </div>
</div>

<script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Letter Chart
    var letterElement = document.getElementById('studentServiceLetterChart');
    if (letterElement && typeof ApexCharts !== 'undefined') {
        var letterOptions = {
            chart: { type: 'bar', height: 240, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '50%' } },
            dataLabels: { enabled: true },
            series: [{ name: 'Jumlah Permohonan', data: [45, 32, 28, 19, 12] }],
            xaxis: { categories: ['Surat Keterangan Aktif', 'Surat Izin Penelitian', 'Surat Keterangan Kelakuan Baik', 'Surat Rekomendasi Beasiswa', 'Surat Pengantar Magang'] },
            colors: ['#f59f00'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(letterElement, letterOptions).render();
    }

    // Complaint Chart
    var compElement = document.getElementById('complaintStatusChart');
    if (compElement && typeof ApexCharts !== 'undefined') {
        var compOptions = {
            chart: { type: 'donut', height: 220 },
            labels: ['Submitted', 'Dalam Proses', 'Terselesaikan', 'Ditutup'],
            series: {!! json_encode($stats['complaint_status_breakdown'] ?? [8, 5, 22, 15]) !!},
            legend: { position: 'bottom', fontSize: '11px' },
            colors: ['#f59f00', '#4299e1', '#0ca678', '#64748b'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(compElement, compOptions).render();
    }
});
</script>
@endactivecan
