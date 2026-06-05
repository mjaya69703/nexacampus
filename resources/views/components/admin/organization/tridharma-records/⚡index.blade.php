<?php

use App\Models\Organization\TridharmaRecord;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => TridharmaRecord::count(),
            'approval' => TridharmaRecord::where('status', 'in_approval')->count(),
            'verified' => TridharmaRecord::where('is_verified', true)->count(),
            'completed' => TridharmaRecord::where('status', 'completed')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Tridharma',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Total', 'value' => $this->stats()['total'], 'color' => 'text-primary'], ['label' => 'Approval', 'value' => $this->stats()['approval'], 'color' => 'text-warning'], ['label' => 'Terverifikasi', 'value' => $this->stats()['verified'], 'color' => 'text-success'], ['label' => 'Selesai', 'value' => $this->stats()['completed'], 'color' => 'text-info']] as $card)
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted text-uppercase">{{ $card['label'] }}</small>
                        <h2 class="{{ $card['color'] }} mb-0">{{ $card['value'] }}</h2>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Tridharma</h3>
                <small class="text-muted">Kelola penelitian, pengabdian masyarakat, publikasi, luaran, budget, dan evidence.</small>
            </div>
            <a href="{{ route('admin.organization.tridharma-records.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Tambah Record
            </a>
        </div>
        <div class="card-body">
            <livewire:organization.tridharma-record-table />
        </div>
    </div>
</div>
