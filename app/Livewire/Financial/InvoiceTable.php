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

    protected string $bulkActionItemLabel = 'student invoice';

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
            ->add('invoice_type', fn (StudentInvoice $model) => ucfirst(str_replace('_', ' ', $model->invoice_type)))
            ->add('student_name', fn (StudentInvoice $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentInvoice $model) => $model->studentProfile?->nim ?? '-')
            ->add('study_program', fn (StudentInvoice $model) => $model->studentProfile?->studyProgram?->name ?? '-')
            ->add('academic_year', fn (StudentInvoice $model) => $model->academicYear?->name ?? '-')
            ->add('semester')
            ->add('total_amount_label', fn (StudentInvoice $model) => $this->money($model->total_amount))
            ->add('outstanding_amount_label', fn (StudentInvoice $model) => $this->money($model->outstanding_amount))
            ->add('status_badge', fn (StudentInvoice $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('due_date', fn (StudentInvoice $model) => $model->due_date?->format('d M Y'));
    }

    public function columns(): array
    {
        return [
            Column::make('Invoice', 'invoice_number')->sortable()->searchable(),
            Column::make('Type', 'invoice_type')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program')->sortable()->searchable()->hidden(),
            Column::make('Academic Year', 'academic_year')->sortable()->searchable(),
            Column::make('Semester', 'semester')->sortable(),
            Column::make('Total', 'total_amount_label'),
            Column::make('Outstanding', 'outstanding_amount_label'),
            Column::make('Status', 'status_badge'),
            Column::make('Due Date', 'due_date')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('academic_year_id', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'issued', 'name' => 'Issued'],
                    ['id' => 'partially_paid', 'name' => 'Partially Paid'],
                    ['id' => 'paid', 'name' => 'Paid'],
                    ['id' => 'overdue', 'name' => 'Overdue'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('issue')]
    public function issue($id): void
    {
        abort_unless(ActivePermission::check('student-invoice.update'), 403);

        try {
            app(InvoicePublishingService::class)->issue(StudentInvoice::findOrFail($id), auth()->id());
            session()->flash('success', 'Invoice berhasil diterbitkan.');
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
                title: "Batalkan invoice?",
                text: "Invoice akan ditandai cancelled dan tidak dianggap outstanding.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya batalkan",
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
        abort_unless(ActivePermission::check('student-invoice.delete'), 403);

        StudentInvoice::findOrFail($id)->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Invoice berhasil dibatalkan.');
        $this->dispatch('pg:eventRefresh-invoiceTable');
    }

    public function actions(StudentInvoice $row): array
    {
        $actions = [];

        if (ActivePermission::check('student-invoice.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if ($row->status === 'draft' && ActivePermission::check('student-invoice.update')) {
            $actions[] = Button::add('issue')
                ->slot('<i class="fa fa-paper-plane"></i>')
                ->class('btn btn-success')
                ->dispatch('issue', ['id' => $row->id]);
        }

        if ($row->isEditable() && ActivePermission::check('student-invoice.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-warning')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if ($row->status !== 'cancelled' && ActivePermission::check('student-invoice.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-ban"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'paid' => 'bg-success',
            'partially_paid' => 'bg-info',
            'overdue' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'issued' => 'bg-primary',
            'draft' => 'bg-light text-dark',
            default => 'bg-warning text-dark',
        };

        return '<span class="badge '.$class.'">'.str_replace('_', ' ', ucfirst($status)).'</span>';
    }
}
