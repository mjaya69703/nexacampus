<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => Role::count(),
            'web' => Role::where('guard_name', 'web')->count(),
            'permissions' => Permission::count(),
            'assigned' => Role::whereHas('users')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Manajemen Akses',
            'pages' => 'Daftar Peran',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.access.header
        title="Daftar Peran"
        description="Kelola role yang menjadi lapisan utama kontrol akses pengguna, termasuk relasi ke permission yang dimilikinya."
        icon="user-shield"
    >
        @activecan('role.create')
            <a href="{{ route('admin.access.roles.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Peran</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <x-admin.access.stat-card label="Total Peran" :value="$stats['total']" icon="user-shield" tone="primary" />
            <x-admin.access.stat-card label="Guard Web" :value="$stats['web']" icon="globe" tone="info" />
            <x-admin.access.stat-card label="Total Permission" :value="$stats['permissions']" icon="key" tone="warning" />
            <x-admin.access.stat-card label="Peran Terpakai" :value="$stats['assigned']" icon="users" tone="success" />
        </x-slot:stats>
    </x-admin.access.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Peran</h4>
                    <span class="text-muted small">Pantau role yang aktif dipakai dan filter data saat jumlah peran bertambah.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:access.role-table />
        </div>
    </div>
</div>