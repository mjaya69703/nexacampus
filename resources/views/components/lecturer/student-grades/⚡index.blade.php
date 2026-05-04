<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public array $gradeRows = [];
    public string $search = '';

    public function mount(): void
    {
        $this->loadRows();
    }

    public function updatedSearch(): void
    {
        $this->loadRows();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Student Grades',
        ]);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Published' => 'bg-green-lt text-green',
            'Finalized' => 'bg-blue-lt text-blue',
            'Draft' => 'bg-yellow-lt text-yellow',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    private function loadRows(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->gradeRows = [];

            return;
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            $this->gradeRows = [];

            return;
        }

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->pluck('course_offering_id');

        if ($offeringIds->isEmpty()) {
            $this->gradeRows = [];

            return;
        }

        $search = mb_strtolower(trim($this->search));

        $rows = StudyPlanDetail::query()
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'courseOffering.studyProgram',
                'studyPlan.studentProfile.user',
                'studentGrade.components',
            ])
            ->whereIn('course_offering_id', $offeringIds)
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                $student = $detail->studyPlan?->studentProfile;
                $offering = $detail->courseOffering;
                $grade = $detail->studentGrade;

                return [
                    'study_plan_detail_id' => $detail->id,
                    'nim' => $student?->nim ?? '-',
                    'student_name' => $student?->user?->name ?? '-',
                    'course' => ($offering?->course?->code ?? '-') . ' - ' . ($offering?->course?->name ?? '-'),
                    'class' => $offering?->label ?? '-',
                    'academic_year' => $offering?->academicYear?->name ?? '-',
                    'study_program' => $offering?->studyProgram?->name ?? '-',
                    'final_score' => $grade?->final_score,
                    'letter_grade' => $grade?->letter_grade ?? '-',
                    'grade_point' => $grade?->grade_point,
                    'grade_status' => $grade?->grade_status ?? 'Draft',
                    'component_count' => $grade?->components?->count() ?? 0,
                ];
            });

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search) {
                $text = mb_strtolower(implode(' ', [
                    $row['nim'],
                    $row['student_name'],
                    $row['course'],
                    $row['class'],
                    $row['study_program'],
                ]));

                return str_contains($text, $search);
            });
        }

        $this->gradeRows = $rows
            ->sortBy(['course', 'student_name'])
            ->values()
            ->all();
    }
};
?>

@push('styles')
    <style>
        .lecturer-grade-list-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card lecturer-grade-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-1">Daftar Nilai Mahasiswa</h3>
                <div class="text-secondary small">Kelola draft dan finalisasi nilai untuk seluruh kelas yang Anda ampu.</div>
            </div>
            <span class="text-secondary small">Total {{ number_format(count($gradeRows)) }} baris</span>
        </div>

        <div class="card-body border-bottom">
            <input
                type="text"
                class="form-control"
                placeholder="Cari mahasiswa, mata kuliah, kelas, atau program studi"
                wire:model.live.debounce.300ms="search"
            >
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Mata Kuliah</th>
                        <th>Kelas</th>
                        <th>Nilai Akhir</th>
                        <th>Huruf</th>
                        <th>Point</th>
                        <th>Status</th>
                        <th>Komponen</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gradeRows as $row)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row['student_name'] }}</div>
                                <div class="text-secondary small">{{ $row['nim'] }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $row['course'] }}</div>
                                <div class="text-secondary small">{{ $row['study_program'] }} • {{ $row['academic_year'] }}</div>
                            </td>
                            <td>{{ $row['class'] }}</td>
                            <td>{{ $row['final_score'] !== null ? number_format((float) $row['final_score'], 2) : '-' }}</td>
                            <td>{{ $row['letter_grade'] }}</td>
                            <td>{{ $row['grade_point'] !== null ? number_format((float) $row['grade_point'], 2) : '-' }}</td>
                            <td>
                                <span class="badge {{ $this->statusBadgeClass($row['grade_status']) }}">{{ $row['grade_status'] }}</span>
                            </td>
                            <td>{{ $row['component_count'] }}</td>
                            <td class="text-end">
                                <a href="{{ route('lecturer.student-grades.edit', ['id' => $row['study_plan_detail_id']]) }}" class="btn btn-primary btn-sm">
                                    Kelola Nilai
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-secondary py-4">Belum ada data nilai untuk dosen ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
