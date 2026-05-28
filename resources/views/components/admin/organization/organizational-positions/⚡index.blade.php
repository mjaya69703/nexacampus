<?php

use App\Models\Organization\OrganizationalPosition;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => OrganizationalPosition::count(),
            'active' => OrganizationalPosition::where('is_active', true)->count(),
            'scoped' => OrganizationalPosition::where('scope_type', '!=', 'none')->count(),
            'assignments' => OrganizationalPosition::query()->withCount('assignments')->get()->sum('assignments_count'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Jabatan',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Jabatan', 'value' => $this->stats()['total'], 'color' => 'text-primary'], ['label' => 'Aktif', 'value' => $this->stats()['active'], 'color' => 'text-success'], ['label' => 'Berscope', 'value' => $this->stats()['scoped'], 'color' => 'text-warning'], ['label' => 'Penugasan', 'value' => $this->stats()['assignments'], 'color' => 'text-info']] as $card)
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
                <h3 class="card-title mb-0">Jabatan</h3>
                <small class="text-muted">Definisikan jabatan dan jenis scope data yang dikelola oleh jabatan tersebut.</small>
            </div>
            <a href="{{ route('admin.organization.organizational-positions.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Tambah Jabatan
            </a>
        </div>
        <div class="card-body">
            <livewire:organization.organizational-position-table />
        </div>
    </div>
</div>
