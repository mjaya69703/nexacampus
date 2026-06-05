<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EdomPeriod;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EdomPeriodTable extends BasePowerGridTable
{
    public string $tableName = 'edomPeriodTable';

    protected ?string $bulkActionModel = EdomPeriod::class;

    protected ?string $bulkActionPermissionPrefix = 'edom-period';

    protected string $bulkActionItemLabel = 'periode EDOM';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EdomPeriod::query()->with('academicYear')->latest('starts_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('academic_year_name', fn (EdomPeriod $model) => $model->academicYear?->name ?? '-')
            ->add('period_range', fn (EdomPeriod $model) => ($model->starts_at?->format('d M Y') ?? '-').' - '.($model->ends_at?->format('d M Y') ?? '-'))
            ->add('minimum_responses')
            ->add('status_badge', fn (EdomPeriod $model) => '<span class="badge '.($model->status === 'open' ? 'bg-success' : ($model->status === 'closed' ? 'bg-secondary' : 'bg-muted')).'">'.str($model->status)->title().'</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year_name'),
            Column::make('Periode', 'period_range'),
            Column::make('Min. Respon', 'minimum_responses')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([['id' => 'draft', 'name' => 'Draft'], ['id' => 'open', 'name' => 'Dibuka'], ['id' => 'closed', 'name' => 'Ditutup']]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.edom-periods.edit', ['id' => $rowId]);
    }

    public function actions(EdomPeriod $row): array
    {
        return ActivePermission::check('edom-period.update')
            ? [Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id])]
            : [];
    }
}
