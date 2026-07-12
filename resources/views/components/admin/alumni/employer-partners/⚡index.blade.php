<?php

use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use Livewire\Component;

new class extends Component
{
    public int $totalPartners = 0;
    public int $activePartners = 0;
    public int $totalJobs = 0;

    public function mount(): void
    {
        $this->totalPartners = EmployerPartner::count();
        $this->activePartners = EmployerPartner::query()->where('is_active', true)->count();
        $this->totalJobs = JobPosting::count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Mitra Perusahaan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Mitra Perusahaan & Industri"
        description="Kelola daftar perusahaan mitra, kerjasama penyerapan tenaga kerja, serta keterhubungan dengan lowongan karir alumni."
        icon="building"
    >
        @activecan('employer-partner.create')
            <a href="{{ route('admin.alumni.employer-partners.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Mitra Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-handshake fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Mitra</div>
                        <div class="fw-bold">{{ $totalPartners }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Mitra Aktif</div>
                        <div class="fw-bold">{{ $activePartners }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-bullhorn fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Lowongan Terdaftar</div>
                        <div class="fw-bold">{{ $totalJobs }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.alumni.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Kolaborasi Industri</h4>
                <div class="text-muted small">Pantau kapasitas jaringan kerjasama perusahaan dalam mendukung rekrutmen alumni kampus.</div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Perusahaan Mitra</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalPartners }}</div>
                            <i class="fa fa-building fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Mitra Kerjasama Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $activePartners }}</div>
                            <i class="fa fa-circle-check fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-12 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Lowongan Dipublikasikan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalJobs }}</div>
                            <i class="fa fa-briefcase fs-4 text-info opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Mitra Perusahaan</h4>
                    <span class="text-muted small">Daftar instansi, sektor industri, kota, kontak person, dan jumlah lowongan kerja.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:alumni.employer-partner-table />
        </div>
    </div>
</div>
