# NexaCampus Pattern Quick Reference — Inertia React CRUD

**Fast lookup guide untuk modul admin baru (Inertia.js 3 + React 19).**

> Seluruh modul admin termasuk **Financial** sudah memakai Inertia React. Pola Blade/Livewire/PowerGrid hanya tersisa sebagai referensi historis di `AI_CONTEXT.md` §3.1b.

---

## 1. File Locations

```
app/Http/Controllers/Admin/[Area]/[Resource]Controller.php
resources/js/pages/Admin/[Area]/[Resources]/Index.tsx
resources/js/pages/Admin/[Area]/[Resources]/Form.tsx
resources/js/pages/Admin/[Area]/[Resources]/Show.tsx      # opsional
resources/js/pages/Admin/[Area]/shared.tsx                # helper area (opsional)
tests/Feature/[Domain]/[Resource]CrudTest.php
```

Komponen shared yang dipakai ulang:

```
resources/js/components/Shared/AdminShell.tsx
resources/js/components/Shared/FlashAlert.tsx
resources/js/components/Shared/Crud/CrudTable.tsx
resources/js/components/Shared/Crud/FilterPanel.tsx
resources/js/components/Shared/Crud/CrudHero.tsx
resources/js/components/Shared/Crud/ConfirmModal.tsx
resources/js/components/Shared/Crud/AsyncSelect.tsx
resources/js/components/Shared/Crud/fields.tsx
resources/js/components/Shared/Crud/ImportModal.tsx
```

---

## 2. Route Registration

`routes/web/admin.php`:

