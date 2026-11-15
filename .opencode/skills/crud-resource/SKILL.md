---
name: crud-resource
description: Pola CRUD admin Inertia React NexaCampus. Gunakan saat membuat resource/modul admin baru, menambah tabel CRUD, halaman admin, controller Inertia, atau mendaftarkan resource di config/resources.php (kata kunci: CRUD, resource, modul admin, Inertia, React, config/resources.php).
---

# CRUD Admin Resource (Inertia React)

Pola wajib untuk semua CRUD admin baru di NexaCampus. `config/resources.php` adalah sumber menu & permission, sedangkan route dan halaman dibuat eksplisit per area memakai **Inertia.js 3 + React 19**.

> [!IMPORTANT]
> Jangan membuat PowerGrid/Livewire/Blade untuk modul admin baru. Sisa Livewire hanya untuk setup wizard. Modul Financial sudah memakai Inertia React.

## Urutan Pembuatan (7 Langkah — JANGAN bolong satu pun)

1. **Migration** → `database/migrations/` (lihat skill `db-schema`)
2. **Model** → `app/Models/{Domain}/{Resource}.php`
3. **Registrasi** → tambah entry di `config/resources.php`
4. **Skip list + route eksplisit** → `routes/web/admin.php`
5. **Controller** → `app/Http/Controllers/Admin/{Area}/{Resource}Controller.php`
6. **Halaman React** → `resources/js/pages/Admin/{Area}/{Resources}/Index.tsx` + `Form.tsx` (+ `Show.tsx`)
7. **Test + sync** → `tests/Feature/{Domain}/...CrudTest.php`, lalu `php artisan resources:sync`

## 1. Registrasi `config/resources.php`

```php
[
    'resource' => 'your-resource',      // singular kebab-case → permission: your-resource.viewAny
    'plural' => 'your-resources',       // dipakai untuk URL
    'area' => 'academic',               // academic/admission/financial/alumni/campus/organization/publication/student-services/system/access
    'component' => 'admin.academic.your-resources', // legacy, tidak dipakai route Inertia
    'actions' => ['index', 'create', 'edit', 'show', 'delete'],
    'permissions' => ['viewAny', 'view', 'create', 'update', 'delete'], // opsional; default dari actions
    'menu' => [
        'title' => 'Your Resources',
        'icon' => 'fas fa-icon-name',
        'group' => 'Akademik',
        'group_icon' => 'fas fa-graduation-cap', // opsional
        'group_order' => 10,
        'order' => 1,
    ],
]
```

Permission mengikuti pola `{resource}.{action}`. Jika route memakai permission yang tidak lazim (mis. `student-credit.create` untuk mencatat mutasi), tambahkan eksplisit ke `permissions` agar `PermissionContractTest` tetap hijau.

## 2. Model Pattern

```php
namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class YourResource extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('your_resource')
            ->logOnly(['name', 'code', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = ['name', 'code', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
```

## 3. Route Eksplisit

Tambahkan plural resource ke skip list loop `ResourceRegistry::all()` di `routes/web/admin.php`:

```php
if (in_array($resource['plural'], ['your-resources']) && ($resource['area'] ?? null) === 'academic') {
    continue; // sudah Inertia React
}
```

Lalu tulis route eksplisit (nama route WAJIB sama dengan yang dipakai sidebar/menu):

```php
Route::get('/academic/your-resources', [YourResourceController::class, 'index'])
    ->middleware('active_permission:your-resource.viewAny')
    ->name('academic.your-resources.index');
Route::get('/academic/your-resources/create', [YourResourceController::class, 'create'])
    ->middleware('active_permission:your-resource.create')
    ->name('academic.your-resources.create');
Route::post('/academic/your-resources', [YourResourceController::class, 'store'])
    ->middleware('active_permission:your-resource.create')
    ->name('academic.your-resources.store');
Route::post('/academic/your-resources/bulk-destroy', [YourResourceController::class, 'bulkDestroy'])
    ->middleware('active_permission:your-resource.delete')
    ->name('academic.your-resources.bulk-destroy');
Route::get('/academic/your-resources/{id}/edit', [YourResourceController::class, 'edit'])
    ->middleware('active_permission:your-resource.update')
    ->name('academic.your-resources.edit');
Route::put('/academic/your-resources/{id}', [YourResourceController::class, 'update'])
    ->middleware('active_permission:your-resource.update')
    ->name('academic.your-resources.update');
Route::delete('/academic/your-resources/{id}', [YourResourceController::class, 'destroy'])
    ->middleware('active_permission:your-resource.delete')
    ->name('academic.your-resources.destroy');
```

## 4. Controller Inertia

```php
namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\YourResource;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class YourResourceController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = YourResource::query();

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $rows = $query->orderBy($sort, $direction)->paginate((int) ($validated['perPage'] ?? 10))->withQueryString();

        return Inertia::render('Admin/Academic/YourResources/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Your Resources'),
            'can' => [
                'create' => ActivePermission::check('your-resource.create'),
                'update' => ActivePermission::check('your-resource.update'),
                'delete' => ActivePermission::check('your-resource.delete'),
            ],
            'data' => [
                'rows' => collect($rows->items())->values()->map(fn (YourResource $row, int $i) => [
                    'id' => $row->id,
                    'no' => ($rows->firstItem() ?? 0) + $i,
                    'name' => $row->name,
                    'code' => $row->code,
                    'isActive' => (bool) $row->is_active,
                    'editUrl' => route('admin.academic.your-resources.edit', $row->id),
                    'deleteUrl' => route('admin.academic.your-resources.destroy', $row->id),
                ])->all(),
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => (int) ($validated['perPage'] ?? 10),
            ],
            'urls' => [
                'index' => route('admin.academic.your-resources.index'),
                'create' => route('admin.academic.your-resources.create'),
                'bulkDestroy' => route('admin.academic.your-resources.bulk-destroy'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        YourResource::create([
            ...$this->validatePayload($request),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.your-resources.index')
            ->with('success', 'Data berhasil ditambahkan.');
    }

    private function validatePayload(Request $request, ?YourResource $row = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', Rule::unique('your_resources', 'code')->ignore($row?->id)],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
```

