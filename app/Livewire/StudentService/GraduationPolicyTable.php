<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationPolicy;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class GraduationPolicyTable extends BasePowerGridTable
{
    public string $tableName = 'graduationPolicyTable';

    protected ?string $bulkActionModel = GraduationPolicy::class;

    protected ?string $bulkActionPermissionPrefix = 'graduation-policy';

    protected string $bulkActionItemLabel = 'graduation policy';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return GraduationPolicy::query()
            ->with('studyProgram')
            ->orderByDesc('is_active')
            ->orderBy('study_program_id');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('scope', fn (GraduationPolicy $model) => $model->studyProgram?->name ?? 'Global')
            ->add('minimum_semester')
            ->add('minimum_passed_credits')
            ->add('minimum_gpa')
            ->add('rules_label', fn (GraduationPolicy $model) => $this->rulesLabel($model))
            ->add('is_active');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Scope', 'scope')->sortable()->searchable(),
            Column::make('Min Semester', 'minimum_semester')->sortable(),
            Column::make('Min SKS', 'minimum_passed_credits')->sortable(),
            Column::make('Min GPA', 'minimum_gpa')->sortable(),
            Column::make('Rules', 'rules_label'),
            Column::make('Active', 'is_active')
                ->toggleable(ActivePermission::check('graduation-policy.update'), 'Active', 'Inactive')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('study_program_id', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        abort_unless(ActivePermission::check('graduation-policy.update'), 403);

        $policy = GraduationPolicy::findOrFail($id);

        if ((bool) $value) {
            $exists = GraduationPolicy::query()
                ->where('is_active', true)
                ->whereKeyNot($policy->id)
                ->when($policy->study_program_id, fn ($query) => $query->where('study_program_id', $policy->study_program_id), fn ($query) => $query->whereNull('study_program_id'))
                ->exists();

            if ($exists) {
                session()->flash('error', 'Sudah ada graduation policy aktif untuk scope ini.');
                $this->dispatch('pg:eventRefresh-graduationPolicyTable');

                return;
            }
        }

        GraduationPolicy::whereKey($id)->update([
            'is_active' => (bool) $value,
        ]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.student-services.graduation-policies.edit', ['id' => $rowId]);
    }

    public function actions(GraduationPolicy $row): array
    {
        if (! ActivePermission::check('graduation-policy.update')) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }

    private function rulesLabel(GraduationPolicy $policy): string
    {
        $rules = collect([
            $policy->require_active_status ? 'Active status' : null,
            $policy->require_no_financial_hold ? 'No graduation hold' : null,
            $policy->require_no_incomplete_grade ? 'No incomplete grade' : null,
            $policy->require_open_yudisium_period ? 'Open period' : null,
        ])->filter()->implode(', ');

        return $rules ?: '-';
    }
}
