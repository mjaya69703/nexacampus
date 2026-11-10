<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\EmployeePositionAssignment;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD unit kerja / biro kampus — paritas halaman Livewire lama
 * termasuk pengelolaan anggota unit (pivot work_unit_user).
 */
class WorkUnitController extends Controller
{
    private const POSITIONS = [
        'member' => 'Anggota Unit',
        'coordinator' => 'Koordinator Bidang',
        'head' => 'Kepala Unit (Head)',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,active_members_count,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = WorkUnit::query()->withCount('activeMembers');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'name') {
            $query->orderBy('name');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $units = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/WorkUnits/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Unit Kerja'),
            'can' => [
                'create' => ActivePermission::check('work-unit.create'),
                'update' => ActivePermission::check('work-unit.update'),
                'delete' => ActivePermission::check('work-unit.delete'),
            ],
            'stats' => [
                'total' => WorkUnit::count(),
                'active' => WorkUnit::where('is_active', true)->count(),
                'inactive' => WorkUnit::where('is_active', false)->count(),
                'members' => (int) WorkUnit::query()->withCount('activeMembers')->get()->sum('active_members_count'),
            ],
            'data' => [
                'rows' => collect($units->items())->values()->map(fn (WorkUnit $unit, int $i) => [
                    'id' => $unit->id,
                    'no' => ($units->firstItem() ?? 0) + $i,
                    'name' => $unit->name,
                    'code' => $unit->code,
                    'description' => $unit->description,
                    'activeMembersCount' => (int) $unit->active_members_count,
                    'isActive' => (bool) $unit->is_active,
                    'createdAt' => $unit->created_at?->format('d M Y'),
                    'editUrl' => route('admin.organization.work-units.edit', $unit->id),
                    'toggleUrl' => route('admin.organization.work-units.toggle', $unit->id),
                    'deleteUrl' => route('admin.organization.work-units.destroy', $unit->id),
                ])->all(),
                'currentPage' => $units->currentPage(),
                'lastPage' => $units->lastPage(),
                'perPage' => $units->perPage(),
                'total' => $units->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'positions' => $this->optionList(self::POSITIONS),
            'urls' => [
                'index' => route('admin.organization.work-units.index'),
                'create' => route('admin.organization.work-units.create'),
                'bulkDestroy' => route('admin.organization.work-units.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/WorkUnits/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Unit Kerja'),
            'mode' => 'create',
            'unit' => [
                'name' => '',
                'code' => '',
                'description' => '',
                'is_active' => true,
                'members' => [],
            ],
            'positions' => $this->optionList(self::POSITIONS),
            'urls' => [
                'index' => route('admin.organization.work-units.index'),
                'submit' => route('admin.organization.work-units.store'),
                'searchUsers' => route('admin.organization.work-units.search-users'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $unit = WorkUnit::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'],
            'is_active' => $validated['is_active'],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->syncMembers($unit, $validated['members'] ?? []);

        return redirect()->route('admin.organization.work-units.index')
            ->with('success', 'Unit kerja berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $unit = WorkUnit::with('members')->findOrFail($id);

        return Inertia::render('Admin/Organization/WorkUnits/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Unit Kerja'),
            'mode' => 'edit',
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'description' => $unit->description ?? '',
                'is_active' => (bool) $unit->is_active,
                'members' => $unit->members->map(fn (User $user) => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'position' => $user->pivot->position ?: 'member',
                    'is_active' => (bool) $user->pivot->is_active,
                ])->values()->all(),
            ],
            'positions' => $this->optionList(self::POSITIONS),
            'urls' => [
                'index' => route('admin.organization.work-units.index'),
                'submit' => route('admin.organization.work-units.update', $unit->id),
                'searchUsers' => route('admin.organization.work-units.search-users'),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $unit = WorkUnit::findOrFail($id);
        $validated = $this->validatePayload($request, $unit);

        $unit->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'],
            'is_active' => $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        $this->syncMembers($unit, $validated['members'] ?? []);

        return redirect()->route('admin.organization.work-units.index')
            ->with('success', 'Unit kerja berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $unit = WorkUnit::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $unit->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status unit kerja berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $unit = WorkUnit::findOrFail($id);

        if ($reason = $this->deleteBlocker($unit)) {
            return back()->with('error', $reason);
        }

        $unit->update(['deleted_by' => $request->user()->id]);
        $unit->delete();

        return redirect()->route('admin.organization.work-units.index')
            ->with('success', 'Unit kerja "'.$unit->name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:work_units,id',
        ]);

        $units = WorkUnit::whereIn('id', $validated['ids'])->get();

        foreach ($units as $unit) {
            $unit->update(['deleted_by' => $request->user()->id]);
            $unit->delete();
        }

        return back()->with('success', $units->count().' unit kerja berhasil dihapus.');
    }

    /**
     * Endpoint pencarian pegawai untuk picker anggota unit (server-side).
     */
    public function searchUsers(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'exclude' => 'nullable|string|max:2000',
        ]);

        $exclude = collect(explode(',', (string) ($validated['exclude'] ?? '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $q = $validated['q'] ?? '';

        $options = User::query()
            ->where('is_active', true)
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->when(filled($q), function ($query) use ($q) {
                $search = '%'.$q.'%';

                $query->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhere('identity_number', 'like', $search);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name,
                'meta' => [
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
            ])
            ->values()
            ->all();

        return response()->json(['options' => $options]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?WorkUnit $unit = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', Rule::unique('work_units', 'code')->ignore($unit?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id', 'distinct'],
            'members.*.position' => ['required', 'string', 'in:member,coordinator,head'],
            'members.*.is_active' => ['boolean'],
        ]);

        $validated['description'] = ($validated['description'] ?? null) ?: null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['members'] = collect($validated['members'] ?? [])
            ->filter(fn (array $member) => filled($member['user_id'] ?? null))
            ->map(fn (array $member) => [
                'user_id' => (int) $member['user_id'],
                'position' => $member['position'] ?? 'member',
                'is_active' => (bool) ($member['is_active'] ?? true),
            ])
            ->unique('user_id')
            ->values()
            ->all();

        return $validated;
    }

    /**
     * @param  array<int, array{user_id: int, position: string, is_active: bool}>  $members
     */
    private function syncMembers(WorkUnit $unit, array $members): void
    {
        $payload = collect($members)
            ->mapWithKeys(fn (array $member) => [
                (int) $member['user_id'] => [
                    'position' => $member['position'] ?? 'member',
                    'is_active' => (bool) ($member['is_active'] ?? true),
                ],
            ])
            ->all();

        $unit->members()->sync($payload);
    }

    /**
     * Unit tidak boleh dihapus selama masih dipakai anggota atau penugasan pegawai.
     */
    private function deleteBlocker(WorkUnit $unit): ?string
    {
        if ($unit->members()->exists()) {
            return 'Unit kerja tidak dapat dihapus karena masih memiliki anggota pegawai.';
        }

        if (EmployeePositionAssignment::where('work_unit_id', $unit->id)->exists()) {
            return 'Unit kerja tidak dapat dihapus karena masih digunakan dalam penugasan jabatan pegawai.';
        }

        if (EmployeeProfile::where('primary_work_unit_id', $unit->id)->exists()) {
            return 'Unit kerja tidak dapat dihapus karena masih menjadi unit kerja utama pegawai.';
        }

        return null;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return Collection::make($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
