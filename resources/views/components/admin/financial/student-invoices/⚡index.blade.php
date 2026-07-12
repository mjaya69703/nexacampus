<?php

use App\Models\Financial\StudentInvoice;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'active' => StudentInvoice::whereIn('status', ['issued', 'partially_paid'])->count(),
            'paid' => StudentInvoice::where('status', 'paid')->count(),
            'overdue' => StudentInvoice::where('status', 'overdue')->count(),
            'total_outstanding' => (float) StudentInvoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])->sum('outstanding_amount'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Tagihan Mahasiswa (Invoices)',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Tagihan & Kewajiban Mahasiswa"
        description="Kelola penerbitan tagihan SPP, registrasi, ujian, serta pantau status pelunasan dan cicilan kewajiban studi."
        icon="file-invoice-dollar"
    >
        @activecan('student-invoice.create')
            <a href="{{ route('admin.financial.student-invoices.create') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Terbitkan Tagihan Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-file-invoice-dollar fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tagihan Aktif</div>
                        <div class="fw-bold">{{ number_format($stats['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-double fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tagihan Lunas</div>
                        <div class="fw-bold">{{ number_format($stats['paid']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-exclamation-triangle fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jatuh Tempo</div>
                        <div class="fw-bold">{{ number_format($stats['overdue']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-sack-dollar fs-6 text-warning"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Piutang</div>
                        <div class="fw-bold">Rp {{ number_format($stats['total_outstanding'], 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Tagihan & Piutang Mahasiswa</h4>
                <div class="text-muted small">Statistik singkat sirkulasi invoice dan status pembayaran biaya studi.</div>
            </div>
            <div class="text-muted small">
                Klik tombol detail pada tabel untuk melihat rincian item atau menyesuaikan invoice.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Tagihan Aktif</span>
                            <i class="fa fa-file-invoice-dollar fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($stats['active']) }}</div>
                        <div class="text-muted small mt-2">Belum lunas / dicicil</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Tagihan Lunas (Paid)</span>
                            <i class="fa fa-check-double fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success lh-1">{{ number_format($stats['paid']) }}</div>
                        <div class="text-muted small mt-2">Pembayaran selesai</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Jatuh Tempo / Menunggak</span>
                            <i class="fa fa-exclamation-triangle fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-2 fw-bold text-danger lh-1">{{ number_format($stats['overdue']) }}</div>
                        <div class="text-muted small mt-2">Melewati batas waktu</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Piutang</span>
                            <i class="fa fa-sack-dollar fs-4 text-warning opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-warning lh-1">Rp {{ number_format($stats['total_outstanding'], 0, ',', '.') }}</div>
                        <div class="text-muted small mt-2">Akumulasi sisa kewajiban</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Tagihan Mahasiswa (Invoices)</h4>
                    <span class="text-muted small">Daftar nomor tagihan, nama mahasiswa, jenis biaya, total tagihan, terbayar, sisa piutang, dan status.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:financial.invoice-table />
        </div>
    </div>
</div>
