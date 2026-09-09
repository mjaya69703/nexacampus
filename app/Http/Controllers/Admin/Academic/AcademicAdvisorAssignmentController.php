<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Support\AcademicAdvisorService;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Dosen Wali — memakai kit CRUD shared.
 *
 * Paritas Blade lama + pemudah operator:
 * - Pencarian async mahasiswa/dosen (ganti limit-8 Livewire).
 * - Jelajah mahasiswa per prodi/angkatan + seleksi lintas halaman
 *   untuk bulk assign kohort (ganti limit-40).
 * - Transfer massal: pindahkan bimbingan ke dosen lain sekaligus,
 *   konflik dilewati dengan laporan (fitur baru).
 * - Guard konflik aktif dipakai di toggle, simpan, ubah, transfer.
 */
class AcademicAdvisorAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'year' => 'nullable|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'lecturer' => 'nullable|integer|exists:lecturer_profiles,id',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user:id,first_name,last_name', 'studentProfile:id,nim,user_id', 'lecturerProfile.user:id,first_name,last_name', 'lecturerProfile:id,nidn', 'academicYear:id,name']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

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

        if (filled($validated['lecturer'] ?? null)) {
            $query->where('lecturer_profile_id', $validated['lecturer']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $assignments = $query->paginate($perPage)->withQueryString();
        $service = app(AcademicAdvisorService::class);

        return Inertia::render('Admin/Academic/AdvisorAssignment/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Dosen Wali'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('academic-advisor-assignment.create'),
                'update' => ActivePermission::check('academic-advisor-assignment.update'),
                'delete' => ActivePermission::check('academic-advisor-assignment.delete'),
                'view' => ActivePermission::check('academic-advisor-assignment.view'),
                'restore' => ActivePermission::any(['academic-advisor-assignment.update', 'academic-advisor-assignment.delete']),
                'toggle' => ActivePermission::check('academic-advisor-assignment.update'),
                'transfer' => ActivePermission::check('academic-advisor-assignment.update'),
            ],
            'stats' => [
                'total' => AcademicAdvisorAssignment::count(),
                'active' => AcademicAdvisorAssignment::where('is_active', true)->count(),
                'students' => AcademicAdvisorAssignment::distinct()->count('student_profile_id'),
                'trashed' => AcademicAdvisorAssignment::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($assignments->items())->values()->map(fn ($a, $i) => [
                    'id' => $a->id,
                    'no' => ($assignments->firstItem() ?? 0) + $i,
                    'student' => trim(($a->studentProfile?->user?->first_name ?? '').' '.($a->studentProfile?->user?->last_name ?? '')) ?: '-',
                    'nim' => $a->studentProfile?->nim,
                    'advisor' => trim(($a->lecturerProfile?->user?->first_name ?? '').' '.($a->lecturerProfile?->user?->last_name ?? '')) ?: '-',
                    'advisorLabel' => $a->lecturerProfile ? $service->lecturerLabel($a->lecturerProfile) : '-',
                    'year' => $a->academicYear?->name ?? 'Umum',
                    'startDate' => $a->start_date?->format('d M Y'),
                    'endDate' => $a->end_date?->format('d M Y'),
                    'isActive' => (bool) $a->is_active,
                    'createdAt' => $a->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.academic.academic-advisor-assignments.show', $a->id),
                    'editUrl' => $isTrash ? null : route('admin.academic.academic-advisor-assignments.edit', $a->id),
                    'deleteUrl' => route('admin.academic.academic-advisor-assignments.destroy', $a->id),
                    'restoreUrl' => route('admin.academic.academic-advisor-assignments.restore', $a->id),
                    'forceUrl' => route('admin.academic.academic-advisor-assignments.force-destroy', $a->id),
                    'toggleUrl' => route('admin.academic.academic-advisor-assignments.toggle', $a->id),
                ])->all(),
                'currentPage' => $assignments->currentPage(),
                'lastPage' => $assignments->lastPage(),
                'perPage' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'year' => $validated['year'] ?? '',
                'program' => $validated['program'] ?? '',
                'lecturer' => $validated['lecturer'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.academic-advisor-assignments.index'),
                'create' => route('admin.academic.academic-advisor-assignments.create'),
                'export' => route('admin.academic.academic-advisor-assignments.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'academic-advisor-assignments']),
                'importTemplate' => route('admin.academic.academic-advisor-assignments.import-template'),
                'importSubmit' => route('admin.academic.academic-advisor-assignments.import'),
                'bulkDestroy' => route('admin.academic.academic-advisor-assignments.bulk-destroy'),
                'bulkRestore' => route('admin.academic.academic-advisor-assignments.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.academic-advisor-assignments.bulk-force-destroy'),
                'transfer' => route('admin.academic.academic-advisor-assignments.transfer'),
                'searchStudents' => route('admin.academic.academic-advisor-assignments.search-students'),
                'searchLecturers' => route('admin.academic.academic-advisor-assignments.search-lecturers'),
                'browseStudents' => route('admin.academic.academic-advisor-assignments.browse-students'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/AdvisorAssignment/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Dosen Wali'),
            'mode' => 'create',
            'assignment' => [
                'lecturer_profile_id' => '', 'student_profile_id' => '', 'academic_year_id' => '',
                'start_date' => '', 'end_date' => '', 'is_active' => true, 'notes' => '',
                'selected_student_ids' => [],
            ],
            'years' => $this->yearOptions(),
            'programs' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'entryYears' => StudentProfile::query()->whereNotNull('entry_year')
                ->distinct()->orderByDesc('entry_year')->pluck('entry_year')->all(),
            'urls' => [
                'index' => route('admin.academic.academic-advisor-assignments.index'),
                'submit' => route('admin.academic.academic-advisor-assignments.store'),
                'searchStudents' => route('admin.academic.academic-advisor-assignments.search-students'),
                'searchLecturers' => route('admin.academic.academic-advisor-assignments.search-lecturers'),
                'browseStudents' => route('admin.academic.academic-advisor-assignments.browse-students'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $isBulk = $request->input('assign_mode') === 'bulk';

        $rules = [
            'assign_mode' => 'required|in:single,bulk',
            'lecturer_profile_id' => 'required|integer|exists:lecturer_profiles,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];

        if ($isBulk) {
            $rules['selected_student_ids'] = 'required|array|min:1';
            $rules['selected_student_ids.*'] = 'integer|exists:student_profiles,id';
        } else {
            $rules['student_profile_id'] = 'required|integer|exists:student_profiles,id';
        }

        $validated = $request->validate($rules);

        $payload = [
            'lecturer_profile_id' => $validated['lecturer_profile_id'],
            'academic_year_id' => $validated['academic_year_id'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ];

        $service = app(AcademicAdvisorService::class);

        try {
            if ($validated['assign_mode'] === 'single') {
                $service->createAssignment($payload + ['student_profile_id' => $validated['student_profile_id']]);

                return redirect()->route('admin.academic.academic-advisor-assignments.index')
                    ->with('success', 'Penugasan dosen wali berhasil ditambahkan.');
            }

            $result = $service->bulkAssign($validated['selected_student_ids'] ?? [], $payload);
            $created = $result['created'] ?? 0;
            $skipped = $result['skipped'] ?? [];

            if ($created > 0 && empty($skipped)) {
                return redirect()->route('admin.academic.academic-advisor-assignments.index')
                    ->with('success', "Dibuat: {$created} penugasan.");
            }

            if ($created > 0) {
                return redirect()->route('admin.academic.academic-advisor-assignments.index')
                    ->with('warning', "Dibuat: {$created}. Dilewati: ".count($skipped).' (bentrok).');
            }

            return back()->with('error', 'Semua baris dilewati karena bentrok dengan penugasan aktif.')->withInput();
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first() ?: 'Penugasan bentrok.')->withInput();
        }
    }

    public function show(Request $request, int $id): Response
    {
        $assignment = AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'lecturerProfile.user', 'academicYear'])
            ->findOrFail($id);

        $service = app(AcademicAdvisorService::class);

        return Inertia::render('Admin/Academic/AdvisorAssignment/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail Dosen Wali'),
            'assignment' => [
                'id' => $assignment->id,
                'student' => trim(($assignment->studentProfile?->user?->first_name ?? '').' '.($assignment->studentProfile?->user?->last_name ?? '')) ?: '-',
                'nim' => $assignment->studentProfile?->nim,
                'program' => $assignment->studentProfile?->studyProgram?->name,
                'advisor' => $assignment->lecturerProfile ? $service->lecturerLabel($assignment->lecturerProfile) : '-',
                'year' => $assignment->academicYear?->name ?? 'Umum (semua tahun)',
                'startDate' => $assignment->start_date?->format('d M Y'),
                'endDate' => $assignment->end_date?->format('d M Y'),
                'isActive' => (bool) $assignment->is_active,
                'notes' => $assignment->notes,
                'createdAt' => $assignment->created_at?->format('d M Y H:i'),
            ],
            'canUpdate' => ActivePermission::check('academic-advisor-assignment.update'),
            'urls' => [
                'index' => route('admin.academic.academic-advisor-assignments.index'),
                'edit' => route('admin.academic.academic-advisor-assignments.edit', $assignment),
            ],
        ]);
    }

    public function edit(Request $request, AcademicAdvisorAssignment $academicAdvisorAssignment): Response
    {
        $academicAdvisorAssignment->load(['studentProfile.user', 'lecturerProfile.user']);

        return Inertia::render('Admin/Academic/AdvisorAssignment/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Dosen Wali'),
            'mode' => 'edit',
            'assignment' => [
                'id' => $academicAdvisorAssignment->id,
                'lecturer_profile_id' => $academicAdvisorAssignment->lecturer_profile_id,
                'lecturer_label' => $academicAdvisorAssignment->lecturerProfile ? app(AcademicAdvisorService::class)->lecturerLabel($academicAdvisorAssignment->lecturerProfile) : '',
                'student_profile_id' => $academicAdvisorAssignment->student_profile_id,
                'student_label' => trim(($academicAdvisorAssignment->studentProfile?->nim ? $academicAdvisorAssignment->studentProfile->nim.' - ' : '').($academicAdvisorAssignment->studentProfile?->user?->name ?? '')),
                'academic_year_id' => $academicAdvisorAssignment->academic_year_id ?? '',
                'start_date' => $academicAdvisorAssignment->start_date?->format('Y-m-d') ?? '',
                'end_date' => $academicAdvisorAssignment->end_date?->format('Y-m-d') ?? '',
                'is_active' => (bool) $academicAdvisorAssignment->is_active,
                'notes' => $academicAdvisorAssignment->notes ?? '',
                'selected_student_ids' => [],
            ],
            'years' => $this->yearOptions(),
            'urls' => [
                'index' => route('admin.academic.academic-advisor-assignments.index'),
                'submit' => route('admin.academic.academic-advisor-assignments.update', $academicAdvisorAssignment),
                'searchStudents' => route('admin.academic.academic-advisor-assignments.search-students'),
                'searchLecturers' => route('admin.academic.academic-advisor-assignments.search-lecturers'),
                'browseStudents' => route('admin.academic.academic-advisor-assignments.browse-students'),
            ],
        ]);
    }

    public function update(Request $request, AcademicAdvisorAssignment $academicAdvisorAssignment)
    {
        $validated = $request->validate([
            'lecturer_profile_id' => 'required|integer|exists:lecturer_profiles,id',
            'student_profile_id' => 'required|integer|exists:student_profiles,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            app(AcademicAdvisorService::class)->updateAssignment($academicAdvisorAssignment, [
                'lecturer_profile_id' => $validated['lecturer_profile_id'],
                'student_profile_id' => $validated['student_profile_id'],
                'academic_year_id' => $validated['academic_year_id'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first() ?: 'Penugasan bentrok.')->withInput();
        }

        return redirect()->route('admin.academic.academic-advisor-assignments.index')
            ->with('success', 'Penugasan dosen wali berhasil diperbarui.');
    }

    public function toggle(Request $request, AcademicAdvisorAssignment $academicAdvisorAssignment)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        if ($validated['is_active']) {
            try {
                app(AcademicAdvisorService::class)->ensureNoActiveConflict(
                    studentProfileId: $academicAdvisorAssignment->student_profile_id,
                    academicYearId: $academicAdvisorAssignment->academic_year_id,
                    startDate: $academicAdvisorAssignment->start_date,
                    endDate: $academicAdvisorAssignment->end_date,
                    ignoreId: $academicAdvisorAssignment->id,
                );
            } catch (ValidationException $exception) {
                return back()->with('error', $exception->validator->errors()->first() ?: 'Penugasan aktif bentrok dengan penugasan lain.');
            }
        }

        $academicAdvisorAssignment->update([
            'is_active' => $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status penugasan berhasil diperbarui.');
    }

    /**
     * Transfer massal bimbingan ke dosen lain (fitur baru).
     * Konflik dilewati satu per satu dengan laporan.
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_advisor_assignments,id',
            'lecturer_profile_id' => 'required|integer|exists:lecturer_profiles,id',
        ]);

        $service = app(AcademicAdvisorService::class);
        $moved = 0;
        $skipped = 0;

        foreach (AcademicAdvisorAssignment::whereIn('id', $validated['ids'])->get() as $assignment) {
            try {
                $service->updateAssignment($assignment, [
                    'lecturer_profile_id' => $validated['lecturer_profile_id'],
                    'student_profile_id' => $assignment->student_profile_id,
                    'academic_year_id' => $assignment->academic_year_id,
                    'start_date' => $assignment->start_date?->format('Y-m-d'),
                    'end_date' => $assignment->end_date?->format('Y-m-d'),
                    'is_active' => $assignment->is_active,
                    'notes' => $assignment->notes,
                    'updated_by' => $request->user()->id,
                ]);
                $moved++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        if ($moved > 0 && $skipped > 0) {
            return back()->with('warning', "Dipindahkan: {$moved}. Dilewati (bentrok): {$skipped}.");
        }

        if ($moved > 0) {
            return back()->with('success', "Dipindahkan: {$moved} bimbingan.");
        }

        return back()->with('error', 'Semua baris dilewati karena bentrok dengan penugasan aktif.');
    }

    public function destroy(AcademicAdvisorAssignment $academicAdvisorAssignment)
    {
        $academicAdvisorAssignment->update(['deleted_by' => auth()->id()]);
        $academicAdvisorAssignment->delete();

        return redirect()->route('admin.academic.academic-advisor-assignments.index')
            ->with('success', 'Penugasan dosen wali berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_advisor_assignments,id',
        ]);

        $count = AcademicAdvisorAssignment::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' penugasan berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $assignment = AcademicAdvisorAssignment::withTrashed()->findOrFail($id);
        $assignment->restore();

        return back()->with('success', 'Penugasan dosen wali berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $assignment = AcademicAdvisorAssignment::withTrashed()->findOrFail($id);
        $assignment->forceDelete();

        return back()->with('success', 'Penugasan dosen wali dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_advisor_assignments,id',
        ]);

        $count = 0;

        foreach (AcademicAdvisorAssignment::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $assignment) {
            $assignment->restore();
            $count++;
        }

        return back()->with('success', $count.' penugasan berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_advisor_assignments,id',
        ]);

        $count = 0;

        foreach (AcademicAdvisorAssignment::withTrashed()->whereIn('id', $validated['ids'])->get() as $assignment) {
            $assignment->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' penugasan dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'lecturerProfile.user', 'academicYear']);

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('academic_advisor_assignments.id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();
        $service = app(AcademicAdvisorService::class);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Dosen PA', 'Tahun', 'Mulai', 'Selesai', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $assignment) {
            $sheet->fromArray([
                trim(($assignment->studentProfile?->user?->first_name ?? '').' '.($assignment->studentProfile?->user?->last_name ?? '')),
                $assignment->studentProfile?->nim,
                $assignment->lecturerProfile ? $service->lecturerLabel($assignment->lecturerProfile) : null,
                $assignment->academicYear?->name ?? 'Umum',
                $assignment->start_date?->format('Y-m-d'),
                $assignment->end_date?->format('Y-m-d'),
                $assignment->is_active ? 'Ya' : 'Tidak',
                $assignment->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'advisor-assignments-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['nim', 'lecturer_nidn', 'academic_year_code', 'start_date', 'end_date', 'is_active', 'notes']], null, 'A1');
        $sheet->fromArray([['2026TI0001', '1001', '2627G', '2026-08-01', '2027-01-31', '1', '']], null, 'A2');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-advisor-assignments.xlsx',
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
        $missing = array_diff(['nim', 'lecturer_nidn'], $header);

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

        $service = app(AcademicAdvisorService::class);
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

            $student = ! empty($data['nim']) ? StudentProfile::where('nim', $data['nim'])->first() : null;

            if (! $student) {
                $rowErrors[] = 'mahasiswa NIM "'.($data['nim'] ?? '').'" tidak ditemukan';
            }

            $lecturer = ! empty($data['lecturer_nidn'])
                ? LecturerProfile::where('nidn', $data['lecturer_nidn'])->orWhere('nip', $data['lecturer_nidn'])->first()
                : null;

            if (! $lecturer) {
                $rowErrors[] = 'dosen "'.($data['lecturer_nidn'] ?? '').'" tidak ditemukan (by NIDN/NIP)';
            }

            $yearId = null;
            $yearCode = $data['academic_year_code'] ?? null;

            if ($yearCode !== null && $yearCode !== '') {
                $yearId = AcademicYear::where('code', $yearCode)->value('id');

                if (! $yearId) {
                    $rowErrors[] = "tahun akademik \"{$yearCode}\" tidak ditemukan";
                }
            }

            $start = $this->parseImportDate($data['start_date'] ?? null);
            $end = $this->parseImportDate($data['end_date'] ?? null);

            if (! empty($data['start_date']) && $start === null) {
                $rowErrors[] = 'start_date tidak valid (Y-m-d)';
            }

            if (! empty($data['end_date']) && $end === null) {
                $rowErrors[] = 'end_date tidak valid (Y-m-d)';
            } elseif ($start !== null && $end !== null && $end < $start) {
                $rowErrors[] = 'end_date minimal sama dengan start_date';
            }

            $isActive = $this->parseImportBool($data['is_active'] ?? null, true);

            if (empty($rowErrors) && $isActive) {
                try {
                    $service->ensureNoActiveConflict(
                        studentProfileId: $student->id,
                        academicYearId: $yearId,
                        startDate: $start,
                        endDate: $end,
                    );
                } catch (ValidationException $exception) {
                    $rowErrors[] = $exception->validator->errors()->first() ?: 'Bentrok penugasan aktif';
                }
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = [
                'student_profile_id' => $student->id,
                'lecturer_profile_id' => $lecturer->id,
                'academic_year_id' => $yearId,
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => $isActive,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.academic-advisor-assignments.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        foreach ($valid as $payload) {
            $service->createAssignment($payload);
        }

        return redirect()->route('admin.academic.academic-advisor-assignments.index')
            ->with('success', count($valid).' penugasan berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function parseImportDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }

    /**
     * Pencarian async mahasiswa (ganti limit-8 Livewire).
     */
    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = StudentProfile::query()
            ->with(['user:id,first_name,last_name,email', 'studyProgram:id,name'])
            ->when($q !== '', fn ($query) => $query
                ->where('nim', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")))
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

    /**
     * Pencarian async dosen (ganti limit-8 Livewire).
     */
    public function searchLecturers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $service = app(AcademicAdvisorService::class);

        $lecturers = LecturerProfile::query()
            ->with('user:id,first_name,last_name')
            ->when($q !== '', fn ($query) => $query
                ->where('nidn', 'like', "%{$q}%")
                ->orWhere('nidk', 'like', "%{$q}%")
                ->orWhere('nip', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")))
            ->orderBy('nidn')
            ->limit(20)
            ->get()
            ->map(fn ($l) => ['id' => $l->id, 'label' => $service->lecturerLabel($l)])
            ->all();

        return response()->json(['options' => $lecturers]);
    }

    /**
     * Jelajah mahasiswa per prodi/angkatan + paginasi untuk bulk kohort.
     */
    public function browseStudents(Request $request)
    {
        $validated = $request->validate([
            'program' => 'nullable|integer|exists:study_programs,id',
            'entry_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'semester' => 'nullable|integer|min:1|max:14',
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = StudentProfile::query()
            ->with(['user:id,first_name,last_name', 'studyProgram:id,name'])
            ->orderBy('nim');

        if (filled($validated['program'] ?? null)) {
            $query->where('study_program_id', $validated['program']);
        }

        if (filled($validated['entry_year'] ?? null)) {
            $query->where('entry_year', $validated['entry_year']);
        }

        if (filled($validated['semester'] ?? null)) {
            $query->where('current_semester', $validated['semester']);
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('nim', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")));
        }

        $students = $query->paginate($validated['per_page'] ?? 25);

        if ($request->boolean('all_ids')) {
            return response()->json([
                'ids' => (clone $query)->limit(2000)->pluck('student_profiles.id')->all(),
            ]);
        }

        return response()->json([
            'rows' => collect($students->items())->map(fn ($s) => [
                'id' => $s->id,
                'nim' => $s->nim,
                'name' => $s->user?->name ?? '-',
                'program' => $s->studyProgram?->name ?? '-',
                'entryYear' => $s->entry_year,
                'semester' => $s->current_semester,
            ])->all(),
            'currentPage' => $students->currentPage(),
            'lastPage' => $students->lastPage(),
            'total' => $students->total(),
        ]);
    }

    /**
     * @return array<int, array{id: int|string, name: string}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])
            ->prepend(['id' => '', 'name' => 'Umum (semua tahun)'])
            ->values()
            ->all();
    }
}
