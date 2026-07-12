<?php

use App\Models\Organization\LecturerWorkloadSubmission;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => LecturerWorkloadSubmission::count(),
            'approval' => LecturerWorkloadSubmission::where('status', 'in_approval')->count(),
            'approved' => LecturerWorkloadSubmission::where('status', 'approved')->count(),
            'avg' => LecturerWorkloadSubmission::avg('total_sks') ?: 0,
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Review BKD']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Review Laporan BKD Dosen"
        description="Periksa dan verifikasi rekapitulasi capaian SKS pengajaran, penelitian, pengabdian, penunjang, serta tugas struktural yang diajukan oleh dosen."
        icon="file-signature"
    >
        @activecan('lecturer-workload-submission.viewAny')
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ route('admin.organization.lecturer-workload-submissions.export', 'csv') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-file-csv"></i> <span>CSV</span>
                </a>
                <a href="{{ route('admin.organization.lecturer-workload-submissions.export', 'xlsx') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-file-excel"></i> <span>Excel</span>
                </a>
                <a href="{{ route('admin.organization.lecturer-workload-submissions.export', 'pdf') }}" class="btn btn-sm btn-light text-danger fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-file-pdf"></i> <span>PDF</span>
                </a>
            </div>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-lines fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Laporan Masuk</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock-rotate-left fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Approval</div>
                        <div class="fw-bold">{{ number_format($this->stats()['approval']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Disetujui (Approved)</div>
                        <div class="fw-bold">{{ number_format($this->stats()['approved']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calculator fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Rata-Rata SKS</div>
                        <div class="fw-bold">{{ number_format($this->stats()['avg'], 1) }} SKS</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Direktori Pengajuan Beban Kerja Dosen</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk menemukan pengajuan BKD berdasarkan nama dosen, periode, atau status persetujuan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.lecturer-workload-submission-table />
        </div>
    </div>
</div>
