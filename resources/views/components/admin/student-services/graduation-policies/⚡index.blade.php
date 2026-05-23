<?php

use App\Models\StudentService\GraduationPolicy;
use Livewire\Component;

new class extends Component
{
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = [
            'total' => GraduationPolicy::count(),
            'active' => GraduationPolicy::where('is_active', true)->count(),
            'program_scoped' => GraduationPolicy::whereNotNull('study_program_id')->count(),
            'global' => GraduationPolicy::whereNull('study_program_id')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Aturan Yudisium',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Policies', 'value' => $summary['total'], 'color' => 'text-primary'], ['label' => 'Active', 'value' => $summary['active'], 'color' => 'text-success'], ['label' => 'Program Scoped', 'value' => $summary['program_scoped'], 'color' => 'text-info'], ['label' => 'Global', 'value' => $summary['global'], 'color' => 'text-warning']] as $card)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                        <div class="h2 mb-0 {{ $card['color'] }}">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Aturan Yudisium</h3>
                <small class="text-muted d-block mt-1">Configure yudisium eligibility using data that is already supported by the system.</small>
            </div>
            <a href="{{ route('admin.student-services.graduation-policies.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Buat Aturan
            </a>
        </div>
        <div class="card-body">
            <livewire:student-service.graduation-policy-table />
        </div>
    </div>
</div>
