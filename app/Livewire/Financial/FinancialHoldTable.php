<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\FinancialHold;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class FinancialHoldTable extends BasePowerGridTable
{
    public string $tableName = 'financialHoldTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return FinancialHold::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'invoice', 'policy'])
            ->orderByRaw("FIELD(status, 'active', 'waived', 'released')")
            ->orderByDesc('blocked_at')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'invoice' => ['invoice_number'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (FinancialHold $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (FinancialHold $model) => $model->studentProfile?->nim ?? '-')
            ->add('invoice_number', fn (FinancialHold $model) => $model->invoice?->invoice_number ?? '-')
            ->add('invoice_type_label', fn (FinancialHold $model) => str($model->invoice?->invoice_type ?? '-')->replace('_', ' ')->title()->toString())
            ->add('hold_type_label', fn (FinancialHold $model) => str($model->hold_type)->replace('_', ' ')->title()->toString())
            ->add('blocked_at_label', fn (FinancialHold $model) => $model->blocked_at?->format('d M Y') ?? '-')
            ->add('waived_until_label', fn (FinancialHold $model) => $model->waived_until?->format('d M Y') ?? '-')
            ->add('status_badge', fn (FinancialHold $model) => $this->statusBadge($model))
            ->add('status');
    }

    public function columns(): array
    {
        return [
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Invoice', 'invoice_number')->sortable()->searchable(),
            Column::make('Type', 'invoice_type_label'),
            Column::make('Hold', 'hold_type_label'),
            Column::make('Blocked At', 'blocked_at_label', 'blocked_at')->sortable(),
            Column::make('Waived Until', 'waived_until_label', 'waived_until')->sortable(),
            Column::make('Status', 'status_badge'),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'waived', 'name' => 'Waived'],
                    ['id' => 'released', 'name' => 'Released'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('hold_type', 'hold_type')
                ->dataSource(collect([
                    ['id' => 'registration', 'name' => 'Registration'],
                    ['id' => 'study_plan', 'name' => 'Study Plan'],
                    ['id' => 'exam_card', 'name' => 'Exam Card'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.financial.financial-holds.show', ['id' => $rowId]);
    }

    public function actions(FinancialHold $row): array
    {
        if (! ActivePermission::check('financial-hold.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(FinancialHold $hold): string
    {
        $class = match ($hold->status) {
            'active' => $hold->isBlocking() ? 'bg-danger' : 'bg-warning text-dark',
            'waived' => 'bg-info',
            'released' => 'bg-success',
            default => 'bg-secondary',
        };

        $label = $hold->status === 'active' && $hold->isBlocking()
            ? 'Blocking'
            : str($hold->status)->title();

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }
}
