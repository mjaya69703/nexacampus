<?php

use App\Models\Academic\StudyPlan;
use Livewire\Component;

new class extends Component {
    public StudyPlan $studyPlan;

    public function mount($id): void
    {
        $this->studyPlan = StudyPlan::with([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'studentRegistration',
            'approvedBy',
            'details.courseOffering.course',
        ])->findOrFail($id);
    }

    public function backToIndex(): void
    {
        $this->redirectRoute('admin.academic.study-plans.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Detail KRS',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Info Header KRS</h5>
                <div>
                    @can('study-plan.update')
                        <a href="{{ route('admin.academic.study-plans.edit', ['id' => $studyPlan->id]) }}" class="btn btn-warning ">
                            <i class="fas fa-pencil me-1"></i> Edit
                        </a>
                    @endcan
                    <button class="btn btn-secondary " wire:click="backToIndex">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Mahasiswa</label>
                        <div class="h6 mb-0">{{ $studyPlan->studentProfile?->user?->name ?? '-' }}</div>
                        <small class="text-muted">NIM: {{ $studyPlan->studentProfile?->nim ?? '-' }}</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Prodi</label>
                        <div class="h6 mb-0">{{ $studyPlan->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label text-muted">Tahun Akademik</label>
                        <div class="h6 mb-0">{{ $studyPlan->academicYear?->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label text-muted">Semester</label>
                        <div class="h6 mb-0">{{ $studyPlan->semester_no ?? '-' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            @php
                                $statusBadge = match($studyPlan->status) {
                                    'Draft' => 'bg-secondary',
                                    'Submitted' => 'bg-info',
                                    'Approved' => 'bg-success',
                                    'Rejected' => 'bg-danger',
                                    'Cancelled' => 'bg-dark',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $studyPlan->status }}</span>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Student Registration</label>
                        <div class="h6 mb-0">{{ $studyPlan->student_registration_id ? '#'.$studyPlan->student_registration_id : '-' }}</div>
                    </div>

                    @if ($studyPlan->notes)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Catatan</label>
                            <div class="p-2  rounded">{{ $studyPlan->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Mata Kuliah (KRS)</h5>
            </div>
            <div class="card-body">
                @if ($studyPlan->details->count() > 0)
                    @php
                        $sortedDetails = $studyPlan->details->sortBy(fn($detail) => [
                            $detail->courseOffering?->semester_no ?? 999,
                            $detail->courseOffering?->course?->name ?? ''
                        ]);
                        $totalSks = (int) $studyPlan->details->sum('credits');
                    @endphp

                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th>Semester</th>
                                    <th>SKS</th>
                                    <th>Status</th>
                                    <th>Repeat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sortedDetails as $index => $detail)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $detail->courseOffering?->course?->code ?? '-' }}</td>
                                        <td>{{ $detail->courseOffering?->course?->name ?? '-' }}</td>
                                        <td>{{ $detail->courseOffering?->label ?? '-' }}</td>
                                        <td>{{ $detail->courseOffering?->semester_no ?? '-' }}</td>
                                        <td>{{ $detail->credits ?? '-' }}</td>
                                        <td><span class="badge bg-info">{{ $detail->status }}</span></td>
                                        <td>
                                            @if ($detail->is_repeat)
                                                <span class="badge bg-warning">Ya</span>
                                            @else
                                                <span class="badge bg-secondary">Tidak</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="5" class="text-end">Total SKS</th>
                                    <th>{{ $totalSks }}</th>
                                    <th colspan="2">{{ $studyPlan->details->count() }} Mata Kuliah</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> Belum ada mata kuliah pada KRS ini.
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Status Submit / Approval</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label text-muted">Submitted At</label>
                        <div>{{ $studyPlan->submitted_at ? $studyPlan->submitted_at->format('d M Y H:i') : '-' }}</div>
                    </div>

                    <div class="col-md-4 mb-2">
                        <label class="form-label text-muted">Approved At</label>
                        <div>{{ $studyPlan->approved_at ? $studyPlan->approved_at->format('d M Y H:i') : '-' }}</div>
                    </div>

                    <div class="col-md-4 mb-2">
                        <label class="form-label text-muted">Approved By</label>
                        <div>{{ $studyPlan->approvedBy?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
