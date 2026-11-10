<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
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
 * CRUD profil kepegawaian — paritas halaman Livewire lama: daftar pegawai,
 * formulir tautan akun user, toggle status aktif, dan penjagaan hapus.
 */
class EmployeeProfileController extends Controller
{
    private const EMPLOYMENT_TYPES = [
        'lecturer' => 'Dosen',
        'tendik' => 'Tenaga Kependidikan',
        'admin_staff' => 'Staff Administrasi',
        'contract' => 'Kontrak',
        'guest' => 'Tamu / Guest',
        'staff' => 'Staff',
    ];

    private const EMPLOYMENT_STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'suspended' => 'Ditangguhkan',
        'resigned' => 'Resign / Berhenti',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'employment_type' => ['nullable', Rule::in(array_keys(self::EMPLOYMENT_TYPES))],
            'employment_status' => ['nullable', Rule::in(array_keys(self::EMPLOYMENT_STATUSES))],
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:employee_number,employment_type,employment_status,active_position_assignments_count,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = EmployeeProfile::query()
            ->with(['user', 'primaryWorkUnit'])
            ->withCount('activePositionAssignments');

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->where('employee_number', 'like', $term)
                    ->orWhereHas('user', function ($user) use ($term) {
                        $user->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('username', 'like', $term)
                            ->orWhere('code', 'like', $term)
                            ->orWhere('identity_number', 'like', $term);
                    })
                    ->orWhereHas('primaryWorkUnit', function ($unit) use ($term) {
                        $unit->where('name', 'like', $term)->orWhere('code', 'like', $term);
                    });
            });
        }

        if (filled($validated['employment_type'] ?? null)) {
            $query->where('employment_type', $validated['employment_type']);
        }

        if (filled($validated['employment_status'] ?? null)) {
            $query->where('employment_status', $validated['employment_status']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $profiles = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/EmployeeProfiles/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Pegawai'),
            'can' => [
                'create' => ActivePermission::check('employee-profile.create'),
                'update' => ActivePermission::check('employee-profile.update'),
                'delete' => ActivePermission::check('employee-profile.delete'),
            ],
            'stats' => [
                'total' => EmployeeProfile::count(),
                'active' => EmployeeProfile::where('is_active', true)->count(),
                'inactive' => EmployeeProfile::where('is_active', false)->count(),
                'positions' => (int) EmployeeProfile::query()
                    ->withCount('activePositionAssignments')
                    ->get()
                    ->sum('active_position_assignments_count'),
            ],
            'data' => [
                'rows' => collect($profiles->items())->values()->map(fn (EmployeeProfile $profile, int $i) => [
                    'id' => $profile->id,
                    'no' => ($profiles->firstItem() ?? 0) + $i,
                    'name' => $profile->user?->name ?? '-',
                    'email' => $profile->user?->email,
                    'username' => $profile->user?->username,
                    'employeeNumber' => $profile->employee_number,
                    'employmentType' => $profile->employment_type,
                    'employmentTypeLabel' => self::EMPLOYMENT_TYPES[$profile->employment_type] ?? ucwords(str_replace('_', ' ', (string) $profile->employment_type)),
                    'employmentStatus' => $profile->employment_status,
                    'employmentStatusLabel' => self::EMPLOYMENT_STATUSES[$profile->employment_status] ?? ucwords(str_replace('_', ' ', (string) $profile->employment_status)),
                    'workUnit' => $profile->primaryWorkUnit?->name,
                    'positionsCount' => (int) $profile->active_position_assignments_count,
                    'isActive' => (bool) $profile->is_active,
                    'createdAt' => $profile->created_at?->format('d M Y'),
                    'editUrl' => route('admin.organization.employee-profiles.edit', $profile->id),
                    'toggleUrl' => route('admin.organization.employee-profiles.toggle', $profile->id),
                    'deleteUrl' => route('admin.organization.employee-profiles.destroy', $profile->id),
                ])->all(),
                'currentPage' => $profiles->currentPage(),
                'lastPage' => $profiles->lastPage(),
                'perPage' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'employment_type' => $validated['employment_type'] ?? '',
                'employment_status' => $validated['employment_status'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'employmentTypes' => $this->optionList(self::EMPLOYMENT_TYPES),
                'employmentStatuses' => $this->optionList(self::EMPLOYMENT_STATUSES),
            ],
            'urls' => [
                'index' => route('admin.organization.employee-profiles.index'),
                'create' => route('admin.organization.employee-profiles.create'),
                'bulkDestroy' => route('admin.organization.employee-profiles.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/EmployeeProfiles/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Pegawai'),
            'mode' => 'create',
            'profile' => [
                'user' => null,
                'employee_number' => '',
                'employment_type' => 'staff',
                'employment_status' => 'active',
                'join_date' => '',
                'end_date' => '',
                'primary_work_unit_id' => '',
                'notes' => '',
                'is_active' => true,
            ],
            'options' => [
                'employmentTypes' => $this->optionList(self::EMPLOYMENT_TYPES),
                'employmentStatuses' => $this->optionList(self::EMPLOYMENT_STATUSES),
                'workUnits' => $this->workUnits(),
            ],
            'urls' => [
                'index' => route('admin.organization.employee-profiles.index'),
                'submit' => route('admin.organization.employee-profiles.store'),
                'searchUsers' => route('admin.organization.employee-profiles.search-users'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        EmployeeProfile::create([
            'user_id' => $validated['user_id'],
            'employee_number' => $validated['employee_number'],
            'employment_type' => $validated['employment_type'],
            'employment_status' => $validated['employment_status'],
            'join_date' => $validated['join_date'],
            'end_date' => $validated['end_date'],
            'primary_work_unit_id' => $validated['primary_work_unit_id'],
            'notes' => $validated['notes'],
            'is_active' => $validated['is_active'],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.employee-profiles.index')
            ->with('success', 'Profil pegawai berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $profile = EmployeeProfile::with('user')->findOrFail($id);

        return Inertia::render('Admin/Organization/EmployeeProfiles/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Pegawai'),
            'mode' => 'edit',
            'profile' => [
                'id' => $profile->id,
                'user' => [
                    'id' => $profile->user?->id,
                    'name' => $profile->user?->name ?? '-',
                    'email' => $profile->user?->email,
                    'username' => $profile->user?->username,
                ],
                'employee_number' => $profile->employee_number ?? '',
                'employment_type' => $profile->employment_type,
                'employment_status' => $profile->employment_status,
                'join_date' => $profile->join_date?->format('Y-m-d') ?? '',
                'end_date' => $profile->end_date?->format('Y-m-d') ?? '',
                'primary_work_unit_id' => $profile->primary_work_unit_id ? (string) $profile->primary_work_unit_id : '',
                'notes' => $profile->notes ?? '',
                'is_active' => (bool) $profile->is_active,
            ],
            'options' => [
                'employmentTypes' => $this->optionList(self::EMPLOYMENT_TYPES),
                'employmentStatuses' => $this->optionList(self::EMPLOYMENT_STATUSES),
                'workUnits' => $this->workUnits(),
            ],
            'urls' => [
                'index' => route('admin.organization.employee-profiles.index'),
                'submit' => route('admin.organization.employee-profiles.update', $profile->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $profile = EmployeeProfile::findOrFail($id);
        $validated = $this->validatePayload($request, $profile);

        $profile->update([
            'employee_number' => $validated['employee_number'],
            'employment_type' => $validated['employment_type'],
            'employment_status' => $validated['employment_status'],
            'join_date' => $validated['join_date'],
            'end_date' => $validated['end_date'],
            'primary_work_unit_id' => $validated['primary_work_unit_id'],
            'notes' => $validated['notes'],
            'is_active' => $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.employee-profiles.index')
            ->with('success', 'Profil pegawai berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $profile = EmployeeProfile::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $profile->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $profile = EmployeeProfile::findOrFail($id);

        if ($profile->positionAssignments()->exists() || $profile->leaveRequests()->exists()) {
            return back()->with('error', 'Profil pegawai tidak dapat dihapus karena masih memiliki riwayat penugasan jabatan atau pengajuan cuti.');
        }

        $profile->update(['deleted_by' => $request->user()->id]);
        $profile->delete();

        return redirect()->route('admin.organization.employee-profiles.index')
            ->with('success', 'Profil pegawai berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employee_profiles,id',
        ]);

        $profiles = EmployeeProfile::whereIn('id', $validated['ids'])->get();

        foreach ($profiles as $profile) {
            $profile->update(['deleted_by' => $request->user()->id]);
            $profile->delete();
        }

        return back()->with('success', $profiles->count().' profil pegawai berhasil dihapus.');
    }

    /**
     * Endpoint pencarian akun user yang belum memiliki profil pegawai.
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

        $usedUserIds = EmployeeProfile::query()->pluck('user_id')->all();
        $q = $validated['q'] ?? '';

        $options = User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $usedUserIds)
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
    private function validatePayload(Request $request, ?EmployeeProfile $profile = null): array
    {
        $validated = $request->validate([
            'user_id' => $profile
                ? ['nullable', 'integer']
                : ['required', 'integer', 'exists:users,id', Rule::unique('employee_profiles', 'user_id')],
            'employee_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employee_profiles', 'employee_number')->ignore($profile?->id),
            ],
            'employment_type' => ['required', 'string', Rule::in(array_keys(self::EMPLOYMENT_TYPES))],
            'employment_status' => ['required', 'string', Rule::in(array_keys(self::EMPLOYMENT_STATUSES))],
            'join_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'primary_work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ]);

        $validated['employee_number'] = ($validated['employee_number'] ?? null) ?: null;
        $validated['join_date'] = ($validated['join_date'] ?? null) ?: null;
        $validated['end_date'] = ($validated['end_date'] ?? null) ?: null;
        $validated['primary_work_unit_id'] = ($validated['primary_work_unit_id'] ?? null) ?: null;
        $validated['notes'] = ($validated['notes'] ?? null) ?: null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['user_id'] = $profile ? $profile->user_id : (int) $validated['user_id'];

        return $validated;
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function workUnits(): array
    {
        return WorkUnit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (WorkUnit $unit) => [
                'id' => (string) $unit->id,
                'label' => $unit->name.($unit->code ? ' ('.$unit->code.')' : ''),
            ])
            ->values()
            ->all();
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
