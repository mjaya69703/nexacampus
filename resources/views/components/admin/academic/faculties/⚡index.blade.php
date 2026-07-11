<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Fakultas',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Fakultas</h3>
            <div class="card-tools d-flex gap-2 flex-wrap justify-content-end">
                @include('templates.import.academic.grid-actions')
                @activecan('faculty.create')
                    <a href="{{ route('admin.academic.faculties.create') }}" class="btn btn-ghost-primary">
                        <i class="fa fa-plus me-2"></i> Tambah Fakultas
                    </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:academic.faculty-table />
        </div>
    </div>

</div>
