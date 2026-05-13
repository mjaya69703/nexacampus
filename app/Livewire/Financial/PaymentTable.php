<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\Payment;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PaymentTable extends BasePowerGridTable
{
    public string $tableName = 'paymentTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return Payment::query()
            ->with(['invoice', 'studentProfile.user', 'studentProfile.studyProgram', 'installment'])
            ->orderByRaw("FIELD(status, 'pending', 'verified', 'rejected', 'failed')")
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'invoice' => ['invoice_number'],
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('payment_number')
            ->add('invoice_number', fn (Payment $model) => $model->invoice?->invoice_number ?? '-')
            ->add('student_name', fn (Payment $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (Payment $model) => $model->studentProfile?->nim ?? '-')
            ->add('installment_label', fn (Payment $model) => $model->installment ? 'Cicilan '.$model->installment->installment_no : 'Invoice')
            ->add('amount_label', fn (Payment $model) => $this->money($model->amount))
            ->add('payment_method', fn (Payment $model) => str($model->payment_method)->replace('_', ' ')->title()->toString())
            ->add('status_badge', fn (Payment $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('paid_at', fn (Payment $model) => $model->paid_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Payment', 'payment_number')->sortable()->searchable(),
            Column::make('Invoice', 'invoice_number')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Target', 'installment_label'),
            Column::make('Amount', 'amount_label'),
            Column::make('Method', 'payment_method'),
            Column::make('Status', 'status_badge'),
            Column::make('Paid At', 'paid_at')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'pending', 'name' => 'Pending'],
                    ['id' => 'verified', 'name' => 'Verified'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'failed', 'name' => 'Failed'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.financial.payments.show', ['id' => $rowId]);
    }

    public function actions(Payment $row): array
    {
        if (! ActivePermission::check('payment.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'verified' => 'bg-success',
            'pending' => 'bg-warning text-dark',
            'rejected', 'failed' => 'bg-danger',
            default => 'bg-secondary',
        };

        return '<span class="badge '.$class.'">'.str_replace('_', ' ', ucfirst($status)).'</span>';
    }
}
