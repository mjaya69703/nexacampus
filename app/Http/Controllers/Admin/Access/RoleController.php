<?php

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Peran — memakai kit CRUD shared (pola PermissionController).
 *
 * Guard hapus paritas RoleTable: role yang masih memiliki user tidak bisa
 * dihapus (satuan, massal, maupun permanen). Opsi permission memakai ID
 * asli database (memperbaiki bug values() di form Blade lama).
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'guard' => 'nullable|in:web,api',
            'sort' => 'nullable|in:id,name,guard_name,user_count,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Role::query()->withCount('users as user_count');

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
        $roles = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Access/Role/Index', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Daftar Peran'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('role.create'),
                'update' => ActivePermission::check('role.update'),
                'delete' => ActivePermission::check('role.delete'),
                'restore' => ActivePermission::any(['role.update', 'role.delete']),
            ],
            'stats' => [
                'total' => Role::count(),
                'web' => Role::where('guard_name', 'web')->count(),
                'permissions' => Permission::count(),
                'assigned' => Role::whereHas('users')->count(),
                'trashed' => Role::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($roles->items())->values()->map(fn ($r, $i) => [
                    'id' => $r->id,
                    'no' => ($roles->firstItem() ?? 0) + $i,
                    'name' => $r->name,
                    'guard' => $r->guard_name,
                    'userCount' => (int) $r->user_count,
                    'createdAt' => $r->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.access.roles.edit', $r->id),
                    'deleteUrl' => route('admin.access.roles.destroy', $r->id),
                    'restoreUrl' => route('admin.access.roles.restore', $r->id),
                    'forceUrl' => route('admin.access.roles.force-destroy', $r->id),
                ])->all(),
                'currentPage' => $roles->currentPage(),
                'lastPage' => $roles->lastPage(),
                'perPage' => $roles->perPage(),
                'total' => $roles->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'guard' => $validated['guard'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.access.roles.index'),
                'create' => route('admin.access.roles.create'),
                'export' => route('admin.access.roles.export'),
                'bulkDestroy' => route('admin.access.roles.bulk-destroy'),
                'bulkRestore' => route('admin.access.roles.bulk-restore'),
                'bulkForceDestroy' => route('admin.access.roles.bulk-force-destroy'),
                'importTemplate' => route('admin.access.roles.import-template'),
                'importSubmit' => route('admin.access.roles.import'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Access/Role/Form', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Tambah Peran'),
            'mode' => 'create',
            'role' => ['name' => '', 'guard_name' => 'web', 'permission_ids' => []],
            'groups' => $this->permissionGroups(),
            'urls' => [
                'index' => route('admin.access.roles.index'),
                'submit' => route('admin.access.roles.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,guard_name,'.$request->input('guard_name'),
            'guard_name' => 'required|string|in:web,api',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (! empty($validated['permission_ids'])) {
            $role->syncPermissions(Permission::whereIn('id', $validated['permission_ids'])->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.access.roles.index')
            ->with('success', 'Peran "'.$role->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, Role $role): Response
    {
        return Inertia::render('Admin/Access/Role/Form', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Edit Peran'),
            'mode' => 'edit',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
            ],
            'groups' => $this->permissionGroups(),
            'urls' => [
                'index' => route('admin.access.roles.index'),
                'submit' => route('admin.access.roles.update', $role),
            ],
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id.',id,guard_name,'.$request->input('guard_name'),
            'guard_name' => 'required|string|in:web,api',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->name = $validated['name'];
        $role->guard_name = $validated['guard_name'];
        $role->save();

        $role->syncPermissions(Permission::whereIn('id', $validated['permission_ids'] ?? [])->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.access.roles.index')
            ->with('success', 'Peran "'.$role->name.'" berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return back()->with('error', 'Role "'.$role->name.'" masih memiliki pengguna yang terkait. Hapus atau pindahkan pengguna tersebut sebelum menghapus role ini.');
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('admin.access.roles.index')
            ->with('success', 'Role "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:roles,id',
        ]);

        $roles = Role::whereIn('id', $validated['ids'])->withCount('users')->get();
        $blocked = $roles->where('users_count', '>', 0);
        $deletable = $roles->where('users_count', 0);

        Role::whereIn('id', $deletable->pluck('id'))->delete();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' role dihapus. '.$blocked->count().' tidak bisa dihapus karena masih memiliki user terkait.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Role tidak bisa dihapus karena masih memiliki user terkait.');
        }

        return back()->with('success', $deletable->count().' role berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $role = Role::withTrashed()->findOrFail($id);
        $role->restore();

        return back()->with('success', 'Role "'.$role->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $role = Role::withTrashed()->findOrFail($id);

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Role "'.$role->name.'" masih memiliki pengguna yang terkait. Hapus atau pindahkan pengguna tersebut sebelum menghapus permanen.');
        }

        $name = $role->name;
        $role->forceDelete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Role "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:roles,id',
        ]);

        $count = 0;

        foreach (Role::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $role) {
            $role->restore();
            $count++;
        }

        return back()->with('success', $count.' role berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:roles,id',
        ]);

        $roles = Role::withTrashed()->whereIn('id', $validated['ids'])->withCount('users')->get();
        $blocked = $roles->where('users_count', '>', 0);
        $deletable = $roles->where('users_count', 0);

        foreach ($deletable as $role) {
            $role->forceDelete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($blocked->isNotEmpty() && $deletable->isNotEmpty()) {
            return back()->with('warning', $deletable->count().' role dihapus permanen. '.$blocked->count().' tidak bisa dihapus karena masih memiliki user terkait.');
        }

        if ($blocked->isNotEmpty()) {
            return back()->with('error', 'Role tidak bisa dihapus karena masih memiliki user terkait.');
        }

        return back()->with('success', $deletable->count().' role dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Role::query()->withCount(['users', 'permissions']);

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
        $sheet->fromArray([['Nama Peran', 'Guard', 'Jumlah Pengguna', 'Jumlah Permission', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $role) {
            $sheet->fromArray([
                $role->name,
                $role->guard_name,
                (int) $role->users_count,
                (int) $role->permissions_count,
                $role->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'roles-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['name', 'guard_name', 'permission_names']], null, 'A1');
        $sheet->fromArray([['operator-pmb', 'web', 'admission-application.viewAny, admission-application.view']], null, 'A2');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-roles.xlsx',
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
        $missing = array_diff(['name'], $header);

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

        $permissionMap = Permission::pluck('id', 'name')->all();
        $seen = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_combine(
                $header,
                array_pad(array_slice($row, 0, count($header)), count($header), null)
            );
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
            $rowErrors = [];

            $name = $data['name'] ?? null;
            $guard = $data['guard_name'] ?? null;
            $guard = $guard === null || $guard === '' ? 'web' : $guard;

            if ($name === null || $name === '') {
                $rowErrors[] = 'name wajib diisi';
            }

            if (! in_array($guard, ['web', 'api'], true)) {
                $rowErrors[] = 'guard_name harus web atau api';
            }

            $combo = $name.'|'.$guard;

            if ($name && in_array($combo, $seen, true)) {
                $rowErrors[] = 'kombinasi name + guard duplikat di dalam file';
            } elseif ($name && Role::where('name', $name)->where('guard_name', $guard)->exists()) {
                $rowErrors[] = 'role sudah terdaftar untuk guard ini';
            } else {
                $seen[] = $combo;
            }

            $permissionIds = [];

            if (! empty($data['permission_names'])) {
                foreach (explode(',', (string) $data['permission_names']) as $permissionName) {
                    $permissionName = trim($permissionName);

                    if ($permissionName === '') {
                        continue;
                    }

                    if (! isset($permissionMap[$permissionName])) {
                        $rowErrors[] = "permission \"{$permissionName}\" tidak ditemukan";
                    } else {
                        $permissionIds[] = $permissionMap[$permissionName];
                    }
                }
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = ['name' => $name, 'guard' => $guard, 'permissionIds' => array_values(array_unique($permissionIds))];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.access.roles.index')->with('import_result', [
                'success' => false,
                'created' => 0,
                'rejected' => count($errors),
                'errors' => $errors,
            ]);
        }

        DB::transaction(function () use ($valid) {
            foreach ($valid as $item) {
                $role = Role::create(['name' => $item['name'], 'guard_name' => $item['guard']]);

                if (! empty($item['permissionIds'])) {
                    $role->syncPermissions(Permission::whereIn('id', $item['permissionIds'])->get());
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.access.roles.index')
            ->with('success', count($valid).' role berhasil diimpor.')
            ->with('import_result', [
                'success' => true,
                'created' => count($valid),
                'rejected' => 0,
                'errors' => [],
            ]);
    }

    /**
     * Opsi permission bergrup per resource dengan ID asli database.
     *
     * @return array<int, array{label: string, options: array<int, array{id: int, label: string}>}>
     */
    private function permissionGroups(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->groupBy(fn ($permission) => ucfirst(str($permission->name)->before('.')->replace('-', ' ')->toString()))
            ->map(fn ($permissions, $label) => [
                'label' => $label,
                'options' => $permissions->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
