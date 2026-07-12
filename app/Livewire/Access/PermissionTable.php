<?php

namespace App\Livewire\Access;

use App\Livewire\BasePowerGridTable;
use App\Models\Access\Permission;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PermissionTable extends BasePowerGridTable
{
    public string $tableName = 'permissionTable';

    protected ?string $bulkActionModel = Permission::class;

    protected ?string $bulkActionPermissionPrefix = 'permission';

    protected string $bulkActionItemLabel = 'permission';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return Permission::query()
            ->with('roles')
            ->withCount(['roles as role_count']);
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
            ->add('guard_name')
            ->add('role_count')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'id')
                ->sortable(),
            Column::make('Nama Hak Akses', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Guard Name', 'guard_name')
                ->sortable()
                ->searchable(),
            Column::make('Digunakan Role', 'role_count')
                ->sortable(),
            Column::make('Tanggal Dibuat', 'created_at')
                ->sortable()
                ->searchable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->placeholder('Cari nama / kode permission (contoh: user.create)...'),
            Filter::select('guard_name', 'guard_name')
                ->dataSource([
                    ['id' => 'web', 'name' => 'web'],
                    ['id' => 'api', 'name' => 'api'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('guard_name', $value)),
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.access.permissions.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $permission = Permission::find($id);

        if ($permission) {
            $this->js('
                Swal.fire({
                    title: "Hapus permission?",
                    text: "'.$permission->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('permission.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus permission!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id permission tidak ditemukan!');

            return;
        }

        $permission = Permission::find($id);

        if ($permission) {
            if ($permission->roles()->count() > 0) {
                $this->js('
                    Swal.fire({
                        title: "Tidak bisa hapus permission",
                        text: "Permission '.$permission->name.' masih digunakan oleh role. Lepaskan relasinya dulu sebelum menghapus.",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                ');

                return;
            }

            $permissionName = $permission->name;
            $permission->delete();

            $this->dispatch('pg:eventRefresh-permissionTable');
            $this->js('
                Swal.fire({
                    title: "Permission dihapus",
                    text: "'.$permissionName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Permission $row): array
    {
        $actions = [];

        if (ActivePermission::check('permission.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->id()
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('permission.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    protected function beforeBulkDelete(Model $model): ?string
    {
        if ($model instanceof Permission && $model->roles()->count() > 0) {
            return 'Sebagian permission tidak bisa dihapus karena masih digunakan oleh role.';
        }

        return null;
    }
}
