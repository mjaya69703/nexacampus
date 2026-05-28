<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\OrganizationalPosition;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class OrganizationalPositionTable extends BasePowerGridTable
{
    public string $tableName = 'organizationalPositionTable';

    protected ?string $bulkActionModel = OrganizationalPosition::class;

    protected ?string $bulkActionPermissionPrefix = 'organizational-position';

    protected string $bulkActionItemLabel = 'jabatan';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return OrganizationalPosition::query()
            ->withCount('assignments')
            ->orderBy('name');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('category')
            ->add('scope_type')
            ->add('assignments_count')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Jabatan', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Kode', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Kategori', 'category')
                ->sortable()
                ->searchable(),
            Column::make('Scope', 'scope_type')
                ->sortable()
                ->searchable(),
            Column::make('Penugasan', 'assignments_count')
                ->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(ActivePermission::check('organizational-position.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::make('Dibuat', 'created_at')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('organizational-position.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mengubah status jabatan.');
            $this->dispatch('pg:eventRefresh-organizationalPositionTable');

            return;
        }

        OrganizationalPosition::query()->whereKey($id)->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-organizationalPositionTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.organizational-positions.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $position = OrganizationalPosition::find($id);

        if (! $position) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus jabatan?",
                text: "'.$position->name.' akan dipindahkan ke tempat sampah.",
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

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('organizational-position.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus jabatan.');

            return;
        }

        $position = OrganizationalPosition::find($id);

        if (! $position) {
            session()->flash('error', 'Jabatan tidak ditemukan.');

            return;
        }

        if ($position->assignments()->exists()) {
            session()->flash('error', 'Jabatan masih memiliki penugasan dan belum bisa dihapus.');

            return;
        }

        $position->update(['deleted_by' => auth()->id()]);
        $position->delete();

        session()->flash('success', 'Jabatan berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-organizationalPositionTable');
    }

    public function actions(OrganizationalPosition $row): array
    {
        $actions = [];

        if (ActivePermission::check('organizational-position.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('organizational-position.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
