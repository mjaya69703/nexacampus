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
            ->add('installment_label', fn (Payment $model) => $model->installment ? 'Cicilan Ke-'.$model->installment->installment_no : 'Tagihan Utama')
            ->add('amount_label', fn (Payment $model) => $this->money($model->amount))
            ->add('payment_method', fn (Payment $model) => match($model->payment_method) {
                'bank_transfer' => 'Transfer Bank',
                'virtual_account' => 'Virtual Account (VA)',
                'credit_card' => 'Kartu Kredit',
                'cash' => 'Tunai / Kasir',
                default => str($model->payment_method)->replace('_', ' ')->title()->toString()
            })
            ->add('status_badge', fn (Payment $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('paid_at', fn (Payment $model) => $model->paid_at?->format('d M Y, H:i') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Nomor Pembayaran', 'payment_number')->sortable()->searchable(),
            Column::make('Nomor Tagihan', 'invoice_number')->sortable()->searchable(),
            Column::make('Nama Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Target Bayar', 'installment_label'),
            Column::make('Nominal Dibayar', 'amount_label'),
            Column::make('Metode Bayar', 'payment_method', 'payment_method'),
            Column::make('Status Verifikasi', 'status_badge', 'status'),
            Column::make('Waktu Bayar', 'paid_at', 'paid_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('payment_number', 'payment_number')
                ->operators(['contains']),
            Filter::inputText('invoice_number', 'invoice.invoice_number')
                ->operators(['contains']),
            Filter::inputText('student_name', 'studentProfile.user.first_name')
                ->operators(['contains']),
            Filter::inputText('nim', 'studentProfile.nim')
                ->operators(['contains']),
            Filter::select('payment_method', 'payment_method')
                ->dataSource(collect([
                    ['id' => 'bank_transfer', 'name' => 'Transfer Bank'],
                    ['id' => 'virtual_account', 'name' => 'Virtual Account (VA)'],
                    ['id' => 'credit_card', 'name' => 'Kartu Kredit / Debit'],
                    ['id' => 'cash', 'name' => 'Tunai / Kasir Kampus'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'pending', 'name' => 'Menunggu Verifikasi (Pending)'],
                    ['id' => 'verified', 'name' => 'Terverifikasi Sah (Verified)'],
                    ['id' => 'rejected', 'name' => 'Ditolak / Tidak Sah (Rejected)'],
                    ['id' => 'failed', 'name' => 'Gagal Sistem (Failed)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('paid_at', 'paid_at'),
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
                ->slot('<i class="fa fa-eye me-1"></i>Detail & Verifikasi')
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
            'verified' => 'bg-success text-white',
            'pending' => 'bg-warning text-dark',
            'rejected', 'failed' => 'bg-danger text-white',
            default => 'bg-secondary text-white',
        };

        $label = match ($status) {
            'verified' => 'Terverifikasi',
            'pending' => 'Menunggu Review',
            'rejected' => 'Ditolak',
            'failed' => 'Gagal',
            default => str_replace('_', ' ', ucfirst($status)),
        };

        return '<span class="badge '.$class.' rounded-pill px-3 py-1 fs-8">'.$label.'</span>';
    }
}
