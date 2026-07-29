@props([
    'stats' => [],
    'subWidgets' => ['stat_card', 'attendance_chart', 'pending_approvals', 'edom_scores', 'bkd_submission_progress', 'leave_balance_summary'],
])

@activecan('employee-profile.viewAny')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    {{-- Header --}}
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-purple bg-opacity-10 text-purple rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fas fa-sitemap fs-4"></i>
            </div>
            <div>
                <h4 class="card-title fw-bold mb-0 text-dark">Modul Kepegawaian &amp; SDM</h4>
                <span class="text-muted small">Ringkasan profil pegawai, presensi harian, dan permohonan cuti.</span>
            </div>
        </div>
        <a href="{{ route('admin.organization.employee-profiles.index') }}" class="btn btn-outline-purple rounded-pill fw-bold px-3 py-2">
            Kelola SDM <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Stat Cards --}}
        @if(in_array('stat_card', $subWidgets))
            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Total Pegawai"
                        :value="$stats['org_employees_count'] ?? 0"
                        icon="fas fa-id-badge"
                        color="purple"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Presensi Masuk Hari Ini"
                        :value="$stats['org_attendance_today_count'] ?? 0"
                        icon="fas fa-user-check"
                        color="success"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Cuti Pegawai Pending"
                        :value="$stats['org_pending_leave_count'] ?? 0"
                        icon="fas fa-business-time"
                        color="warning"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Tridharma Records"
                        :value="$stats['org_tridharma_count'] ?? 0"
                        icon="fas fa-book-bookmark"
                        color="info"
                    />
                </div>
            </div>
        @endif

        {{-- Attendance Chart --}}
        @if(in_array('attendance_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="orgAttendanceChart"
                title="Tren Presensi Kehadiran Pegawai (Mingguan)"
                subtitle="Rekapitulasi kehadiran tepat waktu vs terlambat."
                type="line"
                height="240"
                badge="Attendance Analytics"
                badgeColor="purple"
            />
        @endif

        {{-- Approvals & EDOM Row --}}
        <div class="row g-3">
            {{-- Pending Approvals Table --}}
            @if(in_array('pending_approvals', $subWidgets))
                <div class="col-lg-7">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-file-signature me-2 text-warning"></i>Persetujuan Cuti & Izin</h5>
                                <span class="badge bg-warning-lt text-warning">Menunggu Keputusan</span>
                            </div>
                            @if(isset($stats['pending_approvals']) && count($stats['pending_approvals']) > 0)
                                <div class="table-responsive">
                                    <table class="table table-vcenter table-sm card-table">
                                        <thead>
                                            <tr>
                                                <th>Nama Pegawai</th>
                                                <th>Jenis Pengajuan</th>
                                                <th>Tanggal</th>
                                                <th class="text-end">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($stats['pending_approvals'] as $approval)
                                                <tr>
                                                    <td class="fw-bold text-dark">{{ $approval['employee_name'] }}</td>
                                                    <td>{{ $approval['leave_type'] }}</td>
                                                    <td class="small">{{ $approval['date_range'] }}</td>
                                                    <td class="text-end">
                                                        <a href="#" class="btn btn-ghost-primary px-3 py-1">Review</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted small text-center py-4">Semua pengajuan telah disetujui.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- EDOM Scores --}}
            @if(in_array('edom_scores', $subWidgets))
                <div class="col-lg-5">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-star me-2 text-success"></i>Ringkasan Nilai EDOM</h5>
                                <span class="badge bg-success-lt text-success">Rata-rata</span>
                            </div>
                            <div class="p-3 bg-light bg-opacity-75 rounded-4 d-flex align-items-center justify-content-center flex-column">
                                <h1 class="display-4 fw-bold text-success mb-0">{{ number_format($stats['edom_avg_score'] ?? 4.25, 2) }}</h1>
                                <div class="text-muted small mt-1">Skala 1.00 - 5.00</div>
                                <div class="mt-3 text-center small text-secondary">
                                    Evaluasi Dosen oleh Mahasiswa semester ini menunjukkan peningkatan kualitas pengajaran yang stabil.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- BKD Submission Progress --}}
        @if(in_array('bkd_submission_progress', $subWidgets))
            <div class="card border rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-clipboard-list me-2 text-info"></i>Progress Pengumpulan BKD</h5>
                        <span class="badge bg-info-lt text-info">BKD Tracker</span>
                    </div>
                    <x-admin.dashboard.widgets.progress-bar
                        :current="$stats['bkd_submitted_count'] ?? 0"
                        :total="$stats['bkd_total_lecturers'] ?? 0"
                        label="Dosen Sudah Submit"
                        :batchName="'Periode: ' . ($stats['bkd_period_name'] ?? 'Belum ada periode aktif')"
                        color="auto"
                    />
                </div>
            </div>
        @endif

        {{-- Leave Balance Summary --}}
        @if(in_array('leave_balance_summary', $subWidgets))
            <div class="card border rounded-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-calendar-check me-2 text-warning"></i>Ringkasan Saldo Cuti</h5>
                        <span class="badge bg-warning-lt text-warning">Leave Balance</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <x-admin.dashboard.widgets.stat-card
                                label="Cuti Cukup"
                                :value="$stats['leave_sufficient_count'] ?? 0"
                                icon="fas fa-check"
                                color="success"
                            />
                        </div>
                        <div class="col-4">
                            <x-admin.dashboard.widgets.stat-card
                                label="Sisa ≤ 3"
                                :value="$stats['leave_low_count'] ?? 0"
                                icon="fas fa-exclamation"
                                color="warning"
                            />
                        </div>
                        <div class="col-4">
                            <x-admin.dashboard.widgets.stat-card
                                label="Habis"
                                :value="$stats['leave_exhausted_count'] ?? 0"
                                icon="fas fa-times"
                                color="danger"
                            />
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var attElement = document.getElementById('orgAttendanceChart');
    if (attElement && typeof ApexCharts !== 'undefined') {
        var attOptions = {
            chart: { type: 'line', height: 240, toolbar: { show: false } },
            stroke: { curve: 'smooth', width: 3 },
            series: [
                { name: 'Tepat Waktu', data: [42, 45, 44, 48, 46] },
                { name: 'Terlambat', data: [3, 2, 5, 1, 2] }
            ],
            xaxis: { categories: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'], labels: { style: { colors: '#64748b' } } },
            colors: ['#0ca678', '#f59f00'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(attElement, attOptions).render();
    }
});
</script>
@endactivecan
