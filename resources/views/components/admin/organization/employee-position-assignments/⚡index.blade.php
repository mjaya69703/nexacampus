<?php

use App\Models\Organization\EmployeePositionAssignment;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => EmployeePositionAssignment::count(),
            'active' => EmployeePositionAssignment::where('is_active', true)->count(),
            'primary' => EmployeePositionAssignment::where('is_primary', true)->count(),
            'scoped' => EmployeePositionAssignment::where(function ($query) {
                $query->whereNotNull('faculty_id')
                    ->orWhereNotNull('study_program_id')
                    ->orWhereNotNull('work_unit_id');
            })->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Penugasan Jabatan',
        ]);
    }
};
?>

<div>
    <div class="row mb-3">
        @foreach ([['label' => 'Penugasan', 'value' => $this->stats()['total'], 'color' => 'text-primary'], ['label' => 'Aktif', 'value' => $this->stats()['active'], 'color' => 'text-success'], ['label' => 'Utama', 'value' => $this->stats()['primary'], 'color' => 'text-warning'], ['label' => 'Dengan Scope', 'value' => $this->stats()['scoped'], 'color' => 'text-info']] as $card)
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
                <h3 class="card-title mb-0">Penugasan Jabatan</h3>
                <small class="text-muted">Assign pegawai ke jabatan seperti Kaprodi, Dekan, Kepala Unit, atau Staff Unit beserta scope datanya.</small>
            </div>
            <a href="{{ route('admin.organization.employee-position-assignments.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Tambah Penugasan
            </a>
        </div>
        <div class="card-body">
            <livewire:organization.employee-position-assignment-table />
        </div>
    </div>
</div>