```php
// 1. Skip resource dari loop legacy
if (in_array($resource['plural'], ['your-resources']) && ($resource['area'] ?? null) === 'academic') {
    continue;
}

// 2. Route eksplisit
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

Nama route **wajib** sama dengan yang dipakai sidebar/menu: `admin.[area].[plural].[action]`.

---

## 3. Controller Skeleton

```php
<?php

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
        $query->orderBy($sort, $direction);

        $rows = $query->paginate((int) ($validated['perPage'] ?? 10))->withQueryString();

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
                    'createdAt' => $row->created_at?->format('d M Y'),
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

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/YourResources/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Your Resource'),
            'mode' => 'create',
            'resource' => ['name' => '', 'code' => '', 'is_active' => true],
            'urls' => [
                'index' => route('admin.academic.your-resources.index'),
                'submit' => route('admin.academic.your-resources.store'),
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

    public function edit(Request $request, int $id): Response
    {
        $row = YourResource::findOrFail($id);

        return Inertia::render('Admin/Academic/YourResources/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Your Resource'),
            'mode' => 'edit',
            'resource' => [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'is_active' => (bool) $row->is_active,
            ],
            'urls' => [
                'index' => route('admin.academic.your-resources.index'),
                'submit' => route('admin.academic.your-resources.update', $row->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $row = YourResource::findOrFail($id);
        $row->update([
            ...$this->validatePayload($request, $row),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.your-resources.index')
            ->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $row = YourResource::findOrFail($id);

        // Guard relasi bisnis → back()->with('error', '...') bila masih dipakai.

        $row->update(['deleted_by' => $request->user()->id]);
        $row->delete();

        return redirect()->route('admin.academic.your-resources.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:your_resources,id',
        ]);

        $rows = YourResource::whereIn('id', $validated['ids'])->get();

        foreach ($rows as $row) {
            $row->update(['deleted_by' => $request->user()->id]);
            $row->delete();
        }

        return back()->with('success', $rows->count().' data berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
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

---

## 4. Index.tsx Skeleton

```tsx
import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    name: string;
    code: string;
    isActive: boolean;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; is_active: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    urls: { index: string; create: string; bulkDestroy: string };
};

export default function YourResourcesIndex({ shell, can, data, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Row | 'bulk' | null>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number>, clear = false) => {
        router.get(urls.index, { ...filters, q: search, ...overrides }, {
            preserveState: true,
            replace: true,
            onSuccess: () => { if (clear) setSelected([]); },
        });
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);

        const done = () => {
            setProcessing(false);
            setPending(null);
            setSelected([]);
        };

        if (pending === 'bulk') {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
            return;
        }

        router.delete(pending.deleteUrl, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Your Resources · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Plus}
                        eyebrow="Akademik"
                        title="Daftar Your Resources"
                        description="Kelola data master …"
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah</a>
                        ) : undefined}
                    />

                    <section className="db-card">
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    { key: 'name', label: 'Nama', sortable: true, render: (row) => row.name },
                                    { key: 'code', label: 'Kode', sortable: true, render: (row) => <span className="db-badge gray">{row.code}</span> },
                                    { key: 'is_active', label: 'Status', sortable: true, render: (row) => (
                                        <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                    ) },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={(key) => visit({
                                    sort: key,
                                    direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
                                    page: 1,
                                })}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                filterBar={(
                                    <FilterPanel count={filters.is_active !== '' ? 1 : 0} onReset={() => visit({ is_active: '', page: 1 }, true)}>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending('bulk')}
                                bulkLabel="Hapus terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending(row) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending === 'bulk' ? 'Hapus data terpilih?' : 'Hapus data?'}
                    message={pending === 'bulk' ? `${selected.length} data akan dihapus.` : `"${pending && pending !== 'bulk' ? pending.name : ''}" akan dihapus.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
```

---

## 5. Form.tsx Skeleton

```tsx
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextField } from '../../../../components/Shared/Crud/fields';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    resource: { id?: number; name: string; code: string; is_active: boolean };
    urls: { index: string; submit: string };
};

export default function YourResourceForm({ shell, mode, resource, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: resource.name,
        code: resource.code,
        is_active: resource.is_active,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        isCreate ? form.post(urls.submit) : form.put(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Your Resource · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Plus}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Your Resource' : `Edit ${resource.name}`}
                        description="Lengkapi data di bawah ini."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <section className="db-card">
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama"
                                        required
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode"
                                        required
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        style={{ textTransform: 'uppercase' }}
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                    />
                                </div>
                            </div>
                        </section>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
```

---

## 6. CrudTable Props Cheat Sheet

| Prop | Tipe | Keterangan |
|---|---|---|
| `columns` | `CrudColumn<Row>[]` | `{ key, label, sortable?, align?: 'left'\|'right', render }` |
| `rows` | `Row[]` | Data halaman aktif |
| `page` | `{ currentPage, lastPage, perPage, total }` | Info pagination |
| `sort`, `direction` | `string`, `'asc'\|'desc'` | Sort aktif |
| `onSort` | `(key: string) => void` | Handler sort |
| `search`, `onSearchChange`, `onSearchSubmit` | `string`, fn, fn | Pencarian |
| `filterBar` | `ReactNode` | Isi `FilterPanel` |
| `selected`, `onToggle`, `onToggleAll` | `number[]`, fn, fn | Seleksi massal |
| `canDelete`, `onBulkDelete`, `bulkLabel` | `boolean`, fn, `string` | Bulk delete |
| `canUpdate`, `editUrl`, `onDeleteRow` | `boolean`, `(row) => string`, fn | Aksi baris |
| `onPage`, `perPage`, `onPerPageChange` | fn, `number`, fn | Pagination |
| `emptyText` | `string` | Empty state |

> `CrudTable` **tidak** punya prop `editLabel`; `CrudColumn.align` hanya `'left'` atau `'right'`.

---

## 7. Permission & Flash

```php
// Controller
'can' => [
    'create' => ActivePermission::check('your-resource.create'),
    'update' => ActivePermission::check('your-resource.update'),
    'delete' => ActivePermission::check('your-resource.delete'),
],
```

```php
// Redirect sukses/gagal
return back()->with('success', 'Data berhasil disimpan.');
return back()->with('error', 'Data tidak dapat dihapus karena masih dipakai.');
```

`AdminShell` sudah merender `FlashAlert`, jadi tidak perlu komponen alert tambahan di halaman React.

---

## 8. Test Pattern

```php
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan daftar resource', function () {
    $setup = yourResourceTestSetup();
    $this->actingAs($setup['user']);

    $this->get(route('admin.academic.your-resources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Academic/YourResources/Index')
            ->has('data.rows', 1)
            ->where('can.create', true));
});

it('menolak hapus bila masih dipakai', function () {
    // ... setup relasi ...
    $this->delete(route('admin.academic.your-resources.destroy', $row->id))
        ->assertRedirect()
        ->assertSessionHas('error');
});
```

---

## 9. Checklist Sebelum Selesai

1. `php artisan resources:sync`
2. `npm run typecheck`
3. `npm run build`
4. `php artisan test tests/Feature/[Domain]`
5. Cek halaman di browser (light & dark mode), termasuk search, filter, sort, pagination, create, edit, delete.
6. Pastikan tidak ada route/komponen Blade baru untuk modul admin.
