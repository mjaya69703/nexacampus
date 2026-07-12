<?php

use App\Models\Financial\InvoiceInstallmentRequest;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'submitted' => InvoiceInstallmentRequest::whereIn('status', ['submitted', 'in_approval'])->count(),
            'approved' => InvoiceInstallmentRequest::where('status', 'approved')->count(),
            'rejected' => InvoiceInstallmentRequest::where('status', 'rejected')->count(),
            'average_tenor' => round((float) InvoiceInstallmentRequest::avg('requested_tenor'), 1),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Pengajuan Cicilan Tagihan',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Pengajuan Cicilan Pembayaran"
        description="Kelola permohonan relaksasi cicilan tagihan dari mahasiswa untuk menjaga kelancaran studi dan operasional kampus."
        icon="hourglass-half"
    >
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-hourglass-half fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Menunggu Review</div>
                        <div class="fw-bold">{{ number_format($stats['submitted']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Disetujui</div>
                        <div class="fw-bold">{{ number_format($stats['approved']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-ban fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Ditolak</div>
                        <div class="fw-bold">{{ number_format($stats['rejected']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-days fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Rata-Rata Tenor</div>
                        <div class="fw-bold">{{ $stats['average_tenor'] ?: '-' }} Kali</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Permohonan Cicilan</h4>
                <div class="text-muted small">Statistik singkat pengajuan persetujuan angsuran pembayaran biaya kuliah.</div>
            </div>
            <div class="text-muted small">
                Klik tombol tinjau pada tabel untuk melihat simulasi angsuran dan memberikan keputusan.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Menunggu Review</span>
                            <i class="fa fa-hourglass-half fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['submitted']) }}</div>
                        <div class="text-muted small mt-2">Butuh tindak lanjut</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Disetujui</span>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['approved']) }}</div>
                        <div class="text-muted small mt-2">Pengajuan disepakati</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Ditolak</span>
                            <i class="fa fa-ban fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($stats['rejected']) }}</div>
                        <div class="text-muted small mt-2">Tidak memenuhi kriteria</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Rata-Rata Tenor</span>
                            <i class="fa fa-calendar-days fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-primary lh-1">{{ $stats['average_tenor'] ?: '-' }}</div>
                        <div class="text-muted small mt-2">Kali bayar angsuran</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Pengajuan Cicilan</h4>
                    <span class="text-muted small">Daftar permohonan relaksasi angsuran mahasiswa beserta simulasi dan status persetujuan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.installment-request-table />
        </div>
    </div>
</div>
