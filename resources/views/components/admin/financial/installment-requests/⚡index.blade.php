<?php

use App\Models\Financial\InvoiceInstallmentRequest;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'submitted' => InvoiceInstallmentRequest::where('status', 'submitted')->count(),
            'approved' => InvoiceInstallmentRequest::where('status', 'approved')->count(),
            'rejected' => InvoiceInstallmentRequest::where('status', 'rejected')->count(),
            'average_tenor' => round((float) InvoiceInstallmentRequest::avg('requested_tenor'), 1),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Installment Requests',
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
                    <span class="avatar bg-yellow-lt text-yellow"><i class="fas fa-hourglass-half"></i></span>
                    <div>
                        <div class="text-secondary">Submitted</div>
                        <div class="h2 mb-0">{{ $stats['submitted'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar bg-green-lt text-green"><i class="fas fa-check-circle"></i></span>
                    <div>
                        <div class="text-secondary">Approved</div>
                        <div class="h2 mb-0">{{ $stats['approved'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar bg-red-lt text-red"><i class="fas fa-ban"></i></span>
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
                    <span class="avatar bg-blue-lt text-blue"><i class="fas fa-calendar-days"></i></span>
                    <div>
                        <div class="text-secondary">Avg Tenor</div>
                        <div class="h2 mb-0">{{ $stats['average_tenor'] ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Installment Requests</h3>
                <small class="text-muted">Approve atau reject pengajuan cicilan mahasiswa.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:financial.installment-request-table />
        </div>
    </div>
</div>
