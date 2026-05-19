<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\InvoiceAdjustment;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class InvoiceAdjustmentTable extends BasePowerGridTable
{
    public string $tableName = 'invoiceAdjustmentTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return InvoiceAdjustment::query()
            ->with(['invoice.studentProfile.user', 'createdBy'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'invoice' => ['invoice_number'],
            'invoice.studentProfile.user' => ['first_name', 'last_name', 'email'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('invoice_number', fn (InvoiceAdjustment $model) => $model->invoice?->invoice_number ?? '-')
            ->add('student_name', fn (InvoiceAdjustment $model) => $model->invoice?->studentProfile?->user?->name ?? '-')
            ->add('type_label', fn (InvoiceAdjustment $model) => str($model->adjustment_type)->replace('_', ' ')->title()->toString())
            ->add('amount_label', fn (InvoiceAdjustment $model) => $this->money($model->amount))
            ->add('created_by_name', fn (InvoiceAdjustment $model) => $model->createdBy?->name ?? '-')
            ->add('created_at_label', fn (InvoiceAdjustment $model) => $model->created_at?->format('d M Y H:i') ?? '-')
            ->add('adjustment_type');
    }

    public function columns(): array
    {
        return [
            Column::make('Invoice', 'invoice_number')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('Type', 'type_label')->sortable(),
            Column::make('Amount', 'amount_label'),
            Column::make('Created By', 'created_by_name'),
            Column::make('Created At', 'created_at_label')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('adjustment_type', 'adjustment_type')
                ->dataSource(collect([
                    ['id' => 'correction', 'name' => 'Correction'],
                    ['id' => 'discount', 'name' => 'Discount'],
                    ['id' => 'scholarship', 'name' => 'Scholarship'],
                    ['id' => 'waiver', 'name' => 'Waiver'],
                    ['id' => 'penalty', 'name' => 'Penalty'],
                    ['id' => 'write_off', 'name' => 'Write Off'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('showInvoice')]
    public function showInvoice($rowId): void
    {
        $adjustment = InvoiceAdjustment::findOrFail($rowId);
        $this->redirectRoute('admin.financial.student-invoices.show', ['id' => $adjustment->student_invoice_id]);
    }

    public function actions(InvoiceAdjustment $row): array
    {
        if (! ActivePermission::check('student-invoice.view')) {
            return [];
        }

        return [
            Button::add('showInvoice')
                ->slot('<i class="fa fa-file-invoice-dollar"></i>')
                ->class('btn btn-primary')
                ->dispatch('showInvoice', ['rowId' => $row->id]),
        ];
    }

    private function money(float|string|null $amount): string
    {
        $amount = (float) $amount;
        $prefix = $amount < 0 ? '-Rp ' : 'Rp ';

        return $prefix.number_format(abs($amount), 0, ',', '.');
    }
}
