<?php

namespace App\Livewire\Campus;

use App\Livewire\BasePowerGridTable;
use App\Models\Campus\Building;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class BuildingsTable extends BasePowerGridTable
{
    public string $tableName = 'buildingsTable';

    protected ?string $bulkActionModel = Building::class;

    protected ?string $bulkActionPermissionPrefix = 'building';

    protected string $bulkActionItemLabel = 'gedung';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return Building::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('code')
            ->add('address')
            ->add('floor_count')
            ->add('is_active')
            ->add('desc')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Address', 'address')
                ->sortable()
                ->searchable(),
            Column::make('Floor Count', 'floor_count')
                ->sortable()
                ->searchable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('building.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Description', 'desc')
                ->sortable()
                ->searchable(),
            Column::make('Created at', 'created_at')
                ->sortable()
                ->searchable(),
            Column::action('Action'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('building.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status gedung!');
            $this->dispatch('pg:eventRefresh-buildingsTable');

            return;
        }

        $building = Building::find($id);

        if (! $building) {
            session()->flash('error', 'Gedung tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-buildingsTable');

            return;
        }

        $building->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-buildingsTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.campus.buildings.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $building = Building::find($id);

        if ($building) {
            $this->js('
                Swal.fire({
                    title: "Hapus gedung?",
                    text: "'.addslashes($building->name).' - Data tidak bisa dikembalikan!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya hapus",
                    cancelButtonText: "Batal"
                }).then((result) => {
                    if (result.isConfirmed) {
                        Livewire.dispatch("deleteItem", {id: '.$id.'})
                    }
                });
            ');
        }
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('building.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus gedung!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id gedung tidak ditemukan!');

            return;
        }

        $building = Building::find($id);

        if ($building) {
            $buildingName = $building->name;
            $building->delete();

            $this->dispatch('pg:eventRefresh-buildingsTable');
            $this->js('
                Swal.fire({
                    title: "Gedung dihapus",
                    text: "'.addslashes($buildingName).' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Building $row): array
    {
        $actions = [];

        if (ActivePermission::check('building.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('building.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
