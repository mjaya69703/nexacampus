<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Admission Quotas',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Admission Quotas</h3>
                <small class="text-muted">Define capacity per period, study program, and class type.</small>
            </div>
            @activecan('admission-quota.create')
                <a href="{{ route('admin.admission.admission-quotas.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Add Quota
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:admission.quota-table />
        </div>
    </div>
</div>
