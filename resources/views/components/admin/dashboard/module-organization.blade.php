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
        if (! ActivePermission::check('employee-profile.viewAny') && ! ActivePermission::check('tridharma-record.viewAny')) {
            return;
        }

        try {
            $this->stats = app(DashboardService::class)->organization();
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
            <span class="app-module-icon bg-purple-lt text-purple"><i class="fas fa-sitemap"></i></span>
            <div>
                <h4 class="fw-bold mb-0">Modul Kepegawaian &amp; SDM</h4>
                <span class="text-muted small">Pegawai, presensi, persetujuan cuti, dan tridharma.</span>
            </div>
        </div>
        <a href="{{ route('admin.organization.employee-profiles.index') }}" class="btn btn-outline-purple rounded-pill px-3 py-2">
            Kelola Kepegawaian <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        @if($on('stat_card'))
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Pegawai Aktif" value="{{ number_format($stats['employees_count'] ?? 0) }}" icon="fas fa-users" color="primary" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Presensi Hari Ini" value="{{ number_format($stats['attendance_today_count'] ?? 0) }}" icon="fas fa-fingerprint" color="success" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Cuti Menunggu ACC" value="{{ number_format($stats['pending_leave_count'] ?? 0) }}" icon="fas fa-calendar-xmark" color="warning" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Rekaman Tridharma" value="{{ number_format($stats['tridharma_count'] ?? 0) }}" icon="fas fa-book-open-reader" color="info" />
                </div>
            </div>
        @endif

        @if($on('pending_approvals'))
            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <h5 class="fw-bold mb-3"><i class="fas fa-hourglass-half me-2 text-warning"></i>Cuti/Izin Menunggu Persetujuan</h5>
                            @if(count($stats['pending_leaves'] ?? []) > 0)
                                <div class="table-responsive">
                                    <table class="table table-vcenter table-sm card-table">
                                        <thead>
                                            <tr>
                                                <th>Pegawai</th>
                                                <th>Jenis Cuti</th>
                                                <th>Diajukan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach(($stats['pending_leaves'] ?? []) as $leave)
                                                <tr>
                                                    <td>{{ $leave['employee_name'] }}</td>
                                                    <td>{{ $leave['leave_type'] }}</td>
                                                    <td>{{ $leave['created_at'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted small text-center py-4 mb-0">Tidak ada pengajuan cuti/izin pending.</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if($on('tridharma_stats'))
                    <div class="col-lg-5">
                        <x-admin.dashboard.widgets.chart-container
                            id="orgTridharmaChart"
                            title="Komposisi Tridharma"
                            type="donut"
                            :series="$stats['tridharma_breakdown']['series'] ?? []"
                            :labels="$stats['tridharma_breakdown']['labels'] ?? []"
                            height="220"
                            badge="Tridharma"
                            badgeColor="purple"
                        />
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
