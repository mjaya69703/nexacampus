<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Absensi Pegawai',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Absensi Pegawai</h3>
                <small class="text-muted">Catatan check-in/check-out pegawai dari input manual atau sumber lain.</small>
            </div>
            @activecan('employee-attendance-record.create')
                <a href="{{ route('admin.organization.employee-attendance-records.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Input Absensi
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:organization.employee-attendance-record-table />
        </div>
    </div>
</div>
