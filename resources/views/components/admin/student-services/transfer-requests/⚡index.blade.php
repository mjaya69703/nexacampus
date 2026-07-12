<?php

use App\Models\StudentService\StudentTransferRequest;
use Livewire\Component;

new class extends Component
{
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = [
            'total' => StudentTransferRequest::count(),
            'pending' => StudentTransferRequest::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment'])->count(),
            'approved' => StudentTransferRequest::where('status', 'approved')->count(),
            'applied' => StudentTransferRequest::where('status', 'applied')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Pengajuan Pindah',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Pengajuan Pindah Program & Kelas"
        description="Kelola transfer internal prodi, perpindahan tipe kelas, dan pengajuan keluar/pindah kampus dengan alur persetujuan terpadu."
        icon="right-left"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-right-left fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Pengajuan</div>
                        <div class="fw-bold">{{ $summary['total'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pending Review</div>
                        <div class="fw-bold">{{ $summary['pending'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-double fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Approved</div>
                        <div class="fw-bold">{{ $summary['approved'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Applied</div>
                        <div class="fw-bold">{{ $summary['applied'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Perpindahan Mahasiswa</h4>
                <div class="text-muted small">Statistik pengajuan transfer internal dan perubahan tipe kelas mahasiswa.</div>
            </div>
            <div class="text-muted small">
                Manfaatkan filter tipe transfer dan prodi asal/tujuan untuk memonitor perpindahan.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Pengajuan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['total'] ?? 0 }}</div>
                            <i class="fa fa-right-left fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Pending Review / Bayar</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['pending'] ?? 0 }}</div>
                            <i class="fa fa-clock fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Siap Diterapkan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['approved'] ?? 0 }}</div>
                            <i class="fa fa-check-double fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Sudah Diterapkan (Applied)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['applied'] ?? 0 }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Pengajuan Pindah</h4>
                    <span class="text-muted small">Daftar pengajuan transfer program studi atau tipe kelas beserta status dan aksi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:student-service.student-transfer-request-table />
        </div>
    </div>
</div>
