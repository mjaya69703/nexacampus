<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmployeeLeaveRequestTable extends BasePowerGridTable
{
    public string $tableName = 'employeeLeaveRequestTable';

    protected ?string $bulkActionModel = EmployeeLeaveRequest::class;

    protected ?string $bulkActionPermissionPrefix = 'employee-leave-request';

    protected string $bulkActionItemLabel = 'cuti pegawai';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EmployeeLeaveRequest::query()
            ->with(['employeeProfile.user', 'leaveType', 'approvalRequest'])
            ->orderByRaw("FIELD(status, 'submitted', 'in_approval', 'draft', 'approved', 'rejected', 'cancelled')")
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'employeeProfile.user' => ['first_name', 'last_name', 'email', 'username', 'code'],
            'leaveType' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('request_number')
            ->add('employee_name', fn (EmployeeLeaveRequest $model) => $model->employeeProfile?->user?->name ?? '-')
            ->add('leave_type_name', fn (EmployeeLeaveRequest $model) => $model->leaveType?->name ?? '-')
            ->add('period', fn (EmployeeLeaveRequest $model) => $model->starts_at->format('d M Y').' - '.$model->ends_at->format('d M Y'))
            ->add('total_days')
            ->add('status_badge', fn (EmployeeLeaveRequest $model) => $this->statusBadge($model->status))
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Nomor', 'request_number')->sortable()->searchable(),
            Column::make('Pegawai', 'employee_name')->searchable(),
            Column::make('Jenis', 'leave_type_name')->searchable(),
            Column::make('Periode', 'period'),
            Column::make('Hari', 'total_days')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Dibuat', 'created_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'in_approval', 'name' => 'In Approval'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.employee-leave-requests.show', ['id' => $rowId]);
    }

    public function actions(EmployeeLeaveRequest $row): array
    {
        if (! ActivePermission::check('employee-leave-request.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'in_approval', 'submitted' => 'bg-warning text-dark',
            default => 'bg-muted',
        };

        return '<span class="badge '.$class.'">'.str($status)->replace('_', ' ')->title().'</span>';
    }
}
