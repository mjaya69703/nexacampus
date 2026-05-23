<?php

use App\Models\StudentService\StudentTransferRequest;
use Livewire\Component;

new class extends Component
{
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = [
            'total' => StudentTransferRequest::count(),
            'pending' => StudentTransferRequest::whereIn('status', ['submitted', 'under_review', 'revision_requested', 'approved_pending_payment'])->count(),
            'approved' => StudentTransferRequest::where('status', 'approved')->count(),
            'applied' => StudentTransferRequest::where('status', 'applied')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Pengajuan Pindah',
        ]);
    }
};
?>

<div>
    <x-alert />

    <div class="row mb-3">
        @foreach ([['label' => 'Requests', 'value' => $summary['total'] ?? 0, 'class' => 'text-primary'], ['label' => 'Pending', 'value' => $summary['pending'] ?? 0, 'class' => 'text-warning'], ['label' => 'Approved', 'value' => $summary['approved'] ?? 0, 'class' => 'text-info'], ['label' => 'Applied', 'value' => $summary['applied'] ?? 0, 'class' => 'text-success']] as $card)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                        <div class="h2 mb-0 {{ $card['class'] }}">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Pengajuan Pindah</h3>
                <small class="text-muted">Review student internal transfer requests and apply approved transfer changes.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:student-service.student-transfer-request-table />
        </div>
    </div>
</div>
