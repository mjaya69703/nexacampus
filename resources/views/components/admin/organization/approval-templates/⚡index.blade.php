<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Template Approval',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Template Approval</h3>
                <small class="text-muted">Konfigurasi alur approval berurutan untuk modul kepegawaian dan modul lain.</small>
            </div>
            @activecan('approval-template.create')
                <a href="{{ route('admin.organization.approval-templates.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Tambah Template
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:organization.approval-template-table />
        </div>
    </div>
</div>
