<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentGradeLifecycleService;
use App\Support\StudentGradePublicationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD nilai mahasiswa — memakai kit CRUD shared.
 *
 * Paritas Blade lama: header dua tahap (buat → lengkapi komponen di edit),
 * penilai wajib dosen pengajar offering, bobot ≤ 100%, finalisasi butuh
 * bobot 100%, publish massal hanya Finalized, hapus lunak + SweetAlert.
 *
 * Perbaikan cacat alur lama (via StudentGradeLifecycleService):
 *  Published dikunci (mutasi komponen/header ditolak; koreksi lewat
 *  unpublish → Finalized), finalisasi wajib semua skor terisi + penilai,
 *  ganti KRS dikunci bila sudah ada komponen / bukan Draft, hapus &
 *  turun-status selalu resync transkrip, graded_at hanya saat finalisasi.
 */
class StudentGradeController extends Controller
{
    public const LIFECYCLES = ['Draft', 'Finalized', 'Published'];
    public const LETTERS = ['A+', 'A', 'B+', 'B', 'C', 'D', 'E'];
    public const RESULTS = ['Passed', 'Failed'];

    public function __construct(
        protected StudentGradeLifecycleService $lifecycle = new StudentGradeLifecycleService,
        protected StudentGradePublicationService $publication = new StudentGradePublicationService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'year' => 'nullable|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'offering' => 'nullable|integer|exists:course_offerings,id',
            'letter' => 'nullable|string|max:5',
            'lifecycle' => 'nullable|string|max:20',
            'result' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at,final_score',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentGrade::query()
            ->with([
                'studyPlanDetail.studyPlan.studentProfile.user:id,first_name,last_name',
                'studyPlanDetail.studyPlan.studentProfile.studyProgram:id,name',
                'studyPlanDetail.studyPlan.studentProfile:id,nim,user_id,study_program_id',
                'studyPlanDetail.studyPlan.academicYear:id,name',
                'studyPlanDetail.studyPlan:id,student_profile_id,academic_year_id',
                'studyPlanDetail.courseOffering.course:id,code,name',
                'studyPlanDetail:id,study_plan_id,course_offering_id',
            ])
            ->withCount('components as components_count');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('studyPlanDetail.studyPlan.studentProfile.user', fn ($u) => $u
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($validated['nim'] ?? null)) {
            $query->whereHas('studyPlanDetail.studyPlan.studentProfile',
                fn ($s) => $s->where('nim', 'like', '%'.$validated['nim'].'%'));
        }

        if (filled($validated['year'] ?? null)) {
            $query->whereHas('studyPlanDetail.studyPlan',
                fn ($s) => $s->where('academic_year_id', $validated['year']));
        }

        if (filled($validated['program'] ?? null)) {
            $query->whereHas('studyPlanDetail.studyPlan.studentProfile',
                fn ($s) => $s->where('study_program_id', $validated['program']));
        }

        if (filled($validated['offering'] ?? null)) {
            $query->whereHas('studyPlanDetail',
                fn ($d) => $d->where('course_offering_id', $validated['offering']));
        }

        if (filled($validated['letter'] ?? null)) {
            $query->where('letter_grade', $validated['letter']);
        }

        if (filled($validated['lifecycle'] ?? null)) {
            $query->where('grade_status', $validated['lifecycle']);
        }

        if (filled($validated['result'] ?? null)) {
            $query->where('result_status', $validated['result']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $grades = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/StudentGrade/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Nilai Mahasiswa'),
            'can' => [
                'create' => ActivePermission::check('student-grade.create'),
                'update' => ActivePermission::check('student-grade.update'),
                'delete' => ActivePermission::check('student-grade.delete'),
                'view' => ActivePermission::check('student-grade.view'),
            ],
            'stats' => [
                'total' => StudentGrade::count(),
                'draft' => StudentGrade::where('grade_status', 'Draft')->count(),
                'finalized' => StudentGrade::where('grade_status', 'Finalized')->count(),
                'published' => StudentGrade::where('grade_status', 'Published')->count(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'year' => $validated['year'] ?? '',
                'program' => $validated['program'] ?? '',
                'offering' => $validated['offering'] ?? '',
                'letter' => $validated['letter'] ?? '',
                'lifecycle' => $validated['lifecycle'] ?? '',
                'result' => $validated['result'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'letters' => self::LETTERS,
            'lifecycles' => self::LIFECYCLES,
            'results' => self::RESULTS,
            'data' => [
                'rows' => collect($grades->items())->values()->map(fn ($g, $i) => [
                    'id' => $g->id,
                    'no' => ($grades->firstItem() ?? 0) + $i,
                    'student' => $g->studyPlanDetail?->studyPlan?->studentProfile?->user?->name ?? '-',
                    'nim' => $g->studyPlanDetail?->studyPlan?->studentProfile?->nim,
                    'program' => $g->studyPlanDetail?->studyPlan?->studentProfile?->studyProgram?->name,
                    'year' => $g->studyPlanDetail?->studyPlan?->academicYear?->name,
                    'course' => trim(($g->studyPlanDetail?->courseOffering?->course?->code ?? '').' '.($g->studyPlanDetail?->courseOffering?->course?->name ?? '')) ?: '-',
                    'classLabel' => $g->studyPlanDetail?->courseOffering?->label,
                    'finalScore' => $g->final_score !== null ? (float) $g->final_score : null,
                    'letter' => $g->letter_grade,
                    'point' => $g->grade_point !== null ? (float) $g->grade_point : null,
                    'lifecycle' => $g->grade_status,
                    'lifecycleTone' => $this->lifecycleTone($g->grade_status),
                    'result' => $g->result_status,
                    'resultTone' => $g->result_status === 'Passed' ? 'green' : ($g->result_status === 'Failed' ? 'red' : ''),
                    'components' => (int) $g->components_count,
                    'createdAt' => $g->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.academic.student-grades.show', $g->id),
                    'editUrl' => route('admin.academic.student-grades.edit', $g->id),
                    'deleteUrl' => route('admin.academic.student-grades.destroy', $g->id),
                ])->all(),
                'currentPage' => $grades->currentPage(),
                'lastPage' => $grades->lastPage(),
                'perPage' => $grades->perPage(),
                'total' => $grades->total(),
            ],
            'urls' => [
                'index' => route('admin.academic.student-grades.index'),
                'create' => route('admin.academic.student-grades.create'),
                'store' => route('admin.academic.student-grades.store'),
                'export' => route('admin.academic.student-grades.export'),
                'bulkPublish' => route('admin.academic.student-grades.bulk-publish'),
                'offeringOptions' => route('admin.academic.student-grades.offering-options'),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/StudentGrade/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Buat Nilai Mahasiswa'),
            'mode' => 'create',
            'form' => ['study_plan_detail_id' => null, 'graded_by' => null, 'notes' => ''],
            'detailOptions' => [],
            'graderOptions' => [],
            'urls' => [
                'index' => route('admin.academic.student-grades.index'),
                'submit' => route('admin.academic.student-grades.store'),
                'searchDetails' => route('admin.academic.student-grades.search-details'),
                'graderOptions' => route('admin.academic.student-grades.grader-options'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'study_plan_detail_id' => [
                'required', 'exists:study_plan_details,id',
                Rule::unique('student_grades', 'study_plan_detail_id')->whereNull('deleted_at'),
            ],
            'graded_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ], [
            'study_plan_detail_id.unique' => 'Detail KRS ini sudah punya nilai.',
        ]);

        $detail = StudyPlanDetail::findOrFail($validated['study_plan_detail_id']);

        if (! $this->isOfficiallyEnrolled($detail)) {
            throw ValidationException::withMessages([
                'study_plan_detail_id' => 'Detail KRS ini tidak dalam status terdaftar resmi.',
            ]);
        }

        if (! empty($validated['graded_by']) && ! $this->isEligibleGrader((int) $validated['graded_by'], $detail)) {
            throw ValidationException::withMessages([
                'graded_by' => 'Penilai harus dosen pengajar pada course offering ini.',
            ]);
        }

        $grade = StudentGrade::create([
            'study_plan_detail_id' => $detail->id,
            'grade_status' => 'Draft',
            'graded_by' => $validated['graded_by'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->lifecycle->recalculate($grade);

        return redirect()->route('admin.academic.student-grades.edit', $grade)
            ->with('success', 'Header nilai dibuat. Lengkapi komponen dan finalize saat total bobot 100%.');
    }

    public function edit(Request $request, StudentGrade $studentGrade): Response
    {
        $studentGrade->load([
            'studyPlanDetail.studyPlan.studentProfile.user',
            'studyPlanDetail.studyPlan.studentProfile.studyProgram',
            'studyPlanDetail.studyPlan.academicYear',
            'studyPlanDetail.courseOffering.course',
            'gradedBy:id,first_name,last_name',
            'components',
        ]);

        $detail = $studentGrade->studyPlanDetail;

        return Inertia::render('Admin/Academic/StudentGrade/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Nilai Mahasiswa'),
            'mode' => 'edit',
            'grade' => $this->gradePayload($studentGrade),
            'form' => [
                'study_plan_detail_id' => $studentGrade->study_plan_detail_id,
                'graded_by' => $studentGrade->graded_by,
                'notes' => $studentGrade->notes,
            ],
            'detailOptions' => $detail ? [$this->detailOption($detail)] : [],
            'graderOptions' => $detail ? $this->fetchGraderOptions($detail) : [],
            'urls' => [
                'index' => route('admin.academic.student-grades.index'),
                'submit' => route('admin.academic.student-grades.update', $studentGrade),
                'show' => route('admin.academic.student-grades.show', $studentGrade),
                'finalize' => route('admin.academic.student-grades.finalize', $studentGrade),
                'publish' => route('admin.academic.student-grades.publish', $studentGrade),
                'unpublish' => route('admin.academic.student-grades.unpublish', $studentGrade),
                'searchDetails' => route('admin.academic.student-grades.search-details'),
                'graderOptions' => route('admin.academic.student-grades.grader-options'),
                'componentStore' => route('admin.academic.student-grades.components.store', $studentGrade),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function update(Request $request, StudentGrade $studentGrade)
    {
        try {
            $this->lifecycle->assertMutable($studentGrade);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        $validated = $request->validate([
            'study_plan_detail_id' => [
                'required', 'exists:study_plan_details,id',
                Rule::unique('student_grades', 'study_plan_detail_id')
                    ->whereNull('deleted_at')
                    ->ignore($studentGrade->id),
            ],
            'graded_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $detail = StudyPlanDetail::findOrFail($validated['study_plan_detail_id']);

        // Kunci ganti KRS bila bukan Draft atau komponen sudah ada.
        if ((int) $validated['study_plan_detail_id'] !== (int) $studentGrade->study_plan_detail_id) {
            if ($studentGrade->grade_status !== 'Draft' || $studentGrade->components()->count() > 0) {
                return back()->with('error', 'Kaitan KRS tidak bisa diganti karena komponen sudah ada / status bukan Draft. Buat nilai baru bila perlu.');
            }
        }

        if (! empty($validated['graded_by']) && ! $this->isEligibleGrader((int) $validated['graded_by'], $detail)) {
            throw ValidationException::withMessages([
                'graded_by' => 'Penilai harus dosen pengajar pada course offering ini.',
            ]);
        }

        $studentGrade->update([
            'study_plan_detail_id' => $detail->id,
            'graded_by' => $validated['graded_by'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $this->lifecycle->recalculate($studentGrade);

        return back()->with('success', 'Header nilai diperbarui.');
    }

    public function show(Request $request, StudentGrade $studentGrade): Response
    {
        $studentGrade->load([
            'studyPlanDetail.studyPlan.studentProfile.user',
            'studyPlanDetail.studyPlan.studentProfile.studyProgram',
            'studyPlanDetail.studyPlan.academicYear',
            'studyPlanDetail.courseOffering.course',
            'gradedBy:id,first_name,last_name',
            'components',
        ]);

        return Inertia::render('Admin/Academic/StudentGrade/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail Nilai Mahasiswa'),
            'grade' => $this->gradePayload($studentGrade),
            'can' => [
                'update' => ActivePermission::check('student-grade.update'),
                'delete' => ActivePermission::check('student-grade.delete'),
            ],
            'urls' => [
                'index' => route('admin.academic.student-grades.index'),
                'edit' => route('admin.academic.student-grades.edit', $studentGrade),
                'destroy' => route('admin.academic.student-grades.destroy', $studentGrade),
                'finalize' => route('admin.academic.student-grades.finalize', $studentGrade),
                'publish' => route('admin.academic.student-grades.publish', $studentGrade),
                'unpublish' => route('admin.academic.student-grades.unpublish', $studentGrade),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function destroy(Request $request, StudentGrade $studentGrade)
    {
        $this->lifecycle->deleteGrade($studentGrade, $request->user()->id);

        return redirect()->route('admin.academic.student-grades.index')
            ->with('success', 'Nilai dihapus dan transkrip mahasiswa disinkron ulang.');
    }

    // ── Lifecycle ───────────────────────────────────────────────────

    public function finalize(Request $request, StudentGrade $studentGrade)
    {
        try {
            $this->lifecycle->finalize($studentGrade, $request->input('graded_by') ?? $studentGrade->graded_by, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Nilai difinalisasi dan transkrip disinkron.');
    }

    public function publish(Request $request, StudentGrade $studentGrade)
    {
        if ($studentGrade->grade_status !== 'Finalized') {
            return back()->with('error', 'Hanya nilai Finalized yang bisa dipublish.');
        }

        if (! $this->publication->publish($studentGrade, $request->user()->id)) {
            return back()->with('error', 'Nilai tidak bisa dipublish dari status saat ini.');
        }

        return back()->with('success', 'Nilai dipublish dan sudah bisa dilihat mahasiswa.');
    }

    public function unpublish(Request $request, StudentGrade $studentGrade)
    {
        try {
            $this->lifecycle->unpublish($studentGrade, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Publish dibatalkan. Nilai kembali ke Finalized.');
    }

    public function bulkPublish(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_grades,id',
        ]);

        $published = 0;
        $skipped = 0;

        foreach (StudentGrade::whereIn('id', $validated['ids'])->get() as $grade) {
            if ($this->publication->publish($grade, $request->user()->id)) {
                $published++;
            } else {
                $skipped++;
            }
        }

        $message = $published > 0 ? "{$published} nilai dipublish." : 'Tidak ada nilai yang dipublish.';
        if ($skipped > 0) {
            $message .= " {$skipped} dilewati (bukan Finalized).";
        }

        return back()->with($published > 0 ? 'success' : 'error', $message);
    }

    // ── Komponen ────────────────────────────────────────────────────

    public function componentStore(Request $request, StudentGrade $studentGrade)
    {
        try {
            $this->lifecycle->assertMutable($studentGrade);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'weight_percentage' => 'nullable|numeric|min:0|max:100',
            'score' => 'nullable|numeric|min:0|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->assertWeightCap($studentGrade, (float) ($validated['weight_percentage'] ?? 0));

        $wasFinalized = $studentGrade->grade_status === 'Finalized';

        $studentGrade->components()->create([
            'name' => $validated['name'],
            'weight_percentage' => $validated['weight_percentage'] ?? null,
            'score' => $validated['score'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->afterComponentMutation($studentGrade, $wasFinalized, $request->user()->id);

        return back()->with('success', 'Komponen ditambahkan.'
            .($wasFinalized ? ' Status turun ke Draft — finalisasi ulang bila sudah benar.' : ''));
    }

    public function componentUpdate(Request $request, StudentGrade $studentGrade, int $component)
    {
        try {
            $this->lifecycle->assertMutable($studentGrade);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        $row = $studentGrade->components()->findOrFail($component);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'weight_percentage' => 'nullable|numeric|min:0|max:100',
            'score' => 'nullable|numeric|min:0|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->assertWeightCap($studentGrade, (float) ($validated['weight_percentage'] ?? 0), $row->id);

        $wasFinalized = $studentGrade->grade_status === 'Finalized';

        $row->update([
            'name' => $validated['name'],
            'weight_percentage' => $validated['weight_percentage'] ?? null,
            'score' => $validated['score'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $this->afterComponentMutation($studentGrade, $wasFinalized, $request->user()->id);

        return back()->with('success', 'Komponen diperbarui.'
            .($wasFinalized ? ' Status turun ke Draft — finalisasi ulang bila sudah benar.' : ''));
    }

    public function componentDestroy(Request $request, StudentGrade $studentGrade, int $component)
    {
        try {
            $this->lifecycle->assertMutable($studentGrade);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        $row = $studentGrade->components()->findOrFail($component);
        $wasFinalized = $studentGrade->grade_status === 'Finalized';

        $row->update(['deleted_by' => $request->user()->id]);
        $row->delete();

        $this->afterComponentMutation($studentGrade, $wasFinalized, $request->user()->id);

        return back()->with('success', 'Komponen dihapus.'
            .($wasFinalized ? ' Status turun ke Draft — finalisasi ulang bila sudah benar.' : ''));
    }

    private function afterComponentMutation(StudentGrade $grade, bool $wasFinalized, ?int $actorId): void
    {
        $grade = $this->lifecycle->recalculate($grade);

        if ($wasFinalized) {
            $grade->update(['grade_status' => 'Draft', 'updated_by' => $actorId]);
            $grade->refresh();
        }

        $this->lifecycle->resyncStudent($grade);
    }

    private function assertWeightCap(StudentGrade $grade, float $newWeight, ?int $exceptId = null): void
    {
        $current = (float) $grade->components()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get()
            ->sum(fn ($c) => (float) ($c->weight_percentage ?? 0));

        $projected = round($current + $newWeight, 2);

        if ($projected > 100) {
            throw ValidationException::withMessages([
                'weight_percentage' => "Total bobot tidak boleh melebihi 100% (akan menjadi {$projected}%).",
            ]);
        }
    }

    // ── Endpoint pendukung form ─────────────────────────────────────

    public function searchDetails(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $details = StudyPlanDetail::query()
            ->whereDoesntHave('studentGrade')
            ->officiallyEnrolled()
            ->with([
                'studyPlan.studentProfile.user',
                'studyPlan.academicYear',
                'courseOffering.course',
            ])
            ->when($q !== '', fn ($query) => $query
                ->whereHas('studyPlan.studentProfile', fn ($s) => $s
                    ->where('nim', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")))
                ->orWhereHas('courseOffering.course', fn ($c) => $c
                    ->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($d) => $this->detailOption($d))
            ->all();

        return response()->json(['options' => $details]);
    }

    public function graderOptions(Request $request)
    {
        $validated = $request->validate([
            'detail' => 'required|integer|exists:study_plan_details,id',
        ]);

        $detail = StudyPlanDetail::findOrFail($validated['detail']);

        return response()->json(['options' => $this->fetchGraderOptions($detail)]);
    }

    public function offeringOptions(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $offerings = CourseOffering::query()
            ->with('course:id,code,name')
            ->when($q !== '', fn ($query) => $query
                ->where('label', 'like', "%{$q}%")
                ->orWhereHas('course', fn ($c) => $c
                    ->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'label' => ($o->course?->code ?? '-').' - '.($o->course?->name ?? '-').' / '.$o->label,
            ])
            ->all();

        return response()->json(['options' => $offerings]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudentGrade::query()
            ->with([
                'studyPlanDetail.studyPlan.studentProfile.user',
                'studyPlanDetail.studyPlan.studentProfile.studyProgram',
                'studyPlanDetail.studyPlan.academicYear',
                'studyPlanDetail.courseOffering.course',
            ])
            ->withCount('components as components_count');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Prodi', 'Tahun', 'Mata Kuliah', 'Kelas', 'Skor', 'Huruf', 'Indeks', 'Lifecycle', 'Hasil', 'Jml Komponen', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $grade) {
            $sheet->fromArray([
                $grade->studyPlanDetail?->studyPlan?->studentProfile?->user?->name,
                $grade->studyPlanDetail?->studyPlan?->studentProfile?->nim,
                $grade->studyPlanDetail?->studyPlan?->studentProfile?->studyProgram?->name,
                $grade->studyPlanDetail?->studyPlan?->academicYear?->name,
                trim(($grade->studyPlanDetail?->courseOffering?->course?->code ?? '').' '.($grade->studyPlanDetail?->courseOffering?->course?->name ?? '')),
                $grade->studyPlanDetail?->courseOffering?->label,
                $grade->final_score !== null ? (float) $grade->final_score : null,
                $grade->letter_grade,
                $grade->grade_point !== null ? (float) $grade->grade_point : null,
                $grade->grade_status,
                $grade->result_status,
                (int) $grade->components_count,
                $grade->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'student-grades-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function gradePayload(StudentGrade $grade): array
    {
        $detail = $grade->studyPlanDetail;

        return [
            'id' => $grade->id,
            'student' => $detail?->studyPlan?->studentProfile?->user?->name ?? '-',
            'nim' => $detail?->studyPlan?->studentProfile?->nim,
            'program' => $detail?->studyPlan?->studentProfile?->studyProgram?->name,
            'year' => $detail?->studyPlan?->academicYear?->name,
            'course' => trim(($detail?->courseOffering?->course?->code ?? '').' '.($detail?->courseOffering?->course?->name ?? '')) ?: '-',
            'classLabel' => $detail?->courseOffering?->label,
            'credits' => $detail?->credits ?? $detail?->courseOffering?->credits,
            'gradedBy' => $grade->gradedBy?->name,
            'gradedAt' => $grade->graded_at?->format('d M Y H:i'),
            'lifecycle' => $grade->grade_status,
            'lifecycleTone' => $this->lifecycleTone($grade->grade_status),
            'result' => $grade->result_status,
            'resultTone' => $grade->result_status === 'Passed' ? 'green' : ($grade->result_status === 'Failed' ? 'red' : ''),
            'finalScore' => $grade->final_score !== null ? (float) $grade->final_score : null,
            'letter' => $grade->letter_grade,
            'point' => $grade->grade_point !== null ? (float) $grade->grade_point : null,
            'notes' => $grade->notes,
            'isPublished' => $grade->grade_status === 'Published',
            'isFinalized' => $grade->grade_status === 'Finalized',
            'isDraft' => $grade->grade_status === 'Draft',
            'totalWeight' => round((float) $grade->components->sum(fn ($c) => (float) ($c->weight_percentage ?? 0)), 2),
            'components' => $grade->components
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->values()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'weight' => $c->weight_percentage !== null ? (float) $c->weight_percentage : null,
                    'score' => $c->score !== null ? (float) $c->score : null,
                    'sortOrder' => $c->sort_order,
                    'notes' => $c->notes,
                    'updateUrl' => route('admin.academic.student-grades.components.update', [$grade, $c->id]),
                    'deleteUrl' => route('admin.academic.student-grades.components.destroy', [$grade, $c->id]),
                ])
                ->all(),
            'createdAt' => $grade->created_at?->format('d M Y H:i'),
        ];
    }

    /**
     * @return array{id: int, label: string}
     */
    private function detailOption(StudyPlanDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'label' => ($detail->studyPlan?->studentProfile?->user?->name ?? '-')
                .' ('.($detail->studyPlan?->studentProfile?->nim ?? '-').') - '
                .($detail->courseOffering?->course?->code ?? '-').' - '
                .($detail->courseOffering?->course?->name ?? '-'),
        ];
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function fetchGraderOptions(StudyPlanDetail $detail): array
    {
        if (! $detail->course_offering_id) {
            return [];
        }

        return CourseOfferingLecturer::query()
            ->where('course_offering_id', $detail->course_offering_id)
            ->where('is_active', true)
            ->with('lecturerProfile.user')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($lecturer) => [
                'id' => $lecturer->lecturerProfile?->user_id,
                'label' => $lecturer->lecturerProfile?->user?->name,
            ])
            ->filter(fn ($grader) => ! empty($grader['id']) && ! empty($grader['label']))
            ->unique('id')
            ->values()
            ->all();
    }

    private function isEligibleGrader(int $userId, StudyPlanDetail $detail): bool
    {
        if (! $detail->course_offering_id) {
            return false;
        }

        return CourseOfferingLecturer::query()
            ->where('course_offering_id', $detail->course_offering_id)
            ->where('is_active', true)
            ->whereHas('lecturerProfile', fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }

    private function isOfficiallyEnrolled(StudyPlanDetail $detail): bool
    {
        return StudyPlanDetail::query()
            ->whereKey($detail->id)
            ->officiallyEnrolled()
            ->exists();
    }

    private function lifecycleTone(?string $status): string
    {
        return match ($status) {
            'Finalized' => 'blue',
            'Published' => 'green',
            default => '',
        };
    }
}
