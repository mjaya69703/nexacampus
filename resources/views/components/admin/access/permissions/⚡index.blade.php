<?php

use App\Models\Access\Permission;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => Permission::count(),
            'web' => Permission::where('guard_name', 'web')->count(),
            'api' => Permission::where('guard_name', 'api')->count(),
            'attached' => Permission::whereHas('roles')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Manajemen Akses',
            'pages' => 'Daftar Permission',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.access.header
        title="Daftar Permission"
        description="Kelola permission, guard, dan keterkaitannya dengan role agar kontrol akses tetap mudah dipantau saat data bertambah banyak."
        icon="shield-halved"
    >
        @activecan('permission.create')
            <a href="{{ route('admin.access.permissions.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Permission</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <x-admin.access.stat-card label="Total Permission" :value="$stats['total']" icon="shield-halved" tone="primary" />
            <x-admin.access.stat-card label="Guard Web" :value="$stats['web']" icon="globe" tone="info" />
            <x-admin.access.stat-card label="Guard API" :value="$stats['api']" icon="code-branch" tone="warning" />
            <x-admin.access.stat-card label="Sudah Dipakai Role" :value="$stats['attached']" icon="link" tone="success" />
        </x-slot:stats>
    </x-admin.access.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Permission</h4>
                    <span class="text-muted small">Cari nama permission, guard, dan status pemakaiannya di role.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:access.permission-table />
        </div>
    </div>
</div>
