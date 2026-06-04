<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EdomQuestion;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EdomQuestionTable extends BasePowerGridTable
{
    public string $tableName = 'edomQuestionTable';

    protected ?string $bulkActionModel = EdomQuestion::class;

    protected ?string $bulkActionPermissionPrefix = 'edom-question';

    protected string $bulkActionItemLabel = 'pertanyaan EDOM';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EdomQuestion::query()->orderBy('category')->orderBy('sort_order');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('category')
            ->add('question_text')
            ->add('answer_type')
            ->add('sort_order')
            ->add('active_badge', fn (EdomQuestion $model) => $model->is_active ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Kategori', 'category')->sortable()->searchable(),
            Column::make('Pertanyaan', 'question_text')->searchable(),
            Column::make('Tipe', 'answer_type')->sortable(),
            Column::make('Urutan', 'sort_order')->sortable(),
            Column::make('Status', 'active_badge', 'is_active')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('answer_type', 'answer_type')
                ->dataSource(collect([['id' => 'scale', 'name' => 'Skala'], ['id' => 'text', 'name' => 'Teks']]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.edom-questions.edit', ['id' => $rowId]);
    }

    public function actions(EdomQuestion $row): array
    {
        return ActivePermission::check('edom-question.update')
            ? [Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id])]
            : [];
    }
}
