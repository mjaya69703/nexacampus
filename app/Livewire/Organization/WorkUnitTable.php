<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\WorkUnit;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class WorkUnitTable extends BasePowerGridTable
{
    public string $tableName = 'workUnitTable';

    protected ?string $bulkActionModel = WorkUnit::class;

    protected ?string $bulkActionPermissionPrefix = 'work-unit';

    protected string $bulkActionItemLabel = 'unit kerja';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return WorkUnit::query()->withCount('activeMembers');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('active_members_count')
            ->add('is_active')
            ->add('description')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Unit', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Kode', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Anggota Aktif', 'active_members_count')
                ->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(
                    ActivePermission::check('work-unit.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Deskripsi', 'description')
                ->searchable(),
            Column::make('Dibuat', 'created_at')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name', 'name')->placeholder('Cari nama unit...')->operators(['contains']),
            Filter::inputText('code', 'code')->placeholder('Cari kode unit...')->operators(['contains']),
            Filter::inputText('description', 'description')->placeholder('Cari deskripsi...')->operators(['contains']),
            Filter::boolean('is_active', 'is_active')->label('Aktif', 'Nonaktif'),
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('work-unit.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status unit kerja.');
            $this->dispatch('pg:eventRefresh-workUnitTable');

            return;
        }

        WorkUnit::query()
            ->whereKey($id)
            ->update([
                'is_active' => (bool) $value,
                'updated_by' => auth()->id(),
            ]);

        $this->dispatch('pg:eventRefresh-workUnitTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.work-units.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $unit = WorkUnit::find($id);

        if (! $unit) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus unit kerja?",
                text: "'.addslashes($unit->name).' akan dipindahkan ke tempat sampah.",
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
        if (! ActivePermission::check('work-unit.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus unit kerja.');

            return;
        }

        $unit = WorkUnit::find($id);

        if (! $unit) {
            session()->flash('error', 'Unit kerja tidak ditemukan.');

            return;
        }

        if ($unit->members()->count() > 0 || \App\Models\Organization\OrganizationalPosition::where('work_unit_id', $id)->exists()) {
            session()->flash('error', 'Unit kerja tidak dapat dihapus karena masih memiliki anggota pegawai atau jabatan organisasi terkait.');

            return;
        }

        $unit->update(['deleted_by' => auth()->id()]);
        $unit->delete();

        session()->flash('success', 'Unit kerja berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-workUnitTable');
    }

    public function actions(WorkUnit $row): array
    {
        $actions = [];

        if (ActivePermission::check('work-unit.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('work-unit.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
