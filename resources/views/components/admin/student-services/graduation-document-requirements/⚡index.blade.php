<?php

use App\Models\StudentService\GraduationDocumentRequirement;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Dokumen Yudisium',
        ]);
    }

    public function stats(): array
    {
        return [
            'total' => GraduationDocumentRequirement::count(),
            'active' => GraduationDocumentRequirement::where('is_active', true)->count(),
            'required' => GraduationDocumentRequirement::where('is_required', true)->count(),
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Master Persyaratan Dokumen Yudisium"
        description="Kelola dokumen berkas wajib maupun opsional (Skripsi, Bebas Perpustakaan, TOEFL, dll) untuk pendaftaran yudisium."
        icon="file-invoice"
    >
        @activecan('graduation-document-requirement.create')
            <a href="{{ route('admin.student-services.graduation-document-requirements.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Dokumen Prasyarat</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-invoice fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Persyaratan</div>
                        <div class="fw-bold">{{ $this->stats()['total'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Dokumen Aktif</div>
                        <div class="fw-bold">{{ $this->stats()['active'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-exclamation-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Wajib Diunggah</div>
                        <div class="fw-bold">{{ $this->stats()['required'] }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Dokumen Yudisium</h4>
                <div class="text-muted small">Statistik berkas prasyarat pendaftaran dan kelulusan yudisium.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter program studi, tipe dokumen, status wajib, dan aktif untuk penyaringan.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Persyaratan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['total'] }}</div>
                            <i class="fa fa-file-invoice fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Dokumen Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['active'] }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Wajib Diunggah (Required)</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $this->stats()['required'] }}</div>
                            <i class="fa fa-exclamation-circle fs-4 text-danger opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Daftar Persyaratan Dokumen</h4>
                    <span class="text-muted small">Kelola tipe input dokumen (File/Text/URL), batas ukuran, dan keaktifan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:student-service.graduation-document-requirement-table />
        </div>
    </div>
</div>
