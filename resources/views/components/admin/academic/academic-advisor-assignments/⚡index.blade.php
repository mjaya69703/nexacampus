<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Daftar Assignment Dosen PA',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Assignment Dosen PA</h3>
            <div class="card-tools d-flex gap-2 flex-wrap justify-content-end">
                @include('templates.import.academic.grid-actions')
                @activecan('academic-advisor-assignment.create')
                    <a href="{{ route('admin.academic.academic-advisor-assignments.create') }}" class="btn btn-ghost-primary">
                        <i class="fa fa-plus me-2"></i> Tambah Assignment
                    </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:academic.academic-advisor-assignment-table />
        </div>
    </div>
</div>
