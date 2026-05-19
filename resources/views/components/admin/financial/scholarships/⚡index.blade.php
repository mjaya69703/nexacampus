<?php

use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentScholarship;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => Scholarship::count(),
            'active' => Scholarship::where('is_active', true)->count(),
            'assignments' => StudentScholarship::where('status', 'active')->count(),
            'fixed' => Scholarship::where('discount_type', 'fixed')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Scholarships',
        ]);
    }
};
?>

<div>
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm"><div class="card-body"><div class="subheader">Scholarships</div><div class="h1 mb-0">{{ number_format($stats['total']) }}</div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm"><div class="card-body"><div class="subheader">Active</div><div class="h1 mb-0 text-success">{{ number_format($stats['active']) }}</div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm"><div class="card-body"><div class="subheader">Active Assignments</div><div class="h1 mb-0 text-primary">{{ number_format($stats['assignments']) }}</div></div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm"><div class="card-body"><div class="subheader">Fixed Amount</div><div class="h1 mb-0 text-warning">{{ number_format($stats['fixed']) }}</div></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Scholarships</h3>
                <small class="text-muted">Manage scholarship templates before assigning them to students.</small>
            </div>
            <a href="{{ route('admin.financial.scholarships.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Create Scholarship
            </a>
        </div>
        <div class="card-body">
            <livewire:financial.scholarship-table />
        </div>
    </div>
</div>
