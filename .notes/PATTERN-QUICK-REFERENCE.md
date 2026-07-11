# NexaCampus Pattern Quick Reference

**Fast lookup guide for implementing Grade Book feature**

---

## CRUD Component Structure

### File Locations
```
app/Livewire/Academic/[ResourceName]Table.php
resources/views/components/admin/academic/[kebab-case]/⚡index.blade.php
resources/views/components/admin/academic/[kebab-case]/⚡create.blade.php
resources/views/components/admin/academic/[kebab-case]/⚡edit.blade.php
resources/views/components/admin/academic/[kebab-case]/⚡show.blade.php
```

### Anonymous Component Template
```blade
<?php

use Livewire\Component;
use App\Models\Academic\YourModel;

new class extends Component {
    public array $yourForm = [];
    public array $relatedData = [];

    public function mount(): void {
        $this->yourForm = [
            'field' => '',
        ];
        $this->relatedData = YourRelatedModel::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => $m->id, 'label' => $m->name])
            ->toArray();
    }

    public function save(): void {
        $validated = $this->validate([
            'yourForm.field' => 'required|string',
        ]);
        
        YourModel::create($validated['yourForm']);
        session()->flash('success', 'Data saved.');
        $this->redirectRoute('admin.academic.your-resources.index');
    }

    public function cancel(): void {
        $this->redirectRoute('admin.academic.your-resources.index');
    }

    public function render() {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Your Page Title',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h3 class="card-title">Your Title</h3>
        </div>
        <div class="card-body">
            <!-- Content -->
        </div>
    </div>
</div>
```

---

## PowerGrid Table Component

```php
<?php
namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudentGrade;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class YourResourceTable extends BasePowerGridTable {
    public string $tableName = 'yourResourceTable';
    
    protected ?string $bulkActionModel = YourModel::class;
    protected ?string $bulkActionPermissionPrefix = 'your-resource';
    protected string $bulkActionItemLabel = 'item label in Indonesian';

    public function setUp(): array {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder {
        return YourModel::query()
            ->with(['relations...'])
            ->withCount('relatedModel')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array {
        return [
            'related.model' => ['searchable_field'],
        ];
    }

    public function fields(): PowerGridFields {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('display_field', fn ($model) => $model->related?->name ?? '-');
    }

    public function columns(): array {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Display', 'display_field')->sortable(),
        ];
    }
}
```

---

## Form Patterns

### Input Types

**Text Input**
```blade
<div class="col-md-6 mb-3">
    <label class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" class="form-control" wire:model="form.name" required>
    @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
</div>
```

**Select Dropdown**
```blade
<div class="col-md-6 mb-3">
    <label class="form-label">Pilihan</label>
    <select class="form-select" wire:model="form.option_id">
        <option value="">Pilih Opsi</option>
        @foreach($options as $option)
            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
    @error('form.option_id') <span class="text-danger">{{ $message }}</span> @enderror
</div>
```

**Checkbox**
```blade
<div class="col-12 mb-3">
    <label class="form-check">
        <input type="checkbox" class="form-check-input" wire:model="form.is_active">
        <span class="form-check-label">Aktif</span>
    </label>
</div>
```

**Textarea**
```blade
<div class="col-12 mb-3">
    <label class="form-label">Keterangan</label>
    <textarea class="form-control" wire:model="form.notes" rows="4"></textarea>
</div>
```

### Form Submit
```blade
<div class="mt-4">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <button type="button" class="btn btn-secondary" wire:click="cancel">Batal</button>
</div>
```

---

## Dynamic Row Management

```php
// Component
public array $items = [];

public function addItem(): void {
    $this->items[] = [
        'id' => null,
        'name' => '',
        'value' => null,
        'sort_order' => count($this->items) + 1,
    ];
}

public function removeItem(int $index): void {
    unset($this->items[$index]);
    $this->items = array_values($this->items);
}
```

```blade
<!-- View -->
<div>
    @foreach($items as $index => $item)
        <div class="row mt-2">
            <div class="col-md-8">
                <input type="text" class="form-control"
                       wire:model="items.{{ $index }}.name">
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control"
                       wire:model="items.{{ $index }}.value">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm"
                        wire:click="removeItem({{ $index }})">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </div>
    @endforeach
    
    <button type="button" class="btn btn-secondary mt-3"
            wire:click="addItem">
        <i class="fa fa-plus me-2"></i> Add Item
    </button>
</div>
```

---

## Authorization

### In Views
```blade
@activecan('resource.create')
    <a href="{{ route('admin.academic.resources.create') }}" 
       class="btn btn-ghost-primary">
        <i class="fa fa-plus me-2"></i> Add
    </a>
@endactivecan
```

### In Components
```php
public function mount(): void {
    if (! auth()->user()->can('resource.delete')) {
        abort(403);
    }
}
```

---

## Button Styles

