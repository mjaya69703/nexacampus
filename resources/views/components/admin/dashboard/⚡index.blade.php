<?php

use Livewire\Component;
use App\Models\User;
use App\Models\Access\Role;
use App\Models\Access\Permission;
use App\Models\Settings\Menu;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Route;

new class extends Component
{
    public array $stats = [];
    public array $warnings = [];
    public $recentActivities = [];
    public bool $isSuperuser = false;
    public int $totalWarnings = 0;
    public ?string $lastLoginAt = null;

    public function mount()
    {
        $this->isSuperuser = session('active_role') === 'superuser';

        $this->stats = [
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'menus' => Menu::count(),
        ];

        $invalidRouteMenus = Menu::query()
            ->where('type', 'link')
            ->whereNotNull('route_name')
            ->get()
            ->filter(fn ($menu) => ! Route::has($menu->route_name))
            ->count();

        $this->warnings = [
            'roles_without_permissions' => Role::doesntHave('permissions')->count(),
            'menus_without_permission' => Menu::where('type', 'link')
                ->whereNull('permission_name')
                ->count(),
            'inactive_menus' => Menu::where('is_active', false)->count(),
            'orphan_child_menus' => Menu::whereNotNull('parent_id')
                ->whereDoesntHave('parent')
                ->count(),
            'invalid_route_menus' => $invalidRouteMenus,
        ];

        $this->totalWarnings =
            $this->warnings['roles_without_permissions'] +
            $this->warnings['menus_without_permission'] +
            $this->warnings['orphan_child_menus'] +
            $this->warnings['invalid_route_menus'];

        $this->recentActivities = Activity::query()
            ->with('causer')
            ->latest()
            ->limit(8)
            ->get();

        $this->lastLoginAt = optional(auth()->user())->last_login_at?->format('d M Y H:i');
    }

    public function render()
    {
        $data = [
            'menus' => 'Dashboard',
            'pages' => 'Admin Dashboard',
        ];

        return $this->view()->layout('layouts.app', $data);
    }

    public function activityBadgeClass(string $description): string
    {
        return match ($description) {
            'login', 'selected active role', 'switched active role' => 'bg-blue-lt',
            'created' => 'bg-green-lt',
            'updated' => 'bg-yellow-lt',
            'deleted', 'logout' => 'bg-red-lt',
            default => 'bg-secondary-lt',
        };
    }

    public function activitySubjectLabel($activity): string
    {
        if (! $activity->subject_type) {
            return '-';
        }

        return class_basename($activity->subject_type);
    }
};
?>

