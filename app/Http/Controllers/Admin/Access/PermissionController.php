<?php

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Hak Akses — pilot migrasi Inertia React + kit CRUD shared.
 *
 * Kontrak backend standar yang dipakai ulang menu berikutnya:
 * index (filter/sort/paginasi server) → create/store → edit/update →
 * destroy → bulkDestroy → export (csv/xlsx). Guard hapus paritas
 * PermissionTable: permission yang masih dipakai role tidak bisa dihapus.
 */
class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'guard' => 'nullable|in:web,api',
            'sort' => 'nullable|in:id,name,guard_name,role_count,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Permission::query()->withCount('roles as role_count');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $query->where('name', 'like', '%'.$validated['q'].'%');
        }

        if (filled($validated['guard'] ?? null)) {
            $query->where('guard_name', $validated['guard']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $permissions = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Access/Permission/Index', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Daftar Permission'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('permission.create'),
                'update' => ActivePermission::check('permission.update'),
                'delete' => ActivePermission::check('permission.delete'),
                'restore' => ActivePermission::any(['permission.update', 'permission.delete']),
            ],
            'stats' => [
                'total' => Permission::count(),
                'web' => Permission::where('guard_name', 'web')->count(),
                'api' => Permission::where('guard_name', 'api')->count(),
                'attached' => Permission::whereHas('roles')->count(),
                'trashed' => Permission::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($permissions->items())->values()->map(fn ($p, $i) => [
                    'id' => $p->id,
                    'no' => ($permissions->firstItem() ?? 0) + $i,
                    'name' => $p->name,
                    'guard' => $p->guard_name,
                    'roleCount' => (int) $p->role_count,
                    'createdAt' => $p->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.access.permissions.edit', $p->id),
                    'deleteUrl' => route('admin.access.permissions.destroy', $p->id),
                    'restoreUrl' => route('admin.access.permissions.restore', $p->id),
                    'forceUrl' => route('admin.access.permissions.force-destroy', $p->id),
                ])->all(),
                'currentPage' => $permissions->currentPage(),
                'lastPage' => $permissions->lastPage(),
                'perPage' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'guard' => $validated['guard'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.access.permissions.index'),
                'create' => route('admin.access.permissions.create'),
                'export' => route('admin.access.permissions.export'),
                'bulkDestroy' => route('admin.access.permissions.bulk-destroy'),
                'bulkRestore' => route('admin.access.permissions.bulk-restore'),
                'bulkForceDestroy' => route('admin.access.permissions.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Access/Permission/Form', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Tambah Permission'),
            'mode' => 'create',
            'permission' => ['name' => '', 'guard_name' => 'web', 'role_ids' => []],
            'roles' => $this->roleOptions(),
            'urls' => [
                'index' => route('admin.access.permissions.index'),
                'submit' => route('admin.access.permissions.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,NULL,id,guard_name,'.$request->input('guard_name'),
            'guard_name' => 'required|string|in:web,api',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (! empty($validated['role_ids'])) {
            $permission->syncRoles(Role::whereIn('id', $validated['role_ids'])->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.access.permissions.index')
            ->with('success', 'Permission "'.$permission->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Permission $permission): Response
    {
        return Inertia::render('Admin/Access/Permission/Form', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Edit Permission'),
            'mode' => 'edit',
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'role_ids' => $permission->roles()->pluck('roles.id')->all(),
            ],
            'roles' => $this->roleOptions(),
            'urls' => [
                'index' => route('admin.access.permissions.index'),
                'submit' => route('admin.access.permissions.update', $permission),
            ],
        ]);
    }

    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,'.$permission->id.',id,guard_name,'.$request->input('guard_name'),
            'guard_name' => 'required|string|in:web,api',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $permission->name = $validated['name'];
        $permission->guard_name = $validated['guard_name'];
        $permission->save();

        $permission->syncRoles(Role::whereIn('id', $validated['role_ids'] ?? [])->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.access.permissions.index')
            ->with('success', 'Permission "'.$permission->name.'" berhasil diperbarui.');
    }

    public function destroy(Permission $permission)
    {
        if ($permission->roles()->count() > 0) {
            return back()->with('error', 'Permission "'.$permission->name.'" masih digunakan oleh role. Lepaskan relasinya dulu sebelum menghapus.');
        }

        $name = $permission->name;
        $permission->delete();

        return redirect()->route('admin.access.permissions.index')
            ->with('success', 'Permission "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:permissions,id',
        ]);

        $permissions = Permission::whereIn('id', $validated['ids'])->withCount('roles')->get();
        $blocked = $permissions->where('roles_count', '>', 0);
        $deletable = $permissions->where('roles_count', 0);

        Permission::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' permission dihapus. '.$blocked->count().' tidak bisa dihapus karena masih digunakan oleh role.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Permission tidak bisa dihapus karena masih digunakan oleh role.');
        }

        return back()->with('success', $deletable->count().' permission berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $permission = Permission::withTrashed()->findOrFail($id);
        $permission->restore();

        return back()->with('success', 'Permission "'.$permission->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $permission = Permission::withTrashed()->findOrFail($id);

        if ($permission->roles()->count() > 0) {
            return back()->with('error', 'Permission "'.$permission->name.'" masih digunakan oleh role. Lepaskan relasinya dulu sebelum menghapus permanen.');
        }

        $name = $permission->name;
        $permission->forceDelete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Permission "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:permissions,id',
        ]);

        $count = 0;

        foreach (Permission::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $permission) {
            $permission->restore();
            $count++;
        }

        return back()->with('success', $count.' permission berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:permissions,id',
        ]);

        $permissions = Permission::withTrashed()->whereIn('id', $validated['ids'])->withCount('roles')->get();
        $blocked = $permissions->where('roles_count', '>', 0);
        $deletable = $permissions->where('roles_count', 0);

        foreach ($deletable as $permission) {
            $permission->forceDelete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' permission dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih digunakan oleh role.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Permission tidak bisa dihapus karena masih digunakan oleh role.');
        }

        return back()->with('success', $deletable->count().' permission dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Permission::query()->withCount('roles as role_count');

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $query->where('name', 'like', '%'.$request->query('q').'%');
        }

        if (filled($request->query('guard'))) {
            $query->where('guard_name', $request->query('guard'));
        }

        $rows = $query->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama Permission', 'Guard', 'Dipakai Role', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $permission) {
            $sheet->fromArray([
                $permission->name,
                $permission->guard_name,
                (int) $permission->role_count,
                $permission->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (['A', 'B', 'C', 'D'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'permissions-'.now()->format('Ymd-His').'.'.$format;
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
    private function roleOptions(): array
    {
        return Role::query()->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])
            ->all();
    }
}
