<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyPlan;
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

final class StudyPlanTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'studyPlanTable';

    protected ?string $bulkActionModel = StudyPlan::class;

    protected ?string $bulkActionPermissionPrefix = 'study-plan';

    protected string $bulkActionItemLabel = 'KRS';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return StudyPlan::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear'])
            ->withCount('details')
            ->withSum('details as total_credits', 'credits')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile' => ['nim'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'academicYear' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (StudyPlan $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('student_nim', fn (StudyPlan $model) => $model->studentProfile?->nim ?? '-')
            ->add('study_program_name', fn (StudyPlan $model) => $model->studentProfile?->studyProgram?->name ?? '-')
            ->add('academic_year_name', fn (StudyPlan $model) => $model->academicYear?->name ?? '-')
            ->add('semester_no')
            ->add('status')
            ->add('details_count')
            ->add('total_credits', fn (StudyPlan $model) => (int) ($model->total_credits ?? 0))
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Mahasiswa', 'student_name')
                ->sortable()
                ->searchable(),
            Column::make('NIM', 'student_nim')
                ->sortable()
                ->searchable(),
            Column::make('Prodi', 'study_program_name')
                ->sortable()
                ->searchable(),
            Column::make('Tahun Akademik', 'academic_year_name')
                ->sortable()
                ->searchable(),
            Column::make('Semester', 'semester_no')
                ->sortable(),
            Column::make('Status', 'status')
                ->sortable()
                ->searchable(),
            Column::make('Total Matkul', 'details_count')
                ->sortable(),
            Column::make('Total SKS', 'total_credits')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('student_name', 'student_name')
                ->placeholder('Cari nama mahasiswa...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->whereHas('studentProfile.user', fn (Builder $user) => $user->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                }),
            Filter::inputText('student_nim', 'student_nim')
                ->placeholder('Cari NIM...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->whereHas('studentProfile', fn (Builder $sp) => $sp->where('nim', 'like', '%'.$search.'%'));
                }),
            Filter::select('academic_year_name', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_year_id', $value)),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studentProfile', fn (Builder $student) => $student->where('study_program_id', $value))),
            Filter::select('semester_no', 'semester_no')
                ->dataSource(collect(range(1, 14))->map(fn (int $semester) => ['id' => $semester, 'name' => 'Semester '.$semester]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource(StudyPlan::query()->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->map(fn (string $status) => ['id' => $status, 'name' => $status]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.study-plans.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.study-plans.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $studyPlan = StudyPlan::with('studentProfile.user')->find($id);

        if ($studyPlan) {
            $studentName = $studyPlan->studentProfile?->user?->name ?? 'Mahasiswa';

            $this->js('
                Swal.fire({
                    title: "Hapus KRS?",
                    text: "'.$studentName.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('study-plan.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus KRS!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id KRS tidak ditemukan!');

            return;
        }

        $studyPlan = StudyPlan::find($id);

        if ($studyPlan) {
            $studyPlan->update(['deleted_by' => auth()->id()]);
            $studyPlan->forceDelete();
            $this->dispatch('pg:eventRefresh-studyPlanTable');
            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "KRS berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(StudyPlan $row): array
    {
        $actions = [];

        if (ActivePermission::check('study-plan.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> <span>Detail</span>')
                ->class('btn btn-sm btn-info d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('study-plan.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('study-plan.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
