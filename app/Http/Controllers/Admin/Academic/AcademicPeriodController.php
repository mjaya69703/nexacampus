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
            'urls' => [
                'index' => route('admin.academic.academic-periods.index'),
                'create' => route('admin.academic.academic-periods.create'),
                'export' => route('admin.academic.academic-periods.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'academic-periods']),
                'importTemplate' => route('admin.academic.import-template', ['resource' => 'academic-periods']),
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
