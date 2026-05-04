<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Tahun Akademik',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Tahun Akademik</h3>
            <div class="card-tools">
                @activecan('academic-year.create')
                <a href="{{ route('admin.academic.academic-years.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Tahun Akademik
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:academic.academic-year-table />
        </div>
    </div>

</div>