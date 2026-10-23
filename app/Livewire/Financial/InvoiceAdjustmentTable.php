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
            ->add('type_label', fn (InvoiceAdjustment $model) => match($model->adjustment_type) {
                'correction' => 'Koreksi Tagihan',
                'discount' => 'Potongan (Discount)',
                'scholarship' => 'Potongan Beasiswa',
                'waiver' => 'Pembebasan (Waiver)',
                'penalty' => 'Denda / Sanksi',
                'write_off' => 'Penghapusan (Write Off)',
                default => str($model->adjustment_type)->replace('_', ' ')->title()->toString()
            })
            ->add('amount_label', fn (InvoiceAdjustment $model) => $this->money($model->amount))
            ->add('created_by_name', fn (InvoiceAdjustment $model) => $model->createdBy?->name ?? '-')
            ->add('created_at_label', fn (InvoiceAdjustment $model) => $model->created_at?->format('d M Y, H:i') ?? '-')
            ->add('adjustment_type');
    }

    public function columns(): array
    {
        return [
            Column::make('Nomor Tagihan', 'invoice_number')->sortable()->searchable(),
            Column::make('Nama Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('Jenis Penyesuaian', 'type_label', 'adjustment_type')->sortable(),
            Column::make('Nominal Penyesuaian', 'amount_label'),
            Column::make('Oleh Petugas', 'created_by_name'),
            Column::make('Tanggal Dicatat', 'created_at_label', 'created_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('invoice_number', 'invoice.invoice_number')
                ->operators(['contains']),
            Filter::inputText('student_name', 'invoice.studentProfile.user.first_name')
                ->operators(['contains']),
            Filter::select('type_label', 'adjustment_type')
                ->dataSource(collect([
                    ['id' => 'correction', 'name' => 'Koreksi Tagihan (Correction)'],
                    ['id' => 'discount', 'name' => 'Potongan Diskon (Discount)'],
                    ['id' => 'scholarship', 'name' => 'Potongan Beasiswa (Scholarship)'],
                    ['id' => 'waiver', 'name' => 'Pembebasan Biaya (Waiver)'],
                    ['id' => 'penalty', 'name' => 'Denda Keterlambatan (Penalty)'],
                    ['id' => 'write_off', 'name' => 'Penghapusan Piutang (Write Off)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('created_at_label', 'created_at'),
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
                ->slot('<i class="fa fa-file-invoice-dollar me-1"></i> Lihat Tagihan')
                ->class('btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold')
                ->dispatch('showInvoice', ['rowId' => $row->id]),
        ];
    }

    private function money(float|string|null $amount): string
    {
        $amount = (float) $amount;
        $prefix = $amount < 0 ? '-Rp ' : 'Rp ';

        return $prefix . number_format(abs($amount), 0, ',', '.');
    }
}
