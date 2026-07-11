<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\Course;
use App\Models\Academic\CourseScope;
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

final class CourseTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'courseTable';

    protected ?string $bulkActionModel = Course::class;

    protected ?string $bulkActionPermissionPrefix = 'course';

    protected string $bulkActionItemLabel = 'mata kuliah';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return Course::query()->with(['latestScope.faculty', 'latestScope.studyProgram', 'prerequisites']);
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('scope_type_label', function (Course $model) {
                $scopeType = $model->latestScope?->scope_type;

                return match ($scopeType) {
                    'faculty' => 'Fakultas',
                    'study_program' => 'Program Studi',
                    'global' => 'Global',
                    default => '-',
                };
            })
            ->add('scope_name', function (Course $model) {
                $scope = $model->latestScope;

                if (! $scope) {
                    return '-';
                }

                if ($scope->scope_type === 'global') {
                    return 'Semua Unit';
                }

                if ($scope->scope_type === 'faculty') {
                    return $scope->faculty?->name ?? '-';
                }

                if ($scope->scope_type === 'study_program') {
                    return $scope->studyProgram?->name ?? '-';
                }

                return '-';
            })
            ->add('code')
            ->add('name')
            ->add('short_name')
            ->add('credits')
            ->add('semester_recommendation')
            ->add('requirement_type')
            ->add('category_type')
            ->add('prerequisites', fn (Course $model) => $model->prerequisites->pluck('code')->join(', '))
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Scope Type', 'scope_type_label')
                ->sortable()
                ->searchable(),
            Column::make('Scope', 'scope_name')
                ->sortable()
                ->searchable(),
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Short Name', 'short_name')
                ->sortable()
                ->searchable(),
            Column::make('Credits', 'credits')
                ->sortable()
                ->searchable(),
            Column::make('Semester Rec.', 'semester_recommendation')
                ->sortable()
                ->searchable(),
            Column::make('Requirement', 'requirement_type')
                ->sortable()
                ->searchable(),
            Column::make('Category', 'category_type')
                ->sortable()
                ->searchable(),
            Column::make('Prerequisites', 'prerequisites')
                ->searchable(),
            Column::make('Is Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('course.update'),
                    'Aktif',
                    'Nonaktif'
                )
                ->sortable(),
            Column::make('Created at', 'created_at')
                ->sortable()
                ->searchable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('scope_type_label', 'scope_type')
                ->dataSource(collect([
                    ['id' => 'global', 'name' => 'Global'],
                    ['id' => 'faculty', 'name' => 'Fakultas'],
                    ['id' => 'study_program', 'name' => 'Program Studi'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('latestScope', fn (Builder $scope) => $scope->where('scope_type', $value))),
            Filter::select('scope_faculty', 'scope_faculty_id')
                ->dataSource(Faculty::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('latestScope', fn (Builder $scope) => $scope->where('faculty_id', $value))),
            Filter::select('scope_study_program', 'scope_study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('latestScope', fn (Builder $scope) => $scope->where('study_program_id', $value))),
            Filter::select('semester_recommendation', 'semester_recommendation')
                ->dataSource(collect(range(1, 8))->map(fn (int $semester) => ['id' => $semester, 'name' => 'Semester '.$semester]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('requirement_type', 'requirement_type')
                ->dataSource(Course::query()->select('requirement_type')->distinct()->orderBy('requirement_type')->pluck('requirement_type')->filter()->map(fn (string $type) => ['id' => $type, 'name' => $type]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('category_type', 'category_type')
                ->dataSource(Course::query()->select('category_type')->distinct()->orderBy('category_type')->pluck('category_type')->filter()->map(fn (string $type) => ['id' => $type, 'name' => $type]))
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

        if (! ActivePermission::check('course.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status mata kuliah!');
            $this->dispatch('pg:eventRefresh-courseTable');

            return;
        }

        $course = Course::query()->with('latestScope')->find($id);

        if (! $course) {
            session()->flash('error', 'Mata kuliah tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-courseTable');

            return;
        }

        $isActive = (bool) $value;

        if ($isActive && ! $this->scopeAllowsActive($course->latestScope)) {
            session()->flash('error', 'Mata kuliah hanya bisa aktif jika scope tujuan dalam kondisi aktif.');
            $this->dispatch('pg:eventRefresh-courseTable');

            return;
        }

        $course->update([
            'is_active' => $isActive,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-courseTable');
    }

    private function scopeAllowsActive(?CourseScope $scope): bool
    {
        if (! $scope) {
            return false;
        }

        if ($scope->scope_type === 'global') {
            return true;
        }

        if ($scope->scope_type === 'faculty') {
            return (bool) Faculty::find($scope->scope_id)?->is_active;
        }

        if ($scope->scope_type === 'study_program') {
            return (bool) StudyProgram::find($scope->scope_id)?->is_active;
        }

        return false;
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.courses.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $course = Course::find($id);

        if ($course) {
            $this->js('
                Swal.fire({
                    title: "Hapus mata kuliah?",
                    text: "'.$course->name.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('course.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus mata kuliah!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id mata kuliah tidak ditemukan!');

            return;
        }

        $course = Course::find($id);

        if ($course) {
            $courseName = $course->name;
            $course->delete();

            $this->dispatch('pg:eventRefresh-courseTable');
            $this->js('
                Swal.fire({
                    title: "Mata kuliah dihapus",
                    text: "'.$courseName.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(Course $row): array
    {
        $actions = [];

        if (ActivePermission::check('course.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('course.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
