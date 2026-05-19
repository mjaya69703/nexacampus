<?php

use App\Models\Financial\InvoiceSchedule;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'pending' => InvoiceSchedule::where('status', 'pending')->count(),
            'due' => InvoiceSchedule::where('status', 'pending')->where('is_active', true)->where('publish_at', '<=', now())->count(),
            'completed' => InvoiceSchedule::where('status', 'completed')->count(),
            'failed' => InvoiceSchedule::where('status', 'failed')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Invoice Schedules',
        ]);
    }
};
?>

<div>
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Pending</div><div class="h2 mb-0">{{ $stats['pending'] }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Due Now</div><div class="h2 mb-0 text-warning">{{ $stats['due'] }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Completed</div><div class="h2 mb-0 text-success">{{ $stats['completed'] }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Failed</div><div class="h2 mb-0 text-danger">{{ $stats['failed'] }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Invoice Schedules</h3>
                <small class="text-muted">Schedule invoice publishing so invoices appear automatically at the planned time.</small>
            </div>
            <a href="{{ route('admin.financial.invoice-schedules.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Create Schedule
            </a>
        </div>
        <div class="card-body">
            <livewire:financial.invoice-schedule-table />
        </div>
    </div>
</div>
