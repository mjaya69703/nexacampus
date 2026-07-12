<?php

use App\Models\Financial\StudentScholarship;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => StudentScholarship::count(),
            'active' => StudentScholarship::where('status', 'active')->count(),
            'completed' => StudentScholarship::where('status', 'completed')->count(),
            'revoked' => StudentScholarship::where('status', 'revoked')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Alokasi Beasiswa Mahasiswa',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Alokasi Beasiswa Mahasiswa"
        description="Kelola penetapan program beasiswa ke mahasiswa, atur masa berlaku, dan pantau status pemotongan otomatis pada tagihan."
        icon="user-graduate"
    >
        @activecan('student-scholarship.create')
            <a href="{{ route('admin.financial.student-scholarships.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-user-plus"></i> <span>Alokasikan Beasiswa</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Alokasi</div>
                        <div class="fw-bold">{{ number_format($stats['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Alokasi Aktif</div>
                        <div class="fw-bold">{{ number_format($stats['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-flag-checkered fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Selesai Masa Berlaku</div>
                        <div class="fw-bold">{{ number_format($stats['completed']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-ban fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Dicabut / Batal</div>
                        <div class="fw-bold">{{ number_format($stats['revoked']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Alokasi & Status Beasiswa</h4>
                <div class="text-muted small">Statistik singkat distribusi program beasiswa dan status aktif penerima di kampus.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter atau pencarian tabel untuk meninjau alokasi mahasiswa pada program studi tertentu.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Alokasi Beasiswa</span>
                            <i class="fa fa-users fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['total']) }}</div>
                        <div class="text-muted small mt-2">Seluruh riwayat penugasan</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Alokasi Aktif</span>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['active']) }}</div>
                        <div class="text-muted small mt-2">Berlaku untuk pemotongan invoice</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Selesai Masa Berlaku</span>
                            <i class="fa fa-flag-checkered fs-4 text-info opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-info lh-1">{{ number_format($stats['completed']) }}</div>
                        <div class="text-muted small mt-2">Periode/semester telah berakhir</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Dicabut / Dibatalkan</span>
                            <i class="fa fa-ban fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($stats['revoked']) }}</div>
                        <div class="text-muted small mt-2">Penugasan dibatalkan/sanksi</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Alokasi Beasiswa Mahasiswa</h4>
                    <span class="text-muted small">Daftar NIM, nama mahasiswa, nama program beasiswa, tahun akademik, semester, dan status alokasi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.student-scholarship-table />
        </div>
    </div>
</div>
