<?php

use App\Models\StudentService\StudentLeaveApplication;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Pengajuan Cuti',
        ]);
    }

    public function stats(): array
    {
        return [
            'total' => StudentLeaveApplication::count(),
            'pending' => StudentLeaveApplication::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment'])->count(),
            'approved' => StudentLeaveApplication::where('status', 'approved')->count(),
            'active' => StudentLeaveApplication::where('status', 'activated')->count(),
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Pengajuan Cuti Akademik"
        description="Kelola persetujuan, penerbitan invoice biaya cuti, serta aktivasi status mahasiswa cuti secara terstruktur."
        icon="calendar-minus"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-folder-open fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Pengajuan</div>
                        <div class="fw-bold">{{ $this->stats()['total'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Perlu Review</div>
                        <div class="fw-bold">{{ $this->stats()['pending'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Approved</div>
                        <div class="fw-bold">{{ $this->stats()['approved'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Cuti Aktif</div>
                        <div class="fw-bold">{{ $this->stats()['active'] }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Cuti Akademik</h4>
                <div class="text-muted small">Statistik singkat untuk memantau permohonan cuti dan status keaktifan mahasiswa.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter tahun akademik, program studi, dan alasan cuti untuk mempercepat pemeriksaan.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Pengajuan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['total'] }}</div>
                            <i class="fa fa-folder-open fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Perlu Review & Approval</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['pending'] }}</div>
                            <i class="fa fa-clock fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Siap Diaktifkan (Approved)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['approved'] }}</div>
                            <i class="fa fa-check-circle fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Cuti Aktif Saat Ini</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['active'] }}</div>
                            <i class="fa fa-user-check fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Pengajuan Cuti</h4>
                    <span class="text-muted small">Daftar permohonan cuti, durasi semester, alasan, dan status proses persetujuan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:student-service.student-leave-application-table />
        </div>
    </div>
</div>
