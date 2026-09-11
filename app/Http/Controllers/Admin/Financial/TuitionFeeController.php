<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\TuitionFee;
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
 * CRUD tarif biaya kuliah — memakai kit CRUD shared.
 *
 * Paritas Blade lama: unik (tahun, prodi, semester), toggle is_active,
 * hapus lunak + restore + hapus permanen.
 */
class TuitionFeeController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'year' => 'nullable|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'active' => 'nullable|in:0,1',
            'mode' => 'nullable|in:all,trash',
            'sort' => 'nullable|in:id,created_at,base_fee,payment_deadline',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $query = TuitionFee::query()
            ->with(['academicYear:id,name', 'studyProgram:id,name,code']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($w) use ($q) {
                $w->whereHas('academicYear', fn ($y) => $y
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%"))
                    ->orWhereHas('studyProgram', fn ($p) => $p
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['program'] ?? null)) {
            $query->where('study_program_id', $validated['program']);
        }

        if (filled($validated['semester'] ?? null)) {
            $query->where('semester', $validated['semester']);
        }

        if (isset($validated['active']) && $validated['active'] !== '') {
            $query->where('is_active', (bool) $validated['active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $fees = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/TuitionFee/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Tarif Biaya Kuliah'),
            'can' => [
                'create' => ActivePermission::check('tuition-fee.create'),
                'update' => ActivePermission::check('tuition-fee.update'),
                'delete' => ActivePermission::check('tuition-fee.delete'),
                'restore' => ActivePermission::check('tuition-fee.delete'),
                'toggle' => ActivePermission::check('tuition-fee.update'),
            ],
            'stats' => [
                'total' => TuitionFee::count(),
                'active' => TuitionFee::where('is_active', true)->count(),
                'trashed' => TuitionFee::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($fees->items())->values()->map(fn ($f, $i) => [
                    'id' => $f->id,
                    'no' => ($fees->firstItem() ?? 0) + $i,
                    'year' => $f->academicYear?->name ?? '-',
                    'program' => $f->studyProgram
                        ? $f->studyProgram->name.' ('.$f->studyProgram->code.')'
                        : '-',
                    'semester' => $f->semester,
                    'baseFee' => $this->money($f->base_fee),
                    'total' => $this->money($this->totalOf($f)),
                    'deadline' => $f->payment_deadline?->format('d M Y'),
                    'isActive' => (bool) $f->is_active,
                    'editUrl' => route('admin.financial.tuition-fees.edit', $f->id),
                    'deleteUrl' => route('admin.financial.tuition-fees.destroy', $f->id),
                    'restoreUrl' => route('admin.financial.tuition-fees.restore', $f->id),
                    'forceUrl' => route('admin.financial.tuition-fees.force-destroy', $f->id),
                    'toggleUrl' => route('admin.financial.tuition-fees.toggle', $f->id),
                ])->all(),
                'currentPage' => $fees->currentPage(),
                'lastPage' => $fees->lastPage(),
                'perPage' => $fees->perPage(),
                'total' => $fees->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'year' => $validated['year'] ?? '',
                'program' => $validated['program'] ?? '',
                'semester' => $validated['semester'] ?? '',
                'active' => $validated['active'] ?? '',
                'mode' => $validated['mode'] ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name.' ('.$p->code.')'])->all(),
            'urls' => [
                'index' => route('admin.financial.tuition-fees.index'),
                'create' => route('admin.financial.tuition-fees.create'),
                'store' => route('admin.financial.tuition-fees.store'),
                'export' => route('admin.financial.tuition-fees.export'),
                'bulkDestroy' => route('admin.financial.tuition-fees.bulk-destroy'),
                'bulkRestore' => route('admin.financial.tuition-fees.bulk-restore'),
                'bulkForceDestroy' => route('admin.financial.tuition-fees.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Financial/TuitionFee/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Tambah Tarif Biaya'),
            'mode' => 'create',
            'fee' => [
                'academic_year_id' => '', 'study_program_id' => '', 'semester' => '',
                'base_fee' => '', 'lab_fee' => '', 'library_fee' => '', 'activity_fee' => '',
                'late_penalty_per_day' => '', 'payment_deadline' => '', 'is_active' => true, 'notes' => '',
            ],
            'years' => $this->yearOptions(),
            'programs' => $this->programOptions(),
            'urls' => [
                'index' => route('admin.financial.tuition-fees.index'),
                'submit' => route('admin.financial.tuition-fees.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->rules();

        TuitionFee::create(array_merge($validated, ['created_by' => $request->user()->id]));

        return redirect()->route('admin.financial.tuition-fees.index')
            ->with('success', 'Tarif biaya kuliah berhasil ditambahkan.');
    }

    public function edit(Request $request, TuitionFee $tuitionFee): Response
    {
        return Inertia::render('Admin/Financial/TuitionFee/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Ubah Tarif Biaya'),
            'mode' => 'edit',
            'fee' => [
                'academic_year_id' => $tuitionFee->academic_year_id,
                'study_program_id' => $tuitionFee->study_program_id,
                'semester' => $tuitionFee->semester,
                'base_fee' => $tuitionFee->base_fee,
                'lab_fee' => $tuitionFee->lab_fee,
                'library_fee' => $tuitionFee->library_fee,
                'activity_fee' => $tuitionFee->activity_fee,
                'late_penalty_per_day' => $tuitionFee->late_penalty_per_day,
                'payment_deadline' => $tuitionFee->payment_deadline?->format('Y-m-d'),
                'is_active' => (bool) $tuitionFee->is_active,
                'notes' => $tuitionFee->notes,
            ],
            'years' => $this->yearOptions(),
            'programs' => $this->programOptions(),
            'urls' => [
                'index' => route('admin.financial.tuition-fees.index'),
                'submit' => route('admin.financial.tuition-fees.update', $tuitionFee),
            ],
        ]);
    }

    public function update(Request $request, TuitionFee $tuitionFee)
    {
        $validated = $this->rules($tuitionFee->id);

        $tuitionFee->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return redirect()->route('admin.financial.tuition-fees.index')
            ->with('success', 'Tarif biaya kuliah berhasil diperbarui.');
    }

    public function toggle(Request $request, TuitionFee $tuitionFee)
    {
        $tuitionFee->update([
            'is_active' => ! $tuitionFee->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status tarif diperbarui.');
    }

    public function destroy(TuitionFee $tuitionFee)
    {
        $tuitionFee->delete();

        return back()->with('success', 'Tarif dipindahkan ke sampah.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:tuition_fees,id',
        ]);

        TuitionFee::whereIn('id', $validated['ids'])->get()->each->delete();

        return back()->with('success', count($validated['ids']).' tarif dipindahkan ke sampah.');
    }

    public function restore(int $id)
    {
        TuitionFee::onlyTrashed()->whereKey($id)->firstOrFail()->restore();

        return back()->with('success', 'Tarif dipulihkan dari sampah.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:tuition_fees,id',
        ]);

        TuitionFee::onlyTrashed()->whereIn('id', $validated['ids'])->restore();

        return back()->with('success', count($validated['ids']).' tarif dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        TuitionFee::onlyTrashed()->whereKey($id)->firstOrFail()->forceDelete();

        return back()->with('success', 'Tarif dihapus permanen.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:tuition_fees,id',
        ]);

        TuitionFee::onlyTrashed()->whereIn('id', $validated['ids'])->forceDelete();

        return back()->with('success', count($validated['ids']).' tarif dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = TuitionFee::query()->with(['academicYear', 'studyProgram']);

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Tahun', 'Prodi', 'Semester', 'SPP', 'Lab', 'Perpus', 'Kegiatan', 'Total', 'Denda/Hari', 'Deadline', 'Aktif']], null, 'A1');

        foreach ($rows as $i => $fee) {
            $sheet->fromArray([
                $fee->academicYear?->name,
                $fee->studyProgram?->name,
                $fee->semester,
                (float) $fee->base_fee,
                (float) $fee->lab_fee,
                (float) $fee->library_fee,
                (float) $fee->activity_fee,
                $this->totalOf($fee),
                (float) $fee->late_penalty_per_day,
                $fee->payment_deadline?->format('Y-m-d'),
                $fee->is_active ? 'Ya' : 'Tidak',
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'tuition-fees-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        $unique = Rule::unique('tuition_fees')
            ->where(fn ($q) => $q
                ->where('academic_year_id', request('academic_year_id'))
                ->where('study_program_id', request('study_program_id'))
                ->where('semester', request('semester')))
            ->whereNull('deleted_at');

        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return request()->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id', $unique],
            'study_program_id' => 'required|exists:study_programs,id',
            'semester' => 'required|integer|min:1|max:14',
            'base_fee' => 'required|numeric|min:0',
            'lab_fee' => 'nullable|numeric|min:0',
            'library_fee' => 'nullable|numeric|min:0',
            'activity_fee' => 'nullable|numeric|min:0',
            'late_penalty_per_day' => 'nullable|numeric|min:0',
            'payment_deadline' => 'required|date',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ], [
            'academic_year_id.unique' => 'Kombinasi tahun, prodi, dan semester ini sudah ada.',
        ]);
    }

    private function totalOf(TuitionFee $fee): float
    {
        return (float) $fee->base_fee
            + (float) $fee->lab_fee
            + (float) $fee->library_fee
            + (float) $fee->activity_fee;
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
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

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function programOptions(): array
    {
        return StudyProgram::query()->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name.' ('.$p->code.')'])
            ->all();
    }
}
