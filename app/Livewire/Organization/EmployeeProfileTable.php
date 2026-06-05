<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EmployeeProfile;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmployeeProfileTable extends BasePowerGridTable
{
    public string $tableName = 'employeeProfileTable';

    protected ?string $bulkActionModel = EmployeeProfile::class;

    protected ?string $bulkActionPermissionPrefix = 'employee-profile';

    protected string $bulkActionItemLabel = 'pegawai';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EmployeeProfile::query()
            ->with(['user', 'primaryWorkUnit'])
            ->withCount('activePositionAssignments')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'user' => ['first_name', 'last_name', 'email', 'username', 'code', 'identity_number'],
            'primaryWorkUnit' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('employee_number', fn (EmployeeProfile $model) => $model->employee_number ?: '-')
            ->add('user_name', fn (EmployeeProfile $model) => $model->user?->name ?? '-')
            ->add('user_email', fn (EmployeeProfile $model) => $model->user?->email ?? '-')
            ->add('primary_work_unit_name', fn (EmployeeProfile $model) => $model->primaryWorkUnit?->name ?? '-')
            ->add('employment_type')
            ->add('employment_status')
            ->add('active_position_assignments_count')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Pegawai', 'user_name')
                ->searchable(),
            Column::make('Email', 'user_email')
                ->searchable(),
            Column::make('Nomor Pegawai', 'employee_number')
                ->sortable()
                ->searchable(),
            Column::make('Tipe', 'employment_type')
                ->sortable()
                ->searchable(),
            Column::make('Status', 'employment_status')
                ->sortable()
                ->searchable(),
            Column::make('Unit Utama', 'primary_work_unit_name')
                ->searchable(),
            Column::make('Jabatan Aktif', 'active_position_assignments_count')
                ->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(ActivePermission::check('employee-profile.update'), 'Aktif', 'Nonaktif')
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

        if (! ActivePermission::check('employee-profile.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mengubah status pegawai.');
            $this->dispatch('pg:eventRefresh-employeeProfileTable');

            return;
        }

        EmployeeProfile::query()->whereKey($id)->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-employeeProfileTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.employee-profiles.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $profile = EmployeeProfile::with('user')->find($id);

        if (! $profile) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus profil pegawai?",
                text: "'.$profile->user?->name.' akan dipindahkan ke tempat sampah.",
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
        if (! ActivePermission::check('employee-profile.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus profil pegawai.');

            return;
        }

        $profile = EmployeeProfile::find($id);

        if (! $profile) {
            session()->flash('error', 'Profil pegawai tidak ditemukan.');

            return;
        }

        $profile->update(['deleted_by' => auth()->id()]);
        $profile->delete();

        session()->flash('success', 'Profil pegawai berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-employeeProfileTable');
    }

    public function actions(EmployeeProfile $row): array
    {
        $actions = [];

        if (ActivePermission::check('employee-profile.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('employee-profile.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
