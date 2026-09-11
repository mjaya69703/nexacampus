<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Notifications\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD KRS — memakai kit CRUD shared.
 *
 * Paritas Blade lama: header dua tahap (buat → lengkapi di edit),
 * detail MK per offering (unik per rencana, SKS auto-isi), pembukuan
 * transisi status (submitted/approved + by + notifikasi), dan hapus
 * permanen cascade ke detail (tanpa sampah — sesuai sistem lama).
 */
class StudyPlanController extends Controller
{
    public const STATUSES = ['Draft', 'Submitted', 'Approved', 'Rejected', 'Cancelled'];
    public const DETAIL_STATUSES = ['Draft', 'Taken', 'Dropped', 'Cancelled'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'year' => 'nullable|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'status' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudyPlan::query()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name',
                'studentProfile:id,nim,user_id,study_program_id',
                'academicYear:id,name',
            ])
            ->withCount('details as details_count')
            ->withSum('details as total_credits', 'credits');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('studentProfile.user', fn ($u) => $u
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($validated['nim'] ?? null)) {
            $query->whereHas('studentProfile', fn ($s) => $s->where('nim', 'like', '%'.$validated['nim'].'%'));
        }

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['program'] ?? null)) {
            $query->whereHas('studentProfile', fn ($s) => $s->where('study_program_id', $validated['program']));
        }

        if (filled($validated['semester'] ?? null)) {
            $query->where('semester_no', $validated['semester']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $plans = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/StudyPlan/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar KRS'),
            'can' => [
                'create' => ActivePermission::check('study-plan.create'),
                'update' => ActivePermission::check('study-plan.update'),
                'delete' => ActivePermission::check('study-plan.delete'),
                'view' => ActivePermission::check('study-plan.view'),
            ],
            'stats' => [
                'total' => StudyPlan::count(),
                'submitted' => StudyPlan::where('status', 'Submitted')->count(),
                'approved' => StudyPlan::where('status', 'Approved')->count(),
                'draft' => StudyPlan::where('status', 'Draft')->count(),
            ],
            'data' => [
                'rows' => collect($plans->items())->values()->map(fn ($p, $i) => [
                    'id' => $p->id,
                    'no' => ($plans->firstItem() ?? 0) + $i,
                    'student' => $p->studentProfile?->user?->name ?? '-',
                    'nim' => $p->studentProfile?->nim,
                    'program' => $p->studentProfile?->studyProgram?->name,
                    'year' => $p->academicYear?->name,
                    'semester' => $p->semester_no,
                    'status' => $p->status,
                    'statusTone' => $this->statusTone($p->status),
                    'courses' => (int) $p->details_count,
                    'credits' => (int) $p->total_credits,
                    'createdAt' => $p->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.academic.study-plans.show', $p->id),
                    'editUrl' => route('admin.academic.study-plans.edit', $p->id),
                    'deleteUrl' => route('admin.academic.study-plans.destroy', $p->id),
                ])->all(),
                'currentPage' => $plans->currentPage(),
                'lastPage' => $plans->lastPage(),
                'perPage' => $plans->perPage(),
                'total' => $plans->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'year' => $validated['year'] ?? '',
                'program' => $validated['program'] ?? '',
                'semester' => $validated['semester'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.academic.study-plans.index'),
                'create' => route('admin.academic.study-plans.create'),
                'export' => route('admin.academic.study-plans.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'study-plans']),
                'importTemplate' => route('admin.academic.import-template', ['resource' => 'study-plans']),
                'bulkDestroy' => route('admin.academic.study-plans.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/StudyPlan/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Buat KRS'),
            'mode' => 'create',
            'plan' => [
                'student_profile_id' => '', 'academic_year_id' => '', 'student_registration_id' => '',
                'semester_no' => '', 'status' => 'Draft', 'notes' => '',
            ],
            'years' => $this->yearOptions(),
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.academic.study-plans.index'),
                'submit' => route('admin.academic.study-plans.store'),
                'searchStudents' => route('admin.academic.study-plans.search-students'),
                'registrationOptions' => route('admin.academic.study-plans.registration-options'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_profile_id' => [
                'required', 'exists:student_profiles,id',
                Rule::unique('study_plans', 'student_profile_id')
                    ->where('academic_year_id', $request->input('academic_year_id'))
                    ->whereNull('deleted_at'),
            ],
            'academic_year_id' => 'required|exists:academic_years,id',
            'student_registration_id' => 'nullable|exists:student_registrations,id',
            'semester_no' => 'nullable|integer|min:1|max:14',
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'notes' => 'nullable|string',
        ]);

        $plan = StudyPlan::create([
            'student_profile_id' => $validated['student_profile_id'],
            'academic_year_id' => $validated['academic_year_id'],
            'student_registration_id' => $validated['student_registration_id'] ?? null,
            'semester_no' => $validated['semester_no'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'submitted_at' => $validated['status'] === 'Submitted' ? now() : null,
            'approved_at' => $validated['status'] === 'Approved' ? now() : null,
            'approved_by' => $validated['status'] === 'Approved' ? $request->user()->id : null,
            'created_by' => $request->user()->id,
        ]);

        if (in_array($plan->status, ['Submitted', 'Approved', 'Rejected', 'Cancelled'], true)) {
            app(NotificationDispatchService::class)->studyPlanStatusUpdated($plan, $plan->notes);
        }

        return redirect()->route('admin.academic.study-plans.edit', $plan)
            ->with('success', 'Header KRS berhasil dibuat. Silakan lengkapi detail mata kuliah.');
    }

    public function show(Request $request, int $id): Response
    {
        $plan = StudyPlan::query()
            ->with([
                'studentProfile.user', 'studentProfile.studyProgram',
                'academicYear', 'studentRegistration', 'approvedBy',
                'details.courseOffering.course:id,code,name',
            ])
            ->findOrFail($id);

        return Inertia::render('Admin/Academic/StudyPlan/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail KRS'),
            'plan' => $this->detailPayload($plan),
            'details' => $this->detailRows($plan),
            'canUpdate' => ActivePermission::check('study-plan.update'),
            'urls' => [
                'index' => route('admin.academic.study-plans.index'),
                'edit' => route('admin.academic.study-plans.edit', $plan),
            ],
        ]);
    }

    public function edit(Request $request, StudyPlan $studyPlan): Response
    {
        $studyPlan->load([
            'studentProfile.user', 'studentProfile.studyProgram',
            'details.courseOffering.course:id,code,name,credits',
        ]);

        return Inertia::render('Admin/Academic/StudyPlan/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit KRS'),
            'mode' => 'edit',
            'plan' => [
                'id' => $studyPlan->id,
                'student_profile_id' => $studyPlan->student_profile_id,
                'student_label' => trim(($studyPlan->studentProfile?->nim ? $studyPlan->studentProfile->nim.' - ' : '').($studyPlan->studentProfile?->user?->name ?? '')),
                'academic_year_id' => $studyPlan->academic_year_id,
                'student_registration_id' => $studyPlan->student_registration_id ?? '',
                'semester_no' => $studyPlan->semester_no ?? '',
                'status' => $studyPlan->status,
                'notes' => $studyPlan->notes ?? '',
                'submitted_at' => $studyPlan->submitted_at?->format('d M Y H:i'),
                'approved_at' => $studyPlan->approved_at?->format('d M Y H:i'),
                'approved_by' => $studyPlan->approvedBy?->name,
            ],
            'years' => $this->yearOptions(),
            'statuses' => self::STATUSES,
            'detailStatuses' => self::DETAIL_STATUSES,
            'details' => $this->detailRows($studyPlan),
            'programId' => $studyPlan->studentProfile?->study_program_id,
            'registrationOptions' => $this->fetchRegistrationOptions($studyPlan->student_profile_id, $studyPlan->academic_year_id),
            'urls' => [
                'index' => route('admin.academic.study-plans.index'),
                'submit' => route('admin.academic.study-plans.update', $studyPlan),
                'show' => route('admin.academic.study-plans.show', $studyPlan),
                'searchStudents' => route('admin.academic.study-plans.search-students'),
                'registrationOptions' => route('admin.academic.study-plans.registration-options'),
                'offeringOptions' => route('admin.academic.study-plans.offering-options'),
                'detailStore' => route('admin.academic.study-plans.details.store', $studyPlan),
                'detailBulkDestroy' => route('admin.academic.study-plans.details.bulk-destroy', $studyPlan),
            ],
        ]);
    }

    public function update(Request $request, StudyPlan $studyPlan)
    {
        $validated = $request->validate([
            'student_profile_id' => [
                'required', 'exists:student_profiles,id',
                Rule::unique('study_plans', 'student_profile_id')
                    ->where(fn ($q) => $q->where('academic_year_id', $request->input('academic_year_id')))
                    ->whereNull('deleted_at')
                    ->ignore($studyPlan->id),
            ],
            'academic_year_id' => 'required|exists:academic_years,id',
            'student_registration_id' => 'nullable|exists:student_registrations,id',
            'semester_no' => 'nullable|integer|min:1|max:14',
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'notes' => 'nullable|string',
        ]);

        $previousStatus = $studyPlan->status;
        $status = $validated['status'];

        $payload = [
            'student_profile_id' => $validated['student_profile_id'],
            'academic_year_id' => $validated['academic_year_id'],
            'student_registration_id' => $validated['student_registration_id'] ?? null,
            'semester_no' => $validated['semester_no'] ?? null,
            'status' => $status,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ];

        if ($status === 'Submitted' && ! $studyPlan->submitted_at) {
            $payload['submitted_at'] = now();
        }

        if ($status === 'Approved') {
            $payload['approved_at'] = now();
            $payload['approved_by'] = $request->user()->id;
        }

        if (in_array($status, ['Draft', 'Rejected', 'Cancelled'], true)) {
            $payload['approved_at'] = null;
            $payload['approved_by'] = null;
        }

        $studyPlan->update($payload);

        // Detail Draft baru menjadi roster resmi setelah header KRS disetujui.
        // Jika approval dibatalkan, kembalikan Taken agar kursi dan roster
        // resmi tidak tertahan pada KRS yang tidak lagi Approved.
        if ($status === 'Approved') {
            $studyPlan->details()
                ->where('status', 'Draft')
                ->update([
                    'status' => 'Taken',
                    'updated_by' => $request->user()->id,
                ]);
        } elseif ($previousStatus === 'Approved' && $status !== 'Approved') {
            $studyPlan->details()
                ->where('status', 'Taken')
                ->update([
                    'status' => 'Draft',
                    'updated_by' => $request->user()->id,
                ]);
        }

        if ($previousStatus !== $studyPlan->status
            && in_array($studyPlan->status, ['Submitted', 'Approved', 'Rejected', 'Cancelled'], true)) {
            app(NotificationDispatchService::class)->studyPlanStatusUpdated($studyPlan, $studyPlan->notes);
        }

        return redirect()->route('admin.academic.study-plans.index')
            ->with('success', 'KRS berhasil diperbarui.');
    }

    public function destroy(StudyPlan $studyPlan)
    {
        $studyPlan->update(['deleted_by' => auth()->id()]);
        $studyPlan->forceDelete();

        return redirect()->route('admin.academic.study-plans.index')
            ->with('success', 'KRS berhasil dihapus permanen beserta detailnya.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:study_plans,id',
        ]);

        $plans = StudyPlan::whereIn('id', $validated['ids'])->get();

        foreach ($plans as $plan) {
            $plan->update(['deleted_by' => $request->user()->id]);
            $plan->forceDelete();
        }

        return back()->with('success', $plans->count().' KRS berhasil dihapus permanen beserta detailnya.');
    }

    // ── Detail MK ─────────────────────────────────────────────────

    public function detailStore(Request $request, StudyPlan $studyPlan)
    {
        $validated = $request->validate([
            'course_offering_id' => [
                'required', 'exists:course_offerings,id',
                Rule::unique('study_plan_details', 'course_offering_id')
                    ->where(fn ($q) => $q->where('study_plan_id', $studyPlan->id))
                    ->whereNull('deleted_at'),
            ],
            'credits' => 'nullable|integer|min:1|max:30',
            'is_repeat' => 'nullable|boolean',
            'status' => 'required|in:'.implode(',', self::DETAIL_STATUSES),
            'notes' => 'nullable|string',
        ]);

        $studyPlan->details()->create(array_merge($validated, ['created_by' => $request->user()->id]));

        return back()->with('success', 'Mata kuliah ditambahkan ke KRS.');
    }

    public function detailUpdate(Request $request, StudyPlan $studyPlan, int $detail)
    {
        $validated = $request->validate([
            'course_offering_id' => [
                'required', 'exists:course_offerings,id',
                Rule::unique('study_plan_details', 'course_offering_id')
                    ->where(fn ($q) => $q->where('study_plan_id', $studyPlan->id))
                    ->whereNull('deleted_at')
                    ->ignore($detail),
            ],
            'credits' => 'nullable|integer|min:1|max:30',
            'is_repeat' => 'nullable|boolean',
            'status' => 'required|in:'.implode(',', self::DETAIL_STATUSES),
            'notes' => 'nullable|string',
        ]);

        $studyPlan->details()->whereKey($detail)->firstOrFail()
            ->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return back()->with('success', 'Detail KRS diperbarui.');
    }

    public function detailDestroy(StudyPlan $studyPlan, int $detail)
    {
        $row = $studyPlan->details()->whereKey($detail)->firstOrFail();
        $row->update(['deleted_by' => auth()->id()]);
        $row->forceDelete();

        return back()->with('success', 'Mata kuliah dihapus dari KRS.');
    }

    public function detailBulkDestroy(Request $request, StudyPlan $studyPlan)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:study_plan_details,id',
        ]);

        $rows = $studyPlan->details()->whereIn('id', $validated['ids'])->get();

        foreach ($rows as $row) {
            $row->update(['deleted_by' => $request->user()->id]);
            $row->forceDelete();
        }

        return back()->with('success', $rows->count().' baris dihapus dari KRS.');
    }

    // ── Endpoint pendukung form ───────────────────────────────────

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = StudentProfile::query()
            ->with(['user:id,first_name,last_name', 'studyProgram:id,name'])
            ->when($q !== '', fn ($query) => $query
                ->where('nim', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")))
            ->orderBy('nim')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => trim(($s->nim ? $s->nim.' - ' : '').($s->user?->name ?? '').($s->studyProgram ? ' ('.$s->studyProgram->name.')' : '')),
            ])
            ->all();

        return response()->json(['options' => $students]);
    }

    public function registrationOptions(Request $request)
    {
        $validated = $request->validate([
            'student' => 'required|integer|exists:student_profiles,id',
            'year' => 'required|integer|exists:academic_years,id',
        ]);

        return response()->json([
            'options' => $this->fetchRegistrationOptions($validated['student'], $validated['year']),
        ]);
    }

    /**
     * Offering scoped prodi + tahun + status Open/Draft (paritas form Blade).
     */
    public function offeringOptions(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'q' => 'nullable|string|max:100',
        ]);

        $offerings = CourseOffering::query()
            ->with('course:id,code,name')
            ->where('academic_year_id', $validated['year'])
            ->when(filled($validated['program'] ?? null), fn ($query) => $query->where('study_program_id', $validated['program']))
            ->whereIn('status', ['Open', 'Draft'])
            ->when(filled($validated['q'] ?? null), fn ($query) => $query
                ->where(fn ($sub) => $sub
                    ->where('label', 'like', '%'.$validated['q'].'%')
                    ->orWhereHas('course', fn ($c) => $c
                        ->where('code', 'like', '%'.$validated['q'].'%')
                        ->orWhere('name', 'like', '%'.$validated['q'].'%'))))
            ->orderBy('semester_no')
            ->orderBy('label')
            ->limit(50)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'label' => "{$o->course?->code} - {$o->course?->name} / {$o->label} ({$o->credits} SKS)",
                'credits' => $o->credits,
            ])
            ->all();

        return response()->json(['options' => $offerings]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudyPlan::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear'])
            ->withCount('details as details_count')
            ->withSum('details as total_credits', 'credits');

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Prodi', 'Tahun', 'Semester', 'Status', 'Jml MK', 'Total SKS', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $plan) {
            $sheet->fromArray([
                $plan->studentProfile?->user?->name,
                $plan->studentProfile?->nim,
                $plan->studentProfile?->studyProgram?->name,
                $plan->academicYear?->name,
                $plan->semester_no,
                $plan->status,
                (int) $plan->details_count,
                (int) $plan->total_credits,
                $plan->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'study-plans-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function fetchRegistrationOptions(int $studentId, int $yearId): array
    {
        return StudentRegistration::query()
            ->where('student_profile_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->orderByDesc('created_at')
            ->get(['id', 'registration_status', 'semester_no'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => "#{$r->id} - {$r->registration_status}".($r->semester_no ? " (Smt {$r->semester_no})" : ''),
            ])
            ->all();
    }

    private function detailPayload(StudyPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'student' => $plan->studentProfile?->user?->name ?? '-',
            'nim' => $plan->studentProfile?->nim,
            'program' => $plan->studentProfile?->studyProgram?->name,
            'year' => $plan->academicYear?->name,
            'registration' => $plan->studentRegistration
                ? "#{$plan->studentRegistration->id} - {$plan->studentRegistration->registration_status}"
                : '-',
            'semester' => $plan->semester_no,
            'status' => $plan->status,
            'statusTone' => $this->statusTone($plan->status),
            'notes' => $plan->notes,
            'submittedAt' => $plan->submitted_at?->format('d M Y H:i'),
            'approvedAt' => $plan->approved_at?->format('d M Y H:i'),
            'approvedBy' => $plan->approvedBy?->name,
            'createdAt' => $plan->created_at?->format('d M Y H:i'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detailRows(StudyPlan $plan): array
    {
        return $plan->details()
            ->with('courseOffering.course:id,code,name')
            ->join('course_offerings', 'course_offerings.id', '=', 'study_plan_details.course_offering_id')
            ->leftJoin('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->orderBy('course_offerings.semester_no')
            ->orderBy('courses.name')
            ->select('study_plan_details.*')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'offeringId' => $d->course_offering_id,
                'code' => $d->courseOffering?->course?->code,
                'name' => $d->courseOffering?->course?->name,
                'offeringLabel' => $d->courseOffering?->label,
                'credits' => $d->credits,
                'isRepeat' => (bool) $d->is_repeat,
                'status' => $d->status,
                'notes' => $d->notes,
                'updateUrl' => route('admin.academic.study-plans.details.update', [$plan, $d->id]),
                'deleteUrl' => route('admin.academic.study-plans.details.destroy', [$plan, $d->id]),
            ])
            ->all();
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'Approved' => 'green',
            'Rejected' => 'red',
            'Submitted' => 'amber',
            'Cancelled' => 'gray',
            default => '',
        };
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])
            ->all();
    }
}
