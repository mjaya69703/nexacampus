<?php

use App\Models\StudentService\GraduationApplication;
use Livewire\Component;

new class extends Component
{
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = [
            'total' => GraduationApplication::count(),
            'pending' => GraduationApplication::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested'])->count(),
            'approved' => GraduationApplication::where('status', 'approved')->count(),
            'finalized' => GraduationApplication::where('status', 'finalized')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Pengajuan Yudisium',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Total', 'value' => $summary['total'], 'color' => 'text-primary'], ['label' => 'Menunggu', 'value' => $summary['pending'], 'color' => 'text-warning'], ['label' => 'Disetujui', 'value' => $summary['approved'], 'color' => 'text-info'], ['label' => 'Final', 'value' => $summary['finalized'], 'color' => 'text-success']] as $card)
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
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Pengajuan Yudisium</h3>
                <small class="text-muted">Review pengajuan yudisium mahasiswa.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:student-service.graduation-application-table />
        </div>
    </div>
</div>
