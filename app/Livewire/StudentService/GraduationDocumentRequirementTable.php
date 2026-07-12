<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationDocumentRequirement;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class GraduationDocumentRequirementTable extends BasePowerGridTable
{
    public string $tableName = 'graduationDocumentRequirementTable';

    protected ?string $bulkActionModel = GraduationDocumentRequirement::class;

    protected ?string $bulkActionPermissionPrefix = 'graduation-document-requirement';

    protected string $bulkActionItemLabel = 'graduation document requirement';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return GraduationDocumentRequirement::query()
            ->with('studyProgram')
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('label')
            ->add('document_type')
            ->add('scope', fn (GraduationDocumentRequirement $model) => $model->studyProgram?->name ?? 'Global')
            ->add('required_label', fn (GraduationDocumentRequirement $model) => $model->is_required ? 'Required' : 'Optional')
            ->add('allowed_extensions')
            ->add('max_size_label', fn (GraduationDocumentRequirement $model) => $model->max_size_kb ? number_format($model->max_size_kb).' KB' : '-')
            ->add('sort_order')
            ->add('is_active');
    }

    public function columns(): array
    {
        return [
            Column::make('Label', 'label')->sortable()->searchable(),
            Column::make('Type', 'document_type')->sortable()->searchable(),
            Column::make('Scope', 'scope', 'study_program_id')->sortable()->searchable(),
            Column::make('Required', 'required_label', 'is_required'),
            Column::make('Extensions', 'allowed_extensions')->searchable(),
            Column::make('Max Size', 'max_size_label'),
            Column::make('Order', 'sort_order')->sortable(),
            Column::make('Active', 'is_active')
                ->toggleable(ActivePermission::check('graduation-document-requirement.update'), 'Active', 'Inactive')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('label')->placeholder('Cari nama dokumen...'),
            Filter::select('scope', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::select('document_type', 'document_type')
                ->dataSource(collect([
                    ['id' => 'file', 'name' => 'File Upload'],
                    ['id' => 'text', 'name' => 'Text Input'],
                    ['id' => 'url', 'name' => 'URL Link'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('document_type', $value)),
            Filter::boolean('required_label', 'is_required'),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        abort_unless(ActivePermission::check('graduation-document-requirement.update'), 403);

        GraduationDocumentRequirement::whereKey($id)->update([
            'is_active' => (bool) $value,
        ]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.student-services.graduation-document-requirements.edit', ['id' => $rowId]);
    }

    public function actions(GraduationDocumentRequirement $row): array
    {
        if (! ActivePermission::check('graduation-document-requirement.update')) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }
}
