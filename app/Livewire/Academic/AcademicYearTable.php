<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;

final class AcademicYearTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'academicYearTable';

    protected ?string $bulkActionModel = AcademicYear::class;

    protected ?string $bulkActionPermissionPrefix = 'academic-year';

    protected string $bulkActionItemLabel = 'tahun akademik';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return AcademicYear::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('code')
            ->add('semester')
            ->add('start_date')
            ->add('end_date')
            ->add('is_active')
            ->add('desc')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Semester', 'semester')
                ->sortable()
                ->searchable(),
            Column::make('Start Date', 'start_date')
                ->sortable()
                ->searchable(),
            Column::make('End Date', 'end_date')
                ->sortable()
                ->searchable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('academic-year.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Description', 'desc')
                ->sortable()
                ->searchable(),
            Column::make('Created at', 'created_at')
                ->sortable()
                ->searchable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('semester', 'semester')
                ->dataSource(collect(['Ganjil', 'Genap', 'Pendek'])->map(fn (string $semester) => ['id' => $semester, 'name' => $semester]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active'),
            Filter::datepicker('start_date', 'start_date'),
            Filter::datepicker('end_date', 'end_date'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('academic-year.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status tahun akademik!');
            $this->dispatch('pg:eventRefresh-academicYearTable');

            return;
        }

        $academicYear = AcademicYear::find($id);

        if (! $academicYear) {
            session()->flash('error', 'Tahun akademik tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-academicYearTable');

            return;
        }

        $isActive = (bool) $value;

        if ($isActive) {
            AcademicYear::query()
                ->where('id', '!=', $academicYear->id)
                ->update([
                    'is_active' => false,
                    'updated_by' => auth()->id(),
                ]);
        }

        $academicYear->update([
            'is_active' => $isActive,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-academicYearTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.academic-years.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $academicYear = AcademicYear::find($id);

        if ($academicYear) {
            $this->js('
                Swal.fire({
                    title: "Hapus tahun akademik?",
                    text: "'.$academicYear->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('academic-year.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus tahun akademik!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id tahun akademik tidak ditemukan!');

            return;
        }

        $academicYear = AcademicYear::find($id);

        if ($academicYear) {
            $academicYearName = $academicYear->name;
            $academicYear->delete();

            $this->dispatch('pg:eventRefresh-academicYearTable');
            $this->js('
                Swal.fire({
                    title: "Tahun akademik dihapus",
                    text: "'.$academicYearName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(AcademicYear $row): array
    {
        $actions = [];

        if (ActivePermission::check('academic-year.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('academic-year.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
