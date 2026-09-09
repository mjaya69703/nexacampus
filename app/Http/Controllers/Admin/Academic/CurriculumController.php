<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\Course;
use App\Models\Academic\Curriculum;
use App\Models\Academic\CurriculumCourse;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Kurikulum — memakai kit CRUD shared.
 *
 * Paritas Blade lama + pemudah operator:
 * - Duplikat kurikulum (nama/kode/tahun baru, seluruh baris MK ikut).
 * - Kelola MK per semester di halaman edit (tambah/ubah/hapus satuan).
 * - Hapus ditolak bila masih punya MK — berlaku juga untuk bulk.
 */
class CurriculumController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'program' => 'nullable|integer|exists:study_programs,id',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Curriculum::query()
            ->with('studyProgram:id,name')
            ->withCount('curriculumCourses as courses_count');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['program'] ?? null)) {
            $query->where('study_program_id', $validated['program']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $curriculums = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/Curriculum/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Kurikulum'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('curriculum.create'),
                'update' => ActivePermission::check('curriculum.update'),
                'delete' => ActivePermission::check('curriculum.delete'),
                'view' => ActivePermission::check('curriculum.view'),
                'restore' => ActivePermission::any(['curriculum.update', 'curriculum.delete']),
                'toggle' => ActivePermission::check('curriculum.update'),
                'duplicate' => ActivePermission::check('curriculum.create'),
            ],
            'stats' => [
                'total' => Curriculum::count(),
                'active' => Curriculum::where('is_active', true)->count(),
                'items' => CurriculumCourse::count(),
                'trashed' => Curriculum::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($curriculums->items())->values()->map(fn ($c, $i) => [
                    'id' => $c->id,
                    'no' => ($curriculums->firstItem() ?? 0) + $i,
                    'name' => $c->name,
                    'code' => $c->code,
                    'program' => $c->studyProgram?->name,
                    'startYear' => $c->start_year,
                    'endYear' => $c->end_year,
                    'courseCount' => (int) $c->courses_count,
                    'isActive' => (bool) $c->is_active,
                    'createdAt' => $c->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.academic.curriculums.show', $c->id),
                    'editUrl' => $isTrash ? null : route('admin.academic.curriculums.edit', $c->id),
                    'deleteUrl' => route('admin.academic.curriculums.destroy', $c->id),
                    'restoreUrl' => route('admin.academic.curriculums.restore', $c->id),
                    'forceUrl' => route('admin.academic.curriculums.force-destroy', $c->id),
                    'toggleUrl' => route('admin.academic.curriculums.toggle', $c->id),
                    'duplicateUrl' => route('admin.academic.curriculums.duplicate', $c->id),
                ])->all(),
                'currentPage' => $curriculums->currentPage(),
                'lastPage' => $curriculums->lastPage(),
                'perPage' => $curriculums->perPage(),
                'total' => $curriculums->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'program' => $validated['program'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.curriculums.index'),
                'create' => route('admin.academic.curriculums.create'),
                'export' => route('admin.academic.curriculums.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'curriculums']),
                'importTemplate' => route('admin.academic.curriculums.import-template'),
                'importSubmit' => route('admin.academic.curriculums.import'),
                'bulkDestroy' => route('admin.academic.curriculums.bulk-destroy'),
                'bulkRestore' => route('admin.academic.curriculums.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.curriculums.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/Curriculum/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Kurikulum'),
            'mode' => 'create',
            'curriculum' => [
                'study_program_id' => '', 'name' => '', 'code' => '',
                'start_year' => '', 'end_year' => '', 'is_active' => true, 'desc' => '',
            ],
            'programs' => $this->programOptions(),
            'urls' => [
                'index' => route('admin.academic.curriculums.index'),
                'submit' => route('admin.academic.curriculums.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $studyProgramId = $request->input('study_program_id');

        $validated = $request->validate([
            'study_program_id' => 'required|exists:study_programs,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('curriculums', 'name')->where(fn ($q) => $q->where('study_program_id', $studyProgramId))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('curriculums', 'code')->where(fn ($q) => $q->where('study_program_id', $studyProgramId))],
            'start_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'end_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 10),
            'is_active' => 'nullable|boolean',
            'desc' => 'nullable|string',
        ]);

        $curriculum = Curriculum::create(array_merge($validated, ['created_by' => $request->user()->id]));

        return redirect()->route('admin.academic.curriculums.edit', $curriculum)
            ->with('success', 'Kurikulum tersimpan. Lanjutkan menambah daftar mata kuliah.');
    }

    public function show(Request $request, int $id): Response
    {
        $curriculum = Curriculum::query()->with(['studyProgram:id,name', 'curriculumCourses.course:id,code,name,credits'])->findOrFail($id);

        return Inertia::render('Admin/Academic/Curriculum/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail Kurikulum'),
            'curriculum' => [
                'id' => $curriculum->id,
                'name' => $curriculum->name,
                'code' => $curriculum->code,
                'program' => $curriculum->studyProgram?->name,
                'startYear' => $curriculum->start_year,
                'endYear' => $curriculum->end_year,
                'isActive' => (bool) $curriculum->is_active,
                'desc' => $curriculum->desc,
            ],
            'groups' => $this->semesterGroups($curriculum),
            'canUpdate' => ActivePermission::check('curriculum.update'),
            'urls' => [
                'index' => route('admin.academic.curriculums.index'),
                'edit' => route('admin.academic.curriculums.edit', $curriculum),
            ],
        ]);
    }

    public function edit(Request $request, Curriculum $curriculum): Response
    {
        $curriculum->load(['curriculumCourses.course:id,code,name,credits']);

        return Inertia::render('Admin/Academic/Curriculum/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Kurikulum'),
            'mode' => 'edit',
            'curriculum' => [
                'id' => $curriculum->id,
                'study_program_id' => $curriculum->study_program_id,
                'name' => $curriculum->name,
                'code' => $curriculum->code ?? '',
                'start_year' => $curriculum->start_year ?? '',
                'end_year' => $curriculum->end_year ?? '',
                'is_active' => (bool) $curriculum->is_active,
                'desc' => $curriculum->desc ?? '',
            ],
            'programs' => $this->programOptions(),
            'courses' => $this->semesterGroups($curriculum),
            'courseOptions' => Course::query()->where('is_active', true)->orderBy('code')
                ->get(['id', 'code', 'name', 'credits'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->code} — {$c->name} ({$c->credits} SKS)"])->all(),
            'urls' => [
                'index' => route('admin.academic.curriculums.index'),
                'submit' => route('admin.academic.curriculums.update', $curriculum),
                'show' => route('admin.academic.curriculums.show', $curriculum),
                'courseStore' => route('admin.academic.curriculums.courses.store', $curriculum),
                'courseBulkDestroy' => route('admin.academic.curriculums.courses.bulk-destroy', $curriculum),
                'searchCourses' => route('admin.academic.curriculums.search-courses'),
            ],
        ]);
    }

    public function update(Request $request, Curriculum $curriculum)
    {
        $studyProgramId = $request->input('study_program_id');

        $validated = $request->validate([
            'study_program_id' => 'required|exists:study_programs,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('curriculums', 'name')->where(fn ($q) => $q->where('study_program_id', $studyProgramId))->ignore($curriculum->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('curriculums', 'code')->where(fn ($q) => $q->where('study_program_id', $studyProgramId))->ignore($curriculum->id)],
            'start_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'end_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 10),
            'is_active' => 'nullable|boolean',
            'desc' => 'nullable|string',
        ]);

        $curriculum->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return redirect()->route('admin.academic.curriculums.index')
            ->with('success', 'Kurikulum "'.$curriculum->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $curriculum->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status kurikulum berhasil diperbarui.');
    }

    /**
     * Duplikat kurikulum beserta seluruh baris MK (fitur baru).
     */
    public function duplicate(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('curriculums', 'name')->where(fn ($q) => $q->where('study_program_id', $curriculum->study_program_id))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('curriculums', 'code')->where(fn ($q) => $q->where('study_program_id', $curriculum->study_program_id))],
            'start_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'end_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 10),
        ]);

        $copy = DB::transaction(function () use ($validated, $request, $curriculum) {
            $new = Curriculum::create([
                'study_program_id' => $curriculum->study_program_id,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'start_year' => $validated['start_year'] ?? $curriculum->start_year,
                'end_year' => $validated['end_year'] ?? $curriculum->end_year,
                'is_active' => false,
                'desc' => $curriculum->desc,
                'created_by' => $request->user()->id,
            ]);

            foreach ($curriculum->curriculumCourses as $row) {
                $new->curriculumCourses()->create([
                    'course_id' => $row->course_id,
                    'semester_no' => $row->semester_no,
                    'is_required' => $row->is_required,
                    'sort_order' => $row->sort_order,
                    'credits_override' => $row->credits_override,
                    'notes' => $row->notes,
                    'is_active' => $row->is_active,
                    'created_by' => $request->user()->id,
                ]);
            }

            return $new;
        });

        return redirect()->route('admin.academic.curriculums.edit', $copy)
            ->with('success', 'Kurikulum diduplikat. Sesuaikan tahun dan susunan MK bila perlu.');
    }

    public function destroy(Curriculum $curriculum)
    {
        if ($curriculum->curriculumCourses()->exists()) {
            return back()->with('error', 'Kurikulum tidak dapat dihapus karena masih memiliki daftar mata kuliah.');
        }

        $name = $curriculum->name;
        $curriculum->delete();

        return redirect()->route('admin.academic.curriculums.index')
            ->with('success', 'Kurikulum "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:curriculums,id',
        ]);

        $curriculums = Curriculum::whereIn('id', $validated['ids'])->get();
        $blocked = $curriculums->filter(fn ($c) => $c->curriculumCourses()->exists());
        $deletable = $curriculums->reject(fn ($c) => $c->curriculumCourses()->exists());

        Curriculum::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kurikulum dihapus. '.$blocked->count().' tidak bisa dihapus karena masih punya MK.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kurikulum tidak bisa dihapus karena masih memiliki daftar mata kuliah.');
        }

        return back()->with('success', $deletable->count().' kurikulum berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $curriculum = Curriculum::withTrashed()->findOrFail($id);
        $curriculum->restore();

        return back()->with('success', 'Kurikulum "'.$curriculum->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $curriculum = Curriculum::withTrashed()->findOrFail($id);

        if ($curriculum->curriculumCourses()->withTrashed()->exists()) {
            return back()->with('error', 'Kurikulum tidak dapat dihapus karena masih memiliki daftar mata kuliah.');
        }

        $name = $curriculum->name;
        $curriculum->forceDelete();

        return back()->with('success', 'Kurikulum "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:curriculums,id',
        ]);

        $count = 0;

        foreach (Curriculum::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $curriculum) {
            $curriculum->restore();
            $count++;
        }

        return back()->with('success', $count.' kurikulum berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:curriculums,id',
        ]);

        $curriculums = Curriculum::withTrashed()->whereIn('id', $validated['ids'])->get();
        $blocked = $curriculums->filter(fn ($c) => $c->curriculumCourses()->withTrashed()->exists());
        $deletable = $curriculums->reject(fn ($c) => $c->curriculumCourses()->withTrashed()->exists());

        foreach ($deletable as $curriculum) {
            $curriculum->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' kurikulum dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih punya MK.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Kurikulum tidak bisa dihapus karena masih memiliki daftar mata kuliah.');
        }

        return back()->with('success', $deletable->count().' kurikulum dihapus permanen.');
    }

    // ── Baris MK ──────────────────────────────────────────────────

    public function courseStore(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id', Rule::unique('curriculum_courses', 'course_id')->where(fn ($q) => $q->where('curriculum_id', $curriculum->id))],
            'semester_no' => 'nullable|integer|min:1|max:14',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'credits_override' => 'nullable|integer|min:1|max:30',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $curriculum->curriculumCourses()->create(array_merge($validated, ['created_by' => $request->user()->id]));

        return back()->with('success', 'Mata kuliah ditambahkan ke kurikulum.');
    }

    public function courseUpdate(Request $request, Curriculum $curriculum, int $row)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id', Rule::unique('curriculum_courses', 'course_id')->where(fn ($q) => $q->where('curriculum_id', $curriculum->id))->ignore($row)],
            'semester_no' => 'nullable|integer|min:1|max:14',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'credits_override' => 'nullable|integer|min:1|max:30',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $curriculum->curriculumCourses()->whereKey($row)->firstOrFail()
            ->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return back()->with('success', 'Baris mata kuliah diperbarui.');
    }

    public function courseDestroy(Curriculum $curriculum, int $row)
    {
        $curriculum->curriculumCourses()->whereKey($row)->firstOrFail()->delete();

        return back()->with('success', 'Mata kuliah dihapus dari kurikulum.');
    }

    public function courseBulkDestroy(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:curriculum_courses,id',
        ]);

        $count = $curriculum->curriculumCourses()->whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' baris MK dihapus dari kurikulum.');
    }

    /**
     * Pencarian async MK aktif (ganti dropdown raksasa Blade lama).
     */
    public function searchCourses(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $courses = Course::query()
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code', 'name', 'credits', 'semester_recommendation'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'label' => "{$c->code} — {$c->name} ({$c->credits} SKS)",
                'semester' => $c->semester_recommendation,
            ])
            ->all();

        return response()->json(['options' => $courses]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Curriculum::query()->with('studyProgram:id,name')->withCount('curriculumCourses');

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Kode', 'Prodi', 'Mulai', 'Selesai', 'Jml MK', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $curriculum) {
            $sheet->fromArray([
                $curriculum->name,
                $curriculum->code,
                $curriculum->studyProgram?->name,
                $curriculum->start_year,
                $curriculum->end_year,
                (int) $curriculum->curriculum_courses_count,
                $curriculum->is_active ? 'Ya' : 'Tidak',
                $curriculum->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'curriculums-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['program_code', 'name', 'code', 'start_year', 'end_year', 'is_active']], null, 'A1');
        $sheet->fromArray([['TI', 'Kurikulum 2024', 'K24', '2024', '2028', '1']], null, 'A2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-curriculums.xlsx',
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
        $missing = array_diff(['program_code', 'name'], $header);

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

        $programMap = StudyProgram::pluck('id', 'code')->all();
        $seen = [];
        $valid = [];
        $errors = [];
        $currentYear = (int) date('Y');

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_combine(
                $header,
                array_pad(array_slice($row, 0, count($header)), count($header), null)
            );
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
            $rowErrors = [];

            $programCode = $data['program_code'] ?? null;

            if ($programCode === null || $programCode === '') {
                $rowErrors[] = 'program_code wajib diisi';
            } elseif (! isset($programMap[$programCode])) {
                $rowErrors[] = "prodi \"{$programCode}\" tidak ditemukan";
            }

            $name = $data['name'] ?? null;

            if ($name === null || $name === '') {
                $rowErrors[] = 'name wajib diisi';
            }

            $code = ($data['code'] ?? null) === '' ? null : ($data['code'] ?? null);

            if ($programCode && $name) {
                $programId = $programMap[$programCode] ?? null;

                if ($programId && Curriculum::where('study_program_id', $programId)->where('name', $name)->exists()) {
                    $rowErrors[] = 'nama kurikulum sudah ada di prodi ini';
                }

                if ($code !== null && Curriculum::where('study_program_id', $programId)->where('code', $code)->exists()) {
                    $rowErrors[] = 'kode kurikulum sudah ada di prodi ini';
                }

                $combo = $programId.'|'.$name.'|'.($code ?? '');

                if (in_array($combo, $seen, true)) {
                    $rowErrors[] = 'duplikat di dalam file';
                } else {
                    $seen[] = $combo;
                }
            }

            foreach (['start_year', 'end_year'] as $yearColumn) {
                $yearValue = $data[$yearColumn] ?? null;

                if ($yearValue !== null && $yearValue !== '' && (! is_numeric($yearValue) || (int) $yearValue < 1900 || (int) $yearValue > $currentYear + 10)) {
                    $rowErrors[] = "{$yearColumn} harus 1900–".($currentYear + 10);
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
                'study_program_id' => $programMap[$programCode],
                'name' => $name,
                'code' => $code,
                'start_year' => ($data['start_year'] ?? null) === '' || ($data['start_year'] ?? null) === null ? null : (int) $data['start_year'],
                'end_year' => ($data['end_year'] ?? null) === '' || ($data['end_year'] ?? null) === null ? null : (int) $data['end_year'],
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'desc' => $data['desc'] ?? null,
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.curriculums.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Curriculum::insert($valid);

        return redirect()->route('admin.academic.curriculums.index')
            ->with('success', count($valid).' kurikulum berhasil diimpor. Susun MK-nya dari halaman edit.')
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

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function programOptions(): array
    {
        return StudyProgram::query()->where('is_active', true)->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }

    /**
     * Baris MK dikelompokkan per semester + total SKS.
     *
     * @return array<int, array{semester: string, courses: array, sks: float, count: int}>
     */
    private function semesterGroups(Curriculum $curriculum): array
    {
        $rows = $curriculum->curriculumCourses()
            ->with('course:id,code,name,credits,semester_recommendation')
            ->orderByRaw('COALESCE(semester_no, 999)')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $rows->groupBy(fn ($row) => $row->semester_no ?? 0)
            ->sortKeys()
            ->map(fn ($group, $semester) => [
                'semester' => $semester === 0 ? 'Tanpa semester' : 'Semester '.$semester,
                'courses' => $group->map(fn ($row) => [
                    'id' => $row->id,
                    'courseId' => $row->course_id,
                    'code' => $row->course?->code,
                    'name' => $row->course?->name,
                    'credits' => $row->credits_override ?? $row->course?->credits,
                    'creditsOverride' => $row->credits_override,
                    'semesterRecommendation' => $row->course?->semester_recommendation,
                    'semester' => $row->semester_no,
                    'required' => (bool) $row->is_required,
                    'sort' => (int) $row->sort_order,
                    'notes' => $row->notes,
                    'isActive' => (bool) $row->is_active,
                    'updateUrl' => route('admin.academic.curriculums.courses.update', [$curriculum, $row->id]),
                    'deleteUrl' => route('admin.academic.curriculums.courses.destroy', [$curriculum, $row->id]),
                ])->all(),
                'sks' => $group->sum(fn ($row) => (float) ($row->credits_override ?? $row->course?->credits ?? 0)),
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }
}
