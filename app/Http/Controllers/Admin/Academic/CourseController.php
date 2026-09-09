<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\Course;
use App\Models\Academic\CourseScope;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Mata Kuliah — memakai kit CRUD shared (pola fakultas/prodi).
 *
 * Paritas form Blade lama: scope samping (global/fakultas/prodi via
 * CourseScope), prasyarat M2M self-reference (tanpa diri sendiri),
 * kode unik manual, scope aktif wajib untuk MK aktif — dibungkus
 * transaksi database (lebih aman dari Blade yang 3 tulis non-atomik).
 */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'scope_type' => 'nullable|in:global,faculty,study_program',
            'scope_faculty' => 'nullable|integer|exists:faculties,id',
            'scope_program' => 'nullable|integer|exists:study_programs,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'requirement' => 'nullable|string|max:20',
            'category' => 'nullable|string|max:30',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,code,name,credits,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Course::query()->with(['latestScope.faculty', 'latestScope.studyProgram', 'prerequisites:id,code']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('short_name', 'like', "%{$q}%"));
        }

        if (filled($validated['scope_type'] ?? null)) {
            $type = $validated['scope_type'];
            $query->whereHas('latestScope', fn ($s) => $s->where('scope_type', $type));
        }

        if (filled($validated['scope_faculty'] ?? null)) {
            $facultyId = $validated['scope_faculty'];
            $query->whereHas('latestScope', fn ($s) => $s->where('scope_type', 'faculty')->where('scope_id', $facultyId));
        }

        if (filled($validated['scope_program'] ?? null)) {
            $programId = $validated['scope_program'];
            $query->whereHas('latestScope', fn ($s) => $s->where('scope_type', 'study_program')->where('scope_id', $programId));
        }

        foreach (['semester' => 'semester_recommendation', 'requirement' => 'requirement_type', 'category' => 'category_type'] as $input => $column) {
            if (filled($validated[$input] ?? null)) {
                $query->where($column, $validated[$input]);
            }
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $courses = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/Course/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Mata Kuliah'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('course.create'),
                'update' => ActivePermission::check('course.update'),
                'delete' => ActivePermission::check('course.delete'),
                'restore' => ActivePermission::any(['course.update', 'course.delete']),
                'toggle' => ActivePermission::check('course.update'),
            ],
            'stats' => [
                'total' => Course::count(),
                'active' => Course::where('is_active', true)->count(),
                'credits' => (int) Course::sum('credits'),
                'trashed' => Course::onlyTrashed()->count(),
            ],
            'maxSemester' => (int) Course::max('semester_recommendation'),
            'data' => [
                'rows' => collect($courses->items())->values()->map(fn ($c, $i) => [
                    'id' => $c->id,
                    'no' => ($courses->firstItem() ?? 0) + $i,
                    'code' => $c->code,
                    'name' => $c->name,
                    'credits' => (int) $c->credits,
                    'semester' => $c->semester_recommendation,
                    'scopeType' => $c->latestScope?->scope_type,
                    'scopeLabel' => $this->scopeLabel($c->latestScope),
                    'scopeName' => $this->scopeName($c->latestScope),
                    'requirement' => $c->requirement_type,
                    'category' => $c->category_type,
                    'prerequisites' => $c->prerequisites->pluck('code')->all(),
                    'isActive' => (bool) $c->is_active,
                    'createdAt' => $c->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.academic.courses.edit', $c->id),
                    'deleteUrl' => route('admin.academic.courses.destroy', $c->id),
                    'restoreUrl' => route('admin.academic.courses.restore', $c->id),
                    'forceUrl' => route('admin.academic.courses.force-destroy', $c->id),
                    'toggleUrl' => route('admin.academic.courses.toggle', $c->id),
                ])->all(),
                'currentPage' => $courses->currentPage(),
                'lastPage' => $courses->lastPage(),
                'perPage' => $courses->perPage(),
                'total' => $courses->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'scope_type' => $validated['scope_type'] ?? '',
                'scope_faculty' => $validated['scope_faculty'] ?? '',
                'scope_program' => $validated['scope_program'] ?? '',
                'semester' => $validated['semester'] ?? '',
                'requirement' => $validated['requirement'] ?? '',
                'category' => $validated['category'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'faculties' => Faculty::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all(),
            'programs' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.courses.index'),
                'create' => route('admin.academic.courses.create'),
                'export' => route('admin.academic.courses.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'courses']),
                'importTemplate' => route('admin.academic.courses.import-template'),
                'importSubmit' => route('admin.academic.courses.import'),
                'bulkDestroy' => route('admin.academic.courses.bulk-destroy'),
                'bulkRestore' => route('admin.academic.courses.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.courses.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/Course/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Mata Kuliah'),
            'mode' => 'create',
            'course' => [
                'scope_type' => 'global', 'scope_id' => '', 'code' => '', 'name' => '',
                'short_name' => '', 'credits' => 2, 'semester_recommendation' => '',
                'requirement_type' => 'Wajib', 'category_type' => 'Keilmuan',
                'is_active' => true, 'desc' => '', 'prerequisite_ids' => [],
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.academic.courses.index'),
                'submit' => route('admin.academic.courses.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $scopeError = $this->checkScope($validated['scope_type'], $validated['scope_id'] ?? null, $validated['is_active'] ?? true);

        if ($scopeError) {
            return back()->with('error', $scopeError)->withInput();
        }

        if (Course::where('code', $validated['code'])->exists()) {
            return back()->withErrors(['code' => 'Kode mata kuliah sudah digunakan.'])->withInput();
        }

        DB::transaction(function () use ($validated, $request) {
            $course = Course::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'short_name' => $validated['short_name'] ?? null,
                'credits' => $validated['credits'],
                'semester_recommendation' => $validated['semester_recommendation'] ?? null,
                'requirement_type' => $validated['requirement_type'],
                'category_type' => $validated['category_type'],
                'is_active' => $validated['is_active'] ?? true,
                'desc' => $validated['desc'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            CourseScope::create([
                'course_id' => $course->id,
                'scope_type' => $validated['scope_type'],
                'scope_id' => $validated['scope_type'] === 'global' ? null : $validated['scope_id'],
                'created_by' => $request->user()->id,
            ]);

            $course->prerequisites()->sync($validated['prerequisite_ids'] ?? []);
        });

        return redirect()->route('admin.academic.courses.index')
            ->with('success', 'Mata kuliah "'.$validated['code'].'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Course $course): Response
    {
        $course->load(['latestScope', 'prerequisites:id']);

        return Inertia::render('Admin/Academic/Course/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Mata Kuliah'),
            'mode' => 'edit',
            'course' => [
                'id' => $course->id,
                'scope_type' => $course->latestScope?->scope_type ?? 'global',
                'scope_id' => $course->latestScope?->scope_id ?? '',
                'code' => $course->code,
                'name' => $course->name,
                'short_name' => $course->short_name ?? '',
                'credits' => (int) $course->credits,
                'semester_recommendation' => $course->semester_recommendation ?? '',
                'requirement_type' => $course->requirement_type,
                'category_type' => $course->category_type,
                'is_active' => (bool) $course->is_active,
                'desc' => $course->desc ?? '',
                'prerequisite_ids' => $course->prerequisites->pluck('id')->all(),
            ],
            'options' => $this->formOptions($course->id),
            'urls' => [
                'index' => route('admin.academic.courses.index'),
                'submit' => route('admin.academic.courses.update', $course),
            ],
        ]);
    }

    public function update(Request $request, Course $course)
    {
        $validated = $this->validatePayload($request);

        if (in_array($course->id, array_map('intval', $validated['prerequisite_ids'] ?? []), true)) {
            return back()->withErrors(['prerequisite_ids' => 'Mata kuliah tidak bisa menjadi prasyarat untuk dirinya sendiri.'])->withInput();
        }

        $scopeError = $this->checkScope($validated['scope_type'], $validated['scope_id'] ?? null, $validated['is_active'] ?? true);

        if ($scopeError) {
            return back()->with('error', $scopeError)->withInput();
        }

        if (Course::where('code', $validated['code'])->where('id', '!=', $course->id)->exists()) {
            return back()->withErrors(['code' => 'Kode mata kuliah sudah digunakan.'])->withInput();
        }

        DB::transaction(function () use ($validated, $request, $course) {
            $course->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'short_name' => $validated['short_name'] ?? null,
                'credits' => $validated['credits'],
                'semester_recommendation' => $validated['semester_recommendation'] ?? null,
                'requirement_type' => $validated['requirement_type'],
                'category_type' => $validated['category_type'],
                'is_active' => $validated['is_active'] ?? true,
                'desc' => $validated['desc'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $course->scopes()->delete();

            CourseScope::create([
                'course_id' => $course->id,
                'scope_type' => $validated['scope_type'],
                'scope_id' => $validated['scope_type'] === 'global' ? null : $validated['scope_id'],
                'created_by' => $request->user()->id,
            ]);

            $course->prerequisites()->sync($validated['prerequisite_ids'] ?? []);
        });

        return redirect()->route('admin.academic.courses.index')
            ->with('success', 'Mata kuliah "'.$validated['code'].'" berhasil diperbarui.');
    }

    public function toggle(Request $request, Course $course)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        if ($validated['is_active'] && ! $this->scopeIsActive($course->latestScope)) {
            return back()->with('error', 'Mata kuliah hanya bisa aktif jika scope tujuan dalam kondisi aktif.');
        }

        $course->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status mata kuliah berhasil diperbarui.');
    }

    public function destroy(Course $course)
    {
        $blocker = $this->deleteBlocker($course);

        if ($blocker) {
            return back()->with('error', $blocker);
        }

        $code = $course->code;
        $course->delete();

        return redirect()->route('admin.academic.courses.index')
            ->with('success', 'Mata kuliah "'.$code.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:courses,id',
        ]);

        $courses = Course::whereIn('id', $validated['ids'])->get();
        $blocked = $courses->filter(fn ($c) => $this->deleteBlocker($c) !== null);
        $deletable = $courses->reject(fn ($c) => $this->deleteBlocker($c) !== null);

        foreach ($deletable as $course) {
            $course->delete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' MK dihapus. '.$blocked->count().' tidak bisa dihapus karena terikat kurikulum/prasyarat.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'MK tidak bisa dihapus karena terikat kurikulum atau menjadi prasyarat.');
        }

        return back()->with('success', $deletable->count().' MK berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $course = Course::withTrashed()->findOrFail($id);
        $course->restore();
        $course->scopes()->withTrashed()->restore();

        return back()->with('success', 'Mata kuliah "'.$course->code.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $course = Course::withTrashed()->findOrFail($id);

        if ($course->curriculumCourses()->exists() || $course->requiredByRows()->exists()) {
            return back()->with('error', 'MK tidak bisa dihapus karena terikat kurikulum atau menjadi prasyarat.');
        }

        $code = $course->code;
        $course->scopes()->withTrashed()->forceDelete();
        $course->prerequisiteRows()->withTrashed()->forceDelete();
        $course->forceDelete();

        return back()->with('success', 'Mata kuliah "'.$code.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:courses,id',
        ]);

        $count = 0;

        foreach (Course::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $course) {
            $course->restore();
            $course->scopes()->withTrashed()->restore();
            $count++;
        }

        return back()->with('success', $count.' MK berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:courses,id',
        ]);

        $courses = Course::withTrashed()->whereIn('id', $validated['ids'])->get();
        $blocked = $courses->filter(fn ($c) => $c->curriculumCourses()->exists() || $c->requiredByRows()->exists());
        $deletable = $courses->reject(fn ($c) => $c->curriculumCourses()->exists() || $c->requiredByRows()->exists());

        foreach ($deletable as $course) {
            $course->scopes()->withTrashed()->forceDelete();
            $course->prerequisiteRows()->withTrashed()->forceDelete();
            $course->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' MK dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena terikat kurikulum/prasyarat.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'MK tidak bisa dihapus karena terikat kurikulum atau menjadi prasyarat.');
        }

        return back()->with('success', $deletable->count().' MK dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Course::query()->with('latestScope');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('short_name', 'like', "%{$q}%"));
        }

        $rows = $query->orderBy('code')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Kode', 'Nama', 'SKS', 'Semester', 'Scope', 'Wajib/Pilihan', 'Kategori', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $course) {
            $sheet->fromArray([
                $course->code,
                $course->name,
                (int) $course->credits,
                $course->semester_recommendation,
                $this->scopeLabel($course->latestScope).($this->scopeName($course->latestScope) ? ' - '.$this->scopeName($course->latestScope) : ''),
                $course->requirement_type,
                $course->category_type,
                $course->is_active ? 'Ya' : 'Tidak',
                $course->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'courses-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['code', 'name', 'credits', 'scope', 'requirement_type', 'category_type', 'semester', 'prerequisite_codes', 'is_active']], null, 'A1');
        $sheet->fromArray([['TI101', 'Algoritma', '3', 'P:TI', 'Wajib', 'Keilmuan', '1', '', '1']], null, 'A2');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-courses.xlsx',
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
        $missing = array_diff(['code', 'name', 'credits'], $header);

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

        $facultyMap = Faculty::pluck('id', 'code')->all();
        $programMap = StudyProgram::pluck('id', 'code')->all();
        $prereqMap = Course::pluck('id', 'code')->all();
        $seenCodes = [];
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

            $code = $data['code'] ?? null;

            if ($code === null || $code === '') {
                $rowErrors[] = 'code wajib diisi';
            } else {
                if (strlen((string) $code) > 20) {
                    $rowErrors[] = 'code maksimal 20 karakter';
                } elseif (in_array($code, $seenCodes, true)) {
                    $rowErrors[] = 'code duplikat di dalam file';
                } elseif (Course::where('code', $code)->exists()) {
                    $rowErrors[] = 'code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
                }
            }

            if (($data['name'] ?? null) === null || $data['name'] === '') {
                $rowErrors[] = 'name wajib diisi';
            }

            $credits = $data['credits'] ?? null;

            if (! is_numeric($credits) || (int) $credits < 1 || (int) $credits > 24) {
                $rowErrors[] = 'credits harus 1–24';
            }

            $scopeRaw = trim((string) ($data['scope'] ?? ''));
            $scopeType = 'global';
            $scopeId = null;

            if ($scopeRaw !== '' && strtoupper($scopeRaw) !== 'GLOBAL') {
                if (preg_match('/^F:(.+)$/i', $scopeRaw, $m)) {
                    $facultyCode = trim($m[1]);

                    if (! isset($facultyMap[$facultyCode])) {
                        $rowErrors[] = "fakultas \"{$facultyCode}\" tidak ditemukan";
                    } else {
                        $scopeType = 'faculty';
                        $scopeId = $facultyMap[$facultyCode];
                    }
                } elseif (preg_match('/^P:(.+)$/i', $scopeRaw, $m)) {
                    $programCode = trim($m[1]);

                    if (! isset($programMap[$programCode])) {
                        $rowErrors[] = "prodi \"{$programCode}\" tidak ditemukan";
                    } else {
                        $scopeType = 'study_program';
                        $scopeId = $programMap[$programCode];
                    }
                } else {
                    $rowErrors[] = 'scope harus global, F:KODE, atau P:KODE';
                }
            }

            $requirement = $data['requirement_type'] ?? 'Wajib';

            if (! in_array($requirement, ['Wajib', 'Pilihan'], true)) {
                $rowErrors[] = 'requirement_type harus Wajib/Pilihan';
            }

            $category = $data['category_type'] ?? 'Keilmuan';

            if (! in_array($category, ['Umum', 'MKWU', 'MKU', 'Keilmuan', 'Praktikum', 'Tugas Akhir', 'Magang'], true)) {
                $rowErrors[] = 'category_type tidak valid';
            }

            $semester = $data['semester'] ?? null;

            if ($semester !== null && $semester !== '' && (! is_numeric($semester) || (int) $semester < 1 || (int) $semester > 14)) {
                $rowErrors[] = 'semester harus 1–14';
            }

            $prereqIds = [];

            if (! empty($data['prerequisite_codes'])) {
                foreach (explode(',', (string) $data['prerequisite_codes']) as $prereqCode) {
                    $prereqCode = trim($prereqCode);

                    if ($prereqCode === '') {
                        continue;
                    }

                    if (! isset($prereqMap[$prereqCode])) {
                        $rowErrors[] = "prasyarat \"{$prereqCode}\" tidak ditemukan";
                    } else {
                        $prereqIds[] = $prereqMap[$prereqCode];
                    }
                }
            }

            $isActive = $this->parseImportBool($data['is_active'] ?? null, true);

            if ($isActive) {
                $scopeError = $this->checkScope($scopeType, $scopeId, true);

                if ($scopeError) {
                    $rowErrors[] = $scopeError;
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
                'code' => $code,
                'name' => $data['name'],
                'short_name' => $data['short_name'] ?? null,
                'credits' => (int) $credits,
                'semester_recommendation' => ($semester === null || $semester === '') ? null : (int) $semester,
                'requirement_type' => $requirement,
                'category_type' => $category,
                'is_active' => $isActive,
                'desc' => $data['desc'] ?? null,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'prerequisite_ids' => array_values(array_unique($prereqIds)),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.courses.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        DB::transaction(function () use ($valid, $request) {
            foreach ($valid as $item) {
                $course = Course::create([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'short_name' => $item['short_name'],
                    'credits' => $item['credits'],
                    'semester_recommendation' => $item['semester_recommendation'],
                    'requirement_type' => $item['requirement_type'],
                    'category_type' => $item['category_type'],
                    'is_active' => $item['is_active'],
                    'desc' => $item['desc'],
                    'created_by' => $request->user()->id,
                ]);

                CourseScope::create([
                    'course_id' => $course->id,
                    'scope_type' => $item['scope_type'],
                    'scope_id' => $item['scope_id'],
                    'created_by' => $request->user()->id,
                ]);

                if (! empty($item['prerequisite_ids'])) {
                    $course->prerequisites()->sync($item['prerequisite_ids']);
                }
            }
        });

        return redirect()->route('admin.academic.courses.index')
            ->with('success', count($valid).' MK berhasil diimpor.')
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

    // ── Helpers ───────────────────────────────────────────────────────

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'scope_type' => 'required|in:faculty,study_program,global',
            'scope_id' => 'nullable|integer',
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:100',
            'credits' => 'required|integer|min:1|max:24',
            'semester_recommendation' => 'nullable|integer|min:1|max:14',
            'requirement_type' => 'required|in:Wajib,Pilihan',
            'category_type' => 'required|in:Umum,MKWU,MKU,Keilmuan,Praktikum,Tugas Akhir,Magang',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
            'prerequisite_ids' => 'nullable|array',
            'prerequisite_ids.*' => 'integer|exists:courses,id',
        ]);
    }

    private function checkScope(string $type, mixed $scopeId, bool $wantsActive): ?string
    {
        if ($type === 'global') {
            return null;
        }

        $scope = $type === 'faculty'
            ? Faculty::find($scopeId)
            : StudyProgram::find($scopeId);

        if (! $scope) {
            return 'Scope tujuan tidak ditemukan.';
        }

        if ($wantsActive && ! $scope->is_active) {
            return 'Mata kuliah hanya bisa aktif jika scope tujuan dalam kondisi aktif.';
        }

        return null;
    }

    private function scopeIsActive(?CourseScope $scope): bool
    {
        if (! $scope) {
            return false;
        }

        if ($scope->scope_type === 'global') {
            return true;
        }

        if ($scope->scope_type === 'faculty') {
            return (bool) Faculty::find($scope->scope_id)?->is_active;
        }

        return (bool) StudyProgram::find($scope->scope_id)?->is_active;
    }

    private function deleteBlocker(Course $course): ?string
    {
        if ($course->curriculumCourses()->exists()) {
            return 'Mata kuliah "'.$course->code.'" sudah masuk dalam kurikulum.';
        }

        if ($course->requiredByRows()->exists()) {
            return 'Mata kuliah "'.$course->code.'" menjadi prasyarat untuk mata kuliah lain.';
        }

        return null;
    }

    private function scopeLabel(?CourseScope $scope): string
    {
        return match ($scope?->scope_type) {
            'faculty' => 'Fakultas',
            'study_program' => 'Prodi',
            default => 'Global',
        };
    }

    private function scopeName(?CourseScope $scope): ?string
    {
        if (! $scope || $scope->scope_type === 'global') {
            return null;
        }

        return $scope->scope_type === 'faculty'
            ? $scope->faculty?->name
            : $scope->studyProgram?->name;
    }

    private function formOptions(?int $excludeId = null): array
    {
        $prereqQuery = Course::query()->orderBy('code');

        if ($excludeId) {
            $prereqQuery->where('id', '!=', $excludeId);
        }

        return [
            'faculties' => Faculty::query()->orderBy('name')->get(['id', 'name', 'is_active'])
                ->map(fn ($f) => ['id' => $f->id, 'name' => $f->name.((bool) $f->is_active ? '' : ' (Nonaktif)')])->all(),
            'programs' => StudyProgram::query()->orderBy('name')->get(['id', 'name', 'is_active'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name.((bool) $p->is_active ? '' : ' (Nonaktif)')])->all(),
            'prerequisites' => $prereqQuery->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->code.' — '.$c->name])->all(),
            'requirements' => ['Wajib', 'Pilihan'],
            'categories' => ['Umum', 'MKWU', 'MKU', 'Keilmuan', 'Praktikum', 'Tugas Akhir', 'Magang'],
        ];
    }
}
