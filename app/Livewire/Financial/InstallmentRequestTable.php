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
            ->add('requested_tenor', fn (InvoiceInstallmentRequest $model) => $model->requested_tenor . ' Kali Bayar')
            ->add('simulated_total_label', fn (InvoiceInstallmentRequest $model) => $this->money($model->simulated_total_amount))
            ->add('status_badge', fn (InvoiceInstallmentRequest $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('created_at', fn (InvoiceInstallmentRequest $model) => $model->created_at?->format('d M Y, H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Nomor Tagihan (Invoice)', 'invoice_number')->sortable()->searchable(),
            Column::make('Nama Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Pilihan Tenor', 'requested_tenor')->sortable(),
            Column::make('Total Simulasi Cicilan', 'simulated_total_label'),
            Column::make('Status Pengajuan', 'status_badge', 'status')->sortable(),
            Column::make('Tanggal Pengajuan', 'created_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('invoice_number', 'invoice.invoice_number')
                ->operators(['contains']),
            Filter::inputText('student_name', 'studentProfile.user.first_name')
                ->operators(['contains']),
            Filter::inputText('nim', 'studentProfile.nim')
                ->operators(['contains']),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Menunggu Persetujuan (Submitted)'],
                    ['id' => 'approved', 'name' => 'Disetujui (Approved)'],
                    ['id' => 'rejected', 'name' => 'Ditolak (Rejected)'],
                    ['id' => 'cancelled', 'name' => 'Dibatalkan (Cancelled)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('created_at', 'created_at'),
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
                ->slot('<i class="fa fa-eye me-1"></i>Tinjau')
                ->class('btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'approved' => 'bg-success text-white',
            'submitted', 'in_approval' => 'bg-warning text-dark',
            'rejected' => 'bg-danger text-white',
            'cancelled' => 'bg-secondary text-white',
            default => 'bg-light text-dark',
        };

        $label = match ($status) {
            'approved' => 'Disetujui',
            'submitted' => 'Menunggu Review',
            'in_approval' => 'Proses Approval',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => str_replace('_', ' ', ucfirst($status)),
        };

        return '<span class="badge '.$class.' rounded-pill px-3 py-1 fs-8">'.$label.'</span>';
    }
}
