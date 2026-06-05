<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Cuti Pegawai',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Cuti Pegawai</h3>
                <small class="text-muted">Pengajuan cuti pegawai yang terhubung ke approval Kepegawaian.</small>
            </div>
            @activecan('employee-leave-request.create')
                <a href="{{ route('admin.organization.employee-leave-requests.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Ajukan Cuti
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:organization.employee-leave-request-table />
        </div>
    </div>
</div>
