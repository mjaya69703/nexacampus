<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EmployeeLeaveType;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmployeeLeaveTypeTable extends BasePowerGridTable
{
    public string $tableName = 'employeeLeaveTypeTable';

    protected ?string $bulkActionModel = EmployeeLeaveType::class;

    protected ?string $bulkActionPermissionPrefix = 'employee-leave-type';

    protected string $bulkActionItemLabel = 'jenis cuti pegawai';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EmployeeLeaveType::query()->with('approvalTemplate')->orderBy('name');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('default_days_per_year')
            ->add('requires_approval')
            ->add('is_paid')
            ->add('is_active')
            ->add('approval_template_name', fn (EmployeeLeaveType $model) => $model->approvalTemplate?->name ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Jatah/Tahun', 'default_days_per_year')->sortable(),
            Column::make('Approval', 'requires_approval')->sortable(),
            Column::make('Paid', 'is_paid')->sortable(),
            Column::make('Template', 'approval_template_name')->searchable(),
            Column::make('Aktif', 'is_active')->toggleable(ActivePermission::check('employee-leave-type.update'), 'Aktif', 'Nonaktif')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field === 'is_active' && ActivePermission::check('employee-leave-type.update')) {
            EmployeeLeaveType::whereKey($id)->update(['is_active' => (bool) $value, 'updated_by' => auth()->id()]);
        }
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.employee-leave-types.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        if (! ActivePermission::check('employee-leave-type.delete')) {
            return;
        }

        $type = EmployeeLeaveType::find($id);
        $type?->update(['deleted_by' => auth()->id()]);
        $type?->delete();
        $this->dispatch('pg:eventRefresh-employeeLeaveTypeTable');
    }

    public function actions(EmployeeLeaveType $row): array
    {
        $actions = [];
        if (ActivePermission::check('employee-leave-type.update')) {
            $actions[] = Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('employee-leave-type.delete')) {
            $actions[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
