<?php

use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentInvoice;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];
    public array $invoiceTypeRows = [];

    public function mount(): void
    {
        $this->stats = [
            'verified_payments' => (float) Payment::where('status', 'verified')->sum('amount'),
            'outstanding' => (float) StudentInvoice::whereNotIn('status', ['draft', 'cancelled', 'paid'])->sum('outstanding_amount'),
            'adjustments' => (float) InvoiceAdjustment::sum('amount'),
            'credits' => (float) StudentCreditBalance::sum('balance'),
        ];

        $this->invoiceTypeRows = StudentInvoice::query()
            ->selectRaw('invoice_type, COUNT(*) as invoice_count, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(outstanding_amount) as outstanding_amount')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy('invoice_type')
            ->orderBy('invoice_type')
            ->get()
            ->map(fn (StudentInvoice $row) => [
                'invoice_type' => match($row->invoice_type) {
                    'tuition' => 'SPP / Uang Kuliah',
                    'registration' => 'Biaya Pendaftaran',
                    'exam' => 'Biaya Ujian Akhir',
                    default => str($row->invoice_type)->replace('_', ' ')->title()->toString()
                },
                'invoice_count' => $row->invoice_count,
                'total_amount' => (float) $row->total_amount,
                'paid_amount' => (float) $row->paid_amount,
                'outstanding_amount' => (float) $row->outstanding_amount,
            ])
            ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Laporan Rekapitulasi Keuangan',
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
        title="Laporan Rekapitulasi Keuangan"
        description="Analisis mendalam arus kas penerimaan, saldo piutang mahasiswa, penyesuaian tagihan, serta rekapitulasi per jenis tagihan studi."
        icon="chart-pie"
    >
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.financial.reports.export.csv') }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fas fa-file-csv"></i> <span>Export CSV</span>
            </a>
            <a href="{{ route('admin.financial.reports.export.xlsx') }}" class="btn btn-sm btn-outline-light fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fas fa-file-excel"></i> <span>Export Excel</span>
            </a>
            <a href="{{ route('admin.financial.reports.export.pdf') }}" target="_blank" class="btn btn-sm btn-outline-light fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fas fa-file-pdf"></i> <span>Cetak PDF</span>
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-double fs-6 text-success"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Penerimaan Terverifikasi</div>
                        <div class="fw-bold">{{ $this->money($stats['verified_payments']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-exclamation-circle fs-6 text-danger"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tunggakan Aktif</div>
                        <div class="fw-bold">{{ $this->money($stats['outstanding']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-sliders fs-6 text-info"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Penyesuaian</div>
                        <div class="fw-bold">{{ $this->money($stats['adjustments']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-wallet fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Deposit Mahasiswa</div>
                        <div class="fw-bold">{{ $this->money($stats['credits']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Eksekutif Keuangan</h4>
                <div class="text-muted small">Statistik singkat kondisi penerimaan dan kewajiban keuangan di lingkungan kampus.</div>
            </div>
            <div class="text-muted small">
                Klik tombol ekspor di atas untuk mengunduh laporan dalam format yang diinginkan.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Penerimaan Terverifikasi</span>
                            <i class="fa fa-check-double fs-4 text-success opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-success lh-1">{{ $this->money($stats['verified_payments']) }}</div>
                        <div class="text-muted small mt-2">Dana masuk sah</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Tunggakan Aktif</span>
                            <i class="fa fa-exclamation-circle fs-4 text-danger opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-danger lh-1">{{ $this->money($stats['outstanding']) }}</div>
                        <div class="text-muted small mt-2">Belum dilunasi</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Total Penyesuaian</span>
                            <i class="fa fa-sliders fs-4 text-info opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-dark lh-1">{{ $this->money($stats['adjustments']) }}</div>
                        <div class="text-muted small mt-2">Diskon & koreksi</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-medium">Deposit Mahasiswa</span>
                            <i class="fa fa-wallet fs-4 text-primary opacity-75"></i>
                        </div>
                        <div class="fs-5 fw-bold text-primary lh-1">{{ $this->money($stats['credits']) }}</div>
                        <div class="text-muted small mt-2">Akumulasi saldo lebih</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Rekapitulasi Tagihan Berdasarkan Jenis</h4>
                    <span class="text-muted small">Ringkasan total tagihan, jumlah penerimaan, dan sisa tunggakan per kategori biaya studi.</span>
                </div>
            </div>
        </div>
        <div class="table-responsive p-3">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 px-3">Jenis Tagihan</th>
                        <th class="py-3 px-3 text-end">Jumlah Tagihan</th>
                        <th class="py-3 px-3 text-end">Total Diterbitkan</th>
                        <th class="py-3 px-3 text-end">Sudah Terbayar</th>
                        <th class="py-3 px-3 text-end">Sisa Tunggakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoiceTypeRows as $row)
                        <tr>
                            <td class="px-3 fw-bold">{{ $row['invoice_type'] }}</td>
                            <td class="px-3 text-end">{{ number_format($row['invoice_count']) }}</td>
                            <td class="px-3 text-end fw-medium">{{ $this->money($row['total_amount']) }}</td>
                            <td class="px-3 text-end text-success fw-medium">{{ $this->money($row['paid_amount']) }}</td>
                            <td class="px-3 text-end fw-bold {{ $row['outstanding_amount'] > 0 ? 'text-danger' : 'text-success' }}">{{ $this->money($row['outstanding_amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-5">Belum ada data tagihan yang dapat dirangkum.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
