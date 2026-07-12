<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public $refreshKey;
    protected $listeners = ['deleteItem'];
    public $selected = [];
    public $selectAll = false;
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'with_role' => User::whereHas('roles')->count(),
        ];
    }

    public function deleteItem($id)
    {
        if ($id == auth()->id()) {
            session()->flash('error', 'Anda tidak dapat menghapus pengguna yang sedang aktif.');

            return;
        }

        User::find($id)?->delete();

        $this->refreshKey = uniqid();
        session()->flash('success', 'Data pengguna berhasil dihapus.');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Manajemen Akses',
            'pages' => 'Manajemen Pengguna',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.access.header
        title="Manajemen Pengguna"
        description="Kelola akun pengguna, status aktif, dan peran yang melekat agar hak akses tetap sesuai kebutuhan operasional."
        icon="users"
    >
        @activecan('user.create')
            <a href="{{ route('admin.access.users.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Pengguna</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <x-admin.access.stat-card label="Total Pengguna" :value="$stats['total']" icon="users" tone="primary" />
            <x-admin.access.stat-card label="Aktif" :value="$stats['active']" icon="user-check" tone="success" />
            <x-admin.access.stat-card label="Nonaktif" :value="$stats['inactive']" icon="user-slash" tone="danger" />
            <x-admin.access.stat-card label="Punya Role" :value="$stats['with_role']" icon="id-badge" tone="info" />
        </x-slot:stats>
    </x-admin.access.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Pengguna</h4>
                    <span class="text-muted small">Cari berdasarkan nama, username, atau email saat data pengguna sudah besar.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:access.user-table />
        </div>
    </div>
</div>

@push('styles')
@endpush

@push('scripts')
@endpush
