<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmployeeAttendanceRecordTable extends BasePowerGridTable
{
    public string $tableName = 'employeeAttendanceRecordTable';

    protected ?string $bulkActionModel = EmployeeAttendanceRecord::class;

    protected ?string $bulkActionPermissionPrefix = 'employee-attendance-record';

    protected string $bulkActionItemLabel = 'absensi pegawai';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EmployeeAttendanceRecord::query()
            ->with(['employeeProfile.user', 'workUnit', 'source', 'checkInLocation'])
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in_at');
    }

    public function relationSearch(): array
    {
        return [
            'employeeProfile.user' => ['first_name', 'last_name', 'email', 'username', 'code'],
            'workUnit' => ['name', 'code'],
            'source' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('employee_name', fn (EmployeeAttendanceRecord $model) => $model->employeeProfile?->user?->name ?? '-')
            ->add('attendance_date')
            ->add('status_badge', fn (EmployeeAttendanceRecord $model) => $this->statusBadge($model->status))
            ->add('check_in_at', fn (EmployeeAttendanceRecord $model) => $model->check_in_at?->format('H:i') ?? '-')
            ->add('check_out_at', fn (EmployeeAttendanceRecord $model) => $model->check_out_at?->format('H:i') ?? '-')
            ->add('work_minutes')
            ->add('work_unit_name', fn (EmployeeAttendanceRecord $model) => $model->workUnit?->name ?? '-')
            ->add('source_name', fn (EmployeeAttendanceRecord $model) => $model->source?->name ?? '-')
            ->add('location_name', fn (EmployeeAttendanceRecord $model) => $model->checkInLocation?->name ?? '-')
            ->add('location_status_badge', fn (EmployeeAttendanceRecord $model) => $this->locationStatusBadge($model->location_status))
            ->add('photo_badge', fn (EmployeeAttendanceRecord $model) => $this->photoBadge($model));
    }

    public function columns(): array
    {
        return [
            Column::make('Pegawai', 'employee_name')->searchable(),
            Column::make('Tanggal', 'attendance_date')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Masuk', 'check_in_at'),
            Column::make('Keluar', 'check_out_at'),
            Column::make('Menit', 'work_minutes')->sortable(),
            Column::make('Unit', 'work_unit_name')->searchable(),
            Column::make('Lokasi', 'location_name')->searchable(),
            Column::make('Radius', 'location_status_badge', 'location_status')->sortable(),
            Column::make('Bukti', 'photo_badge'),
            Column::make('Sumber', 'source_name')->searchable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('employee_name', 'employee_name')
                ->placeholder('Cari nama / email...')
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
            Filter::inputText('work_unit_name', 'work_unit_name')
                ->placeholder('Cari unit kerja...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('workUnit', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%')
                                ->orWhere('code', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::inputText('location_name', 'location_name')
                ->placeholder('Cari lokasi...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('checkInLocation', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::datepicker('attendance_date', 'attendance_date'),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'present', 'name' => 'Present'],
                    ['id' => 'late', 'name' => 'Late'],
                    ['id' => 'absent', 'name' => 'Absent'],
                    ['id' => 'leave', 'name' => 'Leave'],
                    ['id' => 'sick', 'name' => 'Sick'],
                    ['id' => 'remote', 'name' => 'Remote'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('delete')]
    public function delete($id): void
    {
        $record = EmployeeAttendanceRecord::with('employeeProfile.user')->find($id);

        if (! $record) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus catatan absensi?",
                text: "Catatan absensi untuk '.addslashes($record->employeeProfile?->user?->name ?: 'Pegawai').' pada tanggal '.$record->attendance_date?->format('d/m/Y').' akan dipindahkan ke tempat sampah.",
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
        if (! ActivePermission::check('employee-attendance-record.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus catatan absensi.');
            return;
        }

        $record = EmployeeAttendanceRecord::find($id);

        if (! $record) {
            session()->flash('error', 'Catatan absensi tidak ditemukan.');
            return;
        }

        $record->update(['deleted_by' => auth()->id()]);
        $record->delete();

        session()->flash('success', 'Catatan absensi berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-employeeAttendanceRecordTable');
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.employee-attendance-records.show', ['id' => $rowId]);
    }

    public function actions(EmployeeAttendanceRecord $row): array
    {
        $actions = [];

        if (ActivePermission::check('employee-attendance-record.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('employee-attendance-record.delete')) {
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
            'present' => 'bg-success',
            'late' => 'bg-warning text-dark',
            'absent' => 'bg-danger',
            'leave', 'sick' => 'bg-info',
            'remote' => 'bg-primary',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str($status)->title().'</span>';
    }

    private function locationStatusBadge(?string $status): string
    {
        $class = match ($status) {
            'inside_radius' => 'bg-success',
            'outside_radius', 'gps_missing' => 'bg-danger',
            'partial' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str($status ?: 'unverified')->replace('_', ' ')->title().'</span>';
    }

    private function photoBadge(EmployeeAttendanceRecord $record): string
    {
        $count = collect([$record->check_in_photo_path, $record->check_out_photo_path])
            ->filter()
            ->count();

        $class = $count > 0 ? 'bg-indigo-lt text-indigo' : 'bg-secondary-lt text-secondary';

        return '<span class="badge '.$class.'">'.$count.' foto</span>';
    }
}
