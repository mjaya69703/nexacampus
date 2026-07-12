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

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Nilai Mahasiswa (KHS)"
        description="Informasi hasil studi, rincian bobot dan skor komponen evaluasi, serta status publikasi penilaian."
        icon="graduation-cap"
    >
        <div class="d-flex align-items-center gap-2">
            @activecan('student-grade.update')
                <a href="{{ route('admin.academic.student-grades.edit', ['id' => $studentGrade->id]) }}" class="btn btn-sm btn-light text-warning fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border">
                    <i class="fa fa-edit"></i> <span>Edit Penilaian</span>
                </a>
            @endactivecan
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2 border" wire:click="backToIndex">
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi Hasil Evaluasi</h4>
                            <div class="text-muted small">Mahasiswa, program studi, tahun akademik, dan mata kuliah yang dinilai.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Nama Mahasiswa</label>
                            <div class="fw-bold text-dark fs-6">{{ $studentGrade->studyPlanDetail?->studyPlan?->studentProfile?->user?->name ?? '-' }}</div>
                            <div class="small text-muted">NIM: {{ $studentGrade->studyPlanDetail?->studyPlan?->studentProfile?->nim ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Program Studi</label>
                            <div class="fw-semibold text-dark">{{ $studentGrade->studyPlanDetail?->studyPlan?->studentProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Tahun Akademik</label>
                            <div class="fw-bold text-primary fs-6">{{ $studentGrade->studyPlanDetail?->studyPlan?->academicYear?->name ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Mata Kuliah</label>
                            <div class="fw-semibold text-dark">
                                {{ $studentGrade->studyPlanDetail?->courseOffering?->course?->code ?? '-' }} -
                                {{ $studentGrade->studyPlanDetail?->courseOffering?->course?->name ?? '-' }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Status Lifecycle</label>
                            <div>
                                @php
                                    $statusBadge = match($studentGrade->grade_status) {
                                        'Draft' => 'bg-secondary',
                                        'Finalized' => 'bg-success',
                                        'Published' => 'bg-primary',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge rounded-pill px-3 py-2 {{ $statusBadge }}">{{ $studentGrade->grade_status ?? 'Draft' }}</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Status Kelulusan</label>
                            <div>
                                @if ($studentGrade->result_status)
                                    <span class="badge rounded-pill px-3 py-2 bg-info">{{ $studentGrade->result_status }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>

                        @if($studentGrade->notes)
                            <div class="col-12 mt-3">
                                <label class="text-muted small d-block mb-1">Catatan Tambahan</label>
                                <div class="p-3 bg-light rounded-3 text-dark border">{{ $studentGrade->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-list-check fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Komponen Penilaian</h4>
                            <div class="text-muted small">Daftar rincian skor evaluasi mahasiswa pada mata kuliah ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @if($studentGrade->components->count() > 0)
                        @php
                            $components = $studentGrade->components->sortBy('sort_order');
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-3" style="width: 50px;">No</th>
                                        <th class="py-3 px-3">Komponen</th>
                                        <th class="py-3 px-3 text-center">Bobot (%)</th>
                                        <th class="py-3 px-3 text-center">Skor</th>
                                        <th class="py-3 px-3 text-center">Urutan</th>
                                        <th class="py-3 px-3">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($components as $index => $component)
                                        <tr>
                                            <td class="px-3 fw-semibold text-muted">{{ $index + 1 }}</td>
                                            <td class="px-3 fw-bold text-dark">{{ $component->name }}</td>
                                            <td class="px-3 text-center fw-semibold text-primary">{{ $component->weight_percentage ? number_format($component->weight_percentage, 2).'%' : '-' }}</td>
                                            <td class="px-3 text-center fw-bold text-success">{{ $component->score ?? '-' }}</td>
                                            <td class="px-3 text-center">{{ $component->sort_order }}</td>
                                            <td class="px-3 text-muted small">{{ $component->notes ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2" class="text-end py-3 px-3 fw-bold">Total Bobot:</th>
                                        <th class="text-center py-3 px-3 fw-bold text-primary fs-6">{{ number_format($this->totalWeight, 2) }}%</th>
                                        <th colspan="3" class="py-3 px-3 fw-semibold text-muted">Sisa Bobot: <span class="badge {{ $this->remainingWeight == 0 ? 'bg-success' : 'bg-warning text-dark' }} px-2 py-1">{{ number_format($this->remainingWeight, 2) }}%</span></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info border-0 shadow-sm rounded-4 p-4 mb-0 d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                <i class="fa fa-info-circle fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Belum Ada Komponen Nilai</h6>
                                <div class="small">Belum ada rincian komponen penilaian untuk mahasiswa pada mata kuliah ini.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-award fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Ringkasan Nilai Akhir</h5>
                            <div class="text-muted small">Evaluasi & indeks prestasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Final Score</label>
                        <div class="fw-bold text-dark fs-4">{{ $studentGrade->final_score ?? '-' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Nilai Huruf (Letter Grade)</label>
                        <div class="fw-bold text-success fs-3">{{ $studentGrade->letter_grade ?? '-' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Bobot Angka (Grade Point)</label>
                        <div class="fw-bold text-primary fs-4">{{ $studentGrade->grade_point ?? '-' }}</div>
                    </div>

                    <hr class="text-muted opacity-25 my-3">

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Total Bobot Komponen</label>
                        <div class="fw-semibold text-dark">{{ number_format($this->totalWeight, 2) }}%</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Dinilai Pada (Graded At)</label>
                        <div class="fw-semibold text-dark small">{{ $studentGrade->graded_at ? $studentGrade->graded_at->format('d F Y H:i') : '-' }}</div>
                    </div>

                    <div>
                        <label class="text-muted small d-block mb-1">Dinilai Oleh (Graded By)</label>
                        <div class="fw-semibold text-dark small">{{ $studentGrade->gradedBy?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
