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

    protected string $bulkActionItemLabel = 'jadwal penerbitan tagihan';

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
            ->add('invoice_kind_label', fn (InvoiceSchedule $model) => match($model->invoice_kind) {
                'tuition' => 'SPP / Uang Kuliah',
                'registration' => 'Biaya Pendaftaran',
                'exam' => 'Biaya Ujian',
                default => str($model->invoice_kind)->title()->toString()
            })
            ->add('target_label', fn (InvoiceSchedule $model) => str($model->generation_mode)->replace('_', ' ')->title()->toString())
            ->add('academic_year_label', fn (InvoiceSchedule $model) => $model->academicYear?->name ?? '-')
            ->add('semester')
            ->add('publish_at_formatted', fn (InvoiceSchedule $model) => $model->publish_at?->format('d M Y, H:i') ?? '-')
            ->add('status_label', fn (InvoiceSchedule $model) => '<span class="badge '.$this->statusClass($model->status).' rounded-pill px-3 py-1 fs-8">'.$this->statusText($model->status).'</span>')
            ->add('is_active_label', fn (InvoiceSchedule $model) => $model->is_active ? '<span class="badge bg-success text-white rounded-pill px-3 py-1 fs-8">Aktif</span>' : '<span class="badge bg-secondary text-white rounded-pill px-3 py-1 fs-8">Nonaktif</span>')
            ->add('created_count')
            ->add('invoices_count');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Jadwal Tagihan', 'name')->sortable()->searchable(),
            Column::make('Jenis Biaya', 'invoice_kind_label', 'invoice_kind')->sortable(),
            Column::make('Target Penerbitan', 'target_label', 'generation_mode')->sortable(),
            Column::make('Tahun Akademik', 'academic_year_label'),
            Column::make('Semester', 'semester')->sortable(),
            Column::make('Jadwal Publish', 'publish_at_formatted', 'publish_at')->sortable(),
            Column::make('Status Eksekusi', 'status_label', 'status')->sortable(),
            Column::make('Status Aktif', 'is_active_label', 'is_active')->sortable(),
            Column::make('Berhasil Terbit', 'created_count')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name', 'name')
                ->operators(['contains']),
            Filter::select('invoice_kind_label', 'invoice_kind')
                ->dataSource(collect([
                    ['id' => 'tuition', 'name' => 'SPP / Uang Kuliah'],
                    ['id' => 'registration', 'name' => 'Biaya Pendaftaran'],
                    ['id' => 'exam', 'name' => 'Biaya Ujian Akhir'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('target_label', 'generation_mode')
                ->dataSource(collect([
                    ['id' => 'all_active', 'name' => 'Semua Mahasiswa Aktif'],
                    ['id' => 'new_students', 'name' => 'Mahasiswa Baru'],
                    ['id' => 'custom_filter', 'name' => 'Filter Khusus / Prodi'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status_label', 'status')
                ->dataSource(collect([
                    ['id' => 'pending', 'name' => 'Menunggu Jadwal (Pending)'],
                    ['id' => 'running', 'name' => 'Sedang Diproses (Running)'],
                    ['id' => 'completed', 'name' => 'Selesai Terbit (Completed)'],
                    ['id' => 'failed', 'name' => 'Gagal Eksekusi (Failed)'],
                    ['id' => 'cancelled', 'name' => 'Dibatalkan (Cancelled)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active_label', 'is_active')
                ->label('Aktif', 'Nonaktif'),
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
        if (! ActivePermission::check('invoice-schedule.update')) {
            return;
        }

        try {
            app(InvoiceScheduleService::class)->run(InvoiceSchedule::findOrFail($id));
            $this->dispatch('alert', type: 'success', message: 'Jadwal penerbitan tagihan berhasil dijalankan.');
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
                ->slot('<i class="fa fa-edit me-1"></i>Edit')
                ->class('btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 me-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if ($row->status === 'pending' && ActivePermission::check('invoice-schedule.update')) {
            $actions[] = Button::add('run')
                ->slot('<i class="fa fa-play me-1"></i>Eksekusi Sekarang')
                ->class('btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1')
                ->dispatch('runSchedule', ['id' => $row->id]);
        }

        return $actions;
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'completed' => 'bg-success text-white',
            'running' => 'bg-info text-white',
            'failed' => 'bg-danger text-white',
            'cancelled' => 'bg-secondary text-white',
            default => 'bg-warning text-dark',
        };
    }

    private function statusText(string $status): string
    {
        return match ($status) {
            'completed' => 'Selesai Terbit',
            'running' => 'Sedang Diproses',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
            'pending' => 'Menunggu Jadwal',
            default => str($status)->title()->toString(),
        };
    }
}
