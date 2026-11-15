<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transkrip akademik lengkap mahasiswa.
 *
 * Koreksi dari Blade lama: IPK memakai hasil studi terbaru bila tersedia
 * dan jatuh ke perhitungan berbobot dari entri transkrip (hanya entri yang
 * dihitung dalam IPK) ketika hasil studi belum terbentuk.
 */
class StudentTranscriptController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->with(['studyProgram', 'user'])->first();

        if (! $profile) {
            return Inertia::render('Student/Transcript/Index', [
                'shell' => ShellProps::make($user, 'Academic', 'Transkrip Akademik'),
                'hasProfile' => false,
                'student' => null,
                'results' => [],
                'entries' => [],
                'summary' => ['totalCourses' => 0, 'totalCredits' => 0, 'passedCredits' => 0, 'cumulativeGpa' => null],
                'urls' => $this->urls(),
            ]);
        }

        $results = StudyResult::query()
            ->with('academicYear')
            ->where('student_profile_id', $profile->id)
            ->orderBy('semester_no')
            ->orderBy('id')
            ->get();

        $entries = TranscriptEntry::query()
            ->with(['course', 'academicYear'])
            ->where('student_profile_id', $profile->id)
            ->orderBy('semester_no')
            ->orderBy('course_id')
            ->orderByDesc('grade_point')
            ->get();

        $latestResult = $results->sortByDesc('semester_no')->first();
        $passedCredits = (int) $entries->where('result_status', 'Passed')->sum('credits');

        return Inertia::render('Student/Transcript/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Transkrip Akademik'),
            'hasProfile' => true,
            'student' => [
                'name' => $profile->user?->name ?? $user->name,
                'nim' => $profile->nim ?? '-',
                'program' => $profile->studyProgram?->name ?? '-',
                'academicStatus' => $profile->academic_status ?? '-',
                'semester' => $profile->current_semester,
            ],
            'results' => $results
                ->map(fn (StudyResult $result) => [
                    'id' => $result->id,
                    'academicYear' => $result->academicYear?->name ?? '-',
                    'semesterNo' => $result->semester_no,
                    'totalCourses' => (int) ($result->total_courses ?? 0),
                    'creditsTaken' => (int) ($result->total_credits_taken ?? 0),
                    'creditsPassed' => (int) ($result->total_credits_passed ?? 0),
                    'semesterGpa' => $result->semester_gpa !== null ? (float) $result->semester_gpa : null,
                    'cumulativeGpa' => $result->cumulative_gpa !== null ? (float) $result->cumulative_gpa : null,
                    'status' => $result->status ?? '-',
                ])
                ->values()
                ->all(),
            'entries' => $entries
                ->map(fn (TranscriptEntry $entry) => [
                    'id' => $entry->id,
                    'courseCode' => $entry->course?->code ?? '-',
                    'courseName' => $entry->course?->name ?? '-',
                    'academicYear' => $entry->academicYear?->name ?? '-',
                    'semesterNo' => $entry->semester_no,
                    'credits' => (int) ($entry->credits ?? 0),
                    'finalScore' => $entry->final_score !== null ? (float) $entry->final_score : null,
                    'letterGrade' => $entry->letter_grade,
                    'gradePoint' => $entry->grade_point !== null ? (float) $entry->grade_point : null,
                    'resultStatus' => $entry->result_status,
                    'countedInGpa' => (bool) $entry->is_counted_in_gpa,
                ])
                ->values()
                ->all(),
            'summary' => [
                'totalCourses' => $entries->count(),
                'totalCredits' => (int) $entries->sum('credits'),
                'passedCredits' => $passedCredits,
                'cumulativeGpa' => $latestResult?->cumulative_gpa !== null
                    ? (float) $latestResult->cumulative_gpa
                    : $this->computedGpa($entries),
            ],
            'urls' => $this->urls(),
        ]);
    }

    private function computedGpa($entries): ?float
    {
        $counted = $entries
            ->where('is_counted_in_gpa', true)
            ->filter(fn (TranscriptEntry $entry) => $entry->grade_point !== null && (int) ($entry->credits ?? 0) > 0);

        $credits = (int) $counted->sum('credits');

        if ($credits === 0) {
            return null;
        }

        $points = $counted->sum(fn (TranscriptEntry $entry) => (float) $entry->grade_point * (int) $entry->credits);

        return round($points / $credits, 2);
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'index' => route('student.transcript.index'),
            'grades' => route('student.grades.index'),
            'progress' => route('student.progress.index'),
        ];
    }
}
