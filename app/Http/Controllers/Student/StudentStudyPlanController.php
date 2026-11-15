<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\TranscriptEntry;
use App\Support\Academic\StudyPlanEnrollmentService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kartu Rencana Studi (KRS) mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - Kredit dan daftar mata kuliah hanya menghitung detail aktif
 *   (Draft/Taken); detail Dropped/Cancelled tidak lagi menggelembungkan
 *   total SKS maupun menghalangi pemilihan ulang.
 * - Seluruh perubahan KRS (tambah, hapus, submit, revisi) tunduk pada
 *   periode akademik yang aktif, bukan hanya submit.
 * - Batas 24 SKS yang selama ini hanya menjadi teks di UI kini ditegakkan
 *   di server melalui StudyPlanEnrollmentService.
 * - Registrasi berstatus Cancelled tidak dianggap sebagai registrasi aktif.
 */
class StudentStudyPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'retake' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $showRetake = ($validated['retake'] ?? '0') === '1';

        $user = $request->user();
        $profile = $user->studentProfile()->with('studyProgram.faculty')->first();
        $activeYear = $this->activeYear();

        $registration = $profile && $activeYear
            ? $this->registration($profile, $activeYear)
            : null;

        $isRegistrationApproved = $registration?->registration_status === 'Approved';
        $isAcademicallyActive = $registration?->academic_status === 'Aktif';
        $hasAccess = $isRegistrationApproved && $isAcademicallyActive;

        $studyPlanPeriod = $activeYear
            ? $this->activePeriod($activeYear, ['Study Plan'])
            : null;
        $revisionPeriod = $activeYear
            ? $this->activePeriod($activeYear, ['Study Plan Revision'])
            : null;
        $changePeriodOpen = (bool) ($studyPlanPeriod ?? $revisionPeriod);

        $plan = null;
        $details = collect();

        if ($profile && $activeYear && $hasAccess) {
            $plan = StudyPlan::query()
                ->where('student_profile_id', $profile->id)
                ->where('academic_year_id', $activeYear->id)
                ->first();

            if ($plan && in_array($plan->status, ['Draft', 'Rejected'], true)) {
                $plan = $this->syncPlan($plan, $registration, $user->id);
            }

            if ($plan) {
                $details = StudyPlanDetail::query()
                    ->with([
                        'courseOffering.course',
                        'courseOffering.courseSchedules' => fn ($query) => $query->where('is_active', true),
                    ])
                    ->where('study_plan_id', $plan->id)
                    ->whereIn('status', ['Draft', 'Taken'])
                    ->orderBy('id')
                    ->get();
            }
        }

        $canModify = $hasAccess
            && $changePeriodOpen
            && (! $plan || in_array($plan->status, ['Draft', 'Rejected'], true));

        $availableOfferings = $profile && $activeYear && $hasAccess && $registration?->semester_no
            ? $this->availableOfferings($profile, $activeYear, $registration, $details, $showRetake)
            : [];

        return Inertia::render('Student/StudyPlan/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Study Plan'),
            'hasProfile' => (bool) $profile,
            'hasActiveYear' => (bool) $activeYear,
            'hasActiveRegistration' => (bool) $registration,
            'isRegistrationApproved' => $isRegistrationApproved,
            'isAcademicallyActive' => $isAcademicallyActive,
            'student' => $profile ? [
                'name' => $user->name,
                'nim' => $profile->nim,
                'program' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->studyProgram?->faculty?->name ?? '-',
                'semester' => $profile->current_semester,
            ] : null,
            'year' => $activeYear ? ['id' => $activeYear->id, 'name' => $activeYear->name] : null,
            'registration' => $registration ? [
                'id' => $registration->id,
                'semesterNo' => $registration->semester_no,
                'status' => $registration->registration_status,
                'academicStatus' => $registration->academic_status,
            ] : null,
            'period' => [
                'open' => (bool) $studyPlanPeriod,
                'revisionOpen' => (bool) $revisionPeriod,
                'changeOpen' => $changePeriodOpen,
                'name' => $studyPlanPeriod?->name,
                'range' => $this->periodRange($studyPlanPeriod),
                'revisionName' => $revisionPeriod?->name,
                'revisionRange' => $this->periodRange($revisionPeriod),
            ],
            'plan' => [
                'id' => $plan?->id,
                'status' => $plan?->status ?? '-',
                'submittedAt' => $plan?->submitted_at?->format('d M Y H:i'),
                'totalCredits' => (int) $details->sum('credits'),
                'maxCredits' => StudyPlanEnrollmentService::MAX_CREDITS,
                'canModify' => $canModify,
                'canSubmit' => $hasAccess
                    && $changePeriodOpen
                    && $plan !== null
                    && in_array($plan->status, ['Draft', 'Rejected'], true)
                    && $details->isNotEmpty(),
                'canRevise' => $hasAccess
                    && $changePeriodOpen
                    && $plan?->status === 'Submitted',
            ],
            'selectedCourses' => $this->selectedCourseRows($details),
            'availableOfferings' => $availableOfferings,
            'showRetake' => $showRetake,
            'urls' => $this->urls(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->mutationContext($request);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $validated = $request->validate([
            'course_offering_id' => ['required', 'integer', 'exists:course_offerings,id'],
        ]);

        ['profile' => $profile, 'year' => $year, 'registration' => $registration, 'plan' => $plan] = $context;

        if (! $this->changePeriodOpen($year)) {
            return back()->with('error', 'Periode pengisian atau revisi KRS belum aktif.');
        }

        $plan = $this->ensurePlan($profile, $year, $registration, $request->user()->id, $plan);

        if (! in_array($plan->status, ['Draft', 'Rejected'], true)) {
            return back()->with('error', 'KRS yang sudah disubmit tidak dapat diubah.');
        }

        $result = app(StudyPlanEnrollmentService::class)->addOffering(
            studyPlan: $plan,
            student: $profile,
            offeringId: (int) $validated['course_offering_id'],
            registrationSemesterNo: (int) $registration->semester_no,
            userId: $request->user()->id,
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Request $request, StudyPlanDetail $detail): RedirectResponse
    {
        $context = $this->mutationContext($request);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['plan' => $plan] = $context;

        if (! $plan || (int) $detail->study_plan_id !== (int) $plan->id) {
            return back()->with('error', 'Detail KRS tidak ditemukan.');
        }

        if (! $this->changePeriodOpen($context['year'])) {
            return back()->with('error', 'Periode pengisian atau revisi KRS belum aktif.');
        }

        if (! in_array($plan->status, ['Draft', 'Rejected'], true)) {
            return back()->with('error', 'KRS yang sudah disubmit tidak dapat diubah.');
        }

        if (! in_array($detail->status, ['Draft', 'Taken'], true)) {
            return back()->with('error', 'Mata kuliah ini tidak lagi aktif pada KRS.');
        }

        $detail->update(['deleted_by' => $request->user()->id]);
        $detail->delete();

        return back()->with('success', 'Mata kuliah berhasil dihapus dari KRS.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $context = $this->mutationContext($request);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['year' => $year, 'plan' => $plan] = $context;

        if (! $plan) {
            return back()->with('error', 'KRS belum memiliki mata kuliah untuk diajukan.');
        }

        if (! $this->changePeriodOpen($year)) {
            return back()->with('error', 'Periode submit KRS belum aktif.');
        }

        if (! in_array($plan->status, ['Draft', 'Rejected'], true)) {
            return back()->with('error', 'Status KRS saat ini tidak dapat disubmit.');
        }

        $detailsCount = StudyPlanDetail::query()
            ->where('study_plan_id', $plan->id)
            ->whereIn('status', ['Draft', 'Taken'])
            ->count();

        if ($detailsCount < 1) {
            return back()->with('error', 'Minimal pilih 1 mata kuliah sebelum submit KRS.');
        }

        $plan->update([
            'status' => 'Submitted',
            'submitted_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'KRS berhasil disubmit dan menunggu persetujuan.');
    }

    public function revise(Request $request): RedirectResponse
    {
        $context = $this->mutationContext($request);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['year' => $year, 'plan' => $plan] = $context;

        if (! $plan) {
            return back()->with('error', 'KRS belum tersedia.');
        }

        if (! $this->changePeriodOpen($year)) {
            return back()->with('error', 'Periode pengisian atau revisi KRS belum aktif.');
        }

        if ($plan->status !== 'Submitted') {
            return back()->with('error', 'Status KRS saat ini tidak bisa direvisi.');
        }

        $plan->update([
            'status' => 'Draft',
            'submitted_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'KRS kembali ke Draft. Silakan lakukan revisi.');
    }

    public function comparison(Request $request): Response
    {
        $validated = $request->validate([
            'left' => ['nullable', 'integer', 'min:1'],
            'right' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $profile = $user->studentProfile()->with('studyProgram.faculty')->first();

        if (! $profile) {
            return Inertia::render('Student/StudyPlan/Comparison', [
                'shell' => ShellProps::make($user, 'Academic', 'Perbandingan KRS'),
                'hasProfile' => false,
                'student' => null,
                'plans' => [],
                'left' => null,
                'right' => null,
                'comparison' => null,
                'urls' => $this->urls(),
            ]);
        }

        $plans = StudyPlan::query()
            ->where('student_profile_id', $profile->id)
            ->with([
                'academicYear',
                'details' => fn ($query) => $query->whereIn('status', ['Draft', 'Taken']),
                'details.courseOffering.course',
            ])
            ->orderByDesc('semester_no')
            ->orderByDesc('id')
            ->get();

        $options = $plans
            ->map(fn (StudyPlan $plan) => [
                'id' => $plan->id,
                'label' => trim(($plan->academicYear?->name ?? 'Tahun akademik').' - Semester '.($plan->semester_no ?? '-')),
                'status' => $plan->status,
            ])
            ->values()
            ->all();

        $validIds = $plans->pluck('id')->map(fn ($id) => (int) $id)->all();

        $leftId = (int) ($validated['left'] ?? 0);
        $rightId = (int) ($validated['right'] ?? 0);

        // Nilai default mengikuti Blade lama: rencana sebelumnya di kiri dan
        // rencana terbaru di kanan. ID yang tidak dimiliki mahasiswa jatuh
        // kembali ke default agar halaman tidak menampilkan perbandingan palsu.
        if ($leftId === 0 || ! in_array($leftId, $validIds, true)) {
            $leftId = (int) ($plans->get(1)?->id ?? $plans->first()?->id ?? 0);
        }

        if ($rightId === 0 || ! in_array($rightId, $validIds, true)) {
            $rightId = (int) ($plans->first()?->id ?? 0);
        }

        $leftPlan = $plans->firstWhere('id', $leftId);
        $rightPlan = $plans->firstWhere('id', $rightId);

        $left = $leftPlan ? $this->summarizePlan($leftPlan) : $this->emptyPlan();
        $right = $rightPlan ? $this->summarizePlan($rightPlan) : $this->emptyPlan();

        return Inertia::render('Student/StudyPlan/Comparison', [
            'shell' => ShellProps::make($user, 'Academic', 'Perbandingan KRS'),
            'hasProfile' => true,
            'student' => [
                'name' => $user->name,
                'nim' => $profile->nim,
                'program' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->studyProgram?->faculty?->name ?? '-',
                'semester' => $profile->current_semester,
            ],
            'plans' => $options,
            'left' => $left,
            'right' => $right,
            'comparison' => $this->comparePlans($left, $right),
            'urls' => $this->urls(),
        ]);
    }

    /**
     * @return array{profile: StudentProfile, year: AcademicYear, registration: StudentRegistration, plan: ?StudyPlan}|RedirectResponse
     */
    private function mutationContext(Request $request): array|RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();

        if (! $profile) {
            return back()->with('error', 'Profil mahasiswa belum tersedia.');
        }

        $year = $this->activeYear();

        if (! $year) {
            return back()->with('error', 'Tahun akademik aktif belum tersedia.');
        }

        $registration = $this->registration($profile, $year);

        if (! $registration) {
            return back()->with('error', 'Registrasi semester untuk tahun akademik aktif belum tersedia.');
        }

        if ($registration->registration_status !== 'Approved') {
            return back()->with('error', 'KRS hanya bisa diproses jika registrasi semester sudah Approved.');
        }

        if ($registration->academic_status !== 'Aktif') {
            return back()->with('error', 'KRS hanya bisa diproses jika status akademik semester ini Aktif.');
        }

        if (! $registration->semester_no) {
            return back()->with('error', 'Semester registrasi belum tersedia.');
        }

        $plan = StudyPlan::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year_id', $year->id)
            ->first();

        return compact('profile', 'year', 'registration', 'plan');
    }

    private function ensurePlan(
        StudentProfile $profile,
        AcademicYear $year,
        StudentRegistration $registration,
        ?int $userId,
        ?StudyPlan $plan = null,
    ): StudyPlan {
        if (! $plan) {
            return StudyPlan::create([
                'student_profile_id' => $profile->id,
                'academic_year_id' => $year->id,
                'student_registration_id' => $registration->id,
                'semester_no' => $registration->semester_no,
                'status' => 'Draft',
                'created_by' => $userId,
            ]);
        }

        return $this->syncPlan($plan, $registration, $userId);
    }

    private function syncPlan(StudyPlan $plan, StudentRegistration $registration, ?int $userId): StudyPlan
    {
        $needsSync = (int) $plan->student_registration_id !== (int) $registration->id
            || ($registration->semester_no !== null && (int) $plan->semester_no !== (int) $registration->semester_no);

        if ($needsSync) {
            $plan->update([
                'student_registration_id' => $registration->id,
                'semester_no' => $registration->semester_no ?? $plan->semester_no,
                'updated_by' => $userId,
            ]);

            $plan->refresh();
        }

        return $plan;
    }

    private function activeYear(): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();
    }

    private function registration(StudentProfile $profile, AcademicYear $year): ?StudentRegistration
    {
        return StudentRegistration::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year_id', $year->id)
            ->where('registration_status', '!=', 'Cancelled')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int, string>  $types
     */
    private function activePeriod(AcademicYear $year, array $types): ?AcademicPeriod
    {
        $now = now();

        return AcademicPeriod::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('type', $types)
            ->where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->first();
    }

    private function changePeriodOpen(AcademicYear $year): bool
    {
        return $this->activePeriod($year, ['Study Plan', 'Study Plan Revision']) !== null;
    }

    private function periodRange(?AcademicPeriod $period): ?string
    {
        if (! $period) {
            return null;
        }

        return $period->start_at?->format('d M Y H:i').' - '.$period->end_at?->format('d M Y H:i');
    }

    /**
     * @param  Collection<int, StudyPlanDetail>  $details
     * @return array<int, array<string, mixed>>
     */
    private function selectedCourseRows(Collection $details): array
    {
        return $details
            ->map(function (StudyPlanDetail $detail) {
                $offering = $detail->courseOffering;
                $course = $offering?->course;

                return [
                    'detailId' => $detail->id,
                    'code' => $course?->code ?? $offering?->code ?? '-',
                    'name' => $course?->name ?? ($offering?->label ?? '-'),
                    'credits' => (int) ($detail->credits ?? $offering?->credits ?? $course?->credits ?? 0),
                    'semesterNo' => $offering?->semester_no,
                    'label' => $offering?->label,
                    'deliveryMode' => $offering?->delivery_mode,
                    'isRepeat' => (bool) $detail->is_repeat,
                    'status' => $detail->status,
                    'schedule' => $this->scheduleSummary($offering),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudyPlanDetail>  $selectedDetails
     * @return array<int, array<string, mixed>>
     */
    private function availableOfferings(
        StudentProfile $profile,
        AcademicYear $year,
        StudentRegistration $registration,
        Collection $selectedDetails,
        bool $showRetake,
    ): array {
        $semesterNo = (int) $registration->semester_no;

        $selectedOfferingIds = $selectedDetails
            ->pluck('course_offering_id')
            ->filter()
            ->values();

        $retakeCourseIds = TranscriptEntry::query()
            ->where('student_profile_id', $profile->id)
            ->whereIn('result_status', ['Failed', 'Incomplete'])
            ->pluck('course_id')
            ->unique()
            ->values();

        $query = CourseOffering::query()
            ->with([
                'course',
                'courseSchedules' => fn ($builder) => $builder->where('is_active', true),
            ])
            ->where('academic_year_id', $year->id)
            ->where('study_program_id', $profile->study_program_id)
            ->where('status', 'Open')
            ->where('semester_no', '<=', $semesterNo)
            ->whereNotIn('id', $selectedOfferingIds)
            ->orderBy('semester_no')
            ->orderBy('id');

        $query->where(function ($builder) use ($semesterNo, $showRetake, $retakeCourseIds) {
            $builder->where('semester_no', $semesterNo);

            if ($showRetake && $retakeCourseIds->isNotEmpty()) {
                $builder->orWhere(function ($retakeQuery) use ($semesterNo, $retakeCourseIds) {
                    $retakeQuery
                        ->where('semester_no', '<', $semesterNo)
                        ->whereIn('course_id', $retakeCourseIds->all());
                });
            }
        });

        return $query
            ->limit(100)
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'code' => $offering->course?->code ?? $offering->code ?? '-',
                'name' => $offering->course?->name ?? '-',
                'credits' => (int) ($offering->credits ?? $offering->course?->credits ?? 0),
                'semesterNo' => $offering->semester_no,
                'label' => $offering->label,
                'deliveryMode' => $offering->delivery_mode,
                'isRetake' => ($offering->semester_no ?? 0) < $semesterNo,
                'schedule' => $this->scheduleSummary($offering),
            ])
            ->values()
            ->all();
    }

    private function scheduleSummary(?CourseOffering $offering): ?string
    {
        if (! $offering) {
            return null;
        }

        $schedules = $offering->relationLoaded('courseSchedules')
            ? $offering->courseSchedules
            : $offering->courseSchedules()->where('is_active', true)->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        return $schedules
            ->map(fn ($schedule) => trim(
                $this->dayLabel($schedule->day_of_week).' '.
                $this->timeLabel($schedule->start_time).'–'.
                $this->timeLabel($schedule->end_time),
            ))
            ->unique()
            ->implode(', ');
    }

    private function timeLabel(mixed $value): string
    {
        return $value instanceof \DateTimeInterface
            ? $value->format('H:i')
            : substr((string) $value, 0, 5);
    }

    private function dayLabel(?string $day): string
    {
        $days = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $days[$day] ?? (string) $day;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizePlan(StudyPlan $plan): array
    {
        $courses = $this->courseRows($plan);

        return [
            'id' => $plan->id,
            'title' => $plan->academicYear?->name ?? 'Tahun akademik',
            'semester' => $plan->semester_no ?? '-',
            'status' => $plan->status,
            'submittedAt' => $plan->submitted_at?->format('d M Y H:i') ?? '-',
            'approvedAt' => $plan->approved_at?->format('d M Y H:i') ?? '-',
            'totalCourses' => $courses->count(),
            'totalCredits' => (int) $courses->sum('credits'),
            'requiredCount' => $courses->where('isRequired', true)->count(),
            'repeatCount' => $courses->where('isRepeat', true)->count(),
            'courses' => $courses->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function courseRows(StudyPlan $plan): Collection
    {
        return $plan->details
            ->filter(fn (StudyPlanDetail $detail) => filled($detail->courseOffering?->course))
            ->map(function (StudyPlanDetail $detail) {
                $offering = $detail->courseOffering;
                $course = $offering?->course;

                return [
                    'courseId' => $course?->id,
                    'code' => $course?->code ?? $offering?->code ?? '-',
                    'name' => $course?->name ?? '-',
                    'credits' => (int) ($detail->credits ?? $offering?->credits ?? $course?->credits ?? 0),
                    'semester' => $offering?->semester_no ?? $course?->semester_recommendation ?? '-',
                    'status' => $detail->status,
                    'isRequired' => (bool) ($offering?->is_required ?? true),
                    'isRepeat' => (bool) $detail->is_repeat,
                    'label' => $offering?->label ?? '-',
                ];
            })
            ->sortBy(fn (array $course) => sprintf('%02d-%s', (int) $course['semester'], $course['code']))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPlan(): array
    {
        return [
            'id' => null,
            'title' => '-',
            'semester' => '-',
            'status' => '-',
            'submittedAt' => '-',
            'approvedAt' => '-',
            'totalCourses' => 0,
            'totalCredits' => 0,
            'requiredCount' => 0,
            'repeatCount' => 0,
            'courses' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function comparePlans(array $left, array $right): array
    {
        $leftCourses = collect($left['courses'] ?? [])->keyBy('courseId');
        $rightCourses = collect($right['courses'] ?? [])->keyBy('courseId');

        $same = $leftCourses->keys()->intersect($rightCourses->keys())->values();
        $added = $rightCourses->keys()->diff($leftCourses->keys())->values();
        $removed = $leftCourses->keys()->diff($rightCourses->keys())->values();

        return [
            'sameCount' => $same->count(),
            'addedCount' => $added->count(),
            'removedCount' => $removed->count(),
            'creditDelta' => (int) (($right['totalCredits'] ?? 0) - ($left['totalCredits'] ?? 0)),
            'same' => $same->map(fn ($courseId) => $rightCourses->get($courseId))->values()->all(),
            'added' => $added->map(fn ($courseId) => $rightCourses->get($courseId))->values()->all(),
            'removed' => $removed->map(fn ($courseId) => $leftCourses->get($courseId))->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'index' => route('student.study-plan.index'),
            'comparison' => route('student.study-plan.comparison'),
            'registration' => route('student.registration.index'),
            'courses' => route('student.study-plan.courses.store'),
            'submit' => route('student.study-plan.submit'),
            'revise' => route('student.study-plan.revise'),
        ];
    }
}
