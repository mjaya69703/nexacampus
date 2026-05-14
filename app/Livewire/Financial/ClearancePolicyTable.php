<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\FinancialClearancePolicy;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ClearancePolicyTable extends BasePowerGridTable
{
    public string $tableName = 'clearancePolicyTable';

    protected ?string $bulkActionModel = FinancialClearancePolicy::class;

    protected ?string $bulkActionPermissionPrefix = 'clearance-policy';

    protected string $bulkActionItemLabel = 'clearance policy';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return FinancialClearancePolicy::query()
            ->orderBy('invoice_type')
            ->orderBy('hold_type');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('invoice_type_label', fn (FinancialClearancePolicy $model) => str($model->invoice_type)->replace('_', ' ')->title()->toString())
            ->add('hold_type_label', fn (FinancialClearancePolicy $model) => str($model->hold_type)->replace('_', ' ')->title()->toString())
            ->add('mode_badge', fn (FinancialClearancePolicy $model) => $this->modeBadge($model->mode))
            ->add('mode')
            ->add('grace_days')
            ->add('is_active')
            ->add('description_label', fn (FinancialClearancePolicy $model) => str($model->description ?: '-')->limit(80)->toString())
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Invoice Type', 'invoice_type_label', 'invoice_type')->sortable()->searchable(),
            Column::make('Hold Target', 'hold_type_label', 'hold_type')->sortable()->searchable(),
            Column::make('Mode', 'mode_badge', 'mode')->sortable(),
            Column::make('Grace Days', 'grace_days')->sortable(),
            Column::make('Active', 'is_active')
                ->toggleable(ActivePermission::check('clearance-policy.update'), 'Active', 'Inactive')
                ->sortable(),
            Column::make('Description', 'description_label', 'description')->searchable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('invoice_type', 'invoice_type')
                ->dataSource(collect($this->options(config('financial.invoice_types', []))))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('hold_type', 'hold_type')
                ->dataSource(collect($this->options($this->holdTargetOptions())))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('mode', 'mode')
                ->dataSource(collect([
                    ['id' => 'warning', 'name' => 'Warning'],
                    ['id' => 'blocking', 'name' => 'Blocking'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        abort_unless(ActivePermission::check('clearance-policy.update'), 403);

        FinancialClearancePolicy::whereKey($id)->update([
            'is_active' => (bool) $value,
        ]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.financial.clearance-policies.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $this->js('
            Swal.fire({
                title: "Hapus clearance policy?",
                text: "Policy ini akan dihapus dari konfigurasi aktif.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteClearancePolicy", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteClearancePolicy')]
    public function deleteItem($id = null): void
    {
        abort_unless(ActivePermission::check('clearance-policy.delete'), 403);

        FinancialClearancePolicy::findOrFail($id)->delete();
        session()->flash('success', 'Clearance policy berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-clearancePolicyTable');
    }

    public function actions(FinancialClearancePolicy $row): array
    {
        $actions = [];

        if (ActivePermission::check('clearance-policy.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('clearance-policy.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function modeBadge(string $mode): string
    {
        $class = $mode === 'blocking' ? 'bg-danger' : 'bg-warning text-dark';

        return '<span class="badge '.$class.'">'.str($mode)->title().'</span>';
    }

    private function options(array $values): array
    {
        return collect($values)
            ->map(fn (string $label, string $value) => [
                'id' => $value,
                'name' => $label,
            ])
            ->all();
    }

    private function holdTargetOptions(): array
    {
        return collect(config('financial.hold_targets', []))
            ->mapWithKeys(fn (array $target, string $key) => [
                $key => $target['label'] ?? str($key)->replace('_', ' ')->title()->toString(),
            ])
            ->all();
    }
}
