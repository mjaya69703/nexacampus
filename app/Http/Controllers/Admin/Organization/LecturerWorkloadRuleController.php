<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\LecturerWorkloadRule;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD aturan konversi SKS BKD — paritas halaman Livewire lama.
 */
class LecturerWorkloadRuleController extends Controller
{
    private const CATEGORIES = [
        'teaching' => 'Pengajaran (Pendidikan & Perkuliahan)',
        'structural' => 'Jabatan Struktural / Tugas Tambahan',
        'tridharma' => 'Tridharma (Penelitian / Pengabdian / Penunjang)',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|in:teaching,structural,tridharma',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:category,source_code,name,sks_value,maximum_sks,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = LecturerWorkloadRule::query();

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('source_code', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category', $validated['category']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'category';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'source_code') {
            $query->orderBy('source_code');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $rules = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/LecturerWorkloadRules/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Aturan SKS BKD'),
            'can' => [
                'create' => ActivePermission::check('lecturer-workload-rule.create'),
                'update' => ActivePermission::check('lecturer-workload-rule.update'),
                'delete' => ActivePermission::check('lecturer-workload-rule.delete'),
            ],
            'stats' => [
                'total' => LecturerWorkloadRule::count(),
                'active' => LecturerWorkloadRule::where('is_active', true)->count(),
                'teaching' => LecturerWorkloadRule::where('category', 'teaching')->count(),
                'tridharma' => LecturerWorkloadRule::where('category', 'tridharma')->count(),
            ],
            'data' => [
                'rows' => collect($rules->items())->values()->map(fn (LecturerWorkloadRule $rule, int $i) => [
                    'id' => $rule->id,
                    'no' => ($rules->firstItem() ?? 0) + $i,
                    'category' => $rule->category,
                    'categoryLabel' => self::CATEGORIES[$rule->category] ?? $rule->category,
                    'sourceCode' => $rule->source_code,
                    'name' => $rule->name,
                    'sksValue' => (float) $rule->sks_value,
                    'maximumSks' => $rule->maximum_sks === null ? null : (float) $rule->maximum_sks,
                    'isActive' => (bool) $rule->is_active,
                    'description' => $rule->description,
                    'editUrl' => route('admin.organization.lecturer-workload-rules.edit', $rule->id),
                    'deleteUrl' => route('admin.organization.lecturer-workload-rules.destroy', $rule->id),
                ])->all(),
                'currentPage' => $rules->currentPage(),
                'lastPage' => $rules->lastPage(),
                'perPage' => $rules->perPage(),
                'total' => $rules->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-rules.index'),
                'create' => route('admin.organization.lecturer-workload-rules.create'),
                'bulkDestroy' => route('admin.organization.lecturer-workload-rules.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/LecturerWorkloadRules/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Aturan SKS'),
            'mode' => 'create',
            'rule' => [
                'category' => 'structural',
                'source_code' => '',
                'name' => '',
                'sks_value' => 1,
                'maximum_sks' => '',
                'is_active' => true,
                'description' => '',
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-rules.index'),
                'submit' => route('admin.organization.lecturer-workload-rules.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        LecturerWorkloadRule::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.lecturer-workload-rules.index')
            ->with('success', 'Aturan SKS berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $rule = LecturerWorkloadRule::findOrFail($id);

        return Inertia::render('Admin/Organization/LecturerWorkloadRules/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Aturan SKS'),
            'mode' => 'edit',
            'rule' => [
                'id' => $rule->id,
                'category' => $rule->category,
                'source_code' => $rule->source_code,
                'name' => $rule->name,
                'sks_value' => (float) $rule->sks_value,
                'maximum_sks' => $rule->maximum_sks === null ? '' : (float) $rule->maximum_sks,
                'is_active' => (bool) $rule->is_active,
                'description' => $rule->description ?? '',
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-rules.index'),
                'submit' => route('admin.organization.lecturer-workload-rules.update', $rule->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $rule = LecturerWorkloadRule::findOrFail($id);
        $validated = $this->validatePayload($request, $rule);

        $rule->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.lecturer-workload-rules.index')
            ->with('success', 'Aturan SKS berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $rule = LecturerWorkloadRule::findOrFail($id);
        $rule->update(['deleted_by' => $request->user()->id]);
        $rule->delete();

        return redirect()->route('admin.organization.lecturer-workload-rules.index')
            ->with('success', 'Aturan BKD "'.$rule->name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:lecturer_workload_rules,id',
        ]);

        $rules = LecturerWorkloadRule::whereIn('id', $validated['ids'])->get();

        foreach ($rules as $rule) {
            $rule->update(['deleted_by' => $request->user()->id]);
            $rule->delete();
        }

        return back()->with('success', $rules->count().' aturan SKS berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?LecturerWorkloadRule $rule = null): array
    {
        $request->merge([
            'source_code' => strtoupper(trim((string) $request->input('source_code'))),
        ]);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'source_code' => [
                'required', 'string', 'max:80',
                Rule::unique('lecturer_workload_rules', 'source_code')
                    ->where('category', $request->input('category'))
                    ->ignore($rule?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sks_value' => ['required', 'numeric', 'min:0'],
            'maximum_sks' => ['nullable', 'numeric', 'gte:sks_value'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['sks_value'] = (float) $validated['sks_value'];
        $validated['maximum_sks'] = filled($validated['maximum_sks'] ?? null) ? (float) $validated['maximum_sks'] : null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['description'] = ($validated['description'] ?? null) ?: null;

        return $validated;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
