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
            'menus' => 'Financial',
            'pages' => 'Invoice Adjustments',
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
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Adjustments</div><div class="h1 mb-0">{{ number_format($stats['count']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Discount/Waiver</div><div class="h2 mb-0 text-success">{{ $this->money($stats['discounts']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Penalties</div><div class="h2 mb-0 text-warning">{{ $this->money($stats['penalties']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Corrections</div><div class="h2 mb-0">{{ $this->money($stats['corrections']) }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Invoice Adjustments</h3>
                <small class="text-muted">Audit trail for post-payment corrections, discounts, scholarships, waivers, and gentle penalties.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:financial.invoice-adjustment-table />
        </div>
    </div>
</div>
