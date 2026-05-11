<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Admission Applications',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Admission Applications</h3>
                <small class="text-muted">Review applicant submissions, documents, and status.</small>
            </div>
            <a href="{{ route('admission.apply') }}" class="btn btn-ghost-primary" target="_blank">
                <i class="fas fa-arrow-up-right-from-square me-1"></i> Public Form
            </a>
        </div>
        <div class="card-body">
            <livewire:admission.application-table />
        </div>
    </div>
</div>
