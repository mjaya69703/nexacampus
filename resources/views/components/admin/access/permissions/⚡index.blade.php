<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'System Management',
            'pages' => 'Daftar Permission',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Permission</h3>
            <div class="card-tools">
                @activecan('permission.create')
                <a href="{{ route('admin.access.permissions.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Permission
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:access.permission-table />
        </div>
    </div>

</div>
