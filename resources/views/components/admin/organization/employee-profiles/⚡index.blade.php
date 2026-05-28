<?php

use App\Models\Organization\EmployeeProfile;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => EmployeeProfile::count(),
            'active' => EmployeeProfile::where('is_active', true)->count(),
            'inactive' => EmployeeProfile::where('is_active', false)->count(),
            'positions' => EmployeeProfile::query()->withCount('activePositionAssignments')->get()->sum('active_position_assignments_count'),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Pegawai',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Pegawai', 'value' => $this->stats()['total'], 'color' => 'text-primary'], ['label' => 'Aktif', 'value' => $this->stats()['active'], 'color' => 'text-success'], ['label' => 'Nonaktif', 'value' => $this->stats()['inactive'], 'color' => 'text-warning'], ['label' => 'Jabatan Aktif', 'value' => $this->stats()['positions'], 'color' => 'text-info']] as $card)
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
                <h3 class="card-title mb-0">Pegawai</h3>
                <small class="text-muted">Kelola profil kepegawaian umum untuk dosen, tendik, staff, dan admin operasional.</small>
            </div>
            <a href="{{ route('admin.organization.employee-profiles.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Tambah Pegawai
            </a>
        </div>
        <div class="card-body">
            <livewire:organization.employee-profile-table />
        </div>
    </div>
</div>
