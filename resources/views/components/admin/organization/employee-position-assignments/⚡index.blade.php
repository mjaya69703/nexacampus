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
    <x-alert />

    <x-admin.organization.header
        title="Daftar Penugasan Jabatan Pegawai"
        description="Atur penempatan dan penugasan jabatan struktural maupun fungsional (seperti Rektor, Dekan, Kaprodi, atau Kepala Unit Kerja) lengkap dengan ruang lingkup wewenang akademis dan kepegawaian."
        icon="user-tie"
    >
        @activecan('employee-position-assignment.create')
            <a href="{{ route('admin.organization.employee-position-assignments.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Penugasan</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-tie fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Penugasan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jabatan Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-star fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Jabatan Utama</div>
                        <div class="fw-bold">{{ number_format($this->stats()['primary']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Scope Terikat</div>
                        <div class="fw-bold">{{ number_format($this->stats()['scoped']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Matriks Penugasan & Wewenang</h4>
                    <span class="text-muted small">Kelola riwayat penetapan SK, masa berlaku penugasan, serta cakupan lingkup data fakultas atau prodi.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.employee-position-assignment-table />
        </div>
    </div>
</div>