```blade
<!-- Primary action -->
<button class="btn btn-primary">Save</button>

<!-- Ghost/Outline variant -->
<a href="{{ route(...) }}" class="btn btn-ghost-primary">Create</a>

<!-- Secondary action -->
<button class="btn btn-secondary">Cancel</button>

<!-- Danger action -->
<button class="btn btn-danger">Delete</button>

<!-- Small button -->
<button class="btn btn-sm btn-secondary">Edit</button>

<!-- With icon -->
<button class="btn btn-primary">
    <i class="fa fa-plus me-2"></i> Add Item
</button>
```

---

## Common Icons (Font Awesome 6)

```blade
<i class="fa fa-plus"></i>           <!-- Add -->
<i class="fa fa-edit"></i>           <!-- Edit -->
<i class="fa fa-trash"></i>          <!-- Delete -->
<i class="fa fa-eye"></i>            <!-- View -->
<i class="fa fa-download"></i>       <!-- Download -->
<i class="fa fa-search"></i>         <!-- Search -->
<i class="fa fa-check"></i>          <!-- Check -->
<i class="fa fa-times"></i>          <!-- Close/Cancel -->
<i class="fa fa-exclamation-triangle"></i> <!-- Warning -->
```

---

## Reactive Updates

```php
// Listen to model changes
public function updatedFormFieldName(): void {
    // Called automatically when wire:model changes
    $this->loadRelatedData();
}

// Live update (debounced by default)
<input wire:model.live="formField">

// Deferred update (on form submit)
<input wire:model.defer="formField">
```

---

## Bootstrap Grid

```blade
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-12">
        <!-- Large: 50%, Medium: 50%, Small: 100% -->
    </div>
    <div class="col-lg-4 col-md-6 col-sm-12">
        <!-- Large: 33%, Medium: 50%, Small: 100% -->
    </div>
    <div class="col-lg-2">
        <!-- Large: 16.67%, Default: 100% -->
    </div>
</div>
```

---

## Registration in config/resources.php

```php
[
    'resource' => 'your-resource',
    'plural' => 'your-resources',
    'area' => 'academic',
    'component' => 'admin.academic.your-resources',
    'actions' => ['index', 'create', 'edit', 'delete'],
    'menu' => [
        'title' => 'Your Resources',
        'icon' => 'fas fa-icon-name',
        'group' => 'Academic',
        'order' => 10,
    ],
]
```

---

## Validation Rules (Common)

```php
$this->validate([
    'form.name' => 'required|string|max:255',
    'form.email' => 'required|email|unique:users,email',
    'form.code' => 'required|string|unique:courses,code',
    'form.credits' => 'required|integer|min:1|max:24',
    'form.is_active' => 'boolean',
    'form.description' => 'nullable|string',
    'form.date' => 'required|date',
    'form.items' => 'array',
    'form.items.*' => 'integer|exists:related_table,id',
]);
```

---

## Color & Theme

**Primary Purple:** `#6842f4`
**Bright Purple:** `#8b6cff`
**Deep Purple:** `#3f249c`
**Gradient:** `linear-gradient(165deg, #8b6cff 0%, #5d3fd3 48%, #372083 100%)`
**Background:** `#f7f3ff`
**Surface:** `#ffffff`

**CSS Classes:**
- `text-danger` - Red for errors/warnings
- `text-success` - Green for success
- `bg-success` - Green background
- `bg-danger` - Red background
- `bg-warning` - Yellow background

---

## Safe Navigation Pattern

```php
// Safely navigate relationships with null coalescing
$user->profile?->company?->location?->city ?? 'Unknown'

// In PowerGrid fields
Column::make('City', 'city', fn ($model) => $model->profile?->company?->location?->city ?? '-')
```

---

## Session Flash Messages

```php
session()->flash('success', 'Data berhasil disimpan.');
session()->flash('error', 'Terjadi kesalahan.');
session()->flash('warning', 'Perhatian!');
session()->flash('info', 'Informasi penting.');
```

---

## Common Route Names Pattern

```
admin.academic.resources.index
admin.academic.resources.create
admin.academic.resources.edit
admin.academic.resources.show
admin.academic.resources.delete

admin.access.users.index
admin.system.settings.index
```

---

## Model Relationships (Return Types)

```php
public function parent(): BelongsTo {
    return $this->belongsTo(ParentModel::class);
}

public function children(): HasMany {
    return $this->hasMany(ChildModel::class);
}

public function related(): BelongsToMany {
    return $this->belongsToMany(RelatedModel::class);
}
```

---

## Eager Load Prevention

```php
// ✓ Good - No N+1 queries
$grades = StudentGrade::with(['student', 'course'])->get();
foreach ($grades as $grade) {
    echo $grade->student->name;
}

// ✗ Bad - N+1 queries
$grades = StudentGrade::all();
foreach ($grades as $grade) {
    echo $grade->student->name; // Extra query per row!
}
```

---

## Activity Logging Setup

```php
use Spatie\Activitylog\Traits\LogsActivity;

class YourModel extends Model {
    use LogsActivity;
    
    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()
            ->useLogName('your_model')
            ->logOnly(['field1', 'field2'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```
