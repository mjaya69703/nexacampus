<?php

use App\Models\Academic\AcademicAdvisorAssignment;
use Livewire\Component;

new class extends Component {
    public AcademicAdvisorAssignment $assignment;

    public function mount($id): void
    {
        $this->assignment = AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'lecturerProfile.user', 'academicYear'])
            ->findOrFail($id);
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.academic.academic-advisor-assignments.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Detail Assignment Dosen PA',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Assignment Dosen PA"
        description="Rincian informasi penugasan bimbingan akademik, profil mahasiswa bimbingan, dosen wali (PA), dan masa berlaku penugasan."
        icon="chalkboard-teacher"
    >
        <div class="d-flex align-items-center gap-2">
            @activecan('academic-advisor-assignment.update')
                <a href="{{ route('admin.academic.academic-advisor-assignments.edit', ['id' => $assignment->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border">
                    <i class="fa fa-edit"></i> <span>Edit Assignment</span>
                </a>
            @endactivecan
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border" wire:click="goBack">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
        </div>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-user-graduate fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi Mahasiswa & Dosen Wali</h4>
                            <div class="text-muted small">Relasi bimbingan akademik yang terdaftar dalam sistem.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border h-100">
                                <label class="text-muted small d-block mb-1 fw-semibold"><i class="fa fa-user me-1 text-primary"></i> Mahasiswa Bimbingan</label>
                                <div class="fw-bold text-dark fs-6">{{ $assignment->studentProfile?->user?->name ?? '-' }}</div>
                                <div class="small text-muted mt-1">NIM: <span class="fw-semibold text-dark">{{ $assignment->studentProfile?->nim ?? '-' }}</span></div>
                                <div class="small text-muted mt-1">Prodi: <span class="fw-semibold text-dark">{{ $assignment->studentProfile?->studyProgram?->name ?? '-' }}</span></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border h-100">
                                <label class="text-muted small d-block mb-1 fw-semibold"><i class="fa fa-user-tie me-1 text-primary"></i> Dosen Pembimbing Akademik</label>
                                <div class="fw-bold text-dark fs-6">{{ $assignment->lecturerProfile?->user?->name ?? '-' }}</div>
                                <div class="small text-muted mt-1">NIDN: <span class="fw-semibold text-dark">{{ $assignment->lecturerProfile?->nidn ?? '-' }}</span></div>
                                <div class="small text-muted mt-1">NIP: <span class="fw-semibold text-dark">{{ $assignment->lecturerProfile?->nip ?? '-' }}</span></div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="text-muted small d-block mb-1 fw-semibold">Catatan Penugasan</label>
                            <div class="p-3 bg-light rounded-3 text-dark border">{{ $assignment->notes ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-calendar-check fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Periode & Status</h5>
                            <div class="text-muted small">Waktu berlaku assignment.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Status Penugasan</label>
                        <div>
                            @if ($assignment->is_active)
                                <span class="badge rounded-pill bg-success px-3 py-2">Aktif</span>
                            @else
                                <span class="badge rounded-pill bg-secondary px-3 py-2">Nonaktif</span>
                            @endif
                        </div>
                    </div>

                    <hr class="text-muted opacity-25 my-3">

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Tahun Akademik</label>
                        <div class="fw-bold text-primary fs-6">{{ $assignment->academicYear?->name ?? 'Umum (Semua Tahun)' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Tanggal Mulai</label>
                        <div class="fw-semibold text-dark">{{ $assignment->start_date?->format('d F Y') ?? '-' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Tanggal Selesai</label>
                        <div class="fw-semibold text-dark">{{ $assignment->end_date?->format('d F Y') ?? '-' }}</div>
                    </div>

                    <hr class="text-muted opacity-25 my-3">

                    <div class="small text-muted">
                        Dibuat pada: <strong class="text-dark">{{ $assignment->created_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
