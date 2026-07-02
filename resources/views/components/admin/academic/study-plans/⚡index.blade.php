<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Daftar KRS',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar KRS</h3>
            <div class="card-tools d-flex gap-2 flex-wrap justify-content-end">
                @include('templates.import.academic.grid-actions')
                @activecan('study-plan.create')
                <a href="{{ route('admin.academic.study-plans.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Buat KRS
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:academic.study-plan-table />
        </div>
    </div>

</div>
