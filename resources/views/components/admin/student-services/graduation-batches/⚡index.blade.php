<?php

use App\Models\StudentService\GraduationBatch;
use Livewire\Component;

new class extends Component
{
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = [
            'total' => GraduationBatch::count(),
            'open' => GraduationBatch::where('status', 'open')->count(),
            'review' => GraduationBatch::where('status', 'review')->count(),
            'finalized' => GraduationBatch::where('status', 'finalized')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Batch Yudisium',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="row mb-3">
        @foreach ([['label' => 'Batch', 'value' => $summary['total'], 'color' => 'text-primary'], ['label' => 'Dibuka', 'value' => $summary['open'], 'color' => 'text-success'], ['label' => 'Review', 'value' => $summary['review'], 'color' => 'text-info'], ['label' => 'Final', 'value' => $summary['finalized'], 'color' => 'text-warning']] as $card)
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
                <h3 class="card-title mb-0">Batch Yudisium</h3>
                <small class="text-muted">Kelola batch resmi yudisium, tanggal penetapan, dan bulk finalize.</small>
            </div>
            <a href="{{ route('admin.student-services.graduation-batches.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Buat Batch
            </a>
        </div>
        <div class="card-body">
            <livewire:student-service.graduation-batch-table />
        </div>
    </div>
</div>
