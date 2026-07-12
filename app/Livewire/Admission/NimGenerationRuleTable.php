<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Admission\NimGenerationRule;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
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
            Column::make('No', 'id')->sortable(),
            Column::make('Nama Aturan', 'name')->sortable()->searchable(),
            Column::make('Pola NIM', 'pattern')->sortable()->searchable(),
            Column::make('Ruang Lingkup (Scope)', 'sequence_scope')->sortable(),
            Column::make('Padding', 'sequence_padding')->sortable(),
            Column::make('Mulai Dari', 'sequence_start')->sortable(),
            Column::make('Counter Terpakai', 'counters_count')->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(ActivePermission::check('nim-generation-rule.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->placeholder('Cari nama aturan NIM...'),
            Filter::inputText('pattern')->placeholder('Cari pola NIM (contoh: {YEAR}{FACULTY})...'),
            Filter::select('sequence_scope', 'sequence_scope')
                ->dataSource(collect([
                    ['id' => 'global', 'name' => 'Global (Seluruh Kampus)'],
                    ['id' => 'faculty', 'name' => 'Per Fakultas'],
                    ['id' => 'study_program', 'name' => 'Per Program Studi'],
                    ['id' => 'period', 'name' => 'Per Gelombang / Periode'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'Aktif', 'Nonaktif'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('nim-generation-rule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status aturan NIM.');
            $this->dispatch('pg:eventRefresh-nimGenerationRuleTable');

            return;
        }

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
                title: "Hapus aturan NIM?",
                text: "Aturan yang sudah memiliki counter sebaiknya tidak dihapus jika sudah dipakai.",
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
        if (! ActivePermission::check('nim-generation-rule.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus aturan NIM.');

            return;
        }

        $rule = NimGenerationRule::find($id);
        if ($rule) {
            $rule->delete();
        }

        session()->flash('success', 'NIM generation rule berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-nimGenerationRuleTable');
    }

    public function actions(NimGenerationRule $row): array
    {
        $actions = [];

        if (ActivePermission::check('nim-generation-rule.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('nim-generation-rule.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
