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
        if (! ActivePermission::check('announcement.viewAny') && ! ActivePermission::check('alumni-profile.viewAny')) {
            return;
        }

        try {
            $this->stats = app(DashboardService::class)->publicationAlumni();
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
            <span class="app-module-icon bg-teal-lt text-teal"><i class="fas fa-bullhorn"></i></span>
            <div>
                <h4 class="fw-bold mb-0">Modul Publikasi &amp; Alumni</h4>
                <span class="text-muted small">Pengumuman, tracer study, dan job board.</span>
            </div>
        </div>
        <a href="{{ route('admin.publication.announcements.index') }}" class="btn btn-outline-teal rounded-pill px-3 py-2">
            Kelola Publikasi <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        @if($on('stat_card'))
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Alumni Terdaftar" value="{{ number_format($stats['alumni_count'] ?? 0) }}" icon="fas fa-user-graduate" color="teal" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Lowongan Aktif" value="{{ number_format($stats['jobs_count'] ?? 0) }}" icon="fas fa-briefcase" color="primary" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Pengumuman Terbit" value="{{ number_format($stats['announcements_count'] ?? 0) }}" icon="fas fa-bullhorn" color="info" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="FAQ Aktif" value="{{ number_format($stats['faqs_count'] ?? 0) }}" icon="fas fa-circle-question" color="success" />
                </div>
            </div>
        @endif

        @if($on('tracer_study_response_rate'))
            <div class="card border rounded-4 mb-4">
                <div class="card-body p-3">
                    <h5 class="fw-bold mb-3"><i class="fas fa-clipboard-list me-2 text-primary"></i>Response Rate Tracer Study</h5>
                    @if(($stats['tracer_sent'] ?? 0) > 0)
                        <p class="text-muted small mb-2">{{ number_format($stats['tracer_responded'] ?? 0) }} respons dari {{ number_format($stats['tracer_sent'] ?? 0) }} kuesioner terkirim.</p>
                        <x-admin.dashboard.widgets.progress-bar
                            label="Response Rate"
                            value="{{ $stats['tracer_response_rate'] ?? 0 }}"
                            color="{{ ($stats['tracer_response_rate'] ?? 0) >= 50 ? 'success' : 'warning' }}"
                            :max="100"
                            suffix="%"
                        />
                    @else
                        <p class="text-muted small text-center py-3 mb-0">Belum ada campaign tracer study yang mengirim kuesioner.</p>
                    @endif
                </div>
            </div>
        @endif

        @if($on('alumni_employment_chart') || $on('job_board_stats'))
            <div class="row g-3">
                @if($on('alumni_employment_chart'))
                    <div class="col-lg-7">
                        <x-admin.dashboard.widgets.chart-container
                            id="pubEmploymentChart"
                            title="Status Pekerjaan Alumni"
                            subtitle="Distribusi status pekerjaan seluruh alumni."
                            type="donut"
                            :series="$stats['employment_breakdown']['series'] ?? []"
                            :labels="$stats['employment_breakdown']['labels'] ?? []"
                            height="240"
                            badge="Alumni"
                            badgeColor="teal"
                        />
                    </div>
                @endif

                @if($on('job_board_stats'))
                    <div class="col-lg-{{ $on('alumni_employment_chart') ? '5' : '12' }}">
                        <div class="card border rounded-4 h-100">
                            <div class="card-body p-3">
                                <h5 class="fw-bold mb-3"><i class="fas fa-briefcase me-2 text-primary"></i>Lowongan Terbaru</h5>
                                @if(count($stats['recent_jobs'] ?? []) > 0)
                                    <div class="divide-y">
                                        @foreach(($stats['recent_jobs'] ?? []) as $job)
                                            <div class="py-2 d-flex justify-content-between align-items-center gap-2">
                                                <div class="min-w-0">
                                                    <div class="fw-semibold text-truncate">{{ $job['title'] }}</div>
                                                    <div class="text-muted small text-truncate">{{ $job['company'] }}</div>
                                                </div>
                                                <small class="text-muted text-nowrap">s.d. {{ $job['deadline'] }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted small text-center py-4 mb-0">Belum ada lowongan aktif.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
