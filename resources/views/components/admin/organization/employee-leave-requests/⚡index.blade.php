<?php

use App\Models\Organization\EmployeeLeaveRequest;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => EmployeeLeaveRequest::count(),
            'in_approval' => EmployeeLeaveRequest::whereIn('status', ['submitted', 'in_approval'])->count(),
            'approved' => EmployeeLeaveRequest::where('status', 'approved')->count(),
            'rejected_cancelled' => EmployeeLeaveRequest::whereIn('status', ['rejected', 'cancelled'])->count(),
        ];
    }

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

    <x-admin.organization.header
        title="Daftar Pengajuan Cuti Pegawai"
        description="Kelola dan verifikasi permohonan izin cuti tahunan, cuti sakit, atau cuti khusus pegawai yang terhubung langsung ke alur persetujuan kepegawaian."
        icon="user-clock"
    >
        @activecan('employee-leave-request.create')
            <a href="{{ route('admin.organization.employee-leave-requests.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Ajukan Cuti Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-lines fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Pengajuan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Approval</div>
                        <div class="fw-bold">{{ number_format($this->stats()['in_approval']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Disetujui</div>
                        <div class="fw-bold">{{ number_format($this->stats()['approved']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-xmark fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Ditolak / Batal</div>
                        <div class="fw-bold">{{ number_format($this->stats()['rejected_cancelled']) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Riwayat & Status Permohonan</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari pengajuan cuti berdasarkan nama pegawai, jenis cuti, atau status persetujuan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.employee-leave-request-table />
        </div>
    </div>
</div>
