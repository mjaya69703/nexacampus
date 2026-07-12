<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\StudentLeaveApplication;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentLeaveApplicationTable extends BasePowerGridTable
{
    public string $tableName = 'studentLeaveApplicationTable';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return StudentLeaveApplication::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'academicYear' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('application_number')
            ->add('student_name', fn (StudentLeaveApplication $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentLeaveApplication $model) => $model->studentProfile?->nim ?? '-')
            ->add('study_program_name', fn (StudentLeaveApplication $model) => $model->studentProfile?->studyProgram?->name ?? '-')
            ->add('academic_year', fn (StudentLeaveApplication $model) => $model->academicYear?->name ?? '-')
            ->add('semester')
            ->add('duration_semesters')
            ->add('reason_category_label', fn (StudentLeaveApplication $model) => str($model->reason_category)->replace('_', ' ')->title()->toString())
            ->add('status_badge', fn (StudentLeaveApplication $model) => $this->statusBadge($model->status))
            ->add('created_at_label', fn (StudentLeaveApplication $model) => $model->created_at?->format('d M Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('Application No', 'application_number')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program_name')->sortable()->searchable(),
            Column::make('Academic Year', 'academic_year')->sortable()->searchable(),
            Column::make('Semester', 'semester')->sortable(),
            Column::make('Duration', 'duration_semesters')->sortable(),
            Column::make('Reason', 'reason_category_label', 'reason_category')->sortable(),
            Column::make('Status', 'status_badge', 'status'),
            Column::make('Submitted', 'created_at_label', 'created_at')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('application_number')->placeholder('Cari No Cuti...'),
            Filter::select('academic_year', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_year_id', $value)),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studentProfile', fn (Builder $student) => $student->where('study_program_id', $value))),
            Filter::select('reason_category_label', 'reason_category')
                ->dataSource(collect([
                    ['id' => 'health', 'name' => 'Kesehatan'],
                    ['id' => 'financial', 'name' => 'Keuangan'],
                    ['id' => 'personal', 'name' => 'Pribadi / Keluarga'],
                    ['id' => 'work', 'name' => 'Pekerjaan'],
                    ['id' => 'academic', 'name' => 'Akademik'],
                    ['id' => 'other', 'name' => 'Lainnya'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('reason_category', $value)),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'in_approval', 'name' => 'Menunggu Approval'],
                    ['id' => 'under_review', 'name' => 'Under Review'],
                    ['id' => 'revision_requested', 'name' => 'Perlu Perbaikan'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'approved_pending_payment', 'name' => 'Menunggu Pembayaran'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'activated', 'name' => 'Cuti Aktif'],
                    ['id' => 'returned', 'name' => 'Returned'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('status', $value)),
            Filter::datepicker('created_at_label', 'created_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.student-services.leave-applications.show', ['id' => $rowId]);
    }

    public function actions(StudentLeaveApplication $row): array
    {
        if (! ActivePermission::check('leave-application.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'returned', 'activated' => 'bg-success',
            'approved' => 'bg-info',
            'approved_pending_payment', 'in_approval' => 'bg-warning text-dark',
            'under_review' => 'bg-primary',
            'revision_requested' => 'bg-warning text-dark',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };

        $label = match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Approval',
            'approved_pending_payment' => 'Menunggu Pembayaran',
            'activated' => 'Cuti Aktif',
            default => str($status)->replace('_', ' ')->title(),
        };

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }
}
