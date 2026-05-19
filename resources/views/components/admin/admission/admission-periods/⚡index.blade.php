<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Admission Periods',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Admission Periods</h3>
            @activecan('admission-period.create')
                <a href="{{ route('admin.admission.admission-periods.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Add Period
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:admission.admission-period-table />
        </div>
    </div>
</div>
