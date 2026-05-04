<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public int $offeringId;
    public array $classInfo = [];
    public array $students = [];

    public function mount(int $offeringId): void
    {
        $this->offeringId = $offeringId;

        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            abort(403);
        }

        $assignment = CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->offeringId)
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram'])
            ->first();

        if (! $assignment || ! $assignment->courseOffering) {
            abort(404);
        }

        $offering = $assignment->courseOffering;

        $this->classInfo = [
            'id' => $offering->id,
            'course' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-'),
            'class' => $offering->label ?? '-',
            'class_code' => $offering->code ?? '-',
            'academic_year' => $offering->academicYear?->name ?? '-',
            'study_program' => $offering->studyProgram?->name ?? '-',
        ];

        $this->students = StudyPlanDetail::query()
            ->with(['studyPlan.studentProfile.user', 'studyPlan'])
            ->where('course_offering_id', $this->offeringId)
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                $studentProfile = $detail->studyPlan?->studentProfile;

                return [
                    'student_profile_id' => $studentProfile?->id,
                    'nim' => $studentProfile?->nim ?? '-',
                    'name' => $studentProfile?->user?->name ?? '-',
                    'study_plan_status' => $detail->studyPlan?->status ?? '-',
                    'is_repeat' => $detail->is_repeat ? 'Ya' : 'Tidak',
                ];
            })
            ->filter(fn (array $student) => $student['student_profile_id'])
            ->unique('student_profile_id')
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Students List',
        ]);
    }
};
?>

@push('styles')
    <style>
        .lecturer-students-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card lecturer-students-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-1">Daftar Mahasiswa Kelas</h3>
                <div class="text-secondary small">{{ $classInfo['course'] }}</div>
            </div>
            <a href="{{ route('lecturer.course-offerings.show', ['id' => $offeringId]) }}" class="btn btn-outline-secondary btn-sm">
                Kembali ke Detail Kelas
            </a>
        </div>
        <div class="card-body">
            <div class="text-secondary small">Kelas {{ $classInfo['class'] }} ({{ $classInfo['class_code'] }}) • {{ $classInfo['academic_year'] }}</div>
            <div class="text-secondary small mt-1">Program Studi: {{ $classInfo['study_program'] }}</div>
        </div>
    </div>

    <div class="card lecturer-students-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Mahasiswa Terdaftar</h3>
            <span class="text-secondary small">Total {{ number_format(count($students)) }} mahasiswa</span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Status KRS</th>
                        <th>Mengulang</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>{{ $student['nim'] }}</td>
                            <td>{{ $student['name'] }}</td>
                            <td>{{ $student['study_plan_status'] }}</td>
                            <td>{{ $student['is_repeat'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-4">Belum ada mahasiswa terdaftar pada kelas ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
