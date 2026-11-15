<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Periode Akademik — memakai kit CRUD shared.
 * Paritas Blade lama: tanpa aturan satu-aktif, hapus tanpa guard relasi.
 */
class AcademicPeriodController extends Controller
{
    public const TYPES = [
        'Student Registration', 'Study Plan', 'Study Plan Revision', 'Grading',
        'Exam', 'Remedial', 'Academic Leave', 'Yudisium', 'Custom',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'year' => 'nullable|integer|exists:academic_years,id',
            'type' => 'nullable|string|max:50',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,start_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = AcademicPeriod::query()->with('academicYear:id,name');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'start_at';
        $direction = $validated['direction'] ?? 'desc';

        if (! in_array($sort, ['id', 'name', 'code', 'start_at', 'created_at'], true)) {
            $sort = 'start_at';
        }

        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $periods = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/AcademicPeriod/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Periode Akademik'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('academic-period.create'),
                'update' => ActivePermission::check('academic-period.update'),
                'delete' => ActivePermission::check('academic-period.delete'),
                'restore' => ActivePermission::any(['academic-period.update', 'academic-period.delete']),
                'toggle' => ActivePermission::check('academic-period.update'),
            ],
            'stats' => [
                'total' => AcademicPeriod::count(),
                'active' => AcademicPeriod::where('is_active', true)->count(),
                'regular' => AcademicPeriod::whereIn('type', ['Reguler', 'Regular', 'Ganjil', 'Genap'])->count(),
                'trashed' => AcademicPeriod::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($periods->items())->values()->map(fn ($p, $i) => [
                    'id' => $p->id,
                    'no' => ($periods->firstItem() ?? 0) + $i,
                    'name' => $p->name,
                    'code' => $p->code,
                    'type' => $p->type,
                    'year' => $p->academicYear?->name,
                    'startAt' => $p->start_at?->format('d M Y H:i'),
                    'endAt' => $p->end_at?->format('d M Y H:i'),
                    'isActive' => (bool) $p->is_active,
                    'createdAt' => $p->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.academic.academic-periods.edit', $p->id),
                    'deleteUrl' => route('admin.academic.academic-periods.destroy', $p->id),
                    'restoreUrl' => route('admin.academic.academic-periods.restore', $p->id),
                    'forceUrl' => route('admin.academic.academic-periods.force-destroy', $p->id),
                    'toggleUrl' => route('admin.academic.academic-periods.toggle', $p->id),
                ])->all(),
                'currentPage' => $periods->currentPage(),
                'lastPage' => $periods->lastPage(),
                'perPage' => $periods->perPage(),
                'total' => $periods->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'year' => $validated['year'] ?? '',
                'type' => $validated['type'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $validated['sort'] ?? 'start_at',
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'typeOptions' => self::TYPES,
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.academic.academic-periods.index'),
                'create' => route('admin.academic.academic-periods.create'),
                'export' => route('admin.academic.academic-periods.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'academic-periods']),
                'importTemplate' => route('admin.academic.academic-periods.import-template'),
                'importSubmit' => route('admin.academic.academic-periods.import'),
                'bulkDestroy' => route('admin.academic.academic-periods.bulk-destroy'),
                'bulkRestore' => route('admin.academic.academic-periods.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.academic-periods.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/AcademicPeriod/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Periode Akademik'),
            'mode' => 'create',
            'period' => [
                'academic_year_id' => '', 'name' => '', 'code' => '', 'type' => 'Custom',
                'start_at' => '', 'end_at' => '', 'is_active' => true, 'desc' => '',
            ],
            'years' => $this->yearOptions(),
            'types' => self::TYPES,
            'urls' => [
                'index' => route('admin.academic.academic-periods.index'),
                'submit' => route('admin.academic.academic-periods.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('academic_periods', 'code')->whereNull('deleted_at')],
            'type' => 'required|in:'.implode(',', self::TYPES),
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $period = AcademicPeriod::create([
            'academic_year_id' => $validated['academic_year_id'],
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'type' => $validated['type'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.academic-periods.index')
            ->with('success', 'Periode "'.$period->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, AcademicPeriod $academicPeriod): Response
    {
        return Inertia::render('Admin/Academic/AcademicPeriod/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Periode Akademik'),
            'mode' => 'edit',
            'period' => [
                'id' => $academicPeriod->id,
                'academic_year_id' => $academicPeriod->academic_year_id,
                'name' => $academicPeriod->name,
                'code' => $academicPeriod->code ?? '',
                'type' => $academicPeriod->type,
                'start_at' => $academicPeriod->start_at?->format('Y-m-d\TH:i'),
                'end_at' => $academicPeriod->end_at?->format('Y-m-d\TH:i'),
                'is_active' => (bool) $academicPeriod->is_active,
                'desc' => $academicPeriod->desc ?? '',
            ],
            'years' => $this->yearOptions(),
            'types' => self::TYPES,
            'urls' => [
                'index' => route('admin.academic.academic-periods.index'),
                'submit' => route('admin.academic.academic-periods.update', $academicPeriod),
            ],
        ]);
    }

    public function update(Request $request, AcademicPeriod $academicPeriod)
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('academic_periods', 'code')->ignore($academicPeriod->id)->whereNull('deleted_at')],
            'type' => 'required|in:'.implode(',', self::TYPES),
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'is_active' => 'boolean',
            'desc' => 'nullable|string',
        ]);

        $academicPeriod->update([
            'academic_year_id' => $validated['academic_year_id'],
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'type' => $validated['type'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'is_active' => $validated['is_active'] ?? true,
            'desc' => $validated['desc'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.academic-periods.index')
            ->with('success', 'Periode "'.$academicPeriod->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, AcademicPeriod $academicPeriod)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $academicPeriod->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status periode berhasil diperbarui.');
    }

    public function destroy(AcademicPeriod $academicPeriod)
    {
        $name = $academicPeriod->name;
        $academicPeriod->delete();

        return redirect()->route('admin.academic.academic-periods.index')
            ->with('success', 'Periode "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_periods,id',
        ]);

        $count = AcademicPeriod::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' periode berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $period = AcademicPeriod::withTrashed()->findOrFail($id);
        $period->restore();

        return back()->with('success', 'Periode "'.$period->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $period = AcademicPeriod::withTrashed()->findOrFail($id);
        $name = $period->name;
        $period->forceDelete();

        return back()->with('success', 'Periode "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_periods,id',
        ]);

        $count = 0;

        foreach (AcademicPeriod::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $period) {
            $period->restore();
            $count++;
        }

        return back()->with('success', $count.' periode berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_periods,id',
        ]);

        $count = 0;

        foreach (AcademicPeriod::withTrashed()->whereIn('id', $validated['ids'])->get() as $period) {
            $period->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' periode dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = AcademicPeriod::query()->with('academicYear:id,name');

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

        $rows = $query->orderByDesc('start_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Kode', 'Tahun', 'Tipe', 'Mulai', 'Selesai', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $period) {
            $sheet->fromArray([
                $period->name,
                $period->code,
                $period->academicYear?->name,
                $period->type,
                $period->start_at?->format('Y-m-d H:i'),
                $period->end_at?->format('Y-m-d H:i'),
                $period->is_active ? 'Ya' : 'Tidak',
                $period->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'academic-periods-'.now()->format('Ymd-His').'.'.$format;
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
        $sheet->fromArray([['academic_year_code', 'name', 'code', 'type', 'start_at', 'end_at', 'is_active']], null, 'A1');
        $sheet->fromArray([['2627G', 'Pengisian KRS Ganjil', 'KRS26G', 'Study Plan', '2026-08-01 00:00', '2026-08-31 23:59', '1']], null, 'A2');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-academic-periods.xlsx',
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
        $missing = array_diff(['academic_year_code', 'name', 'type', 'start_at', 'end_at'], $header);

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

            $yearCode = $data['academic_year_code'] ?? null;

            if ($yearCode === null || $yearCode === '') {
                $rowErrors[] = 'academic_year_code wajib diisi';
            } elseif (! isset($yearMap[$yearCode])) {
                $rowErrors[] = "tahun akademik \"{$yearCode}\" tidak ditemukan";
            }

            if (($data['name'] ?? null) === null || $data['name'] === '') {
                $rowErrors[] = 'name wajib diisi';
            }

            $code = $data['code'] ?? null;

            if ($code !== null && $code !== '') {
                if (strlen((string) $code) > 50) {
                    $rowErrors[] = 'code maksimal 50 karakter';
                } elseif (in_array($code, $seenCodes, true)) {
                    $rowErrors[] = 'code duplikat di dalam file';
                } elseif (AcademicPeriod::where('code', $code)->exists()) {
                    $rowErrors[] = 'code sudah terdaftar';
                } else {
                    $seenCodes[] = $code;
                }
            }

            if (! in_array($data['type'] ?? null, self::TYPES, true)) {
                $rowErrors[] = 'type harus salah satu: '.implode(', ', self::TYPES);
            }

            $start = $this->parseImportDateTime($data['start_at'] ?? null);
            $end = $this->parseImportDateTime($data['end_at'] ?? null);

            if ($start === null) {
                $rowErrors[] = 'start_at tidak valid (Y-m-d H:i)';
            }

            if ($end === null) {
                $rowErrors[] = 'end_at tidak valid (Y-m-d H:i)';
            } elseif ($start !== null && $end <= $start) {
                $rowErrors[] = 'end_at harus setelah start_at';
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
                'name' => $data['name'],
                'code' => ($code === null || $code === '') ? null : $code,
                'type' => $data['type'],
                'start_at' => $start,
                'end_at' => $end,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                'desc' => $data['desc'] ?? null,
                'created_by' => $request->user()->id,
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.academic.academic-periods.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        AcademicPeriod::insert($valid);

        return redirect()->route('admin.academic.academic-periods.index')
            ->with('success', count($valid).' periode berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function parseImportDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
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
