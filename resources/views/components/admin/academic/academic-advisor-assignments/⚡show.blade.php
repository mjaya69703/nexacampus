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

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Assignment Dosen PA</h5>
                <div>
                    @can('academic-advisor-assignment.update')
                        <a href="{{ route('admin.academic.academic-advisor-assignments.edit', ['id' => $assignment->id]) }}" class="btn btn-warning ">
                            <i class="fas fa-pencil me-1"></i> Edit
                        </a>
                    @endcan
                    <button class="btn btn-secondary " wire:click="goBack">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Mahasiswa</label>
                        <div class="h6 mb-0">{{ $assignment->studentProfile?->user?->name ?? '-' }}</div>
                        <small class="text-muted">NIM: {{ $assignment->studentProfile?->nim ?? '-' }}</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Program Studi</label>
                        <div class="h6 mb-0">{{ $assignment->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Dosen PA</label>
                        <div class="h6 mb-0">{{ $assignment->lecturerProfile?->user?->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tahun Akademik</label>
                        <div class="h6 mb-0">{{ $assignment->academicYear?->name ?? 'Umum (Semua Tahun)' }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tanggal Mulai</label>
                        <div class="h6 mb-0">{{ $assignment->start_date?->format('d M Y') ?? '-' }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tanggal Selesai</label>
                        <div class="h6 mb-0">{{ $assignment->end_date?->format('d M Y') ?? '-' }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            @if ($assignment->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Nonaktif</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Catatan</label>
                        <div class="p-2 bg-light rounded">{{ $assignment->notes ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
