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
            Filter::inputText('request_number', 'request_number')->placeholder('Cari nomor pengajuan...')->operators(['contains']),
            Filter::inputText('employee_name', 'employee_name')
                ->placeholder('Cari nama / email pegawai...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('employeeProfile.user', function (Builder $sub) use ($value) {
                            $sub->where(function (Builder $q) use ($value) {
                                $q->where('first_name', 'like', '%' . $value . '%')
                                    ->orWhere('last_name', 'like', '%' . $value . '%')
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $value . '%'])
                                    ->orWhere('username', 'like', '%' . $value . '%')
                                    ->orWhere('email', 'like', '%' . $value . '%');
                            });
                        });
                    }
                }),
            Filter::inputText('leave_type_name', 'leave_type_name')
                ->placeholder('Cari jenis cuti...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('leaveType', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%')
                                ->orWhere('code', 'like', '%' . $value . '%');
                        });
                    }
                }),
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
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.employee-leave-requests.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $req = EmployeeLeaveRequest::with('employeeProfile.user')->find($id);

        if (! $req) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus pengajuan cuti?",
                text: "Pengajuan cuti nomor '.addslashes($req->request_number).' atas nama '.addslashes($req->employeeProfile?->user?->name ?: 'Pegawai').' akan dipindahkan ke tempat sampah.",
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
        if (! ActivePermission::check('employee-leave-request.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus pengajuan cuti.');
            return;
        }

        $req = EmployeeLeaveRequest::find($id);

        if (! $req) {
            session()->flash('error', 'Pengajuan cuti tidak ditemukan.');
            return;
        }

        $req->update(['deleted_by' => auth()->id()]);
        $req->delete();

        session()->flash('success', 'Pengajuan cuti berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-employeeLeaveRequestTable');
    }

    public function actions(EmployeeLeaveRequest $row): array
    {
        $actions = [];
        if (ActivePermission::check('employee-leave-request.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('employee-leave-request.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
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
