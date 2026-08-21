<?php

use App\Support\Dashboard\DashboardService;
use Livewire\Component;

new class extends Component
{
    public array $config = [];
    public array $stats = [];

    public bool $loadError = false;

    public function mount(): void
    {
        if (session('active_role') !== 'superuser') {
            return;
        }

        try {
            $this->stats = app(DashboardService::class)->systemHealth();
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
    $warnings = $stats['warnings'] ?? [];
    $warningItems = collect([
        ($warnings['roles_without_permissions'] ?? 0) > 0 ? ($warnings['roles_without_permissions'] . ' role terdaftar tanpa permission.') : null,
        ($warnings['menus_without_permission'] ?? 0) > 0 ? ($warnings['menus_without_permission'] . ' menu link tanpa permission.') : null,
        ($warnings['orphan_child_menus'] ?? 0) > 0 ? ($warnings['orphan_child_menus'] . ' child menu tanpa parent yang valid.') : null,
        ($warnings['invalid_route_menus'] ?? 0) > 0 ? ($warnings['invalid_route_menus'] . ' menu dengan route name tidak terdaftar.') : null,
    ])->filter()->values()->all();
@endphp

<div class="card rounded-4 mb-4 overflow-hidden">
    <div class="card-header border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-3">
            <span class="app-module-icon bg-secondary-lt text-secondary"><i class="fas fa-shield-halved"></i></span>
            <div>
                <h4 class="fw-bold mb-0">Superuser Control Panel &amp; System Health</h4>
                <span class="text-muted small">Statistik akses global, audit permissions, dan warnings sistem.</span>
            </div>
        </div>
        <span class="badge bg-danger-lt text-danger fw-semibold px-3 py-2 rounded-pill">
            <i class="fas fa-key me-1"></i>Superuser Privileges
        </span>
    </div>

    <div class="card-body p-3 p-md-4">
        @if($on('stat_card'))
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Total Users" value="{{ number_format($stats['users_count'] ?? 0) }}" icon="fas fa-users" color="primary" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Total Roles" value="{{ number_format($stats['roles_count'] ?? 0) }}" icon="fas fa-user-shield" color="info" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="Total Permissions" value="{{ number_format($stats['permissions_count'] ?? 0) }}" icon="fas fa-key" color="success" />
                </div>
                <div class="col-6 col-xl-3">
                    <x-admin.dashboard.widgets.stat-card label="System Warnings" value="{{ number_format($warnings['total'] ?? 0) }}" icon="fas fa-triangle-exclamation" color="{{ ($warnings['total'] ?? 0) > 0 ? 'danger' : 'success' }}" />
                </div>
            </div>
        @endif

        @if($on('warnings') && count($warningItems) > 0)
            <x-admin.dashboard.widgets.alert-banner
                type="danger"
                title="Peringatan Anomali Sistem Memerlukan Perhatian:"
                :items="$warningItems"
            />
        @endif

        @if($on('warnings') && count($warningItems) === 0)
            <x-admin.dashboard.widgets.alert-banner type="success" message="Sistem sehat. Tidak ditemukan anomali konfigurasi." />
        @endif
    </div>
</div>
