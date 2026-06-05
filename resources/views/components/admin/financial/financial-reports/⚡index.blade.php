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
                'invoice_type' => str($row->invoice_type)->replace('_', ' ')->title()->toString(),
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
            'menus' => 'Financial',
            'pages' => 'Financial Reports',
        ]);
    }

    public function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
};
?>

<div>
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Verified Payments</div><div class="h2 mb-0 text-success">{{ $this->money($stats['verified_payments']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Outstanding</div><div class="h2 mb-0 text-danger">{{ $this->money($stats['outstanding']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Net Adjustments</div><div class="h2 mb-0">{{ $this->money($stats['adjustments']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Student Credits</div><div class="h2 mb-0 text-primary">{{ $this->money($stats['credits']) }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Invoice Summary By Type</h3>
                <small class="text-muted">Ringkasan invoice, pembayaran, penyesuaian, dan saldo kredit mahasiswa.</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.financial.reports.export.csv') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-file-csv me-1"></i> CSV
                </a>
                <a href="{{ route('admin.financial.reports.export.xlsx') }}" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </a>
                <a href="{{ route('admin.financial.reports.export.pdf') }}" target="_blank" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Invoice Type</th>
                        <th class="text-end">Invoices</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoiceTypeRows as $row)
                        <tr>
                            <td>{{ $row['invoice_type'] }}</td>
                            <td class="text-end">{{ number_format($row['invoice_count']) }}</td>
                            <td class="text-end">{{ $this->money($row['total_amount']) }}</td>
                            <td class="text-end">{{ $this->money($row['paid_amount']) }}</td>
                            <td class="text-end">{{ $this->money($row['outstanding_amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada invoice yang bisa dirangkum.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
