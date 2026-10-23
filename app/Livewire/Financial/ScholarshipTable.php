<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\Scholarship;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ScholarshipTable extends BasePowerGridTable
{
    public string $tableName = 'scholarshipTable';

    protected ?string $bulkActionModel = Scholarship::class;

    protected ?string $bulkActionPermissionPrefix = 'scholarship';

    protected string $bulkActionItemLabel = 'program beasiswa';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return Scholarship::query()->withCount('assignments')->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('type_label', fn (Scholarship $model) => match($model->type) {
                'merit' => 'Prestasi Akademik (Merit)',
                'need_based' => 'Bantuan Ekonomi / Kurang Mampu',
                'athletic' => 'Prestasi Olahraga / Seni',
                'partner' => 'Kerjasama Instansi Mitra',
                default => str($model->type)->replace('_', ' ')->title()->toString()
            })
            ->add('discount_label', fn (Scholarship $model) => $model->discount_type === 'fixed'
                ? $this->money($model->fixed_amount)
                : number_format((float) $model->discount_percentage, 0).'% dari SPP')
            ->add('duration_semesters', fn (Scholarship $model) => $model->duration_semesters . ' Semester')
            ->add('assignments_count', fn (Scholarship $model) => $model->assignments_count . ' Penerima')
            ->add('is_active_label', fn (Scholarship $model) => $model->is_active
                ? '<span class="badge bg-success text-white rounded-pill px-3 py-1 fs-8">Aktif</span>'
                : '<span class="badge bg-secondary text-white rounded-pill px-3 py-1 fs-8">Nonaktif</span>')
            ->add('is_active');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Program Beasiswa', 'name')->sortable()->searchable(),
            Column::make('Kategori / Jenis', 'type_label', 'type')->sortable(),
            Column::make('Potongan Biaya', 'discount_label'),
            Column::make('Durasi', 'duration_semesters')->sortable(),
            Column::make('Total Penerima', 'assignments_count')->sortable(),
            Column::make('Status', 'is_active_label', 'is_active')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name', 'name')
                ->operators(['contains']),
            Filter::select('type_label', 'type')
                ->dataSource(collect([
                    ['id' => 'merit', 'name' => 'Prestasi Akademik (Merit)'],
                    ['id' => 'need_based', 'name' => 'Bantuan Ekonomi (Need Based)'],
                    ['id' => 'athletic', 'name' => 'Prestasi Non-Akademik (Athletic)'],
                    ['id' => 'partner', 'name' => 'Kerjasama Mitra (Partner)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('is_active_label', 'is_active')
                ->dataSource(collect([
                    ['id' => 1, 'name' => 'Aktif'],
                    ['id' => 0, 'name' => 'Nonaktif'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.financial.scholarships.edit', ['id' => $rowId]);
    }

    public function actions(Scholarship $row): array
    {
        if (! ActivePermission::check('scholarship.update')) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('<i class="fa fa-edit me-1"></i>Edit Program')
                ->class('btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