**Aturan controller:**
- `ShellProps::make($user, $pretitle, $pageTitle)` wajib agar sidebar/topbar React tampil.
- Kirim `can` dari `ActivePermission::check(...)`, bukan `auth()->user()->can()`.
- Map baris tabel eksplisit; jangan kirim model Eloquent mentah. Sertakan `editUrl`/`deleteUrl`.
- `bulkDestroy` wajib punya guard relasi bisnis per baris (jangan soft-delete buta).
- Semua pesan flash & label Bahasa Indonesia.

## 5. Halaman React

Struktur: `resources/js/pages/Admin/{Area}/{Resources}/Index.tsx`, `Form.tsx`, `Show.tsx`.

**Index** memakai `AdminShell`, `CrudHero`, `CrudTable`, `FilterPanel`, `ConfirmModal`:

```tsx
<AdminShell shell={shell}>
    <Head title={`Your Resources · ${shell.appName}`} />
    <div className="db-root"><div className="db-stack">
        <CrudHero icon={Plus} eyebrow="Akademik" title="Daftar Your Resources" description="…"
            actions={can.create ? <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah</a> : undefined} />
        <section className="db-card"><div className="db-card-body">
            <CrudTable<Row>
                columns={[
                    { key: 'name', label: 'Nama', sortable: true, render: (row) => row.name },
                    { key: 'code', label: 'Kode', sortable: true, render: (row) => <span className="db-badge gray">{row.code}</span> },
                ]}
                rows={data.rows}
                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                sort={filters.sort}
                direction={filters.direction}
                onSort={(key) => visit({ sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}
                search={search}
                onSearchChange={setSearch}
                onSearchSubmit={() => visit({ page: 1 })}
                canUpdate={can.update}
                canDelete={can.delete}
                editUrl={(row) => row.editUrl}
                onDeleteRow={(row) => setPending(row)}
                onPage={(page) => visit({ page })}
                perPage={filters.perPage}
                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
            />
        </div></section>
    </div></div>
</AdminShell>
```

**Form** memakai `useForm` + `TextField`/`SwitchField`/`FormActions`:

```tsx
const form = useForm({ name: resource.name, code: resource.code, is_active: resource.is_active });

const submit = (event: FormEvent) => {
    event.preventDefault();
    isCreate ? form.post(urls.submit) : form.put(urls.submit);
};
```

**Komponen shared wajib diketahui:**

| Komponen | Fungsi |
|---|---|
| `components/Shared/AdminShell.tsx` | Sidebar + topbar + flash alert + layout portal |
| `components/Shared/Crud/CrudTable.tsx` | Tabel generik: sort, search, pagination, checkbox, bulk delete, actions |
| `components/Shared/Crud/FilterPanel.tsx` | Panel filter collapsible + badge jumlah filter aktif |
| `components/Shared/Crud/fields.tsx` | `TextField`, `TextareaField`, `SelectField`, `SwitchField`, `FormActions` |
| `components/Shared/Crud/AsyncSelect.tsx` | Pencarian server-side (debounce + endpoint JSON) |
| `components/Shared/Crud/ConfirmModal.tsx` | Konfirmasi hapus/aksi destruktif |
| `components/Shared/Crud/CrudHero.tsx` | Header halaman (ikon, eyebrow, judul, deskripsi, badges, actions) |
| `components/Shared/Crud/ImportModal.tsx` | Modal impor spreadsheet |

**Aturan UI:** gunakan class `db-*`/`crud-*` + CSS variables (`var(--db-card)`, `var(--db-line)`, `var(--db-heading)`, `var(--db-muted)`, `var(--db-brand)`). Jangan hardcode warna terang. Jangan pakai ukuran oversize (`btn-lg`, `form-control-lg`) atau `btn-sm` sembarangan. Detail: skill `ui-blade-darkmode`.

## 6. Test CRUD Inertia

```php
it('menampilkan daftar your resources', function () {
    $setup = yourResourceSetup();

    actingAsAdmin($setup['admin'])
        ->get(route('admin.academic.your-resources.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/YourResources/Index')
            ->has('data.rows', 1));
});
```

Uji juga `can`, filter, create/update, guard delete, bulk delete, dan permission.

## Konvensi Nama Terkait

| Item | Pattern |
|---|---|
| Route | `admin.{area}.{resources}.{action}` → `admin.academic.faculties.create` |
| Permission | `{resource-kebab}.{action}` → `faculty.viewAny` |
| Controller | `Admin/{Area}/{Resource}Controller` |
| Halaman | `pages/Admin/{Area}/{Resources}/{Index,Form,Show}.tsx` |
| Test | `tests/Feature/{Domain}/{Resource}CrudTest.php` |

## Setelah Selesai

```bash
php artisan resources:sync   # sync permissions + menus dari config
php artisan test             # seluruh suite harus hijau
npm run typecheck            # TypeScript
npm run build                # bundle Vite
```

Lalu jalankan checklist di skill `verification-workflow`.
