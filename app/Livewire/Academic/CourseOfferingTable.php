<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Course;
use App\Models\Academic\CourseOffering;
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

final class CourseOfferingTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'courseOfferingTable';

    protected ?string $bulkActionModel = CourseOffering::class;

    protected ?string $bulkActionPermissionPrefix = 'course-offering';

    protected string $bulkActionItemLabel = 'penawaran kelas';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return CourseOffering::query()
            ->with(['academicYear', 'studyProgram', 'course', 'lecturers'])
            ->withCount('lecturers')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'academicYear' => ['name', 'code'],
            'studyProgram' => ['name', 'code'],
            'course' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('academic_year_name', fn (CourseOffering $model) => $model->academicYear?->name ?? '-')
            ->add('study_program_name', fn (CourseOffering $model) => $model->studyProgram?->name ?? '-')
            ->add('course_name', fn (CourseOffering $model) => $model->course?->code.' - '.$model->course?->name ?? '-')
            ->add('label')
            ->add('code')
            ->add('semester_no')
            ->add('capacity')
            ->add('delivery_mode')
            ->add('status')
            ->add('lecturers_count')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Academic Year', 'academic_year_name')
                ->searchable(),
            Column::make('Study Program', 'study_program_name')
                ->searchable(),
            Column::make('Course', 'course_name')
                ->searchable(),
            Column::make('Label', 'label')
                ->sortable()
                ->searchable(),
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Semester', 'semester_no')
                ->sortable(),
            Column::make('Capacity', 'capacity')
                ->sortable(),
            Column::make('Mode', 'delivery_mode')
                ->sortable(),
            Column::make('Status', 'status')
                ->sortable(),
            Column::make('Lecturers', 'lecturers_count')
                ->sortable(),
            Column::make('Created At', 'created_at')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('academic_year_name', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_year_id', $value)),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::select('course_name', 'course_id')
                ->dataSource(Course::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (Course $course) => [
                    'id' => $course->id,
                    'name' => $course->code.' - '.$course->name,
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('course_id', $value)),
            Filter::select('semester_no', 'semester_no')
                ->dataSource(collect(range(1, 8))->map(fn (int $semester) => ['id' => $semester, 'name' => 'Semester '.$semester]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('delivery_mode', 'delivery_mode')
                ->dataSource(CourseOffering::query()->select('delivery_mode')->distinct()->orderBy('delivery_mode')->pluck('delivery_mode')->filter()->map(fn (string $mode) => ['id' => $mode, 'name' => $mode]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource(CourseOffering::query()->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->map(fn (string $status) => ['id' => $status, 'name' => $status]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.course-offerings.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.course-offerings.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $offering = CourseOffering::find($id);

        if ($offering) {
            $this->js('
                Swal.fire({
                    title: "Hapus course offering?",
                    text: "'.$offering->course?->code.' - '.$offering->label.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('course-offering.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus course offering!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id course offering tidak ditemukan!');

            return;
        }

        $offering = CourseOffering::find($id);

        if ($offering) {
            $offeringLabel = $offering->course?->code.' - '.$offering->label;
            $offering->delete();

            $this->dispatch('pg:eventRefresh-courseOfferingTable');
            $this->js('
                Swal.fire({
                    title: "Course offering dihapus",
                    text: "'.$offeringLabel.' berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(CourseOffering $row): array
    {
        $actions = [];

        if (ActivePermission::check('course-offering.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('course-offering.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('course-offering.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
