<?php

use App\Models\Financial\InvoiceAdjustment;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'count' => InvoiceAdjustment::count(),
            'discounts' => abs((float) InvoiceAdjustment::whereIn('adjustment_type', ['discount', 'scholarship', 'waiver', 'write_off'])->sum('amount')),
            'penalties' => (float) InvoiceAdjustment::where('adjustment_type', 'penalty')->sum('amount'),
            'corrections' => (float) InvoiceAdjustment::where('adjustment_type', 'correction')->sum('amount'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Penyesuaian & Potongan Tagihan',
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
        title="Penyesuaian & Potongan Tagihan"
        description="Pantau riwayat lengkap koreksi tagihan, potongan beasiswa pasca-terbit, pembebasan biaya (waiver), serta denda keterlambatan."
        icon="sliders"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-sliders fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Penyesuaian</div>
                        <div class="fw-bold">{{ number_format($stats['count']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-percent fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Potongan / Waiver</div>
                        <div class="fw-bold">{{ $this->money($stats['discounts']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-gavel fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Denda & Sanksi</div>
                        <div class="fw-bold">{{ $this->money($stats['penalties']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-wrench fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Koreksi Sistem</div>
                        <div class="fw-bold">{{ $this->money($stats['corrections']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Penyesuaian Invoice</h4>
                <div class="text-muted small">Statistik singkat akumulasi potongan biaya dan penambahan denda pada tagihan mahasiswa.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter jenis penyesuaian pada tabel untuk melacak spesifik audit trail.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Penyesuaian</span>
                            <i class="fa fa-sliders fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['count']) }}</div>
                        <div class="text-muted small mt-2">Semua entri tercatat</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Potongan & Beasiswa</span>
                            <i class="fa fa-percent fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-success lh-1">{{ $this->money($stats['discounts']) }}</div>
                        <div class="text-muted small mt-2">Diskon & pembebasan</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Denda / Sanksi</span>
                            <i class="fa fa-gavel fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-warning lh-1">{{ $this->money($stats['penalties']) }}</div>
                        <div class="text-muted small mt-2">Penambahan keterlambatan</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Koreksi Sistem</span>
                            <i class="fa fa-wrench fs-4 text-info opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-info lh-1">{{ $this->money($stats['corrections']) }}</div>
                        <div class="text-muted small mt-2">Koreksi nominal invoice</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Jejak Audit Penyesuaian Tagihan</h4>
                    <span class="text-muted small">Daftar rincian penyesuaian, nominal perubahan, petugas penanggung jawab, dan link ke invoice utama.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.invoice-adjustment-table />
        </div>
    </div>
</div>
