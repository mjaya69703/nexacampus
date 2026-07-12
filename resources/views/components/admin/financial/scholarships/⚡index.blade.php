<?php

use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentScholarship;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => Scholarship::count(),
            'active' => Scholarship::where('is_active', true)->count(),
            'assignments' => StudentScholarship::where('status', 'active')->count(),
            'fixed' => Scholarship::where('discount_type', 'fixed')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Program Beasiswa Kampus',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Program Beasiswa Kampus"
        description="Kelola template dan skema potongan beasiswa sebelum dialokasikan secara massal maupun individual ke tagihan mahasiswa."
        icon="award"
    >
        @activecan('scholarship.create')
            <a href="{{ route('admin.financial.scholarships.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Program Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Program</div>
                        <div class="fw-bold">{{ number_format($stats['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Program Aktif</div>
                        <div class="fw-bold">{{ number_format($stats['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-graduate fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Penerima Aktif</div>
                        <div class="fw-bold">{{ number_format($stats['assignments']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-hand-holding-dollar fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Potongan Tetap</div>
                        <div class="fw-bold">{{ number_format($stats['fixed']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Program Beasiswa</h4>
                <div class="text-muted small">Statistik singkat katalog skema beasiswa akademik dan non-akademik kampus.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter pada tabel untuk mencari program berdasarkan jenis potongan atau status.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Program Beasiswa</span>
                            <i class="fa fa-layer-group fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['total']) }}</div>
                        <div class="text-muted small mt-2">Katalog skema terdaftar</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Program Aktif</span>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['active']) }}</div>
                        <div class="text-muted small mt-2">Dapat dialokasikan</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Penerima Beasiswa Aktif</span>
                            <i class="fa fa-user-graduate fs-4 text-info opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-info lh-1">{{ number_format($stats['assignments']) }}</div>
                        <div class="text-muted small mt-2">Mahasiswa penerima sanksi/manfaat</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Potongan Nominal Tetap</span>
                            <i class="fa fa-hand-holding-dollar fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($stats['fixed']) }}</div>
                        <div class="text-muted small mt-2">Skema potongan fixed (Rp)</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Program Beasiswa</h4>
                    <span class="text-muted small">Daftar kode, nama program, skema persentase/fixed, kuota, masa berlaku, dan status aktif.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.scholarship-table />
        </div>
    </div>
</div>
