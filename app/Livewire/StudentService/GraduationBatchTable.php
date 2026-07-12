<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationBatch;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class GraduationBatchTable extends BasePowerGridTable
{
    public string $tableName = 'graduationBatchTable';

    protected ?string $bulkActionModel = GraduationBatch::class;

    protected ?string $bulkActionPermissionPrefix = 'graduation-batch';

    protected string $bulkActionItemLabel = 'graduation batch';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return GraduationBatch::query()
            ->with(['academicPeriod.academicYear', 'studyProgram'])
            ->withCount('applications')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'academicPeriod' => ['name', 'code'],
            'studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('period_label', fn (GraduationBatch $model) => $model->academicPeriod?->name ?? '-')
            ->add('scope_label', fn (GraduationBatch $model) => $model->studyProgram?->name ?? 'All Programs')
            ->add('yudisium_date_label', fn (GraduationBatch $model) => $model->yudisium_date?->format('d M Y') ?? '-')
            ->add('status_badge', fn (GraduationBatch $model) => $this->statusBadge($model->status))
            ->add('applications_count');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Code', 'code')->sortable()->searchable(),
            Column::make('Academic Period', 'period_label')->sortable()->searchable(),
            Column::make('Scope', 'scope_label')->sortable()->searchable(),
            Column::make('Yudisium Date', 'yudisium_date_label', 'yudisium_date')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Applications', 'applications_count')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->placeholder('Cari nama gelombang...'),
            Filter::inputText('code')->placeholder('Cari kode...'),
            Filter::select('period_label', 'academic_period_id')
                ->dataSource(AcademicPeriod::query()->orderByDesc('start_at')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_period_id', $value)),
            Filter::select('scope_label', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'open', 'name' => 'Open'],
                    ['id' => 'review', 'name' => 'Review'],
                    ['id' => 'finalized', 'name' => 'Finalized'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('status', $value)),
            Filter::datepicker('yudisium_date_label', 'yudisium_date'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.student-services.graduation-batches.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.student-services.graduation-batches.edit', ['id' => $rowId]);
    }

    public function actions(GraduationBatch $row): array
    {
        $actions = [];

        if (ActivePermission::check('graduation-batch.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('graduation-batch.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-secondary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        return $actions;
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'open' => 'bg-success',
            'review' => 'bg-primary',
            'finalized' => 'bg-info',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str($status)->title().'</span>';
    }
}
