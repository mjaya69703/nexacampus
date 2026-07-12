<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\Curriculum;
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

final class CurriculumTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'curriculumTable';

    protected ?string $bulkActionModel = Curriculum::class;

    protected ?string $bulkActionPermissionPrefix = 'curriculum';

    protected string $bulkActionItemLabel = 'kurikulum';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return Curriculum::query()
            ->with('studyProgram')
            ->withCount('curriculumCourses');
    }

    public function relationSearch(): array
    {
        return [
            'studyProgram' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('study_program_name', fn (Curriculum $model) => $model->studyProgram?->name)
            ->add('name')
            ->add('code')
            ->add('start_year')
            ->add('end_year')
            ->add('is_active')
            ->add('total_courses', fn (Curriculum $model) => $model->curriculum_courses_count);
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
            Column::make('Study Program', 'study_program_name')
                ->sortable()
                ->searchable(),
            Column::make('Start Year', 'start_year')
                ->sortable()
                ->searchable(),
            Column::make('End Year', 'end_year')
                ->sortable()
                ->searchable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('curriculum.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Total Courses', 'total_courses')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name', 'name')
                ->placeholder('Cari nama kurikulum...')
                ->operators(['contains']),
            Filter::inputText('code', 'code')
                ->placeholder('Cari kode...')
                ->operators(['contains']),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::select('start_year', 'start_year')
                ->dataSource(Curriculum::query()->select('start_year')->distinct()->orderByDesc('start_year')->pluck('start_year')->filter()->map(fn ($year) => ['id' => $year, 'name' => (string) $year]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('end_year', 'end_year')
                ->dataSource(Curriculum::query()->select('end_year')->distinct()->orderByDesc('end_year')->pluck('end_year')->filter()->map(fn ($year) => ['id' => $year, 'name' => (string) $year]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active'),
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('curriculum.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status kurikulum!');
            $this->dispatch('pg:eventRefresh-curriculumTable');

            return;
        }

        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            session()->flash('error', 'Kurikulum tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-curriculumTable');

            return;
        }

        $curriculum->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-curriculumTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.curriculums.edit', ['id' => $rowId]);
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.curriculums.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $curriculum = Curriculum::find($id);

        if ($curriculum) {
            $this->js('
                Swal.fire({
                    title: "Hapus kurikulum?",
                    text: "'.$curriculum->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('curriculum.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus kurikulum!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id kurikulum tidak ditemukan!');

            return;
        }

        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            session()->flash('error', 'Kurikulum tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-curriculumTable');

            return;
        }

        if ($curriculum->curriculumCourses()->exists()) {
            session()->flash('error', 'Kurikulum tidak dapat dihapus karena masih memiliki daftar mata kuliah.');
            $this->dispatch('pg:eventRefresh-curriculumTable');

            return;
        }

        $curriculumName = $curriculum->name;
        $curriculum->delete();

        $this->dispatch('pg:eventRefresh-curriculumTable');
        $this->js('
            Swal.fire({
                title: "Kurikulum dihapus",
                text: "'.$curriculumName.' berhasil dihapus!",
                icon: "success",
                timer: 2000,
                showConfirmButton: false
            });
        ');
    }

    public function actions(Curriculum $row): array
    {
        $actions = [];

        if (ActivePermission::check('curriculum.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('curriculum.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('curriculum.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
