<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Approval',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Approval</h3>
                <small class="text-muted">Monitor dan proses request approval yang berjalan melalui approval engine.</small>
            </div>
            @activecan('approval-request.create')
                <a href="{{ route('admin.organization.approval-requests.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Buat Request
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:organization.approval-request-table />
        </div>
    </div>
</div>
