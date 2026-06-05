<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\LecturerPerformanceReview;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LecturerPerformanceReviewTable extends BasePowerGridTable
{
    public string $tableName = 'lecturerPerformanceReviewTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return LecturerPerformanceReview::query()->with(['owner', 'edomPeriod', 'workloadPeriod', 'lecturerProfile.studyProgram'])->latest('calculated_at');
    }

    public function relationSearch(): array
    {
        return ['owner' => ['first_name', 'last_name', 'email', 'username'], 'edomPeriod' => ['name', 'code']];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('lecturer_name', fn (LecturerPerformanceReview $model) => $model->owner?->name ?? '-')
            ->add('period_name', fn (LecturerPerformanceReview $model) => $model->edomPeriod?->name ?? '-')
            ->add('program_name', fn (LecturerPerformanceReview $model) => $model->lecturerProfile?->studyProgram?->name ?? '-')
            ->add('edom_score')
            ->add('edom_response_count')
            ->add('final_score')
            ->add('status_badge', fn (LecturerPerformanceReview $model) => '<span class="badge '.($model->status === 'published' ? 'bg-success' : 'bg-info').'">'.str($model->status)->title().'</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Dosen', 'lecturer_name')->searchable(),
            Column::make('Periode', 'period_name')->searchable(),
            Column::make('Program Studi', 'program_name'),
            Column::make('EDOM', 'edom_score')->sortable(),
            Column::make('Respon', 'edom_response_count')->sortable(),
            Column::make('Nilai Akhir', 'final_score')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-performance-reviews.show', ['id' => $rowId]);
    }

    public function actions(LecturerPerformanceReview $row): array
    {
        return ActivePermission::check('lecturer-performance-review.view')
            ? [Button::add('show')->slot('<i class="fa fa-eye"></i>')->class('btn btn-primary')->dispatch('show', ['rowId' => $row->id])]
            : [];
    }
}
