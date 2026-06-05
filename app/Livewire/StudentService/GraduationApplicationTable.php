<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\StudentService\GraduationApplication;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class GraduationApplicationTable extends BasePowerGridTable
{
    public string $tableName = 'graduationApplicationTable';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return GraduationApplication::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'graduationBatch'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'graduationBatch' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('application_number')
            ->add('student_name', fn (GraduationApplication $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (GraduationApplication $model) => $model->studentProfile?->nim ?? '-')
            ->add('study_program', fn (GraduationApplication $model) => $model->studentProfile?->studyProgram?->name ?? '-')
            ->add('batch_label', fn (GraduationApplication $model) => $model->graduationBatch?->name ?? '-')
            ->add('graduation_period')
            ->add('status_badge', fn (GraduationApplication $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('created_at_label', fn (GraduationApplication $model) => $model->created_at?->format('d M Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('Application No', 'application_number')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program')->sortable()->searchable(),
            Column::make('Batch', 'batch_label')->sortable()->searchable(),
            Column::make('Periode', 'graduation_period')->sortable()->searchable(),
            Column::make('Status', 'status_badge'),
            Column::make('Submitted', 'created_at_label')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'under_review', 'name' => 'Under Review'],
                    ['id' => 'revision_requested', 'name' => 'Perlu Perbaikan'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'finalized', 'name' => 'Finalized'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.student-services.graduation-applications.show', ['id' => $rowId]);
    }

    public function actions(GraduationApplication $row): array
    {
        if (! ActivePermission::check('graduation-application.view')) {
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
            'finalized' => 'bg-success',
            'approved' => 'bg-info',
            'revision_requested' => 'bg-warning text-dark',
            'under_review' => 'bg-primary',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };

        $label = match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            default => str($status)->replace('_', ' ')->title(),
        };

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }
}
