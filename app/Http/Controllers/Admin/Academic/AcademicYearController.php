<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
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
 * CRUD Tahun Akademik — memakai kit CRUD shared.
 *
 * Paritas aturan "satu tahun aktif": mengaktifkan satu tahun
 * menonaktifkan yang lain — di store, update, maupun toggle.
 * Hapus ditolak bila terikat periode atau penugasan dosen wali.
 */
class AcademicYearController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'semester' => 'nullable|in:Ganjil,Genap,Pendek',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,start_date,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = AcademicYear::query();

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['semester'] ?? null)) {
            $query->where('semester', $validated['semester']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $years = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/AcademicYear/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Tahun Akademik'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('academic-year.create'),
                'update' => ActivePermission::check('academic-year.update'),
                'delete' => ActivePermission::check('academic-year.delete'),
                'restore' => ActivePermission::any(['academic-year.update', 'academic-year.delete']),
                'toggle' => ActivePermission::check('academic-year.update'),
            ],
            'stats' => [
                'total' => AcademicYear::count(),
                'active' => AcademicYear::where('is_active', true)->count(),
                'ganjil' => AcademicYear::where('semester', 'Ganjil')->count(),
                'genap' => AcademicYear::where('semester', 'Genap')->count(),
                'trashed' => AcademicYear::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($years->items())->values()->map(fn ($y, $i) => [
                    'id' => $y->id,
                    'no' => ($years->firstItem() ?? 0) + $i,
                    'name' => $y->name,
                    'code' => $y->code,
                    'semester' => $y->semester,
                    'startDate' => $y->start_date?->format('d M Y'),
                    'endDate' => $y->end_date?->format('d M Y'),
                    'isActive' => (bool) $y->is_active,
                    'createdAt' => $y->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.academic.academic-years.edit', $y->id),
                    'deleteUrl' => route('admin.academic.academic-years.destroy', $y->id),
                    'restoreUrl' => route('admin.academic.academic-years.restore', $y->id),
                    'forceUrl' => route('admin.academic.academic-years.force-destroy', $y->id),
                    'toggleUrl' => route('admin.academic.academic-years.toggle', $y->id),
                ])->all(),
                'currentPage' => $years->currentPage(),
                'lastPage' => $years->lastPage(),
                'perPage' => $years->perPage(),
                'total' => $years->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'semester' => $validated['semester'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.academic.academic-years.index'),
                'create' => route('admin.academic.academic-years.create'),
                'export' => route('admin.academic.academic-years.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'academic-years']),
                'importTemplate' => route('admin.academic.import-template', ['resource' => 'academic-years']),
                'importSubmit' => route('admin.academic.academic-years.import'),
                'bulkDestroy' => route('admin.academic.academic-years.bulk-destroy'),
                'bulkRestore' => route('admin.academic.academic-years.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.academic-years.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/AcademicYear/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Tahun Akademik'),
            'mode' => 'create',
            'year' => [
                'name' => '', 'code' => '', 'semester' => 'Ganjil',
                'start_date' => '', 'end_date' => '', 'is_active' => true, 'desc' => '',
            ],
            'urls' => [
                'index' => route('admin.academic.academic-years.index'),
                'submit' => route('admin.academic.academic-years.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:academic_years,code',
            'semester' => 'required|in:Ganjil,Genap,Pendek',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $isActive = $validated['is_active'] ?? true;

        if ($isActive) {
            $this->deactivateOthers();
        }

        $year = AcademicYear::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'semester' => $validated['semester'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_active' => $isActive,
            'desc' => $validated['desc'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.academic-years.index')
            ->with('success', 'Tahun akademik "'.$year->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, AcademicYear $academicYear): Response
    {
        return Inertia::render('Admin/Academic/AcademicYear/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Tahun Akademik'),
            'mode' => 'edit',
            'year' => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'code' => $academicYear->code,
                'semester' => $academicYear->semester,
                'start_date' => $academicYear->start_date?->format('Y-m-d'),
                'end_date' => $academicYear->end_date?->format('Y-m-d'),
                'is_active' => (bool) $academicYear->is_active,
                'desc' => $academicYear->desc ?? '',
            ],
            'urls' => [
                'index' => route('admin.academic.academic-years.index'),
                'submit' => route('admin.academic.academic-years.update', $academicYear),
            ],
        ]);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:255', Rule::unique('academic_years', 'code')->ignore($academicYear->id)],
            'semester' => 'required|in:Ganjil,Genap,Pendek',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $isActive = $validated['is_active'] ?? true;

        if ($isActive) {
            $this->deactivateOthers($academicYear->id, $request->user()->id);
        }

        $academicYear->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'semester' => $validated['semester'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_active' => $isActive,
            'desc' => $validated['desc'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.academic-years.index')
            ->with('success', 'Tahun akademik "'.$academicYear->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, AcademicYear $academicYear)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        if ($validated['is_active']) {
            $this->deactivateOthers($academicYear->id, $request->user()->id);
        }

        $academicYear->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status tahun akademik berhasil diperbarui.');
    }

    public function destroy(AcademicYear $academicYear)
    {
        if ($academicYear->academicPeriods()->exists() || $academicYear->advisorAssignments()->exists()) {
            return back()->with('error', 'Tahun akademik tidak dapat dihapus karena masih terkait dengan periode akademik atau penugasan dosen wali.');
        }

        $name = $academicYear->name;
        $academicYear->delete();

        return redirect()->route('admin.academic.academic-years.index')
            ->with('success', 'Tahun akademik "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_years,id',
        ]);

        $years = AcademicYear::whereIn('id', $validated['ids'])->get();
        $blocked = $years->filter(fn ($y) => $y->academicPeriods()->exists() || $y->advisorAssignments()->exists());
        $deletable = $years->reject(fn ($y) => $y->academicPeriods()->exists() || $y->advisorAssignments()->exists());

        AcademicYear::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' tahun dihapus. '.$blocked->count().' tidak bisa dihapus karena terikat periode/penugasan.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Tahun akademik tidak bisa dihapus karena terikat periode/penugasan.');
        }

        return back()->with('success', $deletable->count().' tahun akademik berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $year = AcademicYear::withTrashed()->findOrFail($id);
        $year->restore();

        return back()->with('success', 'Tahun akademik "'.$year->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $year = AcademicYear::withTrashed()->findOrFail($id);

        if ($year->academicPeriods()->withTrashed()->exists() || $year->advisorAssignments()->exists()) {
            return back()->with('error', 'Tahun akademik tidak dapat dihapus karena masih terkait dengan periode akademik atau penugasan dosen wali.');
        }

        $name = $year->name;
        $year->forceDelete();

        return back()->with('success', 'Tahun akademik "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_years,id',
        ]);

        $count = 0;

        foreach (AcademicYear::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $year) {
            $year->restore();
            $count++;
        }

        return back()->with('success', $count.' tahun akademik berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_years,id',
        ]);

        $years = AcademicYear::withTrashed()->whereIn('id', $validated['ids'])->get();
        $blocked = $years->filter(fn ($y) => $y->academicPeriods()->withTrashed()->exists() || $y->advisorAssignments()->exists());
        $deletable = $years->reject(fn ($y) => $y->academicPeriods()->withTrashed()->exists() || $y->advisorAssignments()->exists());

        foreach ($deletable as $year) {
            $year->forceDelete();
        }

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' tahun dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena terikat periode/penugasan.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Tahun akademik tidak bisa dihapus karena terikat periode/penugasan.');
        }

        return back()->with('success', $deletable->count().' tahun akademik dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = AcademicYear::query();

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

        $rows = $query->orderByDesc('start_date')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Kode', 'Semester', 'Mulai', 'Selesai', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $year) {
            $sheet->fromArray([
                $year->name,
                $year->code,
                $year->semester,
                $year->start_date?->format('Y-m-d'),
                $year->end_date?->format('Y-m-d'),
                $year->is_active ? 'Ya' : 'Tidak',
                $year->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'academic-years-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['name', 'code', 'semester', 'start_date', 'end_date', 'is_active']], null, 'A1');
        $sheet->fromArray([['2026/2027 Ganjil', '2627G', 'Ganjil', '2026-08-01', '2027-01-31', '1']], null, 'A2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-academic-years.xlsx',
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
        $missing = array_diff(['name', 'code', 'semester', 'start_date', 'end_date'], $header);

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
            } elseif (in_array($code, $seenCodes, true)) {
                $rowErrors[] = 'code duplikat di dalam file';
            } elseif (AcademicYear::where('code', $code)->exists()) {
                $rowErrors[] = 'code sudah terdaftar';
            } else {
                $seenCodes[] = $code;
            }

            if (! in_array($data['semester'] ?? null, ['Ganjil', 'Genap', 'Pendek'], true)) {
                $rowErrors[] = 'semester harus Ganjil/Genap/Pendek';
            }

            $start = $this->parseImportDate($data['start_date'] ?? null);
            $end = $this->parseImportDate($data['end_date'] ?? null);

            if ($start === null) {
                $rowErrors[] = 'start_date tidak valid (Y-m-d)';
            }

            if ($end === null) {
                $rowErrors[] = 'end_date tidak valid (Y-m-d)';
            } elseif ($start !== null && $end < $start) {
                $rowErrors[] = 'end_date minimal sama dengan start_date';
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
                'semester' => $data['semester'],
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, false),
                'desc' => $data['desc'] ?? null,
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.academic-years.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        AcademicYear::insert($valid);

        if (collect($valid)->contains(fn ($row) => $row['is_active'])) {
            $latestId = AcademicYear::query()->whereIn('code', collect($valid)->pluck('code'))->orderByDesc('id')->value('id');
            $this->deactivateOthers($latestId);
        }

        return redirect()->route('admin.academic.academic-years.index')
            ->with('success', count($valid).' tahun akademik berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function deactivateOthers(?int $exceptId = null, ?int $by = null): void
    {
        AcademicYear::query()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['is_active' => false, 'updated_by' => $by]);
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
}
