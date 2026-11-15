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
 * CRUD Fakultas — memakai kit CRUD shared (pola menu access/system).
 *
 * Paritas tabel & form Blade lama: toggle aktif menonaktifkan seluruh
 * prodi (cascade), hapus ditolak bila masih punya prodi — berlaku juga
 * untuk bulk (lebih ketat dari Blade yang meloloskan bulk).
 */
class FacultyController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Faculty::query()->withCount('studyPrograms as study_programs_count');

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

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $faculties = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/Faculty/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Fakultas'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('faculty.create'),
                'update' => ActivePermission::check('faculty.update'),
                'delete' => ActivePermission::check('faculty.delete'),
                'restore' => ActivePermission::any(['faculty.update', 'faculty.delete']),
                'toggle' => ActivePermission::check('faculty.update'),
            ],
            'stats' => [
                'total' => Faculty::count(),
                'active' => Faculty::where('is_active', true)->count(),
                'programs' => StudyProgram::count(),
                'trashed' => Faculty::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($faculties->items())->values()->map(fn ($f, $i) => [
                    'id' => $f->id,
                    'no' => ($faculties->firstItem() ?? 0) + $i,
                    'name' => $f->name,
                    'code' => $f->code,
                    'shortName' => $f->short_name,
                    'programCount' => (int) $f->study_programs_count,
                    'isActive' => (bool) $f->is_active,
                    'desc' => $f->desc,
                    'createdAt' => $f->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.academic.faculties.edit', $f->id),
                    'deleteUrl' => route('admin.academic.faculties.destroy', $f->id),
                    'restoreUrl' => route('admin.academic.faculties.restore', $f->id),
                    'forceUrl' => route('admin.academic.faculties.force-destroy', $f->id),
                    'toggleUrl' => route('admin.academic.faculties.toggle', $f->id),
                ])->all(),
                'currentPage' => $faculties->currentPage(),
                'lastPage' => $faculties->lastPage(),
                'perPage' => $faculties->perPage(),
                'total' => $faculties->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.faculties.index'),
                'create' => route('admin.academic.faculties.create'),
                'export' => route('admin.academic.faculties.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'faculties']),
                'importTemplate' => route('admin.academic.faculties.import-template'),
                'importSubmit' => route('admin.academic.faculties.import'),
                'bulkDestroy' => route('admin.academic.faculties.bulk-destroy'),
                'bulkRestore' => route('admin.academic.faculties.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.faculties.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/Faculty/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Fakultas'),
            'mode' => 'create',
            'faculty' => ['name' => '', 'code' => '', 'short_name' => '', 'is_active' => true, 'desc' => ''],
            'urls' => [
                'index' => route('admin.academic.faculties.index'),
                'submit' => route('admin.academic.faculties.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:faculties,code',
            'short_name' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $faculty = Faculty::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'short_name' => $validated['short_name'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.faculties.index')
            ->with('success', 'Fakultas "'.$faculty->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Faculty $faculty): Response
    {
        return Inertia::render('Admin/Academic/Faculty/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Fakultas'),
            'mode' => 'edit',
            'faculty' => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'code' => $faculty->code,
                'short_name' => $faculty->short_name ?? '',
                'is_active' => (bool) $faculty->is_active,
                'desc' => $faculty->desc ?? '',
            ],
            'urls' => [
                'index' => route('admin.academic.faculties.index'),
                'submit' => route('admin.academic.faculties.update', $faculty),
            ],
        ]);
    }

    public function update(Request $request, Faculty $faculty)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:20', Rule::unique('faculties', 'code')->ignore($faculty->id)],
            'short_name' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $faculty->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'short_name' => $validated['short_name'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        if (! $faculty->is_active) {
            $faculty->studyPrograms()->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        }

        return redirect()->route('admin.academic.faculties.index')
            ->with('success', 'Fakultas "'.$faculty->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, Faculty $faculty)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);
        $isActive = $validated['is_active'];

        $faculty->update(['is_active' => $isActive, 'updated_by' => $request->user()->id]);

        if (! $isActive) {
            $faculty->studyPrograms()->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        }

        return back()->with('success', 'Status fakultas berhasil diperbarui.');
    }

    public function destroy(Faculty $faculty)
    {
        if ($faculty->studyPrograms()->count() > 0) {
            return back()->with('error', 'Fakultas "'.$faculty->name.'" masih memiliki program studi. Hapus atau pindahkan program studi terlebih dahulu.');
        }

        $name = $faculty->name;
        $faculty->delete();

        return redirect()->route('admin.academic.faculties.index')
            ->with('success', 'Fakultas "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:faculties,id',
        ]);

        $faculties = Faculty::whereIn('id', $validated['ids'])->withCount('studyPrograms')->get();
        $blocked = $faculties->where('study_programs_count', '>', 0);
        $deletable = $faculties->where('study_programs_count', 0);

        Faculty::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' fakultas dihapus. '.$blocked->count().' tidak bisa dihapus karena masih memiliki program studi.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Fakultas tidak bisa dihapus karena masih memiliki program studi.');
        }

        return back()->with('success', $deletable->count().' fakultas berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $faculty = Faculty::withTrashed()->findOrFail($id);
        $faculty->restore();

        return back()->with('success', 'Fakultas "'.$faculty->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $faculty = Faculty::withTrashed()->findOrFail($id);

        if ($faculty->studyPrograms()->withTrashed()->count() > 0) {
            return back()->with('error', 'Fakultas "'.$faculty->name.'" masih memiliki program studi. Hapus atau pindahkan program studi terlebih dahulu.');
        }

        $name = $faculty->name;
        $faculty->forceDelete();

        return back()->with('success', 'Fakultas "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:faculties,id',
        ]);

        $count = 0;

        foreach (Faculty::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $faculty) {
            $faculty->restore();
            $count++;
        }

        return back()->with('success', $count.' fakultas berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:faculties,id',
        ]);

        $faculties = Faculty::withTrashed()->whereIn('id', $validated['ids'])->withCount(['studyPrograms' => fn ($q) => $q->withTrashed()])->get();
        $blocked = $faculties->where('study_programs_count', '>', 0);
        $deletable = $faculties->where('study_programs_count', 0);

        foreach ($deletable as $faculty) {
            $faculty->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' fakultas dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih memiliki program studi.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Fakultas tidak bisa dihapus karena masih memiliki program studi.');
        }

        return back()->with('success', $deletable->count().' fakultas dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Faculty::query()->withCount('studyPrograms');

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
        $sheet->fromArray([['Nama', 'Kode', 'Singkatan', 'Jml Prodi', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $faculty) {
            $sheet->fromArray([
                $faculty->name,
                $faculty->code,
                $faculty->short_name,
                (int) $faculty->study_programs_count,
                $faculty->is_active ? 'Ya' : 'Tidak',
                $faculty->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'faculties-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['name', 'code', 'short_name', 'is_active']], null, 'A1');
        $sheet->fromArray([['Fakultas Teknik', 'FT', 'FTEK', '1']], null, 'A2');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-faculties.xlsx',
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
        $missing = array_diff(['name', 'code'], $header);

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
                } elseif (Faculty::where('code', $code)->exists()) {
                    $rowErrors[] = 'code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
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
                'name' => $data['name'],
                'code' => $code,
                'short_name' => $data['short_name'] ?? null,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.faculties.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Faculty::insert($valid);

        return redirect()->route('admin.academic.faculties.index')
            ->with('success', count($valid).' fakultas berhasil diimpor.')
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
}
