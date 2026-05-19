<?php

use App\Models\Financial\StudentScholarship;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'total' => StudentScholarship::count(),
            'active' => StudentScholarship::where('status', 'active')->count(),
            'completed' => StudentScholarship::where('status', 'completed')->count(),
            'revoked' => StudentScholarship::where('status', 'revoked')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Student Scholarships',
        ]);
    }
};
?>

<div>
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Assignments</div><div class="h1 mb-0">{{ number_format($stats['total']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Active</div><div class="h1 mb-0 text-success">{{ number_format($stats['active']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Completed</div><div class="h1 mb-0 text-info">{{ number_format($stats['completed']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Revoked</div><div class="h1 mb-0 text-danger">{{ number_format($stats['revoked']) }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Student Scholarships</h3>
                <small class="text-muted">Assign active scholarship rules to eligible students.</small>
            </div>
            <a href="{{ route('admin.financial.student-scholarships.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Assign Scholarship
            </a>
        </div>
        <div class="card-body">
            <livewire:financial.student-scholarship-table />
        </div>
    </div>
</div>
