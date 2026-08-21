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
        if (! ActivePermission::check('service-letter-request.viewAny') && ! ActivePermission::check('student-complaint.viewAny')) {
            return;
        }

        try {
            $this->stats = app(DashboardService::class)->studentServices();
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
    $graduationTotal = max(1, ($stats['graduation_applications_count'] ?? 0));
@endphp

<div class="card rounded-4 mb-4 overflow-hidden">
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="app-module-icon bg-warning-lt text-warning"><i class="fas fa-hands-helping"></i></span>
            <div>
                <h4 class="fw-bold mb-0">Modul Layanan Mahasiswa</h4>
                <span class="text-muted small">Surat, cuti, pengaduan, dan wisuda.</span>
            </div>
        </div>
        <a href="{{ route('admin.student-services.letter-requests.index') }}" class="btn btn-outline-warning rounded-pill px-3 py-2">
            Kelola Layanan <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        @if($on('stat_card'))
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Surat Pending" value="{{ number_format($stats['pending_letters_count'] ?? 0) }}" icon="fas fa-file-signature" color="warning" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Cuti Menunggu ACC" value="{{ number_format($stats['pending_leaves_count'] ?? 0) }}" icon="fas fa-calendar-xmark" color="info" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Pengaduan Aktif" value="{{ number_format($stats['active_complaints_count'] ?? 0) }}" icon="fas fa-comment-dots" color="danger" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Pendaftar Wisuda" value="{{ number_format($stats['graduation_applications_count'] ?? 0) }}" icon="fas fa-user-graduate" color="primary" />
                </div>
            </div>
        @endif

        @if($on('complaint_resolution_chart'))
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <x-admin.dashboard.widgets.chart-container
                        id="servComplaintChart"
                        title="Status Pengaduan"
                        type="donut"
                        :series="$stats['complaint_breakdown']['series'] ?? []"
                        :labels="$stats['complaint_breakdown']['labels'] ?? []"
                        height="220"
                        badge="Pengaduan"
                        badgeColor="danger"
                    />
                </div>

                @if($on('graduation_progress'))
                    <div class="col-lg-6">
                        <div class="card border rounded-4 h-100">
                            <div class="card-body p-3">
                                <h5 class="fw-bold mb-3"><i class="fas fa-user-graduate me-2 text-primary"></i>Progress Yudisium/Wisuda</h5>
                                <p class="text-muted small mb-2">{{ $stats['graduation_approved_count'] ?? 0 }} dari {{ $stats['graduation_applications_count'] ?? 0 }} pengajuan telah disetujui.</p>
                                <x-admin.dashboard.widgets.progress-bar
                                    label="Disetujui"
                                    value="{{ round((($stats['graduation_approved_count'] ?? 0) / $graduationTotal) * 100) }}"
                                    color="primary"
                                    :max="100"
                                    suffix="%"
                                />
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if($on('pending_requests_table'))
            <div class="card border rounded-4">
                <div class="card-body p-3">
                    <h5 class="fw-bold mb-3"><i class="fas fa-hourglass-half me-2 text-warning"></i>Permohonan Menunggu Persetujuan</h5>
                    @if(count($stats['pending_requests'] ?? []) > 0)
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Detail</th>
                                        <th>Mahasiswa</th>
                                        <th>Diajukan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($stats['pending_requests'] ?? []) as $request)
                                        <tr>
                                            <td><span class="badge bg-secondary-lt text-secondary">{{ $request['type'] }}</span></td>
                                            <td>{{ $request['detail'] }}</td>
                                            <td>{{ $request['student_name'] }}</td>
                                            <td>{{ $request['created_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted small text-center py-4 mb-0">Tidak ada permohonan yang menunggu persetujuan.</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
