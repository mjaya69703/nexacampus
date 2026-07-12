<?php

use App\Models\StudentService\GraduationPolicy;
use Livewire\Component;

new class extends Component
{
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = [
            'total' => GraduationPolicy::count(),
            'active' => GraduationPolicy::where('is_active', true)->count(),
            'program_scoped' => GraduationPolicy::whereNotNull('study_program_id')->count(),
            'global' => GraduationPolicy::whereNull('study_program_id')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Aturan Yudisium',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Master Aturan & Prasyarat Yudisium"
        description="Konfigurasi prasyarat kelulusan (SKS minimum, IPK minimum, bebas tagihan keuangan, dll) per prodi atau global."
        icon="gavel"
    >
        @activecan('graduation-policy.create')
            <a href="{{ route('admin.student-services.graduation-policies.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Aturan Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-gavel fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Aturan</div>
                        <div class="fw-bold">{{ $summary['total'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Aturan Aktif</div>
                        <div class="fw-bold">{{ $summary['active'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-school fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Spesifik Prodi</div>
                        <div class="fw-bold">{{ $summary['program_scoped'] }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-globe fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Berlaku Global</div>
                        <div class="fw-bold">{{ $summary['global'] }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Aturan Yudisium</h4>
                <div class="text-muted small">Statistik kebijakan kelulusan dan cakupan penerapan aturan.</div>
            </div>
            <div class="text-muted small">
                Manfaatkan filter prodi dan status aktif untuk memeriksa kebijakan yudisium.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Aturan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['total'] }}</div>
                            <i class="fa fa-gavel fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Aturan Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['active'] }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Spesifik Prodi</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['program_scoped'] }}</div>
                            <i class="fa fa-school fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Berlaku Global</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $summary['global'] }}</div>
                            <i class="fa fa-globe fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Daftar Kebijakan & Aturan Yudisium</h4>
                    <span class="text-muted small">Kelola parameter SKS, IPK, dan persetujuan aktif per program studi atau global.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:student-service.graduation-policy-table />
        </div>
    </div>
</div>
