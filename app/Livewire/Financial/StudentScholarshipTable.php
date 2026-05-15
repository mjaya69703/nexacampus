<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Financial\StudentScholarship;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentScholarshipTable extends BasePowerGridTable
{
    public string $tableName = 'studentScholarshipTable';

    protected ?string $bulkActionModel = StudentScholarship::class;

    protected ?string $bulkActionPermissionPrefix = 'student-scholarship';

    protected string $bulkActionItemLabel = 'student scholarship';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return StudentScholarship::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'scholarship', 'academicYear'])
            ->orderByRaw("FIELD(status, 'active', 'completed', 'revoked')")
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'scholarship' => ['name'],
            'academicYear' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (StudentScholarship $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentScholarship $model) => $model->studentProfile?->nim ?? '-')
            ->add('scholarship_name', fn (StudentScholarship $model) => $model->scholarship?->name ?? '-')
            ->add('academic_year', fn (StudentScholarship $model) => $model->academicYear?->name ?? 'All Years')
            ->add('semester_label', fn (StudentScholarship $model) => $model->semester ? 'Semester '.$model->semester : 'All Semesters')
            ->add('status_badge', fn (StudentScholarship $model) => $this->statusBadge($model->status))
            ->add('status');
    }

    public function columns(): array
    {
        return [
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Scholarship', 'scholarship_name')->sortable()->searchable(),
            Column::make('Academic Year', 'academic_year')->sortable()->searchable(),
            Column::make('Semester', 'semester_label')->sortable(),
            Column::make('Status', 'status_badge'),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('academic_year_id', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'completed', 'name' => 'Completed'],
                    ['id' => 'revoked', 'name' => 'Revoked'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.financial.student-scholarships.edit', ['id' => $rowId]);
    }

    public function actions(StudentScholarship $row): array
    {
        if (! ActivePermission::check('student-scholarship.update')) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'active' => 'bg-success',
            'completed' => 'bg-info',
            'revoked' => 'bg-danger',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str($status)->replace('_', ' ')->title().'</span>';
    }
}
