<?php

namespace App\Livewire\Access;

use App\Livewire\BasePowerGridTable;
use App\Models\Access\Role;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class RoleTable extends BasePowerGridTable
{
    public string $tableName = 'roleTable';

    protected ?string $bulkActionModel = Role::class;

    protected ?string $bulkActionPermissionPrefix = 'role';

    protected string $bulkActionItemLabel = 'role';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return Role::query()
            ->with('users')
            ->withCount(['users as user_count']);
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
            ->add('user_count')
            ->add('guard_name')
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
            Column::make('Guard Name', 'guard_name')
                ->sortable()
                ->searchable(),
            Column::make('User Count', 'user_count')
                ->sortable(),

            Column::make('Created at', 'created_at')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    // public function filters(): array
    // {
    //     return [
    //     ];
    // }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.access.roles.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $role = Role::find($id);

        if ($role) {
            $this->js('
                Swal.fire({
                    title: "Hapus role?",
                    text: "'.$role->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('role.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus role!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id role tidak ditemukan!');

            return;
        }

        $role = Role::find($id);

        if ($role) {
            if ($role->users()->count() > 0) {
                $this->js('
                    Swal.fire({
                        title: "Tidak bisa hapus role",
                        text: "Role '.$role->name.' masih memiliki pengguna yang terkait. Hapus atau pindahkan pengguna tersebut sebelum menghapus role ini.",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                ');

                return;
            }
            $roleName = $role->name;
            $role->delete();

            $this->dispatch('pg:eventRefresh-roleTable');
            $this->js('
                Swal.fire({
                    title: "Role dihapus",
                    text: "'.$roleName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Role $row): array
    {
        $actions = [];

        if (ActivePermission::check('role.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('role.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    protected function beforeBulkDelete(Model $model): ?string
    {
        if ($model instanceof Role && $model->users()->count() > 0) {
            return 'Sebagian role tidak bisa dihapus karena masih memiliki user terkait.';
        }

        return null;
    }

    /*
    public function actionRules($row): array
    {
       return [
            // Hide button edit for ID 1
            Rule::button('edit')
                ->when(fn($row) => $row->id === 1)
                ->hide(),
        ];
    }
    */
}
