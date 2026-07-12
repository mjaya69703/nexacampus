<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\StudentGradePublicationService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudentGradeTable extends BasePowerGridTable
{
    use WithExport;

    public string $tableName = 'studentGradeTable';

    protected ?string $bulkActionModel = StudentGrade::class;

    protected ?string $bulkActionPermissionPrefix = 'student-grade';

    protected string $bulkActionItemLabel = 'nilai mahasiswa';

    protected ?string $customBulkActionLabel = 'Publish Selected';

    public function setUp(): array
    {
        return [
            ...$this->powerGridSetUp(showToggleColumns: true),
            PowerGrid::exportable('student-grades')
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
                ->stripTags(true),
        ];
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
            ->add('class_label', fn (StudentGrade $model) => $model->studyPlanDetail?->courseOffering?->label ?? '-')
            ->add('semester_no', fn (StudentGrade $model) => $model->studyPlanDetail?->courseOffering?->semester_no ?? '-')
            ->add('final_score')
            ->add('letter_grade')
            ->add('grade_point')
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
            Column::make('Kelas', 'class_label')->searchable(),
            Column::make('Semester', 'semester_no'),
            Column::make('Final Score', 'final_score')->sortable(),
            Column::make('Nilai Huruf', 'letter_grade')->sortable(),
            Column::make('Grade Point', 'grade_point')->sortable(),
            Column::make('Lifecycle', 'grade_status')->sortable()->searchable(),
            Column::make('Status', 'result_status')->sortable(),
            Column::make('Komponen', 'components_count')->sortable(),
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

                    return $query->whereHas('studyPlanDetail.studyPlan.studentProfile.user', fn (Builder $user) => $user->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                }),
            Filter::inputText('nim', 'nim')
                ->placeholder('Cari NIM...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->whereHas('studyPlanDetail.studyPlan.studentProfile', fn (Builder $sp) => $sp->where('nim', 'like', '%'.$search.'%'));
                }),

            Filter::select('academic_year', 'academic_year_id')
                ->dataSource(AcademicYear::query()
                    ->orderByDesc('start_date')
                    ->get(['id', 'name'])
                    ->map(fn (AcademicYear $year) => [
                        'id' => $year->id,
                        'name' => $year->name,
                    ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studyPlanDetail.studyPlan', fn (Builder $studyPlan) => $studyPlan->where('academic_year_id', $value))),

            Filter::select('study_program', 'study_program_id')
                ->dataSource(StudyProgram::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (StudyProgram $program) => [
                        'id' => $program->id,
                        'name' => $program->name,
                    ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studyPlanDetail.studyPlan.studentProfile', fn (Builder $student) => $student->where('study_program_id', $value))),

            Filter::select('course_label', 'course_offering_id')
                ->dataSource(
                    CourseOffering::query()
                        ->with('course')
                        ->orderByDesc('created_at')
                        ->get()
                        ->map(fn (CourseOffering $offering) => [
                            'id' => $offering->id,
                            'label' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-').' / '.$offering->label,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('label')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studyPlanDetail', fn (Builder $detail) => $detail->where('course_offering_id', $value))),

            Filter::select('class_label', 'class_offering_id')
                ->dataSource(
                    CourseOffering::query()
                        ->with('course')
                        ->whereNotNull('label')
                        ->orderBy('label')
                        ->get()
                        ->map(fn (CourseOffering $offering) => [
                            'id' => $offering->id,
                            'label' => ($offering->label ?? '-').' / '.($offering->course?->code ?? '-'),
                        ])
                )
                ->optionValue('id')
                ->optionLabel('label')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studyPlanDetail', fn (Builder $detail) => $detail->where('course_offering_id', $value))),

            Filter::select('semester_no', 'semester_no')
                ->dataSource(collect(range(1, 8))->map(fn (int $semester) => ['id' => $semester, 'label' => 'Semester '.$semester]))
                ->optionValue('id')
                ->optionLabel('label')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studyPlanDetail.courseOffering', fn (Builder $offering) => $offering->where('semester_no', $value))),

            Filter::select('letter_grade', 'letter_grade')
                ->dataSource(collect(['A', 'AB', 'B', 'BC', 'C', 'D', 'E'])->map(fn (string $grade) => ['id' => $grade, 'label' => $grade]))
                ->optionValue('id')
                ->optionLabel('label'),

            Filter::select('grade_status', 'grade_status')
                ->dataSource(collect(['Draft', 'Finalized', 'Published'])->map(fn (string $status) => ['id' => $status, 'label' => $status]))
                ->optionValue('id')
                ->optionLabel('label'),

            Filter::select('result_status', 'result_status')
                ->dataSource(collect(['Passed', 'Failed', 'Incomplete', 'Withdrawn', 'Cancelled'])->map(fn (string $status) => ['id' => $status, 'label' => $status]))
                ->optionValue('id')
                ->optionLabel('label'),

            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    public function exportToXLS(bool $selected = false): StreamedResponse|bool
    {
        $payload = $this->exportPayload($selected);

        if ($payload === false) {
            return false;
        }

        return response()->streamDownload(function () use ($payload) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray($payload['headers'], null, 'A1');

            foreach ($payload['rows'] as $index => $row) {
                $sheet->fromArray(array_values($row), null, 'A'.($index + 2));
            }

            foreach (range('A', $this->spreadsheetLastColumn(count($payload['headers']))) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->exportFileName('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function canCustomBulkAction(): bool
    {
        return ActivePermission::check('student-grade.update');
    }

    public function runCustomBulkAction(): void
    {
        if (! ActivePermission::check('student-grade.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk publish nilai mahasiswa.');

            return;
        }

        $ids = collect($this->checkboxValues)->filter()->values();

        if ($ids->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu nilai mahasiswa terlebih dahulu.');

            return;
        }

        $publicationService = app(StudentGradePublicationService::class);
        $published = 0;
        $skipped = 0;

        foreach (StudentGrade::query()->whereKey($ids)->get() as $grade) {
            if ($publicationService->publish($grade, auth()->id())) {
                $published++;
            } else {
                $skipped++;
            }
        }

        $this->dispatch('pg:eventRefresh-studentGradeTable');
        $this->clearBulkSelection();

        if ($published > 0) {
            session()->flash('success', $published.' nilai mahasiswa berhasil dipublikasikan.');
        }

        if ($skipped > 0) {
            session()->flash('error', $skipped.' nilai dilewati karena belum berstatus Finalized atau sudah Published.');
        }
    }

    public function exportToCsv(bool $selected = false): StreamedResponse|bool
    {
        $payload = $this->exportPayload($selected);

        if ($payload === false) {
            return false;
        }

        return response()->streamDownload(function () use ($payload) {
            echo "\xEF\xBB\xBF";

            $handle = fopen('php://output', 'w');
            fputcsv($handle, $payload['headers']);

            foreach ($payload['rows'] as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $this->exportFileName('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportPayload(bool $selected): array|bool
    {
        if ($selected && count($this->checkboxValues) === 0) {
            return false;
        }

        $columns = $this->columnsWithCurrentHiddenState();
        $rows = $this->prepareToExport($selected);

        return (new Export)->prepare(
            collect($rows),
            $columns,
            (bool) data_get($this->setUp, 'exportable.stripTags', true),
        );
    }

    private function columnsWithCurrentHiddenState(): array
    {
        $currentHiddenStates = collect($this->columns)
            ->mapWithKeys(fn ($column) => [data_get($column, 'field') => data_get($column, 'hidden')]);

        return array_map(function ($column) use ($currentHiddenStates) {
            $column->hidden = (bool) $currentHiddenStates->get($column->field, $column->hidden);

            return $column;
        }, $this->columns());
    }

    private function exportFileName(string $extension): string
    {
        return 'student-grades-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function spreadsheetLastColumn(int $columnCount): string
    {
        $column = '';

        while ($columnCount > 0) {
            $columnCount--;
            $column = chr(65 + ($columnCount % 26)).$column;
            $columnCount = intdiv($columnCount, 26);
        }

        return $column ?: 'A';
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
                ->slot('<i class="fa fa-eye"></i> <span>Detail</span>')
                ->class('btn btn-sm btn-info d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('student-grade.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('student-grade.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
