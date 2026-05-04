<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudentGrade;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentGradeTable extends BasePowerGridTable
{
    public string $tableName = 'studentGradeTable';

    protected ?string $bulkActionModel = StudentGrade::class;

    protected ?string $bulkActionPermissionPrefix = 'student-grade';

    protected string $bulkActionItemLabel = 'nilai mahasiswa';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return StudentGrade::query()
            ->with([
                'studyPlanDetail.studyPlan.studentProfile.user',
                'studyPlanDetail.studyPlan.studentProfile.studyProgram',
                'studyPlanDetail.studyPlan.academicYear',
                'studyPlanDetail.courseOffering.course',
            ])
            ->withCount('components')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studyPlanDetail.studyPlan.studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studyPlanDetail.studyPlan.studentProfile' => ['nim'],
            'studyPlanDetail.studyPlan.studentProfile.studyProgram' => ['name', 'code'],
            'studyPlanDetail.studyPlan.academicYear' => ['name', 'code'],
            'studyPlanDetail.courseOffering.course' => ['code', 'name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (StudentGrade $model) => $model->studyPlanDetail?->studyPlan?->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentGrade $model) => $model->studyPlanDetail?->studyPlan?->studentProfile?->nim ?? '-')
            ->add('study_program', fn (StudentGrade $model) => $model->studyPlanDetail?->studyPlan?->studentProfile?->studyProgram?->name ?? '-')
            ->add('academic_year', fn (StudentGrade $model) => $model->studyPlanDetail?->studyPlan?->academicYear?->name ?? '-')
            ->add('course_label', fn (StudentGrade $model) => ($model->studyPlanDetail?->courseOffering?->course?->code ?? '-').' - '.($model->studyPlanDetail?->courseOffering?->course?->name ?? '-'))
            ->add('final_score')
            ->add('letter_grade')
            ->add('grade_status')
            ->add('result_status')
            ->add('components_count')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Prodi', 'study_program')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year')->sortable()->searchable(),
            Column::make('Mata Kuliah', 'course_label')->sortable()->searchable(),
            Column::make('Final Score', 'final_score')->sortable(),
            Column::make('Nilai Huruf', 'letter_grade')->sortable(),
            Column::make('Lifecycle', 'grade_status')->sortable()->searchable(),
            Column::make('Status', 'result_status')->sortable(),
            Column::make('Komponen', 'components_count')->sortable(),
            Column::action('Action'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.student-grades.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.student-grades.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $grade = StudentGrade::with('studyPlanDetail.courseOffering.course')->find($id);

        if ($grade) {
            $courseLabel = ($grade->studyPlanDetail?->courseOffering?->course?->code ?? '-').' - '.($grade->studyPlanDetail?->courseOffering?->course?->name ?? 'Mata kuliah');

            $this->js('
                Swal.fire({
                    title: "Hapus nilai mahasiswa?",
                    text: "'.$courseLabel.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('student-grade.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus nilai mahasiswa!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id nilai tidak ditemukan!');

            return;
        }

        $grade = StudentGrade::find($id);

        if ($grade) {
            $grade->update(['deleted_by' => auth()->id()]);
            $grade->delete();

            $this->dispatch('pg:eventRefresh-studentGradeTable');
            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Nilai mahasiswa berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(StudentGrade $row): array
    {
        $actions = [];

        if (ActivePermission::check('student-grade.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('student-grade.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('student-grade.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
