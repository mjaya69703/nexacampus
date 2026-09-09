<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\Curriculum;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\GenerateAttendanceSessionsService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Penawaran Kelas + workspace (detail, dosen, jadwal, sesi).
 *
 * Paritas Blade lama: guard hapus (jadwal/absensi/tugas), lecturer
 * sebagai model eksplisit (bukan pivot), generate sesi destruktif
 * dengan konfirmasi eksplisit + blokir bila ada data absensi.
 */
class CourseOfferingController extends Controller
{
    public const DELIVERY_MODES = ['Offline', 'Online', 'Hybrid'];
    public const STATUSES = ['Draft', 'Open', 'Closed', 'Cancelled'];
    public const LECTURER_ROLES = ['Coordinator', 'Primary', 'Secondary', 'Assistant'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'year' => 'nullable|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'course' => 'nullable|integer|exists:courses,id',
            'status' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = CourseOffering::query()
            ->with(['academicYear:id,name', 'studyProgram:id,name', 'course:id,code,name', 'lecturers.lecturerProfile.user:id,first_name,last_name'])
            ->withCount('lecturers as lecturers_count');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('label', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhereHas('course', fn ($c) => $c
                    ->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")));
        }

        foreach (['year' => 'academic_year_id', 'program' => 'study_program_id', 'course' => 'course_id'] as $input => $column) {
            if (filled($validated[$input] ?? null)) {
                $query->where($column, $validated[$input]);
            }
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $offerings = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/CourseOffering/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Penawaran Kelas'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('course-offering.create'),
                'update' => ActivePermission::check('course-offering.update'),
                'delete' => ActivePermission::check('course-offering.delete'),
                'view' => ActivePermission::check('course-offering.view'),
                'restore' => ActivePermission::any(['course-offering.update', 'course-offering.delete']),
            ],
            'stats' => [
                'total' => CourseOffering::count(),
                'open' => CourseOffering::where('status', 'Open')->count(),
                'draft' => CourseOffering::where('status', 'Draft')->count(),
                'capacity' => (int) CourseOffering::sum('capacity'),
                'trashed' => CourseOffering::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($offerings->items())->values()->map(fn ($o, $i) => [
                    'id' => $o->id,
                    'no' => ($offerings->firstItem() ?? 0) + $i,
                    'course' => $o->course ? "{$o->course->code} - {$o->course->name}" : '-',
                    'label' => $o->label,
                    'year' => $o->academicYear?->name,
                    'program' => $o->studyProgram?->name,
                    'semester' => $o->semester_no,
                    'capacity' => $o->capacity,
                    'mode' => $o->delivery_mode,
                    'status' => $o->status,
                    'statusTone' => $this->statusTone($o->status),
                    'lecturers' => $o->lecturers->map(fn ($l) => trim(($l->lecturerProfile?->user?->first_name ?? '').' '.($l->lecturerProfile?->user?->last_name ?? '')))->filter()->values()->all(),
                    'lecturerCount' => (int) $o->lecturers_count,
                    'createdAt' => $o->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.academic.course-offerings.show', $o->id),
                    'editUrl' => $isTrash ? null : route('admin.academic.course-offerings.edit', $o->id),
                    'deleteUrl' => route('admin.academic.course-offerings.destroy', $o->id),
                    'restoreUrl' => route('admin.academic.course-offerings.restore', $o->id),
                    'forceUrl' => route('admin.academic.course-offerings.force-destroy', $o->id),
                ])->all(),
                'currentPage' => $offerings->currentPage(),
                'lastPage' => $offerings->lastPage(),
                'perPage' => $offerings->perPage(),
                'total' => $offerings->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'year' => $validated['year'] ?? '',
                'program' => $validated['program'] ?? '',
                'course' => $validated['course'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'statuses' => self::STATUSES,
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.course-offerings.index'),
                'create' => route('admin.academic.course-offerings.create'),
                'export' => route('admin.academic.course-offerings.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'course-offerings']),
                'importTemplate' => route('admin.academic.course-offerings.import-template'),
                'importSubmit' => route('admin.academic.course-offerings.import'),
                'bulkDestroy' => route('admin.academic.course-offerings.bulk-destroy'),
                'bulkRestore' => route('admin.academic.course-offerings.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.course-offerings.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/CourseOffering/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Penawaran Kelas'),
            'mode' => 'create',
            'offering' => [
                'academic_year_id' => '', 'study_program_id' => '', 'curriculum_id' => '',
                'course_id' => '', 'label' => '', 'code' => '', 'semester_no' => '',
                'capacity' => '', 'credits' => '', 'is_required' => true,
                'delivery_mode' => 'Offline', 'status' => 'Draft', 'notes' => '',
            ],
            'options' => $this->formOptions(),
            'deliveryModes' => self::DELIVERY_MODES,
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.academic.course-offerings.index'),
                'submit' => route('admin.academic.course-offerings.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $offering = CourseOffering::create(array_merge($validated, ['created_by' => $request->user()->id]));

        return redirect()->route('admin.academic.course-offerings.edit', $offering)
            ->with('success', 'Kelas berhasil dibuat. Lanjutkan menugaskan dosen dan menyusun jadwal.');
    }

    public function show(Request $request, int $id): Response
    {
        $offering = CourseOffering::query()
            ->with([
                'academicYear:id,name', 'studyProgram:id,name', 'curriculum:id,name',
                'course:id,code,name,credits',
                'lecturers.lecturerProfile.user:id,first_name,last_name',
                'courseSchedules.room.building:id,name',
                'courseSchedules.lecturerProfile.user:id,first_name,last_name',
                'attendanceSessions' => fn ($q) => $q->orderBy('meeting_no')->withCount('records'),
            ])
            ->withCount(['lecturers', 'courseSchedules', 'attendanceSessions'])
            ->findOrFail($id);

        return Inertia::render('Admin/Academic/CourseOffering/Workspace', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Workspace Kelas'),
            'can' => [
                'update' => ActivePermission::check('course-offering.update'),
                'manageLecturers' => ActivePermission::check('course-offering.update'),
                'manageSchedules' => ActivePermission::check('course-schedule.create'),
                'generate' => ActivePermission::check('course-offering.update'),
            ],
            'offering' => [
                'id' => $offering->id,
                'course' => $offering->course ? "{$offering->course->code} - {$offering->course->name}" : '-',
                'label' => $offering->label,
                'code' => $offering->code,
                'year' => $offering->academicYear?->name,
                'program' => $offering->studyProgram?->name,
                'curriculum' => $offering->curriculum?->name,
                'semester' => $offering->semester_no,
                'capacity' => $offering->capacity,
                'credits' => $offering->credits ?? $offering->course?->credits,
                'mode' => $offering->delivery_mode,
                'status' => $offering->status,
                'statusTone' => $this->statusTone($offering->status),
                'meetings' => $offering->total_meetings,
                'startDate' => $offering->class_start_date?->format('d M Y'),
                'endDate' => $offering->class_end_date?->format('d M Y'),
                'notes' => $offering->notes,
            ],
            'lecturers' => $offering->lecturers->map(fn ($l) => [
                'id' => $l->id,
                'name' => trim(($l->lecturerProfile?->user?->first_name ?? '').' '.($l->lecturerProfile?->user?->last_name ?? '')) ?: '-',
                'role' => $l->role,
                'isActive' => (bool) $l->is_active,
                'notes' => $l->notes,
                'updateUrl' => route('admin.academic.course-offerings.lecturers.update', [$offering, $l->id]),
                'deleteUrl' => route('admin.academic.course-offerings.lecturers.destroy', [$offering, $l->id]),
            ])->all(),
            'schedules' => $offering->courseSchedules->map(fn ($s) => [
                'id' => $s->id,
                'day' => $s->day_of_week,
                'time' => ($s->start_time?->format('H:i') ?? '?').'–'.($s->end_time?->format('H:i') ?? '?'),
                'room' => $s->room ? ($s->room->building?->name ? $s->room->building->name.' · ' : '').$s->room->name : ($s->meeting_link ? 'Online' : '-'),
                'lecturer' => $s->lecturerProfile ? trim(($s->lecturerProfile->user?->first_name ?? '').' '.($s->lecturerProfile->user?->last_name ?? '')) : 'Umum',
                'type' => $s->session_type,
                'isActive' => (bool) $s->is_active,
                'editUrl' => route('admin.academic.course-schedules.edit', $s->id),
            ])->all(),
            'sessions' => $offering->attendanceSessions->map(fn ($s) => [
                'id' => $s->id,
                'meetingNo' => $s->meeting_no,
                'date' => $s->meeting_date?->format('d M Y'),
                'time' => ($s->start_time?->format('H:i') ?? '?').'–'.($s->end_time?->format('H:i') ?? '?'),
                'topic' => $s->topic,
                'status' => $s->status,
                'records' => (int) $s->records_count,
                'url' => route('admin.academic.attendance-sessions.show', ['offeringId' => $offering->id, 'id' => $s->id]),
            ])->all(),
            'students' => $this->enrolledStudents($offering->id),
            'generate' => [
                'canGenerate' => (bool) ($offering->total_meetings && $offering->class_start_date && $offering->class_end_date),
                'activeSchedules' => $offering->courseSchedules->where('is_active', true)->count(),
                'existingSessions' => (int) $offering->attendance_sessions_count,
                'sessionsWithRecords' => AttendanceSession::where('course_offering_id', $offering->id)->whereHas('records')->count(),
                'hasOpened' => AttendanceSession::where('course_offering_id', $offering->id)->where('status', 'Opened')->exists(),
            ],
            'urls' => [
                'index' => route('admin.academic.course-offerings.index'),
                'edit' => route('admin.academic.course-offerings.edit', $offering),
                'lecturerStore' => route('admin.academic.course-offerings.lecturers.store', $offering),
                'scheduleCreate' => route('admin.academic.course-schedules.create', ['offering' => $offering->id]),
                'generate' => route('admin.academic.course-offerings.generate', $offering),
                'searchLecturers' => route('admin.academic.course-offerings.search-lecturers'),
            ],
        ]);
    }

    public function edit(Request $request, CourseOffering $courseOffering): Response
    {
        return Inertia::render('Admin/Academic/CourseOffering/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Penawaran Kelas'),
            'mode' => 'edit',
            'offering' => [
                'id' => $courseOffering->id,
                'academic_year_id' => $courseOffering->academic_year_id,
                'study_program_id' => $courseOffering->study_program_id,
                'curriculum_id' => $courseOffering->curriculum_id ?? '',
                'course_id' => $courseOffering->course_id,
                'label' => $courseOffering->label ?? '',
                'code' => $courseOffering->code ?? '',
                'semester_no' => $courseOffering->semester_no ?? '',
                'capacity' => $courseOffering->capacity ?? '',
                'credits' => $courseOffering->credits ?? '',
                'total_meetings' => $courseOffering->total_meetings ?? '',
                'class_start_date' => $courseOffering->class_start_date?->format('Y-m-d') ?? '',
                'class_end_date' => $courseOffering->class_end_date?->format('Y-m-d') ?? '',
                'is_required' => (bool) $courseOffering->is_required,
                'delivery_mode' => $courseOffering->delivery_mode,
                'status' => $courseOffering->status,
                'notes' => $courseOffering->notes ?? '',
            ],
            'options' => $this->formOptions(),
            'deliveryModes' => self::DELIVERY_MODES,
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.academic.course-offerings.index'),
                'submit' => route('admin.academic.course-offerings.update', $courseOffering),
                'workspace' => route('admin.academic.course-offerings.show', $courseOffering),
            ],
        ]);
    }

    public function update(Request $request, CourseOffering $courseOffering)
    {
        $validated = $this->validatePayload($request, true, $courseOffering->id);

        $courseOffering->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return redirect()->route('admin.academic.course-offerings.show', $courseOffering)
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(CourseOffering $courseOffering)
    {
        $blocker = $this->deleteBlocker($courseOffering);

        if ($blocker) {
            return back()->with('error', $blocker);
        }

        $label = $courseOffering->course?->code.' - '.$courseOffering->label;
        $courseOffering->lecturers()->delete();
        $courseOffering->delete();

        return redirect()->route('admin.academic.course-offerings.index')
            ->with('success', 'Kelas "'.$label.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:course_offerings,id',
        ]);

        $offerings = CourseOffering::whereIn('id', $validated['ids'])->get();
        $blocked = $offerings->filter(fn ($o) => $this->deleteBlocker($o) !== null);
        $deletable = $offerings->reject(fn ($o) => $this->deleteBlocker($o) !== null);

        foreach ($deletable as $offering) {
            $offering->lecturers()->delete();
            $offering->delete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kelas dihapus. '.$blocked->count().' tidak bisa dihapus karena sudah punya jadwal/absensi/tugas.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kelas tidak bisa dihapus karena sudah memiliki jadwal, absensi, atau tugas.');
        }

        return back()->with('success', $deletable->count().' kelas berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $offering = CourseOffering::withTrashed()->findOrFail($id);
        $offering->restore();

        return back()->with('success', 'Kelas berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $offering = CourseOffering::withTrashed()->findOrFail($id);

        if ($offering->courseSchedules()->withTrashed()->exists() || $offering->attendanceSessions()->withTrashed()->exists() || $offering->assignments()->exists()) {
            return back()->with('error', 'Kelas tidak bisa dihapus karena sudah memiliki jadwal, absensi, atau tugas.');
        }

        $offering->lecturers()->withTrashed()->forceDelete();
        $offering->forceDelete();

        return back()->with('success', 'Kelas dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:course_offerings,id',
        ]);

        $count = 0;

        foreach (CourseOffering::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $offering) {
            $offering->restore();
            $count++;
        }

        return back()->with('success', $count.' kelas berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:course_offerings,id',
        ]);

        $offerings = CourseOffering::withTrashed()->whereIn('id', $validated['ids'])->get();
        $blocked = $offerings->filter(fn ($o) => $o->courseSchedules()->withTrashed()->exists() || $o->attendanceSessions()->withTrashed()->exists() || $o->assignments()->exists());
        $deletable = $offerings->reject(fn ($o) => $o->courseSchedules()->withTrashed()->exists() || $o->attendanceSessions()->withTrashed()->exists() || $o->assignments()->exists());

        foreach ($deletable as $offering) {
            $offering->lecturers()->withTrashed()->forceDelete();
            $offering->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kelas dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena sudah punya jadwal/absensi/tugas.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kelas tidak bisa dihapus karena sudah memiliki jadwal, absensi, atau tugas.');
        }

        return back()->with('success', $deletable->count().' kelas dihapus permanen.');
    }

    // ── Dosen pengampu ────────────────────────────────────────────

    public function lecturerStore(Request $request, CourseOffering $courseOffering)
    {
        $validated = $request->validate([
            'lecturer_profile_id' => 'required|integer|exists:lecturer_profiles,id',
            'role' => 'required|in:'.implode(',', self::LECTURER_ROLES),
            'sort_order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($courseOffering->lecturers()->where('lecturer_profile_id', $validated['lecturer_profile_id'])->exists()) {
            return back()->with('error', 'Dosen sudah terdaftar di kelas ini.')->withInput();
        }

        $courseOffering->lecturers()->create(array_merge($validated, [
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]));

        return back()->with('success', 'Dosen ditambahkan ke kelas.');
    }

    public function lecturerUpdate(Request $request, CourseOffering $courseOffering, int $lecturer)
    {
        $validated = $request->validate([
            'role' => 'required|in:'.implode(',', self::LECTURER_ROLES),
            'sort_order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $row = $courseOffering->lecturers()->whereKey($lecturer)->firstOrFail();
        $row->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return back()->with('success', 'Peran dosen diperbarui.');
    }

    public function lecturerDestroy(CourseOffering $courseOffering, int $lecturer)
    {
        $courseOffering->lecturers()->whereKey($lecturer)->firstOrFail()->delete();

        return back()->with('success', 'Dosen dikeluarkan dari kelas.');
    }

    public function searchLecturers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $lecturers = LecturerProfile::query()
            ->with('user:id,first_name,last_name')
            ->when($q !== '', fn ($query) => $query
                ->where('nidn', 'like', "%{$q}%")
                ->orWhere('nip', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")))
            ->orderBy('nidn')
            ->limit(20)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'label' => trim(($l->nidn ?? $l->nip ?? '-').' - '.($l->user?->name ?? '-')),
            ])
            ->all();

        return response()->json(['options' => $lecturers]);
    }

    // ── Generate sesi ─────────────────────────────────────────────

    public function generate(Request $request, CourseOffering $courseOffering)
    {
        $validated = $request->validate(['confirm' => 'required|accepted']);

        try {
            $count = app(GenerateAttendanceSessionsService::class)->generate($courseOffering->fresh());
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Berhasil membuat {$count} sesi pertemuan.");
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = CourseOffering::query()->with(['academicYear:id,name', 'studyProgram:id,name', 'course:id,code,name']);

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('label', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['MK', 'Label', 'Tahun', 'Prodi', 'Semester', 'Kapasitas', 'Mode', 'Status', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $offering) {
            $sheet->fromArray([
                $offering->course ? "{$offering->course->code} - {$offering->course->name}" : null,
                $offering->label,
                $offering->academicYear?->name,
                $offering->studyProgram?->name,
                $offering->semester_no,
                $offering->capacity,
                $offering->delivery_mode,
                $offering->status,
                $offering->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'course-offerings-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['academic_year_code', 'program_code', 'course_code', 'label', 'semester_no', 'capacity', 'delivery_mode', 'status']], null, 'A1');
        $sheet->fromArray([['2627G', 'TI', 'TI101', 'A', '1', '40', 'Offline', 'Draft']], null, 'A2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-course-offerings.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable) {
            return back()->with('error', 'File tidak bisa dibaca. Gunakan template yang disediakan.');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray();
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows ? array_shift($rows) : []);
        $missing = array_diff(['academic_year_code', 'program_code', 'course_code'], $header);

        if (! empty($missing)) {
            return back()->with('error', 'Header wajib hilang: '.implode(', ', $missing).'. Unduh template terbaru.');
        }

        $rows = array_values(array_filter(
            $rows,
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        ));

        if (empty($rows)) {
            return back()->with('error', 'File tidak berisi data.');
        }

        if (count($rows) > 500) {
            return back()->with('error', 'Maksimal 500 baris per import.');
        }

        $yearMap = AcademicYear::pluck('id', 'code')->all();
        $programMap = StudyProgram::pluck('id', 'code')->all();
        $courseMap = Course::pluck('id', 'code')->all();
        $seen = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_combine(
                $header,
                array_pad(array_slice($row, 0, count($header)), count($header), null)
            );
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
            $rowErrors = [];

            $yearCode = $data['academic_year_code'] ?? null;
            $programCode = $data['program_code'] ?? null;
            $courseCode = $data['course_code'] ?? null;
            $label = ($data['label'] ?? null) === '' ? null : ($data['label'] ?? null);

            if (! $yearCode || ! isset($yearMap[$yearCode])) {
                $rowErrors[] = "tahun akademik \"{$yearCode}\" tidak ditemukan";
            }

            if (! $programCode || ! isset($programMap[$programCode])) {
                $rowErrors[] = "prodi \"{$programCode}\" tidak ditemukan";
            }

            if (! $courseCode || ! isset($courseMap[$courseCode])) {
                $rowErrors[] = "MK \"{$courseCode}\" tidak ditemukan";
            }

            $combo = ($yearCode ?? '').'|'.($programCode ?? '').'|'.($courseCode ?? '').'|'.($label ?? '');

            if (in_array($combo, $seen, true)) {
                $rowErrors[] = 'kombinasi tahun+prodi+MK+label duplikat di dalam file';
            } else {
                $seen[] = $combo;
            }

            if (empty($rowErrors) && $yearCode && $programCode && $courseCode
                && isset($yearMap[$yearCode], $programMap[$programCode], $courseMap[$courseCode])
                && CourseOffering::where('academic_year_id', $yearMap[$yearCode])
                    ->where('study_program_id', $programMap[$programCode])
                    ->where('course_id', $courseMap[$courseCode])
                    ->where('label', $label)
                    ->exists()) {
                $rowErrors[] = 'kelas sudah dibuka (duplikat kombinasi)';
            }

            $semester = $data['semester_no'] ?? null;

            if ($semester !== null && $semester !== '' && (! is_numeric($semester) || (int) $semester < 1 || (int) $semester > 14)) {
                $rowErrors[] = 'semester_no harus 1–14';
            }

            $capacity = $data['capacity'] ?? null;

            if ($capacity !== null && $capacity !== '' && (! is_numeric($capacity) || (int) $capacity < 1)) {
                $rowErrors[] = 'capacity minimal 1';
            }

            $mode = ($data['delivery_mode'] ?? null) === '' || ($data['delivery_mode'] ?? null) === null ? 'Offline' : $data['delivery_mode'];

            if (! in_array($mode, self::DELIVERY_MODES, true)) {
                $rowErrors[] = 'delivery_mode harus Offline/Online/Hybrid';
            }

            $status = ($data['status'] ?? null) === '' || ($data['status'] ?? null) === null ? 'Draft' : $data['status'];

            if (! in_array($status, self::STATUSES, true)) {
                $rowErrors[] = 'status harus Draft/Open/Closed/Cancelled';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = [
                'academic_year_id' => $yearMap[$yearCode],
                'study_program_id' => $programMap[$programCode],
                'course_id' => $courseMap[$courseCode],
                'label' => $label,
                'semester_no' => ($semester === null || $semester === '') ? null : (int) $semester,
                'capacity' => ($capacity === null || $capacity === '') ? null : (int) $capacity,
                'delivery_mode' => $mode,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.course-offerings.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        CourseOffering::insert($valid);

        return redirect()->route('admin.academic.course-offerings.index')
            ->with('success', count($valid).' kelas berhasil dibuka. Tugaskan dosen dan susun jadwal dari workspace masing-masing.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function validatePayload(Request $request, bool $isUpdate = false, ?int $ignoreId = null): array
    {
        $uniqueCourse = Rule::unique('course_offerings', 'course_id')
            ->where(fn ($query) => $query
                ->where('academic_year_id', $request->input('academic_year_id'))
                ->where('study_program_id', $request->input('study_program_id'))
                ->where('label', $request->input('label')));

        if ($ignoreId) {
            $uniqueCourse->ignore($ignoreId);
        }

        return $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'study_program_id' => 'required|integer|exists:study_programs,id',
            'curriculum_id' => 'nullable|integer|exists:curriculums,id',
            'course_id' => ['required', 'integer', 'exists:courses,id', $uniqueCourse],
            'label' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:100',
            'semester_no' => 'nullable|integer|min:1|max:14',
            'capacity' => 'nullable|integer|min:1',
            'credits' => 'nullable|integer|min:1|max:24',
            'total_meetings' => 'nullable|integer|min:1|max:32',
            'class_start_date' => 'nullable|date',
            'class_end_date' => 'nullable|date|after_or_equal:class_start_date',
            'is_required' => 'boolean',
            'delivery_mode' => 'required|in:'.implode(',', self::DELIVERY_MODES),
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'notes' => 'nullable|string',
        ]);
    }

    private function deleteBlocker(CourseOffering $offering): ?string
    {
        if ($offering->courseSchedules()->exists()) {
            return 'Kelas penawaran tidak dapat dihapus karena sudah memiliki jadwal perkuliahan.';
        }

        if ($offering->attendanceSessions()->exists() || $offering->assignments()->exists()) {
            return 'Kelas penawaran tidak dapat dihapus karena sudah memiliki data absensi atau tugas.';
        }

        return null;
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'Open' => 'green',
            'Closed' => 'gray',
            'Cancelled' => 'red',
            default => 'amber',
        };
    }

    /**
     * Mahasiswa terdaftar (KRS Approved + status Taken), unik per mahasiswa.
     *
     * @return array<int, array{nim: ?string, name: string, program: string}>
     */
    private function enrolledStudents(int $offeringId): array
    {
        return StudyPlanDetail::query()
            ->where('course_offering_id', $offeringId)
            ->where('status', 'Taken')
            ->whereHas('studyPlan', fn ($q) => $q->where('status', 'Approved'))
            ->with(['studyPlan.studentProfile.user:id,first_name,last_name', 'studyPlan.studentProfile.studyProgram:id,name', 'studyPlan.studentProfile:id,nim,user_id,study_program_id'])
            ->get()
            ->map(fn ($detail) => $detail->studyPlan?->studentProfile)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($student) => [
                'nim' => $student->nim,
                'name' => $student->user?->name ?? '-',
                'program' => $student->studyProgram?->name ?? '-',
            ])
            ->all();
    }

    private function formOptions(): array
    {
        return [
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'programs' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'curriculums' => Curriculum::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'courses' => Course::query()->where('is_active', true)->orderBy('code')
                ->get(['id', 'code', 'name', 'credits'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->code} — {$c->name} ({$c->credits} SKS)"])->all(),
        ];
    }
}
