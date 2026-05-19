<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\InvoiceSchedule;
use App\Support\ActivePermission;
use App\Support\Financial\InvoiceScheduleService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class InvoiceScheduleTable extends BasePowerGridTable
{
    public string $tableName = 'invoiceScheduleTable';

    protected ?string $bulkActionModel = InvoiceSchedule::class;

    protected ?string $bulkActionPermissionPrefix = 'invoice-schedule';

    protected string $bulkActionItemLabel = 'invoice schedule';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return InvoiceSchedule::query()
            ->with(['academicYear'])
            ->withCount('invoices')
            ->orderByRaw("FIELD(status, 'pending', 'running', 'failed', 'completed', 'cancelled')")
            ->orderBy('publish_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('invoice_kind_label', fn (InvoiceSchedule $model) => str($model->invoice_kind)->title()->toString())
            ->add('target_label', fn (InvoiceSchedule $model) => str($model->generation_mode)->replace('_', ' ')->title()->toString())
            ->add('academic_year_label', fn (InvoiceSchedule $model) => $model->academicYear?->name ?? '-')
            ->add('semester')
            ->add('publish_at_formatted', fn (InvoiceSchedule $model) => $model->publish_at?->format('d M Y H:i'))
            ->add('status_label', fn (InvoiceSchedule $model) => '<span class="badge '.$this->statusClass($model->status).'">'.str($model->status)->title()->toString().'</span>')
            ->add('is_active_label', fn (InvoiceSchedule $model) => $model->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>')
            ->add('created_count')
            ->add('invoices_count');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Kind', 'invoice_kind_label')->sortable(),
            Column::make('Target', 'target_label')->sortable(),
            Column::make('Academic Year', 'academic_year_label'),
            Column::make('Semester', 'semester')->sortable(),
            Column::make('Publish At', 'publish_at_formatted', 'publish_at')->sortable(),
            Column::make('Status', 'status_label')->sortable(),
            Column::make('Active', 'is_active_label')->sortable(),
            Column::make('Created', 'created_count')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect(['pending', 'running', 'completed', 'failed', 'cancelled'])->map(fn ($status) => ['id' => $status, 'name' => str($status)->title()]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.financial.invoice-schedules.edit', ['id' => $rowId]);
    }

    #[On('runSchedule')]
    public function runSchedule($id): void
    {
        abort_unless(ActivePermission::check('invoice-schedule.update'), 403);

        try {
            app(InvoiceScheduleService::class)->run(InvoiceSchedule::findOrFail($id));
            $this->dispatch('alert', type: 'success', message: 'Invoice schedule berhasil dijalankan.');
        } catch (\Throwable $exception) {
            $this->dispatch('alert', type: 'error', message: $exception->getMessage());
        }

        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    public function actions(InvoiceSchedule $row): array
    {
        $actions = [];

        if (ActivePermission::check('invoice-schedule.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if ($row->status === 'pending' && ActivePermission::check('invoice-schedule.update')) {
            $actions[] = Button::add('run')
                ->slot('<i class="fa fa-play"></i>')
                ->class('btn btn-success')
                ->dispatch('runSchedule', ['id' => $row->id]);
        }

        return $actions;
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'completed' => 'bg-success',
            'running' => 'bg-info',
            'failed' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            default => 'bg-warning',
        };
    }
}
