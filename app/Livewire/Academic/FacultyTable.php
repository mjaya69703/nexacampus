<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;

final class FacultyTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'facultyTable';

    protected ?string $bulkActionModel = Faculty::class;

    protected ?string $bulkActionPermissionPrefix = 'faculty';

    protected string $bulkActionItemLabel = 'fakultas';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return Faculty::query()->withCount('studyPrograms');
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
            ->add('short_name')
            ->add('study_programs_count')
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
            Column::make('Short Name', 'short_name')
                ->sortable()
                ->searchable(),
            Column::make('Study Programs', 'study_programs_count')
                ->sortable()
                ->searchable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('faculty.update'),
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
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('faculty.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status fakultas!');
            $this->dispatch('pg:eventRefresh-facultyTable');

            return;
        }

        $faculty = Faculty::find($id);

        if (! $faculty) {
            session()->flash('error', 'Fakultas tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-facultyTable');

            return;
        }

        $isActive = (bool) $value;

        $faculty->update([
            'is_active' => $isActive,
            'updated_by' => auth()->id(),
        ]);

        if (! $isActive) {
            StudyProgram::query()
                ->where('faculty_id', $faculty->id)
                ->update([
                    'is_active' => false,
                    'updated_by' => auth()->id(),
                ]);
        }

        $this->dispatch('pg:eventRefresh-facultyTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.faculties.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $faculty = Faculty::find($id);

        if ($faculty) {
            $this->js('
                Swal.fire({
                    title: "Hapus fakultas?",
                    text: "'.$faculty->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('faculty.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus fakultas!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id fakultas tidak ditemukan!');

            return;
        }

        $faculty = Faculty::find($id);

        if ($faculty) {
            if ($faculty->studyPrograms()->count() > 0) {
                $this->js('
                    Swal.fire({
                        title: "Tidak bisa hapus fakultas",
                        text: "Fakultas '.$faculty->name.' masih memiliki program studi. Hapus atau pindahkan program studi terlebih dahulu.",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                ');

                return;
            }

            $facultyName = $faculty->name;
            $faculty->delete();

            $this->dispatch('pg:eventRefresh-facultyTable');
            $this->js('
                Swal.fire({
                    title: "Fakultas dihapus",
                    text: "'.$facultyName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Faculty $row): array
    {
        $actions = [];

        if (ActivePermission::check('faculty.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('faculty.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
