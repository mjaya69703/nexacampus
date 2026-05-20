<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\StudentService\ServiceLetterType;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ServiceLetterTypeTable extends BasePowerGridTable
{
    public string $tableName = 'serviceLetterTypeTable';

    protected ?string $bulkActionModel = ServiceLetterType::class;

    protected ?string $bulkActionPermissionPrefix = 'service-letter-type';

    protected string $bulkActionItemLabel = 'letter type';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return ServiceLetterType::query()->orderBy('name');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('code')
            ->add('fulfillment_mode_label', fn (ServiceLetterType $model) => str($model->fulfillment_mode)->replace('_', ' ')->title()->toString())
            ->add('clearance_label', fn (ServiceLetterType $model) => $model->requires_financial_clearance
                ? '<span class="badge bg-warning text-dark">'.str($model->clearance_hold_type ?: 'required')->replace('_', ' ')->title().'</span>'
                : '<span class="badge bg-secondary">No clearance</span>')
            ->add('is_active_label', fn (ServiceLetterType $model) => $model->is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>')
            ->add('is_active');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Code', 'code')->sortable()->searchable(),
            Column::make('Mode', 'fulfillment_mode_label')->sortable(),
            Column::make('Clearance', 'clearance_label'),
            Column::make('Status', 'is_active_label')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('is_active', 'is_active')
                ->dataSource(collect([
                    ['id' => 1, 'name' => 'Active'],
                    ['id' => 0, 'name' => 'Inactive'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.student-services.letter-types.edit', ['id' => $rowId]);
    }

    public function actions(ServiceLetterType $row): array
    {
        if (! ActivePermission::check('service-letter-type.update')) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }
}
