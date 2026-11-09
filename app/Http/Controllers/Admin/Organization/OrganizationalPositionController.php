<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\OrganizationalPosition;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD master jabatan organisasi — paritas halaman Livewire lama.
 */
class OrganizationalPositionController extends Controller
{
    private const CATEGORIES = [
        'executive' => 'Pimpinan Eksekutif Kampus',
        'faculty' => 'Pimpinan Fakultas',
        'study_program' => 'Pimpinan Program Studi',
        'work_unit' => 'Kepala Unit Kerja',
        'staff' => 'Staff Struktural',
        'academic' => 'Fungsional Akademik',
    ];

    private const SCOPES = [
        'none' => 'Tanpa Scope (Global)',
        'faculty' => 'Fakultas',
        'study_program' => 'Program Studi',
        'work_unit' => 'Unit Kerja',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|in:executive,faculty,study_program,work_unit,staff,academic',
            'scope_type' => 'nullable|in:none,faculty,study_program,work_unit',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,category,scope_type,assignments_count,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = OrganizationalPosition::query()->withCount('assignments');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category', $validated['category']);
        }

        if (filled($validated['scope_type'] ?? null)) {
            $query->where('scope_type', $validated['scope_type']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $positions = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/OrganizationalPositions/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Jabatan'),
            'can' => [
                'create' => ActivePermission::check('organizational-position.create'),
                'update' => ActivePermission::check('organizational-position.update'),
                'delete' => ActivePermission::check('organizational-position.delete'),
            ],
            'stats' => [
                'total' => OrganizationalPosition::count(),
                'active' => OrganizationalPosition::where('is_active', true)->count(),
                'scoped' => OrganizationalPosition::where('scope_type', '!=', 'none')->count(),
                'assignments' => OrganizationalPosition::query()->withCount('assignments')->get()->sum('assignments_count'),
            ],
            'data' => [
                'rows' => collect($positions->items())->values()->map(fn (OrganizationalPosition $position, int $i) => [
                    'id' => $position->id,
                    'no' => ($positions->firstItem() ?? 0) + $i,
                    'name' => $position->name,
                    'code' => $position->code,
                    'category' => $position->category,
                    'categoryLabel' => self::CATEGORIES[$position->category] ?? $position->category,
                    'scopeType' => $position->scope_type,
                    'scopeLabel' => self::SCOPES[$position->scope_type] ?? $position->scope_type,
                    'assignmentsCount' => (int) $position->assignments_count,
                    'isActive' => (bool) $position->is_active,
                    'editUrl' => route('admin.organization.organizational-positions.edit', $position->id),
                    'toggleUrl' => route('admin.organization.organizational-positions.toggle', $position->id),
                    'deleteUrl' => route('admin.organization.organizational-positions.destroy', $position->id),
                ])->all(),
                'currentPage' => $positions->currentPage(),
                'lastPage' => $positions->lastPage(),
                'perPage' => $positions->perPage(),
                'total' => $positions->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'scope_type' => $validated['scope_type'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'scopes' => $this->optionList(self::SCOPES),
            'urls' => [
                'index' => route('admin.organization.organizational-positions.index'),
                'create' => route('admin.organization.organizational-positions.create'),
                'bulkDestroy' => route('admin.organization.organizational-positions.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/OrganizationalPositions/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Jabatan'),
            'mode' => 'create',
            'position' => [
                'name' => '',
                'code' => '',
                'category' => 'staff',
                'scope_type' => 'none',
                'description' => '',
                'is_active' => true,
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'scopes' => $this->optionList(self::SCOPES),
            'urls' => [
                'index' => route('admin.organization.organizational-positions.index'),
                'submit' => route('admin.organization.organizational-positions.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        OrganizationalPosition::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.organizational-positions.index')
            ->with('success', 'Jabatan berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $position = OrganizationalPosition::findOrFail($id);

        return Inertia::render('Admin/Organization/OrganizationalPositions/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Jabatan'),
            'mode' => 'edit',
            'position' => [
                'id' => $position->id,
                'name' => $position->name,
                'code' => $position->code,
                'category' => $position->category,
                'scope_type' => $position->scope_type,
                'description' => $position->description ?? '',
                'is_active' => (bool) $position->is_active,
            ],
            'categories' => $this->optionList(self::CATEGORIES),
            'scopes' => $this->optionList(self::SCOPES),
            'urls' => [
                'index' => route('admin.organization.organizational-positions.index'),
                'submit' => route('admin.organization.organizational-positions.update', $position->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $position = OrganizationalPosition::findOrFail($id);
        $validated = $this->validatePayload($request, $position);

        $position->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.organizational-positions.index')
            ->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $position = OrganizationalPosition::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $position->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status jabatan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $position = OrganizationalPosition::findOrFail($id);

        if ($position->assignments()->exists()) {
            return back()->with('error', 'Jabatan masih memiliki penugasan dan belum bisa dihapus.');
        }

        $position->update(['deleted_by' => $request->user()->id]);
        $position->delete();

        return redirect()->route('admin.organization.organizational-positions.index')
            ->with('success', 'Jabatan "'.$position->name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:organizational_positions,id',
        ]);

        $positions = OrganizationalPosition::whereIn('id', $validated['ids'])->get();

        foreach ($positions as $position) {
            $position->update(['deleted_by' => $request->user()->id]);
            $position->delete();
        }

        return back()->with('success', $positions->count().' jabatan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?OrganizationalPosition $position = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('organizational_positions', 'code')->ignore($position?->id)],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'scope_type' => ['required', Rule::in(array_keys(self::SCOPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

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
