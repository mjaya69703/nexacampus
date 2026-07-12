<?php

use App\Models\Financial\Payment;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'pending' => Payment::where('status', 'pending')->count(),
            'verified' => Payment::where('status', 'verified')->count(),
            'rejected' => Payment::whereIn('status', ['rejected', 'failed'])->count(),
            'verified_amount' => (float) Payment::where('status', 'verified')->sum('amount'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Verifikasi Pembayaran',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Verifikasi Bukti Pembayaran"
        description="Tinjau konfirmasi bukti bayar yang diunggah mahasiswa, validasi mutasi bank, dan perbarui status pelunasan invoice."
        icon="check-double"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Verifikasi</div>
                        <div class="fw-bold">{{ number_format($stats['pending']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Terverifikasi</div>
                        <div class="fw-bold">{{ number_format($stats['verified']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-times-circle fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Ditolak / Gagal</div>
                        <div class="fw-bold">{{ number_format($stats['rejected']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-coins fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Diterima</div>
                        <div class="fw-bold">Rp {{ number_format($stats['verified_amount'], 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Konfirmasi Pembayaran</h4>
                <div class="text-muted small">Statistik singkat proses validasi bukti transfer dan pembayaran kasir mahasiswa.</div>
            </div>
            <div class="text-muted small">
                Klik tombol tinjau pada tabel untuk melihat lampiran bukti transfer dan menyetujui transaksi.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Menunggu Verifikasi</span>
                            <i class="fa fa-clock fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['pending']) }}</div>
                        <div class="text-muted small mt-2">Perlu pengecekan mutasi</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Terverifikasi (Verified)</span>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['verified']) }}</div>
                        <div class="text-muted small mt-2">Pembayaran sah & lunas</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Ditolak / Gagal</span>
                            <i class="fa fa-times-circle fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($stats['rejected']) }}</div>
                        <div class="text-muted small mt-2">Bukti tidak valid/cacat</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Diterima</span>
                            <i class="fa fa-coins fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-primary lh-1">Rp {{ number_format($stats['verified_amount'], 0, ',', '.') }}</div>
                        <div class="text-muted small mt-2">Akumulasi dana masuk</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Konfirmasi Pembayaran</h4>
                    <span class="text-muted small">Daftar transaksi masuk, metode bayar, nama bank/pengirim, nominal transfer, dan status verifikasi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.payment-table />
        </div>
    </div>
</div>
