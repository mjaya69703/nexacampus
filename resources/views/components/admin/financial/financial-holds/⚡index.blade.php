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
            'menus' => 'Financial',
            'pages' => 'Financial Holds',
        ]);
    }
};
?>

<div>
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Active Holds</div>
                    <div class="h1 mb-0">{{ number_format($stats['active']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Blocking Now</div>
                    <div class="h1 mb-0 text-danger">{{ number_format($stats['blocking']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Waived</div>
                    <div class="h1 mb-0 text-info">{{ number_format($stats['waived']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Released</div>
                    <div class="h1 mb-0 text-success">{{ number_format($stats['released']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Financial Holds</h3>
                <small class="text-muted">Review overdue financial clearance holds and grant temporary relief when needed.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:financial.financial-hold-table />
        </div>
    </div>
</div>
