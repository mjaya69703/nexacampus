<?php

use App\Models\Academic\StudentGrade;
use App\Support\StudentGradeCalculator;
use Livewire\Component;

new class extends Component {
    public StudentGrade $studentGrade;

    public function mount($id): void
    {
        $this->studentGrade = StudentGrade::with([
            'studyPlanDetail.studyPlan.studentProfile.user',
            'studyPlanDetail.studyPlan.studentProfile.studyProgram',
            'studyPlanDetail.studyPlan.academicYear',
            'studyPlanDetail.courseOffering.course',
            'gradedBy',
            'components',
        ])->findOrFail($id);
    }

    public function getTotalWeightProperty(): float
    {
        $calculator = new StudentGradeCalculator();

        return $calculator->calculateTotalWeight($this->studentGrade);
    }

    public function getRemainingWeightProperty(): float
    {
        return round(100 - $this->totalWeight, 2);
    }

    public function backToIndex(): void
    {
        $this->redirectRoute('admin.academic.student-grades.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Detail Nilai Mahasiswa',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Info Header Nilai</h5>
                <div>
                    @can('student-grade.update')
                        <a href="{{ route('admin.academic.student-grades.edit', ['id' => $studentGrade->id]) }}" class="btn btn-warning">
                            <i class="fas fa-pencil me-1"></i> Edit
                        </a>
                    @endcan
                    <button class="btn btn-secondary" wire:click="backToIndex">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Mahasiswa</label>
                        <div class="h6 mb-0">{{ $studentGrade->studyPlanDetail?->studyPlan?->studentProfile?->user?->name ?? '-' }}</div>
                        <small class="text-muted">NIM: {{ $studentGrade->studyPlanDetail?->studyPlan?->studentProfile?->nim ?? '-' }}</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Prodi</label>
                        <div class="h6 mb-0">{{ $studentGrade->studyPlanDetail?->studyPlan?->studentProfile?->studyProgram?->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tahun Akademik</label>
                        <div class="h6 mb-0">{{ $studentGrade->studyPlanDetail?->studyPlan?->academicYear?->name ?? '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Mata Kuliah</label>
                        <div class="h6 mb-0">
                            {{ $studentGrade->studyPlanDetail?->courseOffering?->course?->code ?? '-' }} -
                            {{ $studentGrade->studyPlanDetail?->courseOffering?->course?->name ?? '-' }}
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Status Lifecycle</label>
                        <div>
                            @php
                                $statusBadge = match($studentGrade->grade_status) {
                                    'Draft' => 'bg-secondary',
                                    'Finalized' => 'bg-success',
                                    'Published' => 'bg-primary',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $studentGrade->grade_status ?? 'Draft' }}</span>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Total Bobot</label>
                        <div class="h6 mb-0">{{ number_format($this->totalWeight, 2) }}%</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Sisa Bobot</label>
                        <div class="h6 mb-0">{{ number_format($this->remainingWeight, 2) }}%</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Final Score</label>
                        <div class="h6 mb-0">{{ $studentGrade->final_score ?? '-' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Letter Grade</label>
                        <div class="h6 mb-0">{{ $studentGrade->letter_grade ?? '-' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Grade Point</label>
                        <div class="h6 mb-0">{{ $studentGrade->grade_point ?? '-' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label text-muted">Result Status</label>
                        <div>
                            @if ($studentGrade->result_status)
                                <span class="badge bg-info">{{ $studentGrade->result_status }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Graded At</label>
                        <div class="h6 mb-0">{{ $studentGrade->graded_at ? $studentGrade->graded_at->format('d M Y H:i') : '-' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Graded By</label>
                        <div class="h6 mb-0">{{ $studentGrade->gradedBy?->name ?? '-' }}</div>
                    </div>

                    @if($studentGrade->notes)
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Catatan</label>
                            <div class="p-2  rounded">{{ $studentGrade->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Komponen Nilai</h5>
            </div>
            <div class="card-body">
                @if($studentGrade->components->count() > 0)
                    @php
                        $components = $studentGrade->components->sortBy('sort_order');
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Komponen</th>
                                    <th>Bobot (%)</th>
                                    <th>Skor</th>
                                    <th>Urutan</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($components as $index => $component)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $component->name }}</td>
                                        <td>{{ $component->weight_percentage ?? '-' }}</td>
                                        <td>{{ $component->score ?? '-' }}</td>
                                        <td>{{ $component->sort_order }}</td>
                                        <td>{{ $component->notes ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2" class="text-end">Total Bobot</th>
                                    <th>{{ number_format($this->totalWeight, 2) }}%</th>
                                    <th colspan="3">Sisa {{ number_format($this->remainingWeight, 2) }}%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> Belum ada komponen nilai untuk data ini.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
