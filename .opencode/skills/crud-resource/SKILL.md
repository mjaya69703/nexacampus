---
name: crud-resource
description: Pola CRUD admin config-driven NexaCampus. Gunakan saat membuat resource/modul admin baru, menambah tabel CRUD, PowerGrid table, atau halaman admin (kata kunci: CRUD, resource, modul admin, PowerGrid, config/resources.php).
---

# CRUD Admin Resource (Config-Driven)

Pola wajib untuk semua CRUD admin di NexaCampus. Semua resource dikelola via `config/resources.php` dan di-route otomatis via macro `Route::crudLivewire()`.

## Urutan Pembuatan (6 Langkah — JANGAN bolong satu pun)

1. **Migration** → `database/migrations/` (lihat skill `db-schema`)
2. **Model** → `app/Models/{Domain}/{Resource}.php`
3. **Registrasi** → tambah entry di `config/resources.php`
4. **PowerGrid Table** → `app/Livewire/{Domain}/{Resource}Table.php` extends `BasePowerGridTable`
5. **Views** → `resources/views/components/admin/{domain}/{resources}/⚡{action}.blade.php` (anonymous Livewire component, WAJIB prefix `⚡`)
6. **Sync** → `php artisan resources:sync`

## 1. Registrasi `config/resources.php`

```php
[
    'resource' => 'your-resource',      // singular kebab-case → permission: your-resource.viewAny
    'plural' => 'your-resources',       // dipakai untuk URL
    'area' => 'academic',               // academic/admission/financial/alumni/campus/organization/publication/settings/student-service/system/access
    'component' => 'admin.academic.your-resources',
    'actions' => ['index', 'create', 'edit', 'show', 'delete'],
    'menu' => [
        'title' => 'Your Resources',
        'icon' => 'fas fa-icon-name',
        'group' => 'Akademik',
        'group_icon' => 'fas fa-graduation-cap', // optional
        'group_order' => 10,
        'order' => 1,
    ],
]
```

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
            ->logOnly(['field1', 'field2'])
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

## 3. PowerGrid Table Pattern

```php
namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable; // ⭐ SELALU extends ini
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;
use App\Models\Academic\YourResource;
use App\Support\ActivePermission;

final class YourResourceTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'yourResourceTable';
    protected ?string $bulkActionModel = YourResource::class;
    protected ?string $bulkActionPermissionPrefix = 'your-resource';
    protected string $bulkActionItemLabel = 'data';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return YourResource::query()->withCount('relatedModel'); // eager load, hindari N+1
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.your-resources.edit', ['id' => $rowId]);
    }

    // Delete: SweetAlert konfirmasi → dispatch('deleteItem') → ActivePermission::check + delete
}
```

Action button per-row selalu dicek permission:
```php
if (ActivePermission::check('your-resource.update')) { /* Button::add('edit') */ }
```

## 4. View Pattern (Anonymous Blade Component)

File: `⚡index.blade.php`, `⚡create.blade.php`, dst. Inline class + template dalam SATU file.

```blade
<?php
use Livewire\Component;

new class extends Component {
    public array $form = [];

    public function mount(): void
    {
        $this->form = ['name' => '', 'code' => '', 'is_active' => true];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:20|unique:your_resources,code',
            'form.is_active' => 'boolean',
        ]);

        YourResource::create([...$validated['form'], 'created_by' => auth()->id()]);
        session()->flash('success', 'Data berhasil ditambahkan.');
        $this->redirectRoute('admin.academic.your-resources.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Your Resources',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Your Resources</h3>
            @activecan('your-resource.create')
                <a href="{{ route('admin.academic.your-resources.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:academic.your-resource-table />
        </div>
    </div>
</div>
```

Aturan UI wajib: lihat skill `ui-blade-darkmode`.

## Konvensi Nama Terkait

| Item | Pattern |
|---|---|
| Route | `admin.{area}.{resources}.{action}` → `admin.academic.faculties.create` |
| Permission | `{resource-kebab}.{action}` → `faculty.viewAny` |
| Livewire Table | `{Resource}Table` |
| View | `components/admin/{domain}/{resources}/⚡{action}.blade.php` |

## Setelah Selesai

```bash
php artisan resources:sync   # sync permissions + menus dari config
```

Lalu jalankan checklist di skill `verification-workflow`.
