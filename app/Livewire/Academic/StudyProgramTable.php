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

final class StudyProgramTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'studyProgramTable';

    protected ?string $bulkActionModel = StudyProgram::class;

    protected ?string $bulkActionPermissionPrefix = 'study-program';

    protected string $bulkActionItemLabel = 'program studi';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return StudyProgram::query()->with('faculty');
    }

    public function relationSearch(): array
    {
        return [
            'faculty' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('faculty_name', fn (StudyProgram $model) => $model->faculty?->name)
            ->add('name')
            ->add('code')
            ->add('short_name')
            ->add('degree')
            ->add('prefix_degree')
            ->add('suffix_degree')
            ->add('is_active')
            ->add('desc')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Faculty', 'faculty_name')
                ->sortable()
                ->searchable(),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Short Name', 'short_name')
                ->sortable()
                ->searchable(),
            Column::make('Degree', 'degree')
                ->sortable()
                ->searchable(),
            Column::make('Prefix Degree', 'prefix_degree')
                ->sortable()
                ->searchable(),
            Column::make('Suffix Degree', 'suffix_degree')
                ->sortable()
                ->searchable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('study-program.update'),
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
            Filter::select('faculty_name', 'faculty_id')
                ->dataSource(Faculty::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('faculty_id', $value)),
            Filter::select('degree', 'degree')
                ->dataSource(StudyProgram::query()->select('degree')->distinct()->orderBy('degree')->pluck('degree')->filter()->map(fn (string $degree) => ['id' => $degree, 'name' => $degree]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('study-program.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status program studi!');
            $this->dispatch('pg:eventRefresh-studyProgramTable');

            return;
        }

        $studyProgram = StudyProgram::query()->with('faculty')->find($id);

        if (! $studyProgram) {
            session()->flash('error', 'Program studi tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-studyProgramTable');

            return;
        }

        $isActive = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($isActive && $studyProgram->faculty && ! $studyProgram->faculty->is_active) {
            session()->flash('error', 'Program studi hanya bisa aktif jika fakultas yang terhubung berstatus aktif.');
            $this->dispatch('pg:eventRefresh-studyProgramTable');

            return;
        }

        $studyProgram->update([
            'is_active' => $isActive,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-studyProgramTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.study-programs.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $studyProgram = StudyProgram::find($id);

        if ($studyProgram) {
            $this->js('
                Swal.fire({
                    title: "Hapus program studi?",
                    text: "'.$studyProgram->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('study-program.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus program studi!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id program studi tidak ditemukan!');

            return;
        }

        $studyProgram = StudyProgram::find($id);

        if ($studyProgram) {
            $studyProgramName = $studyProgram->name;
            $studyProgram->delete();

            $this->dispatch('pg:eventRefresh-studyProgramTable');
            $this->js('
                Swal.fire({
                    title: "Program studi dihapus",
                    text: "'.$studyProgramName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(StudyProgram $row): array
    {
        $actions = [];

        if (ActivePermission::check('study-program.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('study-program.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
