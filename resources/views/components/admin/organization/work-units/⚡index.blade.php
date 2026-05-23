<?php

use App\Models\Organization\WorkUnit;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => WorkUnit::count(),
            'active' => WorkUnit::where('is_active', true)->count(),
            'inactive' => WorkUnit::where('is_active', false)->count(),
            'members' => WorkUnit::query()->withCount('activeMembers')->get()->sum('active_members_count'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Organisasi',
            'pages' => 'Unit Kerja',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Unit Kerja', 'value' => $this->stats()['total'], 'color' => 'text-primary'], ['label' => 'Aktif', 'value' => $this->stats()['active'], 'color' => 'text-success'], ['label' => 'Nonaktif', 'value' => $this->stats()['inactive'], 'color' => 'text-warning'], ['label' => 'Anggota Aktif', 'value' => $this->stats()['members'], 'color' => 'text-info']] as $card)
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
                <h3 class="card-title mb-0">Unit Kerja</h3>
                <small class="text-muted">Kelola unit operasional kampus untuk assignment tiket, approval, dan pembatasan data staff.</small>
            </div>
            <a href="{{ route('admin.organization.work-units.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Buat Unit
            </a>
        </div>
        <div class="card-body">
            <livewire:organization.work-unit-table />
        </div>
    </div>
</div>
