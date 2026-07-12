<?php

use Livewire\Component;
use App\Models\Academic\AcademicAdvisorAssignment;

new class extends Component {
    public int $totalAssignments = 0;

    public int $activeAssignments = 0;

    public int $inactiveAssignments = 0;

    public int $totalStudents = 0;

    public function mount(): void
    {
        $this->totalAssignments = AcademicAdvisorAssignment::count();
        $this->activeAssignments = AcademicAdvisorAssignment::where('is_active', true)->count();
        $this->inactiveAssignments = AcademicAdvisorAssignment::where('is_active', false)->count();
        $this->totalStudents = AcademicAdvisorAssignment::distinct('student_profile_id')->count('student_profile_id');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Penugasan Dosen PA',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.academic.header
        title="Penugasan Dosen Wali (PA)"
        description="Kelola dan pantau relasi bimbingan akademik antara dosen pembimbing akademik (PA) dengan mahasiswa aktif per tahun akademik."
        icon="chalkboard-teacher"
    >
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @include('templates.import.academic.grid-actions')
            @activecan('academic-advisor-assignment.create')
                <a href="{{ route('admin.academic.academic-advisor-assignments.create') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-plus-circle"></i>
                    <span>Tambah Assignment</span>
                </a>
            @endactivecan
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total</div>
                        <div class="fw-bold">{{ number_format($totalAssignments) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Aktif</div>
                        <div class="fw-bold">{{ number_format($activeAssignments) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-times-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Nonaktif</div>
                        <div class="fw-bold">{{ number_format($inactiveAssignments) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-graduate fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Mahasiswa Dibimbing</div>
                        <div class="fw-bold">{{ number_format($totalStudents) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.academic.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Penugasan Dosen PA</h4>
                <div class="text-muted small">Statistik singkat penugasan berdasarkan status aktif dan jumlah mahasiswa bimbingan.</div>
            </div>
            <div class="text-muted small">Gunakan filter tabel untuk penelusuran per tahun akademik, prodi, atau dosen PA.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Assignment</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($totalAssignments) }}</div>
                            <i class="fa fa-list fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ number_format($activeAssignments) }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Nonaktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-secondary lh-1">{{ number_format($inactiveAssignments) }}</div>
                            <i class="fa fa-times-circle fs-4 text-secondary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Mahasiswa Dibimbing</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ number_format($totalStudents) }}</div>
                            <i class="fa fa-user-graduate fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Daftar Penugasan Dosen Wali</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk melihat pembimbing per prodi, tahun akademik, atau status aktif penugasan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:academic.academic-advisor-assignment-table />
        </div>
    </div>
</div>
