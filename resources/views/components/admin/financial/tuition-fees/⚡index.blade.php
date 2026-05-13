<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Tuition Fees',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Tuition Fees</h3>
                <small class="text-muted">Konfigurasi biaya kuliah per tahun akademik, program studi, dan semester.</small>
            </div>
            @activecan('tuition-fee.create')
                <a href="{{ route('admin.financial.tuition-fees.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Add Fee
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:financial.tuition-fee-table />
        </div>
    </div>
</div>
