<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'NIM Generation Rules',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">NIM Generation Rules</h3>
                <small class="text-muted">Configure flexible student ID formats for admission conversion.</small>
            </div>
            @activecan('nim-generation-rule.create')
                <a href="{{ route('admin.admission.nim-generation-rules.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Add Rule
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:admission.nim-generation-rule-table />
        </div>
    </div>
</div>
