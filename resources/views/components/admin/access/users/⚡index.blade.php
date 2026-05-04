<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\User;

new class extends Component {
    public $refreshKey;
    protected $listeners = ['deleteItem'];
    public $selected = [];
    public $selectAll = false;

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
        $data = [
            'menus' => 'User Management', // Data menu
            'pages' => 'Manajemen Pengguna',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

@push('styles')

@endpush

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Pengguna</h3>
            <div class="card-tools">
                @activecan('user.create')
                <a href="{{ route('admin.access.users.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Pengguna
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">

            <livewire:access.user-table />
        </div>
    </div>

</div>

@push('scripts')
@endpush
