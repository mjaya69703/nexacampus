<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Admission\NimGenerationRule;
use App\Models\Admission\NimSequenceCounter;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Aturan Penomoran NIM — memakai kit CRUD shared.
 *
 * Hanya satu aturan yang boleh aktif pada satu waktu; mengaktifkan sebuah
 * aturan otomatis menonaktifkan aturan lain (paritas tabel Blade lama).
 */
class NimGenerationRuleController extends Controller
{
    public const SCOPES = [
        'global' => 'Global (Seluruh Kampus)',
        'year' => 'Per Tahun Angkatan',
        'period' => 'Per Gelombang / Periode',
        'faculty' => 'Per Fakultas',
        'study_program' => 'Per Program Studi',
        'class_type' => 'Per Jenis Kelas',
        'study_program_year' => 'Per Program Studi + Tahun',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'sequence_scope' => ['nullable', Rule::in(array_keys(self::SCOPES))],
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,pattern,sequence_scope,sequence_padding,sequence_start,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = NimGenerationRule::query()->withCount('counters');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('pattern', 'like', "%{$q}%"));
        }

        if (filled($validated['sequence_scope'] ?? null)) {
            $query->where('sequence_scope', $validated['sequence_scope']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $rules = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Admission/NimRule/Index', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Aturan Penomoran NIM'),
            'can' => [
                'create' => ActivePermission::check('nim-generation-rule.create'),
                'update' => ActivePermission::check('nim-generation-rule.update'),
                'delete' => ActivePermission::check('nim-generation-rule.delete'),
                'toggle' => ActivePermission::check('nim-generation-rule.update'),
            ],
            'stats' => [
                'total' => NimGenerationRule::count(),
                'active' => NimGenerationRule::where('is_active', true)->count(),
                'counters' => NimSequenceCounter::count(),
                'scopes' => NimGenerationRule::query()->distinct()->count('sequence_scope'),
            ],
            'data' => [
                'rows' => collect($rules->items())->values()->map(fn ($rule, $i) => [
                    'id' => $rule->id,
                    'no' => ($rules->firstItem() ?? 0) + $i,
                    'name' => $rule->name,
                    'pattern' => $rule->pattern,
                    'sequenceScope' => $rule->sequence_scope,
                    'scopeLabel' => self::SCOPES[$rule->sequence_scope] ?? $rule->sequence_scope,
                    'sequencePadding' => (int) $rule->sequence_padding,
                    'sequenceStart' => (int) $rule->sequence_start,
                    'countersCount' => (int) $rule->counters_count,
                    'isActive' => (bool) $rule->is_active,
                    'createdAt' => $rule->created_at?->format('d M Y H:i'),
                    'editUrl' => route('admin.admission.nim-generation-rules.edit', $rule),
                    'deleteUrl' => route('admin.admission.nim-generation-rules.destroy', $rule),
                    'toggleUrl' => route('admin.admission.nim-generation-rules.toggle', $rule),
                ])->all(),
                'currentPage' => $rules->currentPage(),
                'lastPage' => $rules->lastPage(),
                'perPage' => $rules->perPage(),
                'total' => $rules->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'sequence_scope' => $validated['sequence_scope'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'scopeOptions' => $this->scopeOptions(),
            'urls' => [
                'index' => route('admin.admission.nim-generation-rules.index'),
                'create' => route('admin.admission.nim-generation-rules.create'),
                'export' => route('admin.admission.nim-generation-rules.export'),
                'bulkDestroy' => route('admin.admission.nim-generation-rules.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Admission/NimRule/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Tambah Aturan NIM'),
            'mode' => 'create',
            'rule' => [
                'name' => 'Default NIM Rule',
                'pattern' => '{yy}{program_code}{sequence}',
                'sequence_scope' => 'study_program_year',
                'sequence_padding' => 4,
                'sequence_start' => 1,
                'is_active' => true,
                'description' => '',
            ],
            'scopeOptions' => $this->scopeOptions(),
            'urls' => [
                'index' => route('admin.admission.nim-generation-rules.index'),
                'submit' => route('admin.admission.nim-generation-rules.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $request) {
            if ($validated['is_active'] ?? false) {
                NimGenerationRule::query()->update(['is_active' => false]);
            }

            NimGenerationRule::create([
                ...$validated,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('admin.admission.nim-generation-rules.index')
            ->with('success', 'Aturan penomoran NIM berhasil ditambahkan.');
    }

    public function edit(Request $request, NimGenerationRule $rule): Response
    {
        return Inertia::render('Admin/Admission/NimRule/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Edit Aturan NIM'),
            'mode' => 'edit',
            'rule' => [
                'id' => $rule->id,
                'name' => $rule->name,
                'pattern' => $rule->pattern,
                'sequence_scope' => $rule->sequence_scope,
                'sequence_padding' => (int) $rule->sequence_padding,
                'sequence_start' => (int) $rule->sequence_start,
                'is_active' => (bool) $rule->is_active,
                'description' => $rule->description ?? '',
            ],
            'scopeOptions' => $this->scopeOptions(),
            'urls' => [
                'index' => route('admin.admission.nim-generation-rules.index'),
                'submit' => route('admin.admission.nim-generation-rules.update', $rule),
            ],
        ]);
    }

    public function update(Request $request, NimGenerationRule $rule)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $request, $rule) {
            if ($validated['is_active'] ?? false) {
                NimGenerationRule::query()->whereKeyNot($rule->id)->update(['is_active' => false]);
            }

            $rule->update([
                ...$validated,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('admin.admission.nim-generation-rules.index')
            ->with('success', 'Aturan penomoran NIM berhasil diperbarui.');
    }

    public function toggle(Request $request, NimGenerationRule $rule)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        DB::transaction(function () use ($validated, $request, $rule) {
            if ($validated['is_active']) {
                NimGenerationRule::query()->whereKeyNot($rule->id)->update(['is_active' => false]);
            }

            $rule->update([
                'is_active' => (bool) $validated['is_active'],
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Status aturan penomoran NIM berhasil diperbarui.');
    }

    public function destroy(NimGenerationRule $rule)
    {
        $name = $rule->name;
        $rule->delete();

        return redirect()->route('admin.admission.nim-generation-rules.index')
            ->with('success', 'Aturan "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:nim_generation_rules,id',
        ]);

        $count = NimGenerationRule::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' aturan NIM berhasil dihapus.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = NimGenerationRule::query()->withCount('counters');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('pattern', 'like', "%{$q}%"));
        }

        if (filled($request->query('sequence_scope'))) {
            $query->where('sequence_scope', $request->query('sequence_scope'));
        }

        if (filled($request->query('is_active'))) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        $rows = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama Aturan', 'Pola NIM', 'Ruang Lingkup', 'Padding', 'Mulai Dari', 'Counter Terpakai', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $rule) {
            $sheet->fromArray([
                $rule->name,
                $rule->pattern,
                self::SCOPES[$rule->sequence_scope] ?? $rule->sequence_scope,
                (int) $rule->sequence_padding,
                (int) $rule->sequence_start,
                (int) $rule->counters_count,
                $rule->is_active ? 'Ya' : 'Tidak',
                $rule->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'aturan-nim-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'pattern' => 'required|string|max:255',
            'sequence_scope' => ['required', Rule::in(array_keys(self::SCOPES))],
            'sequence_padding' => 'required|integer|min:1|max:10',
            'sequence_start' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function scopeOptions(): array
    {
        return collect(self::SCOPES)
            ->map(fn (string $label, string $value) => ['id' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
