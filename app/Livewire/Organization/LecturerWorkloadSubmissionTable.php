<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LecturerWorkloadSubmissionTable extends BasePowerGridTable
{
    public string $tableName = 'lecturerWorkloadSubmissionTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return LecturerWorkloadSubmission::query()
            ->with(['period', 'owner', 'lecturerProfile.studyProgram'])
            ->orderByRaw("FIELD(status, 'in_approval', 'submitted', 'revision', 'draft', 'approved', 'rejected', 'cancelled')")
            ->latest('updated_at');
    }

    public function relationSearch(): array
    {
        return ['owner' => ['first_name', 'last_name', 'email', 'username'], 'period' => ['name', 'code']];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('lecturer_name', fn (LecturerWorkloadSubmission $model) => $model->owner?->name ?? '-')
            ->add('period_name', fn (LecturerWorkloadSubmission $model) => $model->period?->name ?? '-')
            ->add('program_name', fn (LecturerWorkloadSubmission $model) => $model->lecturerProfile?->studyProgram?->name ?? '-')
            ->add('total_sks')
            ->add('status_badge', fn (LecturerWorkloadSubmission $model) => $this->statusBadge($model->status))
            ->add('submitted_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Dosen', 'lecturer_name')->searchable(),
            Column::make('Periode', 'period_name')->searchable(),
            Column::make('Program Studi', 'program_name'),
            Column::make('Total SKS', 'total_sks')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Diajukan', 'submitted_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'in_approval', 'name' => 'Menunggu Approval'],
                    ['id' => 'approved', 'name' => 'Disetujui'],
                    ['id' => 'revision', 'name' => 'Revisi'],
                    ['id' => 'rejected', 'name' => 'Ditolak'],
                    ['id' => 'cancelled', 'name' => 'Dibatalkan'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-workload-submissions.show', ['id' => $rowId]);
    }

    public function actions(LecturerWorkloadSubmission $row): array
    {
        return ActivePermission::check('lecturer-workload-submission.view')
            ? [Button::add('show')->slot('<i class="fa fa-eye"></i>')->class('btn btn-primary')->dispatch('show', ['rowId' => $row->id])]
            : [];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'revision' => 'bg-info',
            'in_approval', 'submitted' => 'bg-warning text-dark',
            'cancelled' => 'bg-secondary',
            default => 'bg-muted',
        };

        return '<span class="badge '.$class.'">'.str($status)->replace('_', ' ')->title().'</span>';
    }
}
