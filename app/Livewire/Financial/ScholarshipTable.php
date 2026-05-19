<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\Scholarship;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ScholarshipTable extends BasePowerGridTable
{
    public string $tableName = 'scholarshipTable';

    protected ?string $bulkActionModel = Scholarship::class;

    protected ?string $bulkActionPermissionPrefix = 'scholarship';

    protected string $bulkActionItemLabel = 'scholarship';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return Scholarship::query()->withCount('assignments')->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('type_label', fn (Scholarship $model) => str($model->type)->replace('_', ' ')->title()->toString())
            ->add('discount_label', fn (Scholarship $model) => $model->discount_type === 'fixed'
                ? $this->money($model->fixed_amount)
                : number_format((float) $model->discount_percentage, 2).'%')
            ->add('duration_semesters')
            ->add('assignments_count')
            ->add('is_active_label', fn (Scholarship $model) => $model->is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>')
            ->add('is_active');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Type', 'type_label')->sortable(),
            Column::make('Discount', 'discount_label'),
            Column::make('Duration', 'duration_semesters')->sortable(),
            Column::make('Assignments', 'assignments_count')->sortable(),
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
        $this->redirectRoute('admin.financial.scholarships.edit', ['id' => $rowId]);
    }

    public function actions(Scholarship $row): array
    {
        if (! ActivePermission::check('scholarship.update')) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
