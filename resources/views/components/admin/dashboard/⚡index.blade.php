<?php

use App\Support\Dashboard\DashboardConfig;
use App\Support\Dashboard\DashboardService;
use App\Support\ActivePermission;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $isSuperuser = false;
    public ?string $lastLoginAt = null;
    public array $recentActivities = [];
    public array $widgetConfig = [];

    public function mount(): void
    {
        $this->isSuperuser = session('active_role') === 'superuser';
        $this->lastLoginAt = auth()->user()?->last_login_at?->format('d M Y H:i');
        $this->recentActivities = $this->isSuperuser ? app(DashboardService::class)->recentActivities(6) : [];
        $this->widgetConfig = DashboardConfig::forUser(auth()->user());
    }

    #[On('dashboard-widgets-updated')]
    public function refreshWidgetConfig(): void
    {
        $this->widgetConfig = DashboardConfig::forUser(auth()->user());
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Dashboard',
            'pages' => 'Control Center Dashboard',
        ]);
    }
};
?>

<div class="app-dashboard-shell w-full" style="width: 100% !important">
    <x-alert />

    {{-- Hero Header --}}
    <div class="app-dashboard-hero rounded-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="app-hero-icon flex-shrink-0">
                    <i class="fas fa-gauge-high"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-1">Selamat Datang, {{ auth()->user()?->name }}</h2>
                    <span class="opacity-75 small">
                        Control Center Dashboard Terintegrasi NexaCampus
                        @if($lastLoginAt)
                            &middot; Login terakhir {{ $lastLoginAt }}
                        @endif
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-white bg-opacity-10 border border-light border-opacity-25 text-uppercase fw-semibold px-3 py-2 rounded-pill">
                    Role Aktif: {{ strtoupper(session('active_role') ?? 'Admin') }}
                </span>
                @if($isSuperuser)
                    <span class="badge bg-danger fw-semibold px-3 py-2 rounded-pill">Superuser</span>
                @endif
                <livewire:admin.dashboard.widget-configurator />
            </div>
        </div>
    </div>

    {{-- Module Widgets (lazy, streaming per module) --}}
    {{-- PENTING: nama komponen WAJIB statis agar dikompilasi Livewire. --}}
    @if($isSuperuser && ($widgetConfig['system_health']['stat_card'] ?? true))
        <livewire:admin.dashboard.module-system-health lazy :config="$widgetConfig['system_health'] ?? []" />
    @endif

    @if(ActivePermission::check('admission-application.viewAny') && ($widgetConfig['admission']['stat_card'] ?? true))
        <livewire:admin.dashboard.module-admission lazy :config="$widgetConfig['admission'] ?? []" />
    @endif

    @if((ActivePermission::check('student-invoice.viewAny') || ActivePermission::check('payment.viewAny')) && ($widgetConfig['financial']['stat_card'] ?? true))
        <livewire:admin.dashboard.module-financial lazy :config="$widgetConfig['financial'] ?? []" />
    @endif

    @if((ActivePermission::check('course-offering.viewAny') || ActivePermission::check('study-plan.viewAny')) && ($widgetConfig['academic']['stat_card'] ?? true))
        <livewire:admin.dashboard.module-academic lazy :config="$widgetConfig['academic'] ?? []" />
    @endif

    @if((ActivePermission::check('service-letter-request.viewAny') || ActivePermission::check('student-complaint.viewAny')) && ($widgetConfig['student_services']['stat_card'] ?? true))
        <livewire:admin.dashboard.module-student-services lazy :config="$widgetConfig['student_services'] ?? []" />
    @endif

    @if((ActivePermission::check('employee-profile.viewAny') || ActivePermission::check('tridharma-record.viewAny')) && ($widgetConfig['organization']['stat_card'] ?? true))
        <livewire:admin.dashboard.module-organization lazy :config="$widgetConfig['organization'] ?? []" />
    @endif

    @if((ActivePermission::check('announcement.viewAny') || ActivePermission::check('alumni-profile.viewAny')) && ($widgetConfig['publication_alumni']['stat_card'] ?? true))
        <livewire:admin.dashboard.module-publication-alumni lazy :config="$widgetConfig['publication_alumni'] ?? []" />
    @endif

    @if(!(
        ($isSuperuser && ($widgetConfig['system_health']['stat_card'] ?? true)) ||
        (ActivePermission::check('admission-application.viewAny') && ($widgetConfig['admission']['stat_card'] ?? true)) ||
        ((ActivePermission::check('student-invoice.viewAny') || ActivePermission::check('payment.viewAny')) && ($widgetConfig['financial']['stat_card'] ?? true)) ||
        ((ActivePermission::check('course-offering.viewAny') || ActivePermission::check('study-plan.viewAny')) && ($widgetConfig['academic']['stat_card'] ?? true)) ||
        ((ActivePermission::check('service-letter-request.viewAny') || ActivePermission::check('student-complaint.viewAny')) && ($widgetConfig['student_services']['stat_card'] ?? true)) ||
        ((ActivePermission::check('employee-profile.viewAny') || ActivePermission::check('tridharma-record.viewAny')) && ($widgetConfig['organization']['stat_card'] ?? true)) ||
        ((ActivePermission::check('announcement.viewAny') || ActivePermission::check('alumni-profile.viewAny')) && ($widgetConfig['publication_alumni']['stat_card'] ?? true))
    ))
        <div class="card rounded-4 mb-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-lock fs-2 opacity-50 mb-3 d-block"></i>
                <h5 class="fw-bold mb-1">Belum Ada Modul yang Dapat Diakses</h5>
                <p class="text-muted small mb-0">Role aktif Anda belum memiliki izin untuk melihat modul manapun. Hubungi administrator.</p>
            </div>
        </div>
    @endif

    {{-- Bottom Row: Quick Access + Activities --}}
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card rounded-4 h-100">
                <div class="card-header border-bottom p-3 p-md-4 d-flex align-items-center gap-3">
                    <span class="app-module-icon bg-primary-lt text-primary"><i class="fas fa-bolt"></i></span>
                    <h5 class="fw-bold mb-0">Akses Cepat Modul Utama</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-2">
                        @activecan('admission-application.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.admission.admission-applications.index') }}" class="btn btn-outline-primary w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-user-plus fs-4"></i>
                                    <span>
                                        <span class="fw-bold d-block">Pendaftar PMB</span>
                                        <span class="small text-muted">Verifikasi Dokumen</span>
                                    </span>
                                </a>
                            </div>
                        @endactivecan

                        @activecan('student-invoice.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-outline-success w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-wallet fs-4"></i>
                                    <span>
                                        <span class="fw-bold d-block">Tagihan UKT</span>
                                        <span class="small text-muted">Financial Billing</span>
                                    </span>
                                </a>
                            </div>
                        @endactivecan

                        @activecan('course-offering.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.academic.course-offerings.index') }}" class="btn btn-outline-info w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-graduation-cap fs-4"></i>
                                    <span>
                                        <span class="fw-bold d-block">Penawaran Kelas</span>
                                        <span class="small text-muted">Akademik &amp; Jadwal</span>
                                    </span>
                                </a>
                            </div>
                        @endactivecan

                        @activecan('service-letter-request.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.student-services.letter-requests.index') }}" class="btn btn-outline-warning w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-file-signature fs-4"></i>
                                    <span>
                                        <span class="fw-bold d-block">Layanan Surat</span>
                                        <span class="small text-muted">Permohonan Surat</span>
                                    </span>
                                </a>
                            </div>
                        @endactivecan
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card rounded-4 h-100">
                <div class="card-header border-bottom p-3 p-md-4 d-flex align-items-center gap-3">
                    <span class="app-module-icon bg-secondary-lt text-secondary"><i class="fas fa-clock-rotate-left"></i></span>
                    <h5 class="fw-bold mb-0">Aktivitas Sistem Terkini</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if(count($recentActivities) > 0)
                        <div class="divide-y">
                            @foreach($recentActivities as $act)
                                <div class="py-2 d-flex justify-content-between align-items-center gap-2">
                                    <div class="min-w-0">
                                        <span class="badge bg-primary-lt text-primary me-1">{{ $act['description'] }}</span>
                                        <span class="fw-semibold small">{{ $act['causer'] }}</span>
                                    </div>
                                    <small class="text-muted text-nowrap">{{ $act['at'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fs-2 opacity-25 mb-2 d-block"></i>
                            <p class="text-muted small mb-0">Belum ada catatan aktivitas sistem.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
