<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class InstallmentRequestTable extends BasePowerGridTable
{
    public string $tableName = 'installmentRequestTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return InvoiceInstallmentRequest::query()
            ->with(['invoice', 'studentProfile.user', 'studentProfile.studyProgram'])
            ->orderByRaw("FIELD(status, 'submitted', 'approved', 'rejected', 'cancelled')")
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
            ->add('invoice_number', fn (InvoiceInstallmentRequest $model) => $model->invoice?->invoice_number ?? '-')
            ->add('student_name', fn (InvoiceInstallmentRequest $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (InvoiceInstallmentRequest $model) => $model->studentProfile?->nim ?? '-')
            ->add('requested_tenor')
            ->add('simulated_total_label', fn (InvoiceInstallmentRequest $model) => $this->money($model->simulated_total_amount))
            ->add('status_badge', fn (InvoiceInstallmentRequest $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('created_at', fn (InvoiceInstallmentRequest $model) => $model->created_at?->format('d M Y H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Invoice', 'invoice_number')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Tenor', 'requested_tenor')->sortable(),
            Column::make('Simulated Total', 'simulated_total_label'),
            Column::make('Status', 'status_badge'),
            Column::make('Submitted At', 'created_at')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.financial.installment-requests.show', ['id' => $rowId]);
    }

    public function actions(InvoiceInstallmentRequest $row): array
    {
        if (! ActivePermission::check('installment-request.view')) {
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
            'approved' => 'bg-success',
            'submitted', 'in_approval' => 'bg-warning text-dark',
            'rejected' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            default => 'bg-light text-dark',
        };

        return '<span class="badge '.$class.'">'.str_replace('_', ' ', ucfirst($status)).'</span>';
    }
}
