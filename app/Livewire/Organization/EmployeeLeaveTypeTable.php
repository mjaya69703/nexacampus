<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EmployeeLeaveType;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
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

    public function filters(): array
    {
        return [
            Filter::inputText('name', 'name')->placeholder('Cari nama cuti...')->operators(['contains']),
            Filter::inputText('code', 'code')->placeholder('Cari kode...')->operators(['contains']),
            Filter::inputText('approval_template_name', 'approval_template_name')
                ->placeholder('Cari template approval...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('approvalTemplate', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::boolean('requires_approval', 'requires_approval')->label('Ya', 'Tidak'),
            Filter::boolean('is_paid', 'is_paid')->label('Paid', 'Unpaid'),
            Filter::boolean('is_active', 'is_active')->label('Aktif', 'Nonaktif'),
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
        $type = EmployeeLeaveType::find($id);

        if (! $type) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus jenis cuti?",
                text: "Jenis cuti '.addslashes($type->name).' akan dipindahkan ke tempat sampah.",
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
        if (! ActivePermission::check('employee-leave-type.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus jenis cuti.');
            return;
        }

        $type = EmployeeLeaveType::find($id);

        if (! $type) {
            session()->flash('error', 'Jenis cuti tidak ditemukan.');
            return;
        }

        if ($type->requests()->exists() || $type->balances()->exists()) {
            session()->flash('error', 'Jenis cuti tidak dapat dihapus karena masih digunakan dalam pengajuan cuti atau saldo cuti pegawai.');
            return;
        }

        $type->update(['deleted_by' => auth()->id()]);
        $type->delete();

        session()->flash('success', 'Jenis cuti berhasil dihapus.');
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
