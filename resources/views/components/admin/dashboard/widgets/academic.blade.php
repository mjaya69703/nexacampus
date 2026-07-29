@props([
    'stats' => [],
    'subWidgets' => ['stat_card', 'gpa_chart', 'krs_approval_table', 'attendance_chart', 'grade_appeal_table', 'schedule_conflict_alert'],
])

@activecan('course-offering.viewAny')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    {{-- Header --}}
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fas fa-graduation-cap fs-4"></i>
            </div>
            <div>
                <h4 class="card-title fw-bold mb-0 text-dark">Modul Akademik &amp; Perkuliahan</h4>
                <span class="text-muted small">Ringkasan penawaran kelas, krs online, distribusi IPK mahasiswa, dan kurikulum.</span>
            </div>
        </div>
        <a href="{{ route('admin.academic.course-offerings.index') }}" class="btn btn-outline-info rounded-pill fw-bold px-3 py-2">
            Kelola Akademik <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Stat Cards --}}
        @if(in_array('stat_card', $subWidgets))
            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Kelas Ditawarkan"
                        :value="$stats['academic_course_offerings_count'] ?? 0"
                        icon="fas fa-chalkboard-user"
                        color="info"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="KRS Perlu ACC DPA"
                        :value="$stats['academic_pending_krs_count'] ?? 0"
                        icon="fas fa-list-check"
                        color="warning"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Mahasiswa Aktif"
                        :value="$stats['academic_active_students_count'] ?? 0"
                        icon="fas fa-user-graduate"
                        color="primary"
                    />
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Program Studi"
                        :value="$stats['academic_study_programs_count'] ?? 0"
                        icon="fas fa-building-columns"
                        color="dark"
                    />
                </div>
            </div>
        @endif

        {{-- GPA Chart --}}
        @if(in_array('gpa_chart', $subWidgets))
            <x-admin.dashboard.widgets.chart-container
                id="academicGpaChart"
                title="Distribusi IPK Mahasiswa Aktif"
                subtitle="Sebaran rentang Indeks Prestasi Kumulatif seluruh mahasiswa."
                type="bar"
                height="250"
                badge="GPA Analytics"
                badgeColor="info"
            />
        @endif

        {{-- KRS & Attendance Row --}}
        <div class="row g-3">
            {{-- KRS Approval Table --}}
            @if(in_array('krs_approval_table', $subWidgets))
                <div class="col-lg-7">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-list-check me-2 text-warning"></i>KRS Butuh Persetujuan DPA</h5>
                                <span class="badge bg-warning-lt text-warning">Prioritas</span>
                            </div>
                            @if(isset($stats['krs_pending']) && count($stats['krs_pending']) > 0)
                                <div class="table-responsive">
                                    <table class="table table-vcenter table-sm card-table">
                                        <thead>
                                            <tr>
                                                <th>NIM</th>
                                                <th>Nama Mahasiswa</th>
                                                <th>SKS</th>
                                                <th class="text-end">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($stats['krs_pending'] as $krs)
                                                <tr>
                                                    <td class="fw-semibold text-primary">{{ $krs['nim'] }}</td>
                                                    <td class="fw-bold text-dark">{{ $krs['name'] }}</td>
                                                    <td>{{ $krs['sks'] }} SKS</td>
                                                    <td class="text-end">
                                                        <a href="#" class="btn btn-ghost-primary px-3 py-1">Review</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted small text-center py-4">Semua KRS sudah disetujui. Tidak ada antrean.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Attendance Chart --}}
            @if(in_array('attendance_chart', $subWidgets))
                <div class="col-lg-5">
                    <x-admin.dashboard.widgets.chart-container
                        id="academicAttendanceChart"
                        title="Tingkat Kehadiran"
                        subtitle="Rata-rata kehadiran mahasiswa."
                        type="radialBar"
                        height="200"
                        badge="Rata-rata"
                        badgeColor="success"
                    />
                </div>
            @endif
        </div>

        {{-- Grade Appeal Table --}}
        @if(in_array('grade_appeal_table', $subWidgets))
            <div class="card border rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-gavel me-2 text-warning"></i>Banding Nilai Menunggu Review</h5>
                        <span class="badge bg-warning-lt text-warning">Grade Appeal</span>
                    </div>
                    @if(isset($stats['pending_grade_appeals']) && count($stats['pending_grade_appeals']) > 0)
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>Mahasiswa</th>
                                        <th>Mata Kuliah</th>
                                        <th>Nilai Asal</th>
                                        <th>Alasan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['pending_grade_appeals'] as $appeal)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $appeal['student_name'] }}</td>
                                            <td>{{ $appeal['course_name'] }}</td>
                                            <td><span class="badge bg-secondary">{{ $appeal['original_grade'] }}</span></td>
                                            <td class="small text-muted" style="max-width: 200px;">{{ Str::limit($appeal['reason'], 50) }}</td>
                                            <td><span class="badge bg-warning-lt text-warning">Pending</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted small text-center py-4">Tidak ada banding nilai yang menunggu review. ✅</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Schedule Conflict Alert --}}
        @if(in_array('schedule_conflict_alert', $subWidgets))
            @php
                $conflicts = $stats['schedule_conflicts'] ?? [];
            @endphp
            @if(count($conflicts) > 0)
                <x-admin.dashboard.widgets.alert-banner
                    type="warning"
                    :title="count($conflicts) . ' Konflik Jadwal Terdeteksi!'"
                    :items="array_slice($conflicts, 0, 5)"
                />
            @else
                <x-admin.dashboard.widgets.alert-banner
                    type="success"
                    message="Tidak ada konflik jadwal terdeteksi. Semua jadwal aman!"
                />
            @endif
        @endif
    </div>
</div>

<script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // GPA Chart
    var gpaElement = document.getElementById('academicGpaChart');
    if (gpaElement && typeof ApexCharts !== 'undefined') {
        var gpaOptions = {
            chart: { type: 'bar', height: 250, toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '40%', distributed: true } },
            dataLabels: { enabled: true },
            series: [{ name: 'Jumlah Mahasiswa', data: [15, 85, 340, 520, 210] }],
            xaxis: { categories: ['< 2.00', '2.00 - 2.50', '2.51 - 3.00', '3.01 - 3.50', '3.51 - 4.00'], labels: { style: { colors: '#64748b' } } },
            colors: ['#d63939', '#f59f00', '#4299e1', '#206bc4', '#0ca678'],
            tooltip: { theme: 'dark' }
        };
        new ApexCharts(gpaElement, gpaOptions).render();
    }

    // Attendance Chart
    var attElement = document.getElementById('academicAttendanceChart');
    if (attElement && typeof ApexCharts !== 'undefined') {
        var attOptions = {
            chart: { type: 'radialBar', height: 200 },
            series: [88],
            labels: ['Kehadiran'],
            colors: ['#0ca678'],
            plotOptions: {
                radialBar: {
                    hollow: { size: '65%' },
                    dataLabels: {
                        value: { fontSize: '24px', fontWeight: 'bold', formatter: function (val) { return val + "%" } }
                    }
                }
            }
        };
        new ApexCharts(attElement, attOptions).render();
    }
});
</script>
@endactivecan
