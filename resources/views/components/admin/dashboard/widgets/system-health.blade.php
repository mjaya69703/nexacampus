@props([
    'stats' => [],
    'warnings' => [],
    'activities' => [],
])

@if(session('active_role') === 'superuser')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    {{-- Header --}}
    <div class="card-header bg-dark text-white p-3 p-md-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white bg-opacity-20 text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="fas fa-shield-alt fs-5"></i>
            </div>
            <div>
                <h4 class="card-title fw-bold mb-0 text-white">Superuser Control Panel &amp; System Health</h4>
                <span class="text-white text-opacity-75 small">Statistik akses global, audit permissions, dan warnings sistem.</span>
            </div>
        </div>
        <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill">
            <i class="fas fa-key me-1"></i>Superuser Privileges
        </span>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- Stat Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-sm-3 col-xl-3">
                <x-admin.dashboard.widgets.stat-card
                    label="Total Users"
                    :value="$stats['users'] ?? 0"
                    icon="fas fa-users"
                    color="primary"
                />
            </div>
            <div class="col-6 col-sm-3 col-xl-3">
                <x-admin.dashboard.widgets.stat-card
                    label="Total Roles"
                    :value="$stats['roles'] ?? 0"
                    icon="fas fa-user-shield"
                    color="info"
                />
            </div>
            <div class="col-6 col-sm-3 col-xl-3">
                <x-admin.dashboard.widgets.stat-card
                    label="Total Permissions"
                    :value="$stats['permissions'] ?? 0"
                    icon="fas fa-key"
                    color="success"
                />
            </div>
            <div class="col-6 col-sm-3 col-xl-3">
                <x-admin.dashboard.widgets.stat-card
                    label="System Warnings"
                    :value="$warnings['total'] ?? 0"
                    icon="fas fa-triangle-exclamation"
                    color="danger"
                />
            </div>
        </div>

        {{-- Warnings Alert --}}
        @if(isset($warnings['total']) && $warnings['total'] > 0)
            <x-admin.dashboard.widgets.alert-banner
                type="danger"
                title="Peringatan Anomali Sistem Memerlukan Perhatian:"
                :items="collect([
                    $warnings['roles_without_permissions'] > 0 ? $warnings['roles_without_permissions'] . ' Role terdaftar tanpa permission.' : null,
                    $warnings['menus_without_permission'] > 0 ? $warnings['menus_without_permission'] . ' Menu link tanpa permission.' : null,
                    $warnings['orphan_child_menus'] > 0 ? $warnings['orphan_child_menus'] . ' Child menu tanpa parent yang valid.' : null,
                    $warnings['invalid_route_menus'] > 0 ? $warnings['invalid_route_menus'] . ' Menu dengan route name yang tidak terdaftar.' : null,
                ])->filter()->toArray()"
            />
        @endif
    </div>
</div>
@endif
