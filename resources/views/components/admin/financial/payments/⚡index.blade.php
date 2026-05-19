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
            'menus' => 'Financial',
            'pages' => 'Payments',
        ]);
    }
};
?>

<div>
    <x-alert />

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar bg-yellow-lt text-yellow"><i class="fas fa-clock"></i></span>
                    <div>
                        <div class="text-secondary">Pending</div>
                        <div class="h2 mb-0">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar bg-green-lt text-green"><i class="fas fa-check"></i></span>
                    <div>
                        <div class="text-secondary">Verified</div>
                        <div class="h2 mb-0">{{ $stats['verified'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar bg-red-lt text-red"><i class="fas fa-times"></i></span>
                    <div>
                        <div class="text-secondary">Rejected</div>
                        <div class="h2 mb-0">{{ $stats['rejected'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar bg-blue-lt text-blue"><i class="fas fa-coins"></i></span>
                    <div>
                        <div class="text-secondary">Verified Amount</div>
                        <div class="h4 mb-0">Rp {{ number_format($stats['verified_amount'], 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Payment Verification</h3>
                <small class="text-muted">Review bukti pembayaran mahasiswa dan update status invoice.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:financial.payment-table />
        </div>
    </div>
</div>
