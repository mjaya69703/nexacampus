<?php

use App\Models\StudentService\ServiceLetterRequest;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Pengajuan Surat',
        ]);
    }

    public function stats(): array
    {
        return [
            'total' => ServiceLetterRequest::count(),
            'pending' => ServiceLetterRequest::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested'])->count(),
            'approved' => ServiceLetterRequest::where('status', 'approved')->count(),
            'issued' => ServiceLetterRequest::where('status', 'issued')->count(),
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Pengajuan & Penerbitan Surat"
        description="Periksa, setujui, dan terbitkan surat keterangan mahasiswa secara manual maupun otomatis (PDF generated)."
        icon="envelope-open-text"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-inbox fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Permohonan</div>
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
                    <i class="fa fa-file-signature fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Siap Diterbitkan</div>
                        <div class="fw-bold">{{ $this->stats()['approved'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Sudah Terbit</div>
                        <div class="fw-bold">{{ $this->stats()['issued'] }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Pengajuan Surat</h4>
                <div class="text-muted small">Statistik singkat untuk memantau beban kerja dan waktu layanan surat mahasiswa.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter jenis surat dan program studi untuk mempercepat verifikasi.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Permohonan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['total'] }}</div>
                            <i class="fa fa-inbox fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Perlu Review / Approval</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['pending'] }}</div>
                            <i class="fa fa-clock fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Siap Diterbitkan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['approved'] }}</div>
                            <i class="fa fa-file-signature fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Sudah Terbit</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['issued'] }}</div>
                            <i class="fa fa-file-check fs-4 text-success opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Pengajuan Surat</h4>
                    <span class="text-muted small">Daftar permohonan surat keterangan mahasiswa, keperluan, dan status pemrosesan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:student-service.service-letter-request-table />
        </div>
    </div>
</div>
