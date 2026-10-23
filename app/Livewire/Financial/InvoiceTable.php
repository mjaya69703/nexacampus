<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Financial\StudentInvoice;
use App\Support\ActivePermission;
use App\Support\Financial\InvoicePublishingService;
use App\Support\Financial\InvoiceStatusService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class InvoiceTable extends BasePowerGridTable
{
    public string $tableName = 'invoiceTable';

    protected ?string $bulkActionModel = StudentInvoice::class;

    protected ?string $bulkActionPermissionPrefix = 'student-invoice';

    protected string $bulkActionItemLabel = 'tagihan mahasiswa';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        StudentInvoice::query()
            ->whereIn('status', ['issued', 'partially_paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->get()
            ->each(fn (StudentInvoice $invoice) => app(InvoiceStatusService::class)->refresh($invoice));

        return StudentInvoice::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'academicYear' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('invoice_number')
            ->add('invoice_type', fn (StudentInvoice $model) => match($model->invoice_type) {
                'tuition' => 'SPP / Kuliah',
                'registration' => 'Pendaftaran',
                'exam' => 'Ujian Akhir',
                default => ucfirst(str_replace('_', ' ', $model->invoice_type))
            })
            ->add('student_name', fn (StudentInvoice $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentInvoice $model) => $model->studentProfile?->nim ?? '-')
            ->add('study_program', fn (StudentInvoice $model) => $model->studentProfile?->studyProgram?->name ?? '-')
            ->add('academic_year', fn (StudentInvoice $model) => $model->academicYear?->name ?? '-')
            ->add('semester')
            ->add('total_amount_label', fn (StudentInvoice $model) => $this->money($model->total_amount))
            ->add('outstanding_amount_label', fn (StudentInvoice $model) => $this->money($model->outstanding_amount))
            ->add('status_badge', fn (StudentInvoice $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('due_date', fn (StudentInvoice $model) => $model->due_date?->format('d M Y') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Nomor Tagihan', 'invoice_number')->sortable()->searchable(),
            Column::make('Jenis', 'invoice_type', 'invoice_type')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program')->sortable()->searchable()->hidden(),
            Column::make('Tahun Akademik', 'academic_year')->sortable()->searchable(),
            Column::make('Semester', 'semester')->sortable(),
            Column::make('Total Tagihan', 'total_amount_label'),
            Column::make('Sisa Tunggakan', 'outstanding_amount_label'),
            Column::make('Status Bayar', 'status_badge', 'status'),
            Column::make('Jatuh Tempo', 'due_date', 'due_date')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('invoice_number', 'invoice_number')
                ->operators(['contains']),
            Filter::inputText('student_name', 'studentProfile.user.first_name')
                ->operators(['contains']),
            Filter::inputText('nim', 'studentProfile.nim')
                ->operators(['contains']),
            Filter::select('invoice_type', 'invoice_type')
                ->dataSource(collect([
                    ['id' => 'tuition', 'name' => 'SPP / Uang Kuliah'],
                    ['id' => 'registration', 'name' => 'Biaya Pendaftaran'],
                    ['id' => 'exam', 'name' => 'Biaya Ujian Akhir'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('academic_year', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft Belum Terbit (Draft)'],
                    ['id' => 'issued', 'name' => 'Aktif / Belum Bayar (Issued)'],
                    ['id' => 'partially_paid', 'name' => 'Cicilan / Bayar Sebagian (Partially Paid)'],
                    ['id' => 'paid', 'name' => 'Lunas (Paid)'],
                    ['id' => 'overdue', 'name' => 'Menunggak / Lewat Tempo (Overdue)'],
                    ['id' => 'cancelled', 'name' => 'Dibatalkan (Cancelled)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('due_date', 'due_date'),
        ];
    }

    #[On('issue')]
    public function issue($id): void
    {
        if (! ActivePermission::check('student-invoice.update')) {
            return;
        }

        try {
            app(InvoicePublishingService::class)->issue(StudentInvoice::findOrFail($id), auth()->id());
            session()->flash('success', 'Tagihan berhasil diterbitkan ke mahasiswa.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->dispatch('pg:eventRefresh-invoiceTable');
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.financial.student-invoices.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.financial.student-invoices.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $this->js('
            Swal.fire({
                title: "Batalkan tagihan ini?",
                text: "Tagihan akan ditandai cancelled dan tidak dianggap sebagai tunggakan mahasiswa.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, Batalkan",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("cancelInvoice", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('cancelInvoice')]
    public function cancelInvoice($id = null): void
    {
        if (! ActivePermission::check('student-invoice.delete')) {
            return;
        }

        StudentInvoice::findOrFail($id)->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Tagihan berhasil dibatalkan.');
        $this->dispatch('pg:eventRefresh-invoiceTable');
    }

    public function actions(StudentInvoice $row): array
    {
        $actions = [];

        if (ActivePermission::check('student-invoice.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye me-1"></i>Detail')
                ->class('btn btn-sm btn-outline-info rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 me-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if ($row->status === 'draft' && ActivePermission::check('student-invoice.update')) {
            $actions[] = Button::add('issue')
                ->slot('<i class="fa fa-paper-plane me-1"></i>Terbit')
                ->class('btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 me-1')
                ->dispatch('issue', ['id' => $row->id]);
        }

        if ($row->isEditable() && ActivePermission::check('student-invoice.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit me-1"></i>Edit')
                ->class('btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 me-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if ($row->status !== 'cancelled' && ActivePermission::check('student-invoice.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-ban me-1"></i>Batal')
                ->class('btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'paid' => 'bg-success text-white',
            'partially_paid' => 'bg-info text-white',
            'overdue' => 'bg-danger text-white',
            'cancelled' => 'bg-secondary text-white',
            'issued' => 'bg-primary text-white',
            'draft' => 'bg-light text-dark',
            default => 'bg-warning text-dark',
        };

        $label = match ($status) {
            'paid' => 'Lunas',
            'partially_paid' => 'Cicilan',
            'overdue' => 'Jatuh Tempo',
            'cancelled' => 'Dibatalkan',
            'issued' => 'Aktif',
            'draft' => 'Draft',
            default => str_replace('_', ' ', ucfirst($status)),
        };

        return '<span class="badge '.$class.' rounded-pill px-3 py-1 fs-8">'.$label.'</span>';
    }
}
