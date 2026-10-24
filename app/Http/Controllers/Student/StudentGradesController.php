<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\GradeAppeal;
use App\Models\Academic\StudentGrade;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar nilai mahasiswa beserta komponen penilaian dan status banding.
 *
 * Koreksi dari Blade lama: ringkasan memakai jumlah mata kuliah unik dan
 * total SKS dari detail KRS, bukan dua penghitung yang sama-sama berisi
 * jumlah baris nilai terpublikasi.
 */
class StudentGradesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        if (! $profile) {
            return Inertia::render('Student/Grades/Index', [
                'shell' => ShellProps::make($user, 'Academic', 'Nilai Saya'),
                'hasProfile' => false,
                'rows' => [],
                'stats' => ['courses' => 0, 'credits' => 0, 'avgPoint' => null, 'activeAppeals' => 0],
                'urls' => $this->urls(),
            ]);
        }

        $grades = StudentGrade::query()
            ->with([
                'studyPlanDetail.studyPlan.academicYear',
                'studyPlanDetail.courseOffering.course',
                'components' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->whereHas('studyPlanDetail.studyPlan', fn ($query) => $query->where('student_profile_id', $profile->id))
            ->where('grade_status', 'Published')
            ->orderByDesc('graded_at')
            ->orderByDesc('id')
            ->get();

        $appeals = GradeAppeal::query()
            ->where('student_profile_id', $profile->id)
            ->whereIn('student_grade_id', $grades->pluck('id'))
            ->latest('submitted_at')
            ->latest('id')
            ->get()
            ->groupBy('student_grade_id');

        $rows = $grades
            ->map(function (StudentGrade $grade) use ($appeals) {
                $detail = $grade->studyPlanDetail;
                $studyPlan = $detail?->studyPlan;
                $offering = $detail?->courseOffering;
                $course = $offering?->course;
                $latestAppeal = $appeals->get($grade->id)?->first();
                $components = $grade->components->map(fn ($component) => [
                    'name' => $component->name,
                    'weight' => $component->weight_percentage !== null ? (float) $component->weight_percentage : null,
                    'score' => $component->score !== null ? (float) $component->score : null,
                    'notes' => $component->notes,
                ])->values()->all();

                return [
                    'id' => $grade->id,
                    'courseCode' => $course?->code ?? '-',
                    'courseName' => $course?->name ?? ($offering?->label ?? '-'),
                    'classLabel' => $offering?->label ?? '-',
                    'academicYear' => $studyPlan?->academicYear?->name ?? '-',
                    'semesterNo' => $studyPlan?->semester_no,
                    'credits' => (int) ($detail?->credits ?? 0),
                    'finalScore' => $grade->final_score !== null ? (float) $grade->final_score : null,
                    'letterGrade' => $grade->letter_grade,
                    'gradePoint' => $grade->grade_point !== null ? (float) $grade->grade_point : null,
                    'resultStatus' => $grade->result_status,
                    'appealStatus' => $latestAppeal?->status,
                    'hasActiveAppeal' => in_array($latestAppeal?->status, ['submitted', 'under_review'], true),
                    'components' => $components,
                    'totalWeight' => round(collect($components)->sum('weight'), 2),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Student/Grades/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Nilai Saya'),
            'hasProfile' => true,
            'rows' => $rows,
            'stats' => [
                'courses' => count($rows),
                'credits' => (int) $grades->sum(fn (StudentGrade $grade) => (int) ($grade->studyPlanDetail?->credits ?? 0)),
                'avgPoint' => $grades->whereNotNull('grade_point')->avg('grade_point'),
                'activeAppeals' => $appeals->flatten()->whereIn('status', ['submitted', 'under_review'])->count(),
            ],
            'urls' => $this->urls(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'index' => route('student.grades.index'),
            'appeals' => route('student.grade-appeals.index'),
            'transcript' => route('student.transcript.index'),
            'progress' => route('student.progress.index'),
        ];
    }
}
