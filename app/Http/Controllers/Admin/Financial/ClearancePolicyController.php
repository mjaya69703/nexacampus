<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialClearancePolicy;
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
 * CRUD kebijakan bebas tanggungan — memakai kit CRUD shared.
 *
 * Paritas Blade lama: mode warning/blocking, grace days, toggle aktif,
 * hapus permanen (tanpa sampah — model tanpa SoftDeletes).
 */
class ClearancePolicyController extends Controller
{
    public const MODES = ['warning', 'blocking'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'invoice_type' => 'nullable|string|max:50',
            'hold_type' => 'nullable|string|max:50',
            'mode' => 'nullable|string|max:20',
            'active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,created_at,grace_days',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = FinancialClearancePolicy::query()->withCount('holds as holds_count');

        if (filled($validated['q'] ?? null)) {
            $query->where('description', 'like', '%'.$validated['q'].'%');
        }

        foreach (['invoice_type', 'hold_type', 'mode'] as $key) {
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
        $policies = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/ClearancePolicy/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Kebijakan Bebas Tanggungan'),
            'can' => [
                'create' => ActivePermission::check('clearance-policy.create'),
                'update' => ActivePermission::check('clearance-policy.update'),
                'delete' => ActivePermission::check('clearance-policy.delete'),
                'toggle' => ActivePermission::check('clearance-policy.update'),
            ],
            'stats' => [
                'total' => FinancialClearancePolicy::count(),
                'active' => FinancialClearancePolicy::where('is_active', true)->count(),
                'blocking' => FinancialClearancePolicy::where('mode', 'blocking')->count(),
            ],
            'data' => [
                'rows' => collect($policies->items())->values()->map(fn ($p, $i) => [
                    'id' => $p->id,
                    'no' => ($policies->firstItem() ?? 0) + $i,
                    'invoiceType' => $this->label($p->invoice_type),
                    'holdType' => $this->holdLabel($p->hold_type),
                    'mode' => $p->mode,
                    'modeTone' => $p->mode === 'blocking' ? 'red' : 'amber',
                    'modeText' => $p->mode === 'blocking' ? 'Pemblokiran' : 'Peringatan',
                    'graceDays' => (int) $p->grace_days.' hari',
                    'isActive' => (bool) $p->is_active,
                    'holds' => (int) $p->holds_count,
                    'description' => $p->description,
                    'editUrl' => route('admin.financial.clearance-policies.edit', $p->id),
                    'deleteUrl' => route('admin.financial.clearance-policies.destroy', $p->id),
                    'toggleUrl' => route('admin.financial.clearance-policies.toggle', $p->id),
                ])->all(),
                'currentPage' => $policies->currentPage(),
                'lastPage' => $policies->lastPage(),
                'perPage' => $policies->perPage(),
                'total' => $policies->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'invoice_type' => $validated['invoice_type'] ?? '',
                'hold_type' => $validated['hold_type'] ?? '',
                'mode' => $validated['mode'] ?? '',
                'active' => $validated['active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'invoiceTypes' => $this->typeOptions(config('financial.invoice_types', [])),
            'holdTypes' => $this->holdOptions(),
            'modes' => self::MODES,
            'urls' => [
                'index' => route('admin.financial.clearance-policies.index'),
                'create' => route('admin.financial.clearance-policies.create'),
                'store' => route('admin.financial.clearance-policies.store'),
                'export' => route('admin.financial.clearance-policies.export'),
                'bulkDestroy' => route('admin.financial.clearance-policies.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Financial/ClearancePolicy/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Tambah Kebijakan'),
            'mode' => 'create',
            'policy' => [
                'invoice_type' => '', 'hold_type' => '', 'mode' => 'warning',
                'grace_days' => 0, 'is_active' => true, 'description' => '',
            ],
            'invoiceTypes' => $this->typeOptions(config('financial.invoice_types', [])),
            'holdTypes' => $this->holdOptions(),
            'modes' => self::MODES,
            'urls' => [
                'index' => route('admin.financial.clearance-policies.index'),
                'submit' => route('admin.financial.clearance-policies.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request), [
            'invoice_type.unique' => 'Kombinasi jenis tagihan dan target ini sudah ada.',
        ]);

        FinancialClearancePolicy::create($validated);

        return redirect()->route('admin.financial.clearance-policies.index')
            ->with('success', 'Kebijakan bebas tanggungan berhasil ditambahkan.');
    }

    public function edit(Request $request, FinancialClearancePolicy $clearancePolicy): Response
    {
        return Inertia::render('Admin/Financial/ClearancePolicy/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Ubah Kebijakan'),
            'mode' => 'edit',
            'policy' => [
                'invoice_type' => $clearancePolicy->invoice_type,
                'hold_type' => $clearancePolicy->hold_type,
                'mode' => $clearancePolicy->mode,
                'grace_days' => $clearancePolicy->grace_days,
                'is_active' => (bool) $clearancePolicy->is_active,
                'description' => $clearancePolicy->description,
            ],
            'invoiceTypes' => $this->typeOptions(config('financial.invoice_types', [])),
            'holdTypes' => $this->holdOptions(),
            'modes' => self::MODES,
            'urls' => [
                'index' => route('admin.financial.clearance-policies.index'),
                'submit' => route('admin.financial.clearance-policies.update', $clearancePolicy),
            ],
        ]);
    }

    public function update(Request $request, FinancialClearancePolicy $clearancePolicy)
    {
        $validated = $request->validate($this->rules($request, $clearancePolicy->id), [
            'invoice_type.unique' => 'Kombinasi jenis tagihan dan target ini sudah ada.',
        ]);

        $clearancePolicy->update($validated);

        return redirect()->route('admin.financial.clearance-policies.index')
            ->with('success', 'Kebijakan bebas tanggungan berhasil diperbarui.');
    }

    public function toggle(FinancialClearancePolicy $clearancePolicy)
    {
        $clearancePolicy->update(['is_active' => ! $clearancePolicy->is_active]);

        return back()->with('success', 'Status kebijakan diperbarui.');
    }

    public function destroy(FinancialClearancePolicy $clearancePolicy)
    {
        $clearancePolicy->delete();

        return back()->with('success', 'Kebijakan dihapus permanen.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:financial_clearance_policies,id',
        ]);

        FinancialClearancePolicy::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', count($validated['ids']).' kebijakan dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = FinancialClearancePolicy::query()->withCount('holds as holds_count');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderBy('invoice_type')->orderBy('hold_type')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Jenis Tagihan', 'Target Hold', 'Mode', 'Grace (Hari)', 'Aktif', 'Jml Hold', 'Deskripsi']], null, 'A1');

        foreach ($rows as $i => $policy) {
            $sheet->fromArray([
                $this->label($policy->invoice_type),
                $this->holdLabel($policy->hold_type),
                $policy->mode,
                (int) $policy->grace_days,
                $policy->is_active ? 'Ya' : 'Tidak',
                (int) $policy->holds_count,
                $policy->description,
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'clearance-policies-'.now()->format('Ymd-His').'.'.$format;
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
    private function rules(Request $request, ?int $ignoreId = null): array
    {
        $unique = Rule::unique('financial_clearance_policies', 'invoice_type')
            ->where('hold_type', (string) $request->input('hold_type'));

        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return [
            'invoice_type' => ['required', Rule::in(array_keys(config('financial.invoice_types', []))), $unique],
            'hold_type' => ['required', Rule::in(array_keys(config('financial.hold_targets', [])))],
            'mode' => ['required', Rule::in(self::MODES)],
            'grace_days' => 'required|integer|min:0|max:365',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }

    private function label(?string $value): string
    {
        return config('financial.invoice_types.'.$value, str((string) $value)->replace('_', ' ')->title()->toString());
    }

    private function holdLabel(?string $value): string
    {
        return config('financial.hold_targets.'.$value.'.label', str((string) $value)->replace('_', ' ')->title()->toString());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function typeOptions(array $values): array
    {
        return collect($values)
            ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function holdOptions(): array
    {
        return collect(config('financial.hold_targets', []))
            ->map(fn ($target, $key) => [
                'id' => $key,
                'name' => $target['label'] ?? str($key)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }
}
