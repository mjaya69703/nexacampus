<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
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
 * CRUD Program Studi — memakai kit CRUD shared (pola fakultas).
 *
 * Paritas kopling fakultas di 3 titik (Blade lama): opsi fakultas hanya
 * yang aktif saat tambah, prodi aktif wajib berfakultas aktif (saat
 * tambah, ubah, maupun toggle), hapus ditolak bila terikat kurikulum
 * atau cakupan MK — berlaku juga untuk bulk.
 */
class StudyProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'faculty' => 'nullable|integer|exists:faculties,id',
            'degree' => 'nullable|string|max:10',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = StudyProgram::query()->with('faculty:id,name,is_active');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('short_name', 'like', "%{$q}%"));
        }

        if (filled($validated['faculty'] ?? null)) {
            $query->where('faculty_id', $validated['faculty']);
        }

        if (filled($validated['degree'] ?? null)) {
            $query->where('degree', $validated['degree']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('study_programs.is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $programs = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/StudyProgram/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Program Studi'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('study-program.create'),
                'update' => ActivePermission::check('study-program.update'),
                'delete' => ActivePermission::check('study-program.delete'),
                'restore' => ActivePermission::any(['study-program.update', 'study-program.delete']),
                'toggle' => ActivePermission::check('study-program.update'),
            ],
            'stats' => [
                'total' => StudyProgram::count(),
                'active' => StudyProgram::where('is_active', true)->count(),
                'faculties' => Faculty::count(),
                'trashed' => StudyProgram::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($programs->items())->values()->map(fn ($p, $i) => [
                    'id' => $p->id,
                    'no' => ($programs->firstItem() ?? 0) + $i,
                    'name' => $p->name,
                    'code' => $p->code,
                    'degree' => $p->degree,
                    'faculty' => $p->faculty?->name,
                    'facultyActive' => $p->faculty ? (bool) $p->faculty->is_active : null,
                    'isActive' => (bool) $p->is_active,
                    'createdAt' => $p->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.academic.study-programs.edit', $p->id),
                    'deleteUrl' => route('admin.academic.study-programs.destroy', $p->id),
                    'restoreUrl' => route('admin.academic.study-programs.restore', $p->id),
                    'forceUrl' => route('admin.academic.study-programs.force-destroy', $p->id),
                    'toggleUrl' => route('admin.academic.study-programs.toggle', $p->id),
                ])->all(),
                'currentPage' => $programs->currentPage(),
                'lastPage' => $programs->lastPage(),
                'perPage' => $programs->perPage(),
                'total' => $programs->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'faculty' => $validated['faculty'] ?? '',
                'degree' => $validated['degree'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'facultyOptions' => Faculty::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all(),
            'degreeOptions' => StudyProgram::query()->distinct()->orderBy('degree')->pluck('degree')->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.study-programs.index'),
                'create' => route('admin.academic.study-programs.create'),
                'export' => route('admin.academic.study-programs.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'study-programs']),
                'importTemplate' => route('admin.academic.study-programs.import-template'),
                'importSubmit' => route('admin.academic.study-programs.import'),
                'bulkDestroy' => route('admin.academic.study-programs.bulk-destroy'),
                'bulkRestore' => route('admin.academic.study-programs.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.study-programs.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/StudyProgram/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Program Studi'),
            'mode' => 'create',
            'program' => [
                'faculty_id' => '', 'name' => '', 'code' => '', 'short_name' => '',
                'degree' => 'S1', 'prefix_degree' => '', 'suffix_degree' => '',
                'is_active' => true, 'desc' => '',
            ],
            'faculties' => $this->facultyOptions(),
            'urls' => [
                'index' => route('admin.academic.study-programs.index'),
                'submit' => route('admin.academic.study-programs.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'faculty_id' => 'nullable|exists:faculties,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:study_programs,code',
            'short_name' => 'nullable|string|max:50',
            'degree' => 'required|in:D3,D4,S1,S2,S3',
            'prefix_degree' => 'nullable|string|max:255',
            'suffix_degree' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $isActive = $validated['is_active'] ?? true;
        $faculty = ! empty($validated['faculty_id']) ? Faculty::find($validated['faculty_id']) : null;

        if ($isActive && $faculty && ! $faculty->is_active) {
            return back()->with('error', 'Program studi tidak boleh terhubung ke fakultas nonaktif.')->withInput();
        }

        $program = StudyProgram::create([
            'faculty_id' => $validated['faculty_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'short_name' => $validated['short_name'] ?? null,
            'degree' => $validated['degree'],
            'prefix_degree' => $validated['prefix_degree'] ?? null,
            'suffix_degree' => $validated['suffix_degree'] ?? null,
            'is_active' => $isActive,
            'desc' => $validated['desc'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.study-programs.index')
            ->with('success', 'Program studi "'.$program->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, StudyProgram $studyProgram): Response
    {
        return Inertia::render('Admin/Academic/StudyProgram/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Program Studi'),
            'mode' => 'edit',
            'program' => [
                'id' => $studyProgram->id,
                'faculty_id' => $studyProgram->faculty_id ?? '',
                'name' => $studyProgram->name,
                'code' => $studyProgram->code,
                'short_name' => $studyProgram->short_name ?? '',
                'degree' => $studyProgram->degree,
                'prefix_degree' => $studyProgram->prefix_degree ?? '',
                'suffix_degree' => $studyProgram->suffix_degree ?? '',
                'is_active' => (bool) $studyProgram->is_active,
                'desc' => $studyProgram->desc ?? '',
            ],
            'faculties' => $this->facultyOptions(),
            'urls' => [
                'index' => route('admin.academic.study-programs.index'),
                'submit' => route('admin.academic.study-programs.update', $studyProgram),
            ],
        ]);
    }

    public function update(Request $request, StudyProgram $studyProgram)
    {
        $validated = $request->validate([
            'faculty_id' => 'nullable|exists:faculties,id',
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:20', Rule::unique('study_programs', 'code')->ignore($studyProgram->id)],
            'short_name' => 'nullable|string|max:50',
            'degree' => 'required|in:D3,D4,S1,S2,S3',
            'prefix_degree' => 'nullable|string|max:255',
            'suffix_degree' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $isActive = $validated['is_active'] ?? true;
        $faculty = ! empty($validated['faculty_id']) ? Faculty::find($validated['faculty_id']) : null;

        if ($isActive && (! $faculty || ! $faculty->is_active)) {
            return back()->with('error', 'Program studi aktif harus terhubung ke fakultas yang aktif.')->withInput();
        }

        $studyProgram->update([
            'faculty_id' => $validated['faculty_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'short_name' => $validated['short_name'] ?? null,
            'degree' => $validated['degree'],
            'prefix_degree' => $validated['prefix_degree'] ?? null,
            'suffix_degree' => $validated['suffix_degree'] ?? null,
            'is_active' => $isActive,
            'desc' => $validated['desc'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.study-programs.index')
            ->with('success', 'Program studi "'.$studyProgram->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, StudyProgram $studyProgram)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);
        $isActive = $validated['is_active'];

        if ($isActive && $studyProgram->faculty && ! $studyProgram->faculty->is_active) {
            return back()->with('error', 'Program studi hanya bisa aktif jika fakultas yang terhubung berstatus aktif.');
        }

        $studyProgram->update(['is_active' => $isActive, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status program studi berhasil diperbarui.');
    }

    public function destroy(StudyProgram $studyProgram)
    {
        if ($studyProgram->curriculums()->exists() || $studyProgram->courseScopes()->exists()) {
            return back()->with('error', 'Program studi tidak dapat dihapus karena masih terkait dengan data kurikulum atau mata kuliah.');
        }

        $name = $studyProgram->name;
        $studyProgram->delete();

        return redirect()->route('admin.academic.study-programs.index')
            ->with('success', 'Program studi "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:study_programs,id',
        ]);

        $programs = StudyProgram::whereIn('id', $validated['ids'])->get();
        $blocked = $programs->filter(fn ($p) => $p->curriculums()->exists() || $p->courseScopes()->exists());
        $deletable = $programs->reject(fn ($p) => $p->curriculums()->exists() || $p->courseScopes()->exists());

        StudyProgram::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' prodi dihapus. '.$blocked->count().' tidak bisa dihapus karena terikat kurikulum atau MK.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Prodi tidak bisa dihapus karena terikat kurikulum atau MK.');
        }

        return back()->with('success', $deletable->count().' prodi berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $program = StudyProgram::withTrashed()->findOrFail($id);
        $program->restore();

        return back()->with('success', 'Program studi "'.$program->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $program = StudyProgram::withTrashed()->findOrFail($id);

        if ($program->curriculums()->exists() || $program->courseScopes()->withTrashed()->exists()) {
            return back()->with('error', 'Program studi tidak dapat dihapus karena masih terkait dengan data kurikulum atau mata kuliah.');
        }

        $name = $program->name;
        $program->forceDelete();

        return back()->with('success', 'Program studi "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:study_programs,id',
        ]);

        $count = 0;

        foreach (StudyProgram::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $program) {
            $program->restore();
            $count++;
        }

        return back()->with('success', $count.' prodi berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:study_programs,id',
        ]);

        $programs = StudyProgram::withTrashed()->whereIn('id', $validated['ids'])->get();
        $blocked = $programs->filter(fn ($p) => $p->curriculums()->exists() || $p->courseScopes()->withTrashed()->exists());
        $deletable = $programs->reject(fn ($p) => $p->curriculums()->exists() || $p->courseScopes()->withTrashed()->exists());

        foreach ($deletable as $program) {
            $program->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' prodi dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena terikat kurikulum atau MK.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Prodi tidak bisa dihapus karena terikat kurikulum atau MK.');
        }

        return back()->with('success', $deletable->count().' prodi dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudyProgram::query()->with('faculty:id,name');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('short_name', 'like', "%{$q}%"));
        }

        $rows = $query->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Kode', 'Fakultas', 'Jenjang', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $program) {
            $sheet->fromArray([
                $program->name,
                $program->code,
                $program->faculty?->name,
                $program->degree,
                $program->is_active ? 'Ya' : 'Tidak',
                $program->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'study-programs-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['faculty_code', 'name', 'code', 'degree', 'short_name', 'is_active']], null, 'A1');
        $sheet->fromArray([['FT', 'Teknik Informatika', 'TI', 'S1', '', '1']], null, 'A2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-study-programs.xlsx',
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
        $missing = array_diff(['faculty_code', 'name', 'code', 'degree'], $header);

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
        $facultyActive = Faculty::where('is_active', true)->pluck('code')->all();
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

            $facultyCode = $data['faculty_code'] ?? null;

            if ($facultyCode === null || $facultyCode === '') {
                $rowErrors[] = 'faculty_code wajib diisi';
            } elseif (! isset($facultyMap[$facultyCode])) {
                $rowErrors[] = "fakultas \"{$facultyCode}\" tidak ditemukan";
            }

            if (($data['name'] ?? null) === null || $data['name'] === '') {
                $rowErrors[] = 'name wajib diisi';
            }

            $code = $data['code'] ?? null;

            if ($code === null || $code === '') {
                $rowErrors[] = 'code wajib diisi';
            } else {
                if (strlen((string) $code) > 20) {
                    $rowErrors[] = 'code maksimal 20 karakter';
                } elseif (in_array($code, $seenCodes, true)) {
                    $rowErrors[] = 'code duplikat di dalam file';
                } elseif (StudyProgram::where('code', $code)->exists()) {
                    $rowErrors[] = 'code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
                }
            }

            $degree = $data['degree'] ?? null;

            if (! in_array($degree, ['D3', 'D4', 'S1', 'S2', 'S3'], true)) {
                $rowErrors[] = 'degree harus D3/D4/S1/S2/S3';
            }

            $isActive = $this->parseImportBool($data['is_active'] ?? null, true);

            if ($isActive && $facultyCode && ! in_array($facultyCode, $facultyActive, true)) {
                $rowErrors[] = 'prodi aktif wajib berfakultas aktif';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = [
                'faculty_id' => $facultyMap[$facultyCode],
                'name' => $data['name'],
                'code' => $code,
                'short_name' => $data['short_name'] ?? null,
                'degree' => $degree,
                'prefix_degree' => $data['prefix_degree'] ?? null,
                'suffix_degree' => $data['suffix_degree'] ?? null,
                'is_active' => $isActive,
                'desc' => $data['desc'] ?? null,
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.study-programs.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        StudyProgram::insert($valid);

        return redirect()->route('admin.academic.study-programs.index')
            ->with('success', count($valid).' prodi berhasil diimpor.')
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

    /**
     * @return array<int, array{id: int|string, name: string}>
     */
    private function facultyOptions(): array
    {
        return Faculty::query()->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name.((bool) $f->is_active ? '' : ' (Nonaktif)'),
            ])
            ->prepend(['id' => '', 'name' => 'Tanpa Fakultas'])
            ->values()
            ->all();
    }
}
