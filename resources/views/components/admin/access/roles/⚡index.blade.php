<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'System Management',
            'pages' => 'Daftar Peran',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Peran</h3>
            <div class="card-tools">
                @activecan('role.create')
                <a href="{{ route('admin.access.roles.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Peran
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:access.role-table />
        </div>
    </div>

</div>