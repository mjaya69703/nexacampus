<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Jenis Cuti Pegawai',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Jenis Cuti Pegawai</h3>
                <small class="text-muted">Atur tipe cuti, jatah default, dan template approval.</small>
            </div>
            @activecan('employee-leave-type.create')
                <a href="{{ route('admin.organization.employee-leave-types.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Tambah Jenis
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:organization.employee-leave-type-table />
        </div>
    </div>
</div>
