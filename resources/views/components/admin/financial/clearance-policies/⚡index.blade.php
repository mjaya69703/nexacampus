<?php

use App\Models\Financial\FinancialClearancePolicy;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => FinancialClearancePolicy::count(),
            'active' => FinancialClearancePolicy::where('is_active', true)->count(),
            'blocking' => FinancialClearancePolicy::where('mode', 'blocking')->where('is_active', true)->count(),
            'warning' => FinancialClearancePolicy::where('mode', 'warning')->where('is_active', true)->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Clearance Policies',
        ]);
    }
};
?>

<div>
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Policies</div>
                    <div class="h1 mb-0">{{ number_format($stats['total']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Active</div>
                    <div class="h1 mb-0 text-success">{{ number_format($stats['active']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Blocking</div>
                    <div class="h1 mb-0 text-danger">{{ number_format($stats['blocking']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader">Warning Only</div>
                    <div class="h1 mb-0 text-warning">{{ number_format($stats['warning']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Clearance Policies</h3>
                <small class="text-muted">Configure which overdue invoice types create warnings or academic access holds.</small>
            </div>
            <a href="{{ route('admin.financial.clearance-policies.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Create Policy
            </a>
        </div>
        <div class="card-body">
            <livewire:financial.clearance-policy-table />
        </div>
    </div>
</div>
