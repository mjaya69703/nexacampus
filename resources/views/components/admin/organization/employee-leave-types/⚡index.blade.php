<?php

use App\Models\Organization\EmployeeLeaveType;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => EmployeeLeaveType::count(),
            'active' => EmployeeLeaveType::where('is_active', true)->count(),
            'paid' => EmployeeLeaveType::where('is_paid', true)->count(),
            'requires_approval' => EmployeeLeaveType::where('requires_approval', true)->count(),
        ];
    }

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

    <x-admin.organization.header
        title="Daftar Jenis Cuti Pegawai"
        description="Atur jenis dan klasifikasi cuti yang tersedia untuk pegawai, kuota jatah default pertahun, syarat lampiran dokumen, serta tautkan ke template alur persetujuan."
        icon="calendar-alt"
    >
        @activecan('employee-leave-type.create')
            <a href="{{ route('admin.organization.employee-leave-types.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Jenis Cuti</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-alt fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Jenis Cuti</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Cuti Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-coins fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Digaji (Paid Leave)</div>
                        <div class="fw-bold">{{ number_format($this->stats()['paid']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Wajib Approval</div>
                        <div class="fw-bold">{{ number_format($this->stats()['requires_approval']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Klasifikasi & Aturan Cuti</h4>
                    <span class="text-muted small">Kelola kuota, status aktif, dan kewajiban unggah bukti dokumen permohonan cuti.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.employee-leave-type-table />
        </div>
    </div>
</div>