<div class="app-dashboard app-dashboard-admin">
    <div class="row row-deck row-cards">
        <div class="col-sm-6 col-lg-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">
                                <i class="fas fa-users"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($stats['users']) }}
                            </div>
                            <div class="text-secondary">Users</div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.access.users.index') }}" class="card-btn">Lihat Users</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-azure text-white avatar">
                                <i class="fas fa-user-shield"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($stats['roles']) }}
                            </div>
                            <div class="text-secondary">Roles</div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.access.roles.index') }}" class="card-btn">Lihat Roles</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-green text-white avatar">
                                <i class="fas fa-key"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($stats['permissions']) }}
                            </div>
                            <div class="text-secondary">Permissions</div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.access.permissions.index') }}" class="card-btn">Lihat Permissions</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-orange text-white avatar">
                                <i class="fas fa-bars"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($stats['menus']) }}
                            </div>
                            <div class="text-secondary">Menus</div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.system.menus.index') }}" class="card-btn">Lihat Menus</a>
            </div>
        </div>

        <div class="col-sm-6 col-lg-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-red text-white avatar">
                                <i class="fas fa-triangle-exclamation"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                {{ number_format($totalWarnings) }}
                            </div>
                            <div class="text-secondary">Warnings</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer p-2 text-secondary small">
                    Issue yang perlu dicek
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-2">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary mb-1">Last Login</div>
                    <div class="font-weight-medium">
                        {{ $lastLoginAt ?? '-' }}
                    </div>
                    <div class="text-secondary small text-capitalize mt-1">
                        Role aktif: {{ session('active_role') ?? '-' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-deck row-cards mt-1">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">System Overview</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card card-sm bg-body-tertiary border-0">
                                <div class="card-body">
                                    <div class="text-secondary mb-1">App Version</div>
                                    <div class="h3 m-0">{{ config('app.version', 'v1.0.0') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card card-sm bg-body-tertiary border-0">
                                <div class="card-body">
                                    <div class="text-secondary mb-1">Environment</div>
                                    <div class="h3 m-0">{{ app()->environment() }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card card-sm bg-body-tertiary border-0">
                                <div class="card-body">
                                    <div class="text-secondary mb-1">Active Role</div>
                                    <div class="h3 m-0 text-capitalize">{{ session('active_role') ?? '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card card-sm bg-body-tertiary border-0">
                                <div class="card-body">
                                    <div class="text-secondary mb-1">Debug Mode</div>
                                    <div class="h3 m-0">
                                        @if (config('app.debug'))
                                            <span class="badge bg-yellow-lt">ON</span>
                                        @else
                                            <span class="badge bg-green-lt">OFF</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($isSuperuser)
                            <div class="col-12">
                                <div class="alert alert-warning mb-0">
                                    <div class="d-flex">
                                        <div><i class="fas fa-shield-alt me-2"></i></div>
                                        <div>
                                            Anda sedang menggunakan role <strong>superuser</strong>. Pastikan perubahan sistem dilakukan dengan hati-hati.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>

                <div class="list-group list-group-flush">
                    @php $hasQuickAction = false; @endphp

                    @activecan('user.viewAny')
                        @php $hasQuickAction = true; @endphp
                        <a href="{{ route('admin.access.users.index') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i> Manage Users
                        </a>
                    @endactivecan

                    @activecan('role.viewAny')
                        @php $hasQuickAction = true; @endphp
                        <a href="{{ route('admin.access.roles.index') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-shield me-2"></i> Manage Roles
                        </a>
                    @endactivecan

                    @activecan('permission.viewAny')
                        @php $hasQuickAction = true; @endphp
                        <a href="{{ route('admin.access.permissions.index') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-key me-2"></i> Manage Permissions
                        </a>
                    @endactivecan

                    @activecan('menu.viewAny')
                        @php $hasQuickAction = true; @endphp
                        <a href="{{ route('admin.system.menus.index') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-bars me-2"></i> Manage Menus
                        </a>
                    @endactivecan

                    @activecan('setting.viewAny')
                        @php $hasQuickAction = true; @endphp
                        <a href="{{ route('admin.system.settings.index') }}" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i> System Settings
                        </a>
                    @endactivecan

                    @if (! $hasQuickAction)
                        <div class="p-3 text-secondary">
                            Belum ada quick action yang tersedia untuk role aktif ini.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row row-deck row-cards mt-1">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Warnings & Anomalies</h3>
                </div>
                <div class="card-body">
                    <div class="divide-y">
                        <div class="row py-2 align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">Roles tanpa permission</div>
                                <div class="text-secondary">Role terdaftar tetapi belum punya permission sama sekali</div>
                            </div>
                            <div class="col-auto">
                                <span class="badge {{ $warnings['roles_without_permissions'] > 0 ? 'bg-red-lt' : 'bg-green-lt' }}">
                                    {{ $warnings['roles_without_permissions'] }}
                                </span>
                            </div>
                        </div>

                        <div class="row py-2 align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">Menu link tanpa permission</div>
                                <div class="text-secondary">Menu jenis link yang bisa diakses global</div>
                            </div>
                            <div class="col-auto">
                                <span class="badge {{ $warnings['menus_without_permission'] > 0 ? 'bg-yellow-lt' : 'bg-green-lt' }}">
                                    {{ $warnings['menus_without_permission'] }}
                                </span>
                            </div>
                        </div>

                        <div class="row py-2 align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">Inactive menus</div>
                                <div class="text-secondary">Menu yang ada di database tapi tidak aktif</div>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-azure-lt">
                                    {{ $warnings['inactive_menus'] }}
                                </span>
                            </div>
                        </div>

                        <div class="row py-2 align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">Orphan child menus</div>
                                <div class="text-secondary">Child menu yang parent-nya tidak ditemukan</div>
                            </div>
                            <div class="col-auto">
                                <span class="badge {{ $warnings['orphan_child_menus'] > 0 ? 'bg-red-lt' : 'bg-green-lt' }}">
                                    {{ $warnings['orphan_child_menus'] }}
                                </span>
                            </div>
                        </div>

                        <div class="row py-2 align-items-center">
                            <div class="col">
                                <div class="font-weight-medium">Invalid route menus</div>
                                <div class="text-secondary">Menu dengan route_name yang tidak terdaftar</div>
                            </div>
                            <div class="col-auto">
                                <span class="badge {{ $warnings['invalid_route_menus'] > 0 ? 'bg-red-lt' : 'bg-green-lt' }}">
                                    {{ $warnings['invalid_route_menus'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Recent Activity</h3>
                    <span class="badge bg-blue-lt">{{ count($recentActivities) }} items</span>
                </div>
                <div class="card-body p-0">
                    @if (count($recentActivities))
                        <div class="list-group list-group-flush">
                            @foreach ($recentActivities as $activity)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge {{ $this->activityBadgeClass($activity->description) }}">
                                                    {{ $activity->description }}
                                                </span>
                                                <span class="text-secondary small">
                                                    {{ $this->activitySubjectLabel($activity) }}
                                                </span>
                                            </div>

                                            <div class="mt-1">
                                                <strong>{{ optional($activity->causer)->name ?? 'System' }}</strong>
                                                <span class="text-secondary">
                                                    melakukan aktivitas ini
                                                </span>
                                            </div>

                                            @if ($activity->properties && count($activity->properties->toArray()))
                                                <div class="small text-secondary mt-1">
                                                    @if (data_get($activity->properties, 'active_role'))
                                                        Role: {{ data_get($activity->properties, 'active_role') }}
                                                    @elseif (data_get($activity->properties, 'attributes.name'))
                                                        Target: {{ data_get($activity->properties, 'attributes.name') }}
                                                    @elseif (data_get($activity->properties, 'old.name'))
                                                        Target: {{ data_get($activity->properties, 'old.name') }}
                                                    @else
                                                        Activity recorded
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        <div class="text-secondary small text-end">
                                            {{ $activity->created_at->format('d M Y') }}<br>
                                            {{ $activity->created_at->format('H:i') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-3 text-secondary">
                            Belum ada activity log.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
