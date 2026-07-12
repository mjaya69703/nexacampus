<?php

use App\Models\Financial\FinancialClearancePolicy;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => FinancialClearancePolicy::count(),
            'active' => FinancialClearancePolicy::where('is_active', true)->count(),
            'blocking' => FinancialClearancePolicy::where('mode', 'blocking')->where('is_active', true)->count(),
            'warning' => FinancialClearancePolicy::where('mode', 'warning')->where('is_active', true)->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Kebijakan Clearance & Pemblokiran',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Kebijakan Clearance & Pemblokiran"
        description="Atur jenis tagihan tunggakan yang akan memicu sanksi pemblokiran layanan akademik atau peringatan otomatis kepada mahasiswa."
        icon="file-shield"
    >
        @activecan('clearance-policy.create')
            <a href="{{ route('admin.financial.clearance-policies.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Kebijakan Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-shield fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kebijakan</div>
                        <div class="fw-bold">{{ number_format($stats['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kebijakan Aktif</div>
                        <div class="fw-bold">{{ number_format($stats['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-lock fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Mode Pemblokiran</div>
                        <div class="fw-bold">{{ number_format($stats['blocking']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-triangle-exclamation fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Mode Peringatan</div>
                        <div class="fw-bold">{{ number_format($stats['warning']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Kebijakan Clearance</h4>
                <div class="text-muted small">Statistik singkat untuk memantau status penerapan sanksi akademik di kampus.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter atau toggle status pada tabel untuk mengontrol aturan sanksi.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Aturan</span>
                            <i class="fa fa-file-shield fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['total']) }}</div>
                        <div class="text-muted small mt-2">Seluruh aturan sanksi</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Kebijakan Aktif</span>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['active']) }}</div>
                        <div class="text-muted small mt-2">Diberlakukan saat ini</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Mode Pemblokiran</span>
                            <i class="fa fa-lock fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($stats['blocking']) }}</div>
                        <div class="text-muted small mt-2">Membatasi akses layanan</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Mode Peringatan</span>
                            <i class="fa fa-triangle-exclamation fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($stats['warning']) }}</div>
                        <div class="text-muted small mt-2">Notifikasi tanpa blokir</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Kebijakan Clearance</h4>
                    <span class="text-muted small">Daftar konfigurasi pemicu tunggakan, target pemblokiran, mode sanksi, dan aksi pengelolaan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.clearance-policy-table />
        </div>
    </div>
</div>
