<?php

use App\Models\Financial\InvoiceSchedule;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'pending' => InvoiceSchedule::where('status', 'pending')->count(),
            'due' => InvoiceSchedule::where('status', 'pending')->where('is_active', true)->where('publish_at', '<=', now())->count(),
            'completed' => InvoiceSchedule::where('status', 'completed')->count(),
            'failed' => InvoiceSchedule::where('status', 'failed')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Penjadwalan Penerbitan Tagihan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Penjadwalan Penerbitan Tagihan"
        description="Atur jadwal pembuatan dan publikasi tagihan massal secara otomatis sesuai kalender akademik untuk efisiensi operasional keuangan."
        icon="clock"
    >
        @activecan('invoice-schedule.create')
            <a href="{{ route('admin.financial.invoice-schedules.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Jadwal Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu (Pending)</div>
                        <div class="fw-bold">{{ number_format($stats['pending']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-bell fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Siap Terbit (Due Now)</div>
                        <div class="fw-bold">{{ number_format($stats['due']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-double fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Berhasil Diterbitkan</div>
                        <div class="fw-bold">{{ number_format($stats['completed']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-triangle-exclamation fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Gagal Eksekusi</div>
                        <div class="fw-bold">{{ number_format($stats['failed']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Eksekusi Jadwal Tagihan</h4>
                <div class="text-muted small">Statistik singkat proses otomatisasi pembuatan invoice massal mahasiswa.</div>
            </div>
            <div class="text-muted small">
                Klik tombol eksekusi pada tabel jika ingin memicu pembuatan tagihan sebelum waktu jadwal.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Menunggu Eksekusi</span>
                            <i class="fa fa-clock fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['pending']) }}</div>
                        <div class="text-muted small mt-2">Belum tiba waktu terbit</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Siap Diterbitkan</span>
                            <i class="fa fa-bell fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-warning lh-1">{{ number_format($stats['due']) }}</div>
                        <div class="text-muted small mt-2">Jatuh tempo hari ini/lewat</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Berhasil Diterbitkan</span>
                            <i class="fa fa-check-double fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['completed']) }}</div>
                        <div class="text-muted small mt-2">Selesai diproses sistem</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Gagal Proses</span>
                            <i class="fa fa-triangle-exclamation fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($stats['failed']) }}</div>
                        <div class="text-muted small mt-2">Kendala atau error sistem</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Jadwal Penerbitan Tagihan</h4>
                    <span class="text-muted small">Daftar jadwal pembuatan invoice untuk SPP maupun tagihan kustom lainnya.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.invoice-schedule-table />
        </div>
    </div>
</div>
