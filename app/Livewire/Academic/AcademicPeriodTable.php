<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicPeriod;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AcademicPeriodTable extends BasePowerGridTable
{
    public string $tableName = 'academicPeriodTable';

    protected ?string $bulkActionModel = AcademicPeriod::class;

    protected ?string $bulkActionPermissionPrefix = 'academic-period';

    protected string $bulkActionItemLabel = 'periode akademik';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return AcademicPeriod::query()
            ->with('academicYear')
            ->orderByDesc('start_at');
    }

    public function relationSearch(): array
    {
        return [
            'academicYear' => [
                'name',
                'code',
            ],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('academic_year_name', fn (AcademicPeriod $model) => $model->academicYear?->name ?? '-')
            ->add('name')
            ->add('code')
            ->add('type')
            ->add('start_at')
            ->add('end_at')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Academic Year', 'academic_year_name')
                ->searchable(),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Type', 'type')
                ->sortable()
                ->searchable(),
            Column::make('Start At', 'start_at')
                ->sortable(),
            Column::make('End At', 'end_at')
                ->sortable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('academic-period.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Created At', 'created_at')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('academic-period.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status periode akademik!');
            $this->dispatch('pg:eventRefresh-academicPeriodTable');

            return;
        }

        $period = AcademicPeriod::find($id);

        if (! $period) {
            session()->flash('error', 'Periode akademik tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-academicPeriodTable');

            return;
        }

        $period->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-academicPeriodTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.academic-periods.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $period = AcademicPeriod::find($id);

        if ($period) {
            $this->js('
                Swal.fire({
                    title: "Hapus periode akademik?",
                    text: "'.$period->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('academic-period.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus periode akademik!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id periode akademik tidak ditemukan!');

            return;
        }

        $period = AcademicPeriod::find($id);

        if ($period) {
            $periodName = $period->name;
            $period->delete();

            $this->dispatch('pg:eventRefresh-academicPeriodTable');
            $this->js('
                Swal.fire({
                    title: "Periode akademik dihapus",
                    text: "'.$periodName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(AcademicPeriod $row): array
    {
        $actions = [];

        if (ActivePermission::check('academic-period.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('academic-period.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
