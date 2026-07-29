<?php

namespace App\Livewire\Campus;

use App\Livewire\BasePowerGridTable;
use App\Models\Campus\CampusAsset;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class CampusAssetsTable extends BasePowerGridTable
{
    public string $tableName = 'campusAssetsTable';

    protected ?string $bulkActionModel = CampusAsset::class;
    protected ?string $bulkActionPermissionPrefix = 'campus-asset';
    protected string $bulkActionItemLabel = 'aset kampus';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return CampusAsset::query()
            ->with('room')
            ->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('asset_code')
            ->add('name')
            ->add('category', fn ($row) => ucfirst(str_replace('_', ' ', $row->category)))
            ->add('room_name', fn ($row) => $row->room?->name ?? '-')
            ->add('brand')
            ->add('condition_badge', function ($row) {
                return match ($row->condition) {
                    'good' => '<span class="badge bg-success text-white">Baik</span>',
                    'minor_damage' => '<span class="badge bg-warning text-white">Rusak Ringan</span>',
                    'major_damage' => '<span class="badge bg-danger text-white">Rusak Berat</span>',
                    'in_repair' => '<span class="badge bg-info text-white">Sedang Perbaikan</span>',
                    default => '<span class="badge bg-secondary text-white">-</span>',
                };
            })
            ->add('status_badge', function ($row) {
                return match ($row->status) {
                    'active' => '<span class="badge bg-success text-white">Aktif</span>',
                    'maintenance' => '<span class="badge bg-warning text-white">Maintenance</span>',
                    'disposed' => '<span class="badge bg-secondary text-white">Penghapusan</span>',
                    default => '<span class="badge bg-secondary text-white">-</span>',
                };
            });
    }

    public function columns(): array
    {
        return [
            Column::make('Kode Aset', 'asset_code')->sortable()->searchable(),
            Column::make('Nama Barang', 'name')->sortable()->searchable(),
            Column::make('Kategori', 'category'),
            Column::make('Ruangan', 'room_name'),
            Column::make('Merk', 'brand')->sortable()->searchable(),
            Column::make('Kondisi', 'condition_badge'),
            Column::make('Status', 'status_badge'),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('asset_code')->placeholder('Cari kode...'),
            Filter::inputText('name')->placeholder('Cari nama...'),
            Filter::select('condition', 'condition')
                ->dataSource([
                    ['id' => 'good', 'name' => 'Baik'],
                    ['id' => 'minor_damage', 'name' => 'Rusak Ringan'],
                    ['id' => 'major_damage', 'name' => 'Rusak Berat'],
                    ['id' => 'in_repair', 'name' => 'Sedang Perbaikan'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('condition', $value)),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.campus.assets.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $item = CampusAsset::find($id);
        if ($item) {
            $this->js('
                Swal.fire({
                    title: "Hapus aset?",
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
        if (! ActivePermission::check('campus-asset.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin!');
            return;
        }

        $item = CampusAsset::find($id);
        if ($item) {
            $name = $item->name;
            $item->delete();
            $this->dispatch('pg:eventRefresh-campusAssetsTable');
            $this->js('
                Swal.fire({
                    title: "Aset Dihapus",
                    text: "'.addslashes($name).' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(CampusAsset $row): array
    {
        $actions = [];
        if (ActivePermission::check('campus-asset.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('campus-asset.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }
        return $actions;
    }
}
