<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentComplaintCategory;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentComplaintCategoryTable extends BasePowerGridTable
{
    public string $tableName = 'studentComplaintCategoryTable';

    protected ?string $bulkActionModel = StudentComplaintCategory::class;

    protected ?string $bulkActionPermissionPrefix = 'student-complaint-category';

    protected string $bulkActionItemLabel = 'kategori pengaduan';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return StudentComplaintCategory::query()->with(['defaultWorkUnit'])->withCount('complaints');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('default_work_unit', fn ($row) => $row->defaultWorkUnit?->name ?? '-')
            ->add('default_sla_hours')
            ->add('complaints_count')
            ->add('is_active');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Unit Default', 'default_work_unit', 'default_work_unit_id'),
            Column::make('SLA (Jam)', 'default_sla_hours')->sortable(),
            Column::make('Tiket', 'complaints_count')->sortable(),
            Column::make('Aktif', 'is_active')->toggleable(ActivePermission::check('student-complaint-category.update'), 'Aktif', 'Nonaktif')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->placeholder('Cari kategori...'),
            Filter::inputText('code')->placeholder('Cari kode...'),
            Filter::select('default_work_unit', 'default_work_unit_id')
                ->dataSource(WorkUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('default_work_unit_id', $value)),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field === 'is_active' && ActivePermission::check('student-complaint-category.update')) {
            StudentComplaintCategory::whereKey($id)->update(['is_active' => (bool) $value]);
        }

        $this->dispatch('pg:eventRefresh-studentComplaintCategoryTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.student-services.complaint-categories.edit', ['id' => $rowId]);
    }

    public function actions(StudentComplaintCategory $row): array
    {
        return ActivePermission::check('student-complaint-category.update')
            ? [Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id])]
            : [];
    }
}
