<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\LecturerWorkloadRule;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LecturerWorkloadRuleTable extends BasePowerGridTable
{
    public string $tableName = 'lecturerWorkloadRuleTable';

    protected ?string $bulkActionModel = LecturerWorkloadRule::class;

    protected ?string $bulkActionPermissionPrefix = 'lecturer-workload-rule';

    protected string $bulkActionItemLabel = 'aturan SKS';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return LecturerWorkloadRule::query()->orderBy('category')->orderBy('source_code');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('category')
            ->add('source_code')
            ->add('name')
            ->add('sks_value')
            ->add('maximum_sks', fn (LecturerWorkloadRule $model) => $model->maximum_sks ?: '-')
            ->add('active_badge', fn (LecturerWorkloadRule $model) => $model->is_active ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Kategori', 'category')->sortable()->searchable(),
            Column::make('Kode Sumber', 'source_code')->sortable()->searchable(),
            Column::make('Nama', 'name')->searchable(),
            Column::make('SKS', 'sks_value')->sortable(),
            Column::make('Maksimum', 'maximum_sks'),
            Column::make('Status', 'active_badge', 'is_active')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('category', 'category')
                ->dataSource(collect([
                    ['id' => 'teaching', 'name' => 'Mengajar'],
                    ['id' => 'structural', 'name' => 'Jabatan'],
                    ['id' => 'tridharma', 'name' => 'Tridharma'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-workload-rules.edit', ['id' => $rowId]);
    }

    public function actions(LecturerWorkloadRule $row): array
    {
        return ActivePermission::check('lecturer-workload-rule.update')
            ? [Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id])]
            : [];
    }
}
