<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EmployeePositionAssignment;
use App\Support\ActivePermission;
use App\Support\Organization\EmployeePositionAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmployeePositionAssignmentTable extends BasePowerGridTable
{
    public string $tableName = 'employeePositionAssignmentTable';

    protected ?string $bulkActionModel = EmployeePositionAssignment::class;

    protected ?string $bulkActionPermissionPrefix = 'employee-position-assignment';

    protected string $bulkActionItemLabel = 'penugasan jabatan';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EmployeePositionAssignment::query()
            ->with(['employeeProfile.user', 'position', 'faculty', 'studyProgram', 'workUnit'])
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'employeeProfile.user' => ['first_name', 'last_name', 'email', 'username', 'code', 'identity_number'],
            'position' => ['name', 'code'],
            'faculty' => ['name', 'code'],
            'studyProgram' => ['name', 'code'],
            'workUnit' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('employee_name', fn (EmployeePositionAssignment $model) => $model->employeeProfile?->user?->name ?? '-')
            ->add('position_name', fn (EmployeePositionAssignment $model) => $model->position?->name ?? '-')
            ->add('scope_name', fn (EmployeePositionAssignment $model) => $model->faculty?->name ?? $model->studyProgram?->name ?? $model->workUnit?->name ?? '-')
            ->add('starts_at', fn (EmployeePositionAssignment $model) => $model->starts_at?->format('Y-m-d') ?? '-')
            ->add('ends_at', fn (EmployeePositionAssignment $model) => $model->ends_at?->format('Y-m-d') ?? '-')
            ->add('is_primary')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Pegawai', 'employee_name')
                ->searchable(),
            Column::make('Jabatan', 'position_name')
                ->searchable(),
            Column::make('Scope', 'scope_name')
                ->searchable(),
            Column::make('Mulai', 'starts_at')
                ->sortable(),
            Column::make('Selesai', 'ends_at')
                ->sortable(),
            Column::make('Utama', 'is_primary')
                ->toggleable(ActivePermission::check('employee-position-assignment.update'), 'Ya', 'Tidak')
                ->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(ActivePermission::check('employee-position-assignment.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::make('Dibuat', 'created_at')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if (! in_array($field, ['is_active', 'is_primary'], true)) {
            return;
        }

        if (! ActivePermission::check('employee-position-assignment.update')) {
            session()->flash('error', 'Anda tidak memiliki izin mengubah penugasan jabatan.');
            $this->dispatch('pg:eventRefresh-employeePositionAssignmentTable');

            return;
        }

        EmployeePositionAssignment::query()->whereKey($id)->update([
            $field => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        if ($field === 'is_active') {
            $assignment = EmployeePositionAssignment::find($id);

            if ($assignment) {
                app(EmployeePositionAssignmentService::class)->syncWorkUnitMembership($assignment);
            }
        }

        $this->dispatch('pg:eventRefresh-employeePositionAssignmentTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.employee-position-assignments.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $assignment = EmployeePositionAssignment::with(['employeeProfile.user', 'position'])->find($id);

        if (! $assignment) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus penugasan jabatan?",
                text: "'.$assignment->employeeProfile?->user?->name.' - '.$assignment->position?->name.' akan dipindahkan ke tempat sampah.",
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
        if (! ActivePermission::check('employee-position-assignment.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus penugasan jabatan.');

            return;
        }

        $assignment = EmployeePositionAssignment::find($id);

        if (! $assignment) {
            session()->flash('error', 'Penugasan jabatan tidak ditemukan.');

            return;
        }

        $assignment->update(['deleted_by' => auth()->id()]);
        $assignment->delete();

        session()->flash('success', 'Penugasan jabatan berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-employeePositionAssignmentTable');
    }

    public function actions(EmployeePositionAssignment $row): array
    {
        $actions = [];

        if (ActivePermission::check('employee-position-assignment.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('employee-position-assignment.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
