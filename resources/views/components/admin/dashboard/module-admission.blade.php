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
        if (! ActivePermission::check('admission-application.viewAny')) {
            return;
        }

        try {
            $this->stats = app(DashboardService::class)->admission();
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
            <span class="app-module-icon bg-primary-lt text-primary"><i class="fas fa-user-plus"></i></span>
            <div>
                <h4 class="fw-bold mb-0">Modul PMB &amp; Penerimaan</h4>
                <span class="text-muted small">Pendaftar, seleksi, dan konversi mahasiswa baru.</span>
            </div>
        </div>
        <a href="{{ route('admin.admission.admission-applications.index') }}" class="btn btn-outline-primary rounded-pill px-3 py-2">
            Kelola PMB <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-3 p-md-4">
        @if($on('stat_card'))
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Total Pendaftar" value="{{ number_format($stats['applicants_count'] ?? 0) }}" icon="fas fa-user-plus" color="primary" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Menunggu Verifikasi" value="{{ number_format($stats['pending_verification_count'] ?? 0) }}" icon="fas fa-hourglass-half" color="warning" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Diterima" value="{{ number_format($stats['accepted_count'] ?? 0) }}" icon="fas fa-check-circle" color="success" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Jadi Mahasiswa" value="{{ number_format($stats['converted_count'] ?? 0) }}" icon="fas fa-user-graduate" color="info" />
                </div>
            </div>
        @endif

        @if($on('trend_chart') || $on('status_funnel'))
            <div class="row g-3 mb-4">
                @if($on('trend_chart'))
                    <div class="col-lg-8">
                        <x-admin.dashboard.widgets.chart-container
                            id="admTrendChart"
                            title="Tren Pendaftaran 6 Bulan Terakhir"
                            subtitle="Jumlah pendaftar baru per bulan."
                            type="bar"
                            :series="[['name' => 'Pendaftar', 'data' => $stats['monthly_trend']['data'] ?? []]]"
                            :categories="$stats['monthly_trend']['labels'] ?? []"
                            height="240"
                            badge="Tren"
                            badgeColor="primary"
                        />
                    </div>
                @endif
                @if($on('status_funnel'))
                    <div class="col-lg-{{ $on('trend_chart') ? '4' : '12' }}">
                        <div class="card border rounded-4 h-100">
                            <div class="card-body p-3">
                                <h5 class="fw-bold mb-3"><i class="fas fa-filter me-2 text-primary"></i>Pipeline Status PMB</h5>
                                @foreach([
                                    'Draft' => [$stats['status_counts']['draft'] ?? 0, 'secondary'],
                                    'Diverifikasi' => [$stats['status_counts']['submitted'] ?? 0, 'warning'],
                                    'Diterima' => [$stats['status_counts']['accepted'] ?? 0, 'success'],
                                    'Konversi Maba' => [$stats['status_counts']['converted'] ?? 0, 'primary'],
                                ] as $label => [$count, $color])
                                    <x-admin.dashboard.widgets.progress-bar :label="$label" :value="$count" color="{{ $color }}" :max="max(1, $stats['applicants_count'] ?? 0)" />
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="row g-3 mb-4">
            @if($on('top_schools'))
                <div class="col-lg-6">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <h5 class="fw-bold mb-3"><i class="fas fa-school me-2 text-info"></i>Top Asal Sekolah</h5>
                            @forelse(($stats['top_schools'] ?? []) as $school)
                                <x-admin.dashboard.widgets.progress-bar :label="$school['name']" :value="$school['count']" color="info" :max="max(1, $stats['applicants_count'] ?? 0)" suffix="{{ $school['percentage'] }}%" />
                            @empty
                                <p class="text-muted small text-center py-3 mb-0">Belum ada data asal sekolah.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
            @if($on('top_programs'))
                <div class="col-lg-6">
                    <div class="card border rounded-4 h-100">
                        <div class="card-body p-3">
                            <h5 class="fw-bold mb-3"><i class="fas fa-building-columns me-2 text-purple"></i>Sebaran Program Studi</h5>
                            @forelse(($stats['top_programs'] ?? []) as $program)
                                <x-admin.dashboard.widgets.progress-bar :label="$program['name']" :value="$program['count']" color="purple" :max="max(1, $stats['applicants_count'] ?? 0)" suffix="{{ $program['percentage'] }}%" />
                            @empty
                                <p class="text-muted small text-center py-3 mb-0">Belum ada pemilihan program studi.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if($on('quota_progress') && count($stats['quota_progress'] ?? []) > 0)
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card border rounded-4">
                        <div class="card-body p-3">
                            <h5 class="fw-bold mb-3"><i class="fas fa-bullseye me-2 text-success"></i>Progress Kuota per Program Studi</h5>
                            @foreach(($stats['quota_progress'] ?? []) as $quota)
                                <x-admin.dashboard.widgets.progress-bar
                                    :label="$quota['name'] . ' (' . $quota['accepted'] . '/' . $quota['quota'] . ')'"
                                    :value="$quota['percentage']"
                                    color="{{ $quota['percentage'] >= 90 ? 'danger' : 'success' }}"
                                    :max="100"
                                    suffix="{{ $quota['percentage'] }}%"
                                />
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($on('recent_table'))
            <div class="card border rounded-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-list me-2 text-secondary"></i>Pendaftar Terbaru</h5>
                        <span class="badge bg-secondary-lt text-secondary">5 Terakhir</span>
                    </div>
                    @if(count($stats['recent_applicants'] ?? []) > 0)
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>No. Pendaftaran</th>
                                        <th>Nama</th>
                                        <th>Asal Sekolah</th>
                                        <th>Prodi Pilihan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($stats['recent_applicants'] ?? []) as $applicant)
                                        <tr>
                                            <td class="fw-semibold">{{ $applicant['application_number'] }}</td>
                                            <td>{{ $applicant['name'] }}</td>
                                            <td>{{ $applicant['high_school'] }}</td>
                                            <td>{{ $applicant['study_program'] }}</td>
                                            <td><span class="badge {{ $applicant['status_badge_class'] }}">{{ $applicant['status_label'] }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fs-2 opacity-25 mb-2 d-block"></i>
                            <p class="text-muted small mb-0">Belum ada pendaftar pada periode ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
