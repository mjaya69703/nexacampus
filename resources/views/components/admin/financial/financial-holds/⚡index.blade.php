<?php

use App\Models\Financial\FinancialHold;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'active' => FinancialHold::where('status', 'active')->count(),
            'blocking' => FinancialHold::where('status', 'active')->where('blocked_at', '<=', now())->count(),
            'waived' => FinancialHold::where('status', 'waived')->where('waived_until', '>=', now())->count(),
            'released' => FinancialHold::where('status', 'released')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Blokir & Penahanan Akses',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Daftar Pemblokiran Layanan Akademik"
        description="Pantau penahanan akses akademik mahasiswa akibat tunggakan pembayaran serta kelola pemberian dispensasi atau pelepasan blokir."
        icon="lock"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-lock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Blokir Aktif</div>
                        <div class="fw-bold">{{ number_format($stats['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-hand-pulse fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Terblokir Saat Ini</div>
                        <div class="fw-bold">{{ number_format($stats['blocking']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-hand-holding-heart fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Dispensasi (Waived)</div>
                        <div class="fw-bold">{{ number_format($stats['waived']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-lock-open fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Telah Dilepas</div>
                        <div class="fw-bold">{{ number_format($stats['released']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Status Blokir</h4>
                <div class="text-muted small">Statistik singkat kondisi penahanan layanan KRS, Rencana Studi, dan Kartu Ujian.</div>
            </div>
            <div class="text-muted small">
                Klik tombol detail pada tabel untuk memproses dispensasi atau melepas blokir.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Blokir Aktif</span>
                            <i class="fa fa-lock fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['active']) }}</div>
                        <div class="text-muted small mt-2">Total catatan aktif</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Terblokir Saat Ini</span>
                            <i class="fa fa-hand-pulse fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($stats['blocking']) }}</div>
                        <div class="text-muted small mt-2">Masa blokir berlaku</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Dispensasi (Waived)</span>
                            <i class="fa fa-hand-holding-heart fs-4 text-info opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-info lh-1">{{ number_format($stats['waived']) }}</div>
                        <div class="text-muted small mt-2">Penangguhan sementara</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Telah Dilepas</span>
                            <i class="fa fa-lock-open fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['released']) }}</div>
                        <div class="text-muted small mt-2">Blokir terselesaikan</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Financial Holds</h4>
                    <span class="text-muted small">Daftar mahasiswa yang terindikasi pemblokiran beserta status penangguhan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.financial-hold-table />
        </div>
    </div>
</div>
