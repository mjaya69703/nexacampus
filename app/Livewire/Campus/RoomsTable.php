<?php

namespace App\Livewire\Campus;

use App\Livewire\BasePowerGridTable;
use App\Models\Campus\Building;
use App\Models\Campus\Room;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class RoomsTable extends BasePowerGridTable
{
    public string $tableName = 'roomsTable';

    protected ?string $bulkActionModel = Room::class;

    protected ?string $bulkActionPermissionPrefix = 'room';

    protected string $bulkActionItemLabel = 'ruang';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return Room::query()->with('building')->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('building_id')
            ->add('building.name')
            ->add('name')
            ->add('code')
            ->add('floor')
            ->add('capacity')
            ->add('type')
            ->add('is_active')
            ->add('desc')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Building', 'building.name')
                ->sortable()
                ->searchable(),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Floor', 'floor')
                ->sortable()
                ->searchable(),
            Column::make('Capacity', 'capacity')
                ->sortable()
                ->searchable(),
            Column::make('Type', 'type')
                ->sortable()
                ->searchable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('room.update'),
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

    public function filters(): array
    {
        return [
            Filter::inputText('name')->placeholder('Cari nama ruangan...'),
            Filter::inputText('code')->placeholder('Cari kode ruangan...'),
            Filter::select('building_name', 'building_id')
                ->dataSource(
                    Building::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (Building $building) => [
                            'id' => $building->id,
                            'name' => $building->name,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('building_id', $value)),
            Filter::select('floor', 'floor')
                ->dataSource(
                    Room::query()
                        ->select('floor')
                        ->whereNotNull('floor')
                        ->distinct()
                        ->orderBy('floor')
                        ->pluck('floor')
                        ->filter()
                        ->map(fn ($floor) => [
                            'id' => (string) $floor,
                            'name' => (string) $floor,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('floor', $value)),
            Filter::select('type', 'type')
                ->dataSource(
                    Room::query()
                        ->select('type')
                        ->distinct()
                        ->orderBy('type')
                        ->pluck('type')
                        ->filter()
                        ->map(fn (string $type) => [
                            'id' => $type,
                            'name' => $type,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('type', $value)),
            Filter::boolean('is_active', 'is_active'),
            Filter::datepicker('created_at'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('room.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status ruangan!');
            $this->dispatch('pg:eventRefresh-roomsTable');

            return;
        }

        $room = Room::find($id);

        if (! $room) {
            session()->flash('error', 'Ruangan tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-roomsTable');

            return;
        }

        $room->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-roomsTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.campus.rooms.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $room = Room::find($id);

        if ($room) {
            $this->js('
                Swal.fire({
                    title: "Hapus ruangan?",
                    text: "'.addslashes($room->name).' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('room.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus ruangan!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id ruangan tidak ditemukan!');

            return;
        }

        $room = Room::find($id);

        if ($room) {
            $roomName = $room->name;
            $room->delete();

            $this->dispatch('pg:eventRefresh-roomsTable');
            $this->js('
                Swal.fire({
                    title: "Ruangan dihapus",
                    text: "'.addslashes($roomName).' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Room $row): array
    {
        $actions = [];

        if (ActivePermission::check('room.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('room.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
