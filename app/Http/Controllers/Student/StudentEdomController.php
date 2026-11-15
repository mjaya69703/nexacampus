<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EdomResponse;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\EdomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Evaluasi Dosen oleh Mahasiswa (EDOM).
 *
 * Koreksi dari Blade lama:
 * - Target evaluasi hanya kelas yang masih aktif pada KRS (bukan seluruh
 *   detail KRS, termasuk kelas yang sudah dibatalkan).
 * - Halaman form memvalidasi bahwa kombinasi periode/kelas/dosen memang
 *   layak dievaluasi; sebelumnya siapa pun bisa membuka ID sembarang.
 * - Evaluasi yang sudah dikirim diarahkan kembali ke daftar, bukan
 *   menampilkan form yang pasti gagal saat submit.
 */
class StudentEdomController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $this->openPeriod();
        $targets = collect();

        if ($period !== null) {
            $targets = app(EdomService::class)
                ->eligibleTargetsForStudent($user, $period)
                ->values();
        }

        $rows = $targets->map(fn (array $target) => [
            'periodId' => $target['period_id'],
            'offeringId' => $target['course_offering_id'],
            'lecturerId' => $target['lecturer_profile_id'],
            'course' => $target['course'],
            'label' => $target['label'],
            'academicYear' => $target['academic_year'],
            'lecturer' => $target['lecturer'],
            'submittedAt' => $target['submitted_at'],
            'completed' => $target['completed'],
            'url' => route('student.edom.show', [
                'periodId' => $target['period_id'],
                'courseOfferingId' => $target['course_offering_id'],
                'lecturerProfileId' => $target['lecturer_profile_id'],
            ]),
        ])->values();

        return Inertia::render('Student/Edom/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Evaluasi Dosen'),
            'hasProfile' => $user->studentProfile()->exists(),
            'period' => $period === null ? null : [
                'id' => $period->id,
                'name' => $period->name,
                'startsAt' => $period->starts_at?->format('d M Y'),
                'endsAt' => $period->ends_at?->format('d M Y'),
            ],
            'targets' => $rows->all(),
            'stats' => [
                'total' => $rows->count(),
                'completed' => $rows->where('completed', true)->count(),
                'pending' => $rows->where('completed', false)->count(),
            ],
        ]);
    }

    public function show(Request $request, int $periodId, int $courseOfferingId, int $lecturerProfileId): Response|RedirectResponse
    {
        $user = $request->user();

        $period = EdomPeriod::query()->find($periodId);

        abort_unless($period !== null, 404, 'Periode evaluasi tidak ditemukan.');

        if (! $period->isOpen()) {
            return redirect()
                ->route('student.edom.index')
                ->with('error', 'Periode evaluasi ini sudah ditutup.');
        }

        $profile = $user->studentProfile()->first();

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $eligible = $this->isEligible($profile->id, $period, $courseOfferingId, $lecturerProfileId);

        abort_unless($eligible, 403, 'Evaluasi ini tidak tersedia untuk kelas Anda.');

        $alreadySubmitted = EdomResponse::query()
            ->where('edom_period_id', $period->id)
            ->where('course_offering_id', $courseOfferingId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('student_profile_id', $profile->id)
            ->exists();

        if ($alreadySubmitted) {
            return redirect()
                ->route('student.edom.index')
                ->with('info', 'Evaluasi untuk kelas dan dosen ini sudah dikirim.');
        }

        $offering = CourseOffering::query()->with('course')->findOrFail($courseOfferingId);
        $lecturer = LecturerProfile::query()->with('user')->findOrFail($lecturerProfileId);

        $questions = EdomQuestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EdomQuestion $question) => [
                'id' => $question->id,
                'category' => $question->category,
                'categoryLabel' => str((string) $question->category)->replace('_', ' ')->title()->toString(),
                'text' => $question->question_text,
                'answerType' => $question->answer_type,
                'isRequired' => (bool) $question->is_required,
            ])
            ->values()
            ->all();

        return Inertia::render('Student/Edom/Show', [
            'shell' => ShellProps::make($user, 'Academic', 'Isi Evaluasi Dosen'),
            'period' => ['id' => $period->id, 'name' => $period->name],
            'course' => [
                'id' => $offering->id,
                'name' => $offering->course?->name ?? '-',
                'code' => $offering->course?->code ?? '-',
                'label' => $offering->label,
            ],
            'lecturer' => [
                'id' => $lecturer->id,
                'name' => $lecturer->user?->name ?? '-',
            ],
            'questions' => $questions,
            'urls' => [
                'index' => route('student.edom.index'),
                'submit' => route('student.edom.submit', [
                    'periodId' => $period->id,
                    'courseOfferingId' => $courseOfferingId,
                    'lecturerProfileId' => $lecturerProfileId,
                ]),
            ],
        ]);
    }

    public function submit(Request $request, int $periodId, int $courseOfferingId, int $lecturerProfileId): RedirectResponse
    {
        $period = EdomPeriod::query()->find($periodId);

        abort_unless($period !== null, 404, 'Periode evaluasi tidak ditemukan.');

        $validated = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable'],
        ]);

        $answers = collect($validated['answers'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [(int) $key => $value])
            ->all();

        app(EdomService::class)->submit(
            $request->user(),
            $period,
            $courseOfferingId,
            $lecturerProfileId,
            $answers
        );

        return redirect()
            ->route('student.edom.index')
            ->with('success', 'Evaluasi berhasil dikirim. Terima kasih untuk masukannya.');
    }

    private function openPeriod(): ?EdomPeriod
    {
        $period = EdomPeriod::query()
            ->where('status', 'open')
            ->latest('starts_at')
            ->first();

        return $period !== null && $period->isOpen() ? $period : null;
    }

    private function isEligible(int $studentProfileId, EdomPeriod $period, int $courseOfferingId, int $lecturerProfileId): bool
    {
        $enrolled = StudyPlanDetail::query()
            ->officiallyEnrolled()
            ->where('course_offering_id', $courseOfferingId)
            ->whereHas('studyPlan', function ($query) use ($studentProfileId, $period) {
                $query->where('student_profile_id', $studentProfileId)
                    ->when($period->academic_year_id, fn ($nested) => $nested->where('academic_year_id', $period->academic_year_id));
            })
            ->exists();

        $assigned = CourseOfferingLecturer::query()
            ->where('course_offering_id', $courseOfferingId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->exists();

        return $enrolled && $assigned;
    }
}
