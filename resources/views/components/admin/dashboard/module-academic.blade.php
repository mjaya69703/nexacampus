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
        if (! ActivePermission::check('course-offering.viewAny') && ! ActivePermission::check('study-plan.viewAny')) {
            return;
        }

        try {
            $this->stats = app(DashboardService::class)->academic();
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
            <span class="app-module-icon bg-info-lt text-info"><i class="fas fa-graduation-cap"></i></span>
            <div>
                <h4 class="fw-bold mb-0">Modul Akademik &amp; Perkuliahan</h4>
                <span class="text-muted small">Kelas, KRS, kehadiran, dan banding nilai.</span>
            </div>
        </div>
        <a href="{{ route('admin.academic.course-offerings.index') }}" class="btn btn-outline-info rounded-pill px-3 py-2">
            Kelola Akademik <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        @if($on('stat_card'))
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Mahasiswa Aktif" value="{{ number_format($stats['active_students_count'] ?? 0) }}" icon="fas fa-user-graduate" color="primary" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Kelas Ditawarkan" value="{{ number_format($stats['course_offerings_count'] ?? 0) }}" icon="fas fa-chalkboard" color="info" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="KRS Menunggu ACC DPA" value="{{ number_format($stats['pending_krs_count'] ?? 0) }}" icon="fas fa-file-signature" color="warning" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card
                        label="Rata-rata Kehadiran"
                        value="{{ ($stats['attendance_rate'] ?? null) !== null ? $stats['attendance_rate'] . '%' : 'N/A' }}"
                        icon="fas fa-user-check"
                        color="success"
                    />
                </div>
            </div>
        @endif

        @if($on('gpa_chart') || $on('attendance_chart'))
            <div class="row g-3 mb-4">
                @if($on('gpa_chart'))
                    <div class="col-lg-{{ $on('attendance_chart') ? '7' : '12' }}">
                        <x-admin.dashboard.widgets.chart-container
                            id="acadGpaChart"
                            title="Distribusi IPK Mahasiswa"
                            subtitle="Jumlah mahasiswa per rentang IPK kumulatif."
                            type="bar"
                            :series="$stats['gpa_distribution']['series'] ?? []"
                            :categories="$stats['gpa_distribution']['labels'] ?? []"
                            height="240"
                            badge="IPK"
                            badgeColor="info"
                        />
                    </div>
                @endif
                @if($on('attendance_chart'))
                    <div class="col-lg-{{ $on('gpa_chart') ? '5' : '12' }}">
                        <x-admin.dashboard.widgets.chart-container
                            id="acadAttendanceChart"
                            title="Komposisi Kehadiran Perkuliahan"
                            type="donut"
                            :series="$stats['attendance_breakdown']['series'] ?? []"
                            :labels="$stats['attendance_breakdown']['labels'] ?? []"
                            height="240"
                            badge="Presensi"
                            badgeColor="success"
                        />
                    </div>
                @endif
            </div>
        @endif

        @if($on('krs_approval_table'))
            <div class="card border rounded-4 mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-file-signature me-2 text-warning"></i>KRS Menunggu Persetujuan</h5>
                        <a href="{{ route('admin.academic.study-plans.index') }}" class="small text-decoration-none">Kelola KRS</a>
                    </div>
                    @if(count($stats['pending_krs_list'] ?? []) > 0)
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>Mahasiswa</th>
                                        <th>NIM</th>
                                        <th>Semester</th>
                                        <th>Diajukan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($stats['pending_krs_list'] ?? []) as $plan)
                                        <tr>
                                            <td>{{ $plan['student_name'] }}</td>
                                            <td class="text-muted">{{ $plan['nim'] }}</td>
                                            <td>Semester {{ $plan['semester_no'] }}</td>
                                            <td>{{ $plan['submitted_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted small text-center py-4 mb-0">Tidak ada pengajuan KRS yang menunggu persetujuan.</p>
                    @endif
                </div>
            </div>
        @endif

        @if($on('grade_appeal_table'))
            <div class="card border rounded-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-scale-balanced me-2 text-danger"></i>Banding Nilai Pending</h5>
                        <span class="badge bg-danger-lt text-danger">{{ count($stats['pending_appeals'] ?? []) }} Terbaru</span>
                    </div>
                    @if(count($stats['pending_appeals'] ?? []) > 0)
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>Mahasiswa</th>
                                        <th>Kelas</th>
                                        <th>Nilai Diajukan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($stats['pending_appeals'] ?? []) as $appeal)
                                        <tr>
                                            <td>{{ $appeal['student_name'] }}</td>
                                            <td>{{ $appeal['course'] }}</td>
                                            <td class="fw-semibold">{{ $appeal['requested_score'] }}</td>
                                            <td><span class="badge bg-warning-lt text-warning">{{ $appeal['status_label'] }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted small text-center py-4 mb-0">Tidak ada banding nilai yang pending.</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
