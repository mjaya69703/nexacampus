<?php

use App\Models\Financial\TuitionFee;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => TuitionFee::count(),
            'active' => TuitionFee::where('is_active', true)->count(),
            'archived' => TuitionFee::where('is_active', false)->count(),
            'avg_base_fee' => (float) TuitionFee::where('is_active', true)->avg('base_fee'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Tarif Biaya Kuliah (SPP)',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Tarif Biaya Kuliah (SPP)"
        description="Kelola standar tarif pokok dan komponen biaya kuliah per program studi, tahun akademik, dan angkatan mahasiswa."
        icon="money-check-dollar"
    >
        @activecan('tuition-fee.create')
            <a href="{{ route('admin.financial.tuition-fees.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Tarif SPP</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Tarif</div>
                        <div class="fw-bold">{{ number_format($stats['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tarif Aktif</div>
                        <div class="fw-bold">{{ number_format($stats['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-archive fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Nonaktif / Arsip</div>
                        <div class="fw-bold">{{ number_format($stats['archived']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-chart-line fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Rata-rata SPP</div>
                        <div class="fw-bold">{{ $this->money($stats['avg_base_fee']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Tarif & Komponen Biaya Kuliah</h4>
                <div class="text-muted small">Akumulasi master tarif SPP dan rata-rata biaya per program studi.</div>
            </div>
            <div class="text-muted small">
                Klik tombol edit atau duplikasi pada tabel untuk memperbarui tarif semester baru.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Master Tarif SPP</span>
                            <i class="fa fa-layer-group fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['total']) }}</div>
                        <div class="text-muted small mt-2">Seluruh konfigurasi tarif terdaftar</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Tarif Aktif & Berlaku</span>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['active']) }}</div>
                        <div class="text-muted small mt-2">Dapat digunakan penerbitan invoice</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Tarif Nonaktif (Arsip)</span>
                            <i class="fa fa-archive fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($stats['archived']) }}</div>
                        <div class="text-muted small mt-2">Tarif lawas / digantikan</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Rata-rata Biaya Pokok SPP</span>
                            <i class="fa fa-chart-line fs-4 text-info opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-info lh-1">{{ $this->money($stats['avg_base_fee']) }}</div>
                        <div class="text-muted small mt-2">Nilai tengah base fee aktif</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Tarif Biaya Kuliah (SPP)</h4>
                    <span class="text-muted small">Daftar program studi, tahun akademik, semester, tarif dasar, total komponen tambahan, dan status aktif.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.tuition-fee-table />
        </div>
    </div>
</div>
