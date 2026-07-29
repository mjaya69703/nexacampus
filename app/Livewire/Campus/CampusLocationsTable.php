<?php

namespace App\Livewire\Campus;

use App\Livewire\BasePowerGridTable;
use App\Models\Campus\CampusLocation;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class CampusLocationsTable extends BasePowerGridTable
{
    public string $tableName = 'campusLocationsTable';

    protected ?string $bulkActionModel = CampusLocation::class;
    protected ?string $bulkActionPermissionPrefix = 'campus-location';
    protected string $bulkActionItemLabel = 'lokasi kampus';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return CampusLocation::query()->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('code')
            ->add('address')
            ->add('phone')
            ->add('email')
            ->add('is_main')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')->sortable(),
            Column::make('Nama Kampus', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Alamat', 'address')->sortable()->searchable(),
            Column::make('Kampus Utama', 'is_main')
                ->toggleable(ActivePermission::check('campus-location.update'), 'Ya', 'Tidak')
                ->sortable(),
            Column::make('Status', 'is_active')
                ->toggleable(ActivePermission::check('campus-location.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::make('Dibuat Pada', 'created_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->placeholder('Cari nama...'),
            Filter::inputText('code')->placeholder('Cari kode...'),
            Filter::boolean('is_main', 'is_main'),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if (! ActivePermission::check('campus-location.update')) {
            session()->flash('error', 'Anda tidak memiliki izin!');
            $this->dispatch('pg:eventRefresh-campusLocationsTable');
            return;
        }

        $item = CampusLocation::find($id);
        if ($item) {
            $item->update([
                $field => (bool) $value,
                'updated_by' => auth()->id(),
            ]);
        }

        $this->dispatch('pg:eventRefresh-campusLocationsTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.campus.locations.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $item = CampusLocation::find($id);
        if ($item) {
            $this->js('
                Swal.fire({
                    title: "Hapus lokasi kampus?",
                    text: "'.addslashes($item->name).' - Data tidak dapat dikembalikan!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya, Hapus",
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
        if (! ActivePermission::check('campus-location.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin!');
            return;
        }

        $item = CampusLocation::find($id);
        if ($item) {
            $name = $item->name;
            $item->delete();
            $this->dispatch('pg:eventRefresh-campusLocationsTable');
            $this->js('
                Swal.fire({
                    title: "Lokasi Dihapus",
                    text: "'.addslashes($name).' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(CampusLocation $row): array
    {
        $actions = [];
        if (ActivePermission::check('campus-location.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('campus-location.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }
        return $actions;
    }
}
