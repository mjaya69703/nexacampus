<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\Scholarship;
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
 * CRUD program beasiswa — memakai kit CRUD shared.
 *
 * Paritas Blade lama: tipe full/partial/merit/need_based, potongan
 * persentase vs nominal, durasi semester, toggle aktif, hapus lunak +
 * restore + hapus permanen.
 */
class ScholarshipController extends Controller
{
    public const TYPES = ['full', 'partial', 'merit', 'need_based'];
    public const DISCOUNT_TYPES = ['percentage', 'fixed'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:20',
            'discount_type' => 'nullable|string|max:20',
            'active' => 'nullable|in:0,1',
            'mode' => 'nullable|in:all,trash',
            'sort' => 'nullable|in:id,created_at,name',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $query = Scholarship::query()->withCount('assignments as recipients_count');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $query->where('name', 'like', '%'.$validated['q'].'%');
        }

        foreach (['type', 'discount_type'] as $key) {
            if (filled($validated[$key] ?? null)) {
                $query->where($key, $validated[$key]);
            }
        }

        if (isset($validated['active']) && $validated['active'] !== '') {
            $query->where('is_active', (bool) $validated['active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $scholarships = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/Scholarship/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Program Beasiswa'),
            'can' => [
                'create' => ActivePermission::check('scholarship.create'),
                'update' => ActivePermission::check('scholarship.update'),
                'delete' => ActivePermission::check('scholarship.delete'),
                'restore' => ActivePermission::check('scholarship.delete'),
                'toggle' => ActivePermission::check('scholarship.update'),
            ],
            'stats' => [
                'total' => Scholarship::count(),
                'active' => Scholarship::where('is_active', true)->count(),
                'trashed' => Scholarship::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($scholarships->items())->values()->map(fn ($s, $i) => [
                    'id' => $s->id,
                    'no' => ($scholarships->firstItem() ?? 0) + $i,
                    'name' => $s->name,
                    'type' => $s->type,
                    'typeText' => $this->typeLabel($s->type),
                    'discount' => $s->discount_type === 'fixed'
                        ? $this->money($s->fixed_amount)
                        : rtrim(rtrim((string) $s->discount_percentage, '0'), '.').'% dari SPP',
                    'duration' => $s->duration_semesters !== null
                        ? (int) $s->duration_semesters.' semester'
                        : '-',
                    'recipients' => (int) $s->recipients_count.' penerima',
                    'isActive' => (bool) $s->is_active,
                    'editUrl' => route('admin.financial.scholarships.edit', $s->id),
                    'deleteUrl' => route('admin.financial.scholarships.destroy', $s->id),
                    'restoreUrl' => route('admin.financial.scholarships.restore', $s->id),
                    'forceUrl' => route('admin.financial.scholarships.force-destroy', $s->id),
                    'toggleUrl' => route('admin.financial.scholarships.toggle', $s->id),
                ])->all(),
                'currentPage' => $scholarships->currentPage(),
                'lastPage' => $scholarships->lastPage(),
                'perPage' => $scholarships->perPage(),
                'total' => $scholarships->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'discount_type' => $validated['discount_type'] ?? '',
                'active' => $validated['active'] ?? '',
                'mode' => $validated['mode'] ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'types' => self::TYPES,
            'discountTypes' => self::DISCOUNT_TYPES,
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.financial.scholarships.index'),
                'create' => route('admin.financial.scholarships.create'),
                'store' => route('admin.financial.scholarships.store'),
                'export' => route('admin.financial.scholarships.export'),
                'exportPdf' => route('admin.financial.exports.pdf', ['resource' => 'scholarships']),
                'bulkDestroy' => route('admin.financial.scholarships.bulk-destroy'),
                'bulkRestore' => route('admin.financial.scholarships.bulk-restore'),
                'bulkForceDestroy' => route('admin.financial.scholarships.bulk-force-destroy'),
                'importTemplate' => route('admin.financial.scholarships.import-template'),
                'importSubmit' => route('admin.financial.scholarships.import'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Financial/Scholarship/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Tambah Beasiswa'),
            'mode' => 'create',
            'scholarship' => [
                'name' => '', 'description' => '', 'type' => 'partial',
                'discount_type' => 'percentage', 'discount_percentage' => '',
                'fixed_amount' => '', 'duration_semesters' => '1',
                'requirements' => '', 'is_active' => true,
            ],
            'types' => self::TYPES,
            'discountTypes' => self::DISCOUNT_TYPES,
            'urls' => [
                'index' => route('admin.financial.scholarships.index'),
                'submit' => route('admin.financial.scholarships.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        Scholarship::create($validated);

        return redirect()->route('admin.financial.scholarships.index')
            ->with('success', 'Program beasiswa berhasil ditambahkan.');
    }

    public function edit(Request $request, Scholarship $scholarship): Response
    {
        return Inertia::render('Admin/Financial/Scholarship/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Ubah Beasiswa'),
            'mode' => 'edit',
            'scholarship' => [
                'name' => $scholarship->name,
                'description' => $scholarship->description,
                'type' => $scholarship->type,
                'discount_type' => $scholarship->discount_type,
                'discount_percentage' => $scholarship->discount_percentage,
                'fixed_amount' => $scholarship->fixed_amount,
                'duration_semesters' => $scholarship->duration_semesters,
                'requirements' => $scholarship->requirements,
                'is_active' => (bool) $scholarship->is_active,
            ],
            'types' => self::TYPES,
            'discountTypes' => self::DISCOUNT_TYPES,
            'urls' => [
                'index' => route('admin.financial.scholarships.index'),
                'submit' => route('admin.financial.scholarships.update', $scholarship),
            ],
        ]);
    }

    public function update(Request $request, Scholarship $scholarship)
    {
        $validated = $request->validate($this->rules());

        $scholarship->update($validated);

        return redirect()->route('admin.financial.scholarships.index')
            ->with('success', 'Program beasiswa berhasil diperbarui.');
    }

    public function toggle(Scholarship $scholarship)
    {
        $scholarship->update(['is_active' => ! $scholarship->is_active]);

        return back()->with('success', 'Status beasiswa diperbarui.');
    }

    public function destroy(Scholarship $scholarship)
    {
        $scholarship->delete();

        return back()->with('success', 'Beasiswa dipindahkan ke sampah.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:scholarships,id',
        ]);

        Scholarship::whereIn('id', $validated['ids'])->get()->each->delete();

        return back()->with('success', count($validated['ids']).' beasiswa dipindahkan ke sampah.');
    }

    public function restore(int $id)
    {
        Scholarship::onlyTrashed()->whereKey($id)->firstOrFail()->restore();

        return back()->with('success', 'Beasiswa dipulihkan dari sampah.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:scholarships,id',
        ]);

        Scholarship::onlyTrashed()->whereIn('id', $validated['ids'])->restore();

        return back()->with('success', count($validated['ids']).' beasiswa dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        Scholarship::onlyTrashed()->whereKey($id)->firstOrFail()->forceDelete();

        return back()->with('success', 'Beasiswa dihapus permanen.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:scholarships,id',
        ]);

        Scholarship::onlyTrashed()->whereIn('id', $validated['ids'])->forceDelete();

        return back()->with('success', count($validated['ids']).' beasiswa dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Scholarship::query()->withCount('assignments as recipients_count');

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Tipe', 'Metode', 'Potongan %', 'Nominal', 'Durasi (Smt)', 'Penerima', 'Aktif']], null, 'A1');

        foreach ($rows as $i => $scholarship) {
            $sheet->fromArray([
                $scholarship->name,
                $scholarship->type,
                $scholarship->discount_type,
                $scholarship->discount_percentage !== null ? (float) $scholarship->discount_percentage : null,
                $scholarship->fixed_amount !== null ? (float) $scholarship->fixed_amount : null,
                $scholarship->duration_semesters,
                (int) $scholarship->recipients_count,
                $scholarship->is_active ? 'Ya' : 'Tidak',
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'scholarships-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['name', 'type', 'discount_type', 'discount_percentage', 'fixed_amount', 'duration_semesters', 'requirements', 'is_active']], null, 'A1');
        $sheet->fromArray([['Beasiswa Prestasi', 'merit', 'percentage', '50', '', '8', 'IPK minimal 3.50', '1']], null, 'A2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-scholarships.xlsx',
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
        $missing = array_diff(['name', 'type', 'discount_type'], $header);

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

        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_map(
                fn ($v) => is_string($v) ? trim($v) : $v,
                array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null))
            );
            $rowErrors = [];

            if (($data['name'] ?? null) === null || $data['name'] === '') {
                $rowErrors[] = 'name wajib diisi';
            }

            if (! in_array($data['type'] ?? null, self::TYPES, true)) {
                $rowErrors[] = 'type harus '.implode('/', self::TYPES);
            }

            if (! in_array($data['discount_type'] ?? null, self::DISCOUNT_TYPES, true)) {
                $rowErrors[] = 'discount_type harus percentage/fixed';
            }

            $isPercentage = ($data['discount_type'] ?? null) === 'percentage';

            if ($isPercentage) {
                $percent = $data['discount_percentage'] ?? null;

                if ($percent === null || $percent === '' || ! is_numeric($percent) || (float) $percent < 0.01 || (float) $percent > 100) {
                    $rowErrors[] = 'discount_percentage harus 0.01-100 untuk percentage';
                }
            } else {
                $fixed = $data['fixed_amount'] ?? null;

                if ($fixed === null || $fixed === '' || ! is_numeric($fixed) || (float) $fixed < 1) {
                    $rowErrors[] = 'fixed_amount wajib >= 1 untuk fixed';
                }
            }

            $duration = $data['duration_semesters'] ?? null;

            if ($duration === null || $duration === '' || filter_var($duration, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]) === false) {
                $rowErrors[] = 'duration_semesters harus 1-20';
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
                'description' => null,
                'type' => $data['type'],
                'discount_type' => $data['discount_type'],
                'discount_percentage' => $isPercentage ? (float) $data['discount_percentage'] : null,
                'fixed_amount' => ! $isPercentage ? (float) $data['fixed_amount'] : null,
                'duration_semesters' => (int) $duration,
                'requirements' => null,
                'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.financial.scholarships.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Scholarship::insert($valid);

        return redirect()->route('admin.financial.scholarships.index')
            ->with('success', count($valid).' beasiswa berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => ['required', Rule::in(self::TYPES)],
            'discount_type' => ['required', Rule::in(self::DISCOUNT_TYPES)],
            'discount_percentage' => 'exclude_unless:discount_type,percentage|required|numeric|min:0.01|max:100',
            'fixed_amount' => 'exclude_unless:discount_type,fixed|required|numeric|min:1',
            'duration_semesters' => 'required|integer|min:1|max:20',
            'requirements' => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
        ];
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'full' => 'Pembebasan Penuh',
            'partial' => 'Potongan Sebagian',
            'merit' => 'Prestasi Akademik',
            'need_based' => 'Bantuan Ekonomi',
            default => str((string) $type)->replace('_', ' ')->title()->toString(),
        };
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
