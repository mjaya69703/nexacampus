<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'Campus Management',
            'pages' => 'Daftar Ruangan',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Ruangan</h3>
            <div class="card-tools">
                @activecan('room.create')
                <a href="{{ route('admin.campus.rooms.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Ruangan
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:campus.rooms-table />
        </div>
    </div>

</div>
