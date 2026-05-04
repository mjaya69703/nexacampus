<?php

namespace App\Livewire\System;

use App\Livewire\BasePowerGridTable;
use App\Models\Settings\Menu;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class MenuTable extends BasePowerGridTable
{
    public string $tableName = 'menuTable';

    protected ?string $bulkActionModel = Menu::class;

    protected ?string $bulkActionPermissionPrefix = 'menu';

    protected string $bulkActionItemLabel = 'menu';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return Menu::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('parent_id')
            ->add('type')
            ->add('title')
            ->add('route_name')
            ->add('url')
            ->add('icon')
            ->add('permission_name')
            ->add('sort_order')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Parent id', 'parent_id'),
            Column::make('Type', 'type')
                ->sortable()
                ->searchable(),

            Column::make('Title', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Route name', 'route_name')
                ->sortable()
                ->searchable(),

            Column::make('Url', 'url')
                ->sortable()
                ->searchable(),

            Column::make('Icon', 'icon')
                ->sortable()
                ->searchable(),

            Column::make('Permission name', 'permission_name')
                ->sortable()
                ->searchable(),

            Column::make('Sort order', 'sort_order')
                ->sortable()
                ->searchable(),

            Column::make('Is active', 'is_active')
                ->sortable()
                ->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable(),

            Column::make('Created at', 'created_at')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.system.menus.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $menu = Menu::find($id);

        if ($menu) {
            $this->js('
                Swal.fire({
                    title: "Hapus menu?",
                    text: "'.$menu->title.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('menu.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus menu!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id menu tidak ditemukan!');

            return;
        }

        $menu = Menu::find($id);

        if ($menu) {
            $menuName = $menu->title;
            $menu->delete();

            $this->dispatch('pg:eventRefresh-menuTable');
            $this->js('
                Swal.fire({
                    title: "Menu dihapus",
                    text: "'.$menuName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Menu $row): array
    {
        $actions = [];

        if (ActivePermission::check('menu.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('menu.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
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
