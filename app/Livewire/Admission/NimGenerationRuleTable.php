<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Admission\NimGenerationRule;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class NimGenerationRuleTable extends BasePowerGridTable
{
    public string $tableName = 'nimGenerationRuleTable';

    protected ?string $bulkActionModel = NimGenerationRule::class;

    protected ?string $bulkActionPermissionPrefix = 'nim-generation-rule';

    protected string $bulkActionItemLabel = 'NIM generation rule';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return NimGenerationRule::query()->withCount('counters')->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('pattern')
            ->add('sequence_scope')
            ->add('sequence_padding')
            ->add('sequence_start')
            ->add('counters_count')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Pattern', 'pattern')->sortable()->searchable(),
            Column::make('Scope', 'sequence_scope')->sortable(),
            Column::make('Padding', 'sequence_padding')->sortable(),
            Column::make('Start', 'sequence_start')->sortable(),
            Column::make('Counters', 'counters_count')->sortable(),
            Column::make('Active', 'is_active')
                ->toggleable(ActivePermission::check('nim-generation-rule.update'), 'Active', 'Inactive')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        abort_unless(ActivePermission::check('nim-generation-rule.update'), 403);

        if ((bool) $value) {
            NimGenerationRule::query()->whereKeyNot($id)->update(['is_active' => false]);
        }

        NimGenerationRule::whereKey($id)->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-nimGenerationRuleTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.admission.nim-generation-rules.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $this->js('
            Swal.fire({
                title: "Hapus NIM rule?",
                text: "Rule yang sudah memiliki counter sebaiknya tidak dihapus jika sudah dipakai.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteNimGenerationRule", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteNimGenerationRule')]
    public function deleteItem($id = null): void
    {
        abort_unless(ActivePermission::check('nim-generation-rule.delete'), 403);

        NimGenerationRule::findOrFail($id)->delete();
        session()->flash('success', 'NIM generation rule berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-nimGenerationRuleTable');
    }

    public function actions(NimGenerationRule $row): array
    {
        $actions = [];

        if (ActivePermission::check('nim-generation-rule.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('nim-generation-rule.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
