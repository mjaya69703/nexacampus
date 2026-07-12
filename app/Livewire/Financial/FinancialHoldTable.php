<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Financial\FinancialHold;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class FinancialHoldTable extends BasePowerGridTable
{
    public string $tableName = 'financialHoldTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return FinancialHold::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'invoice', 'policy'])
            ->orderByRaw("FIELD(status, 'active', 'waived', 'released')")
            ->orderByDesc('blocked_at')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'invoice' => ['invoice_number'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (FinancialHold $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (FinancialHold $model) => $model->studentProfile?->nim ?? '-')
            ->add('invoice_number', fn (FinancialHold $model) => $model->invoice?->invoice_number ?? '-')
            ->add('invoice_type_label', fn (FinancialHold $model) => str($model->invoice?->invoice_type ?? '-')->replace('_', ' ')->title()->toString())
            ->add('hold_type_label', fn (FinancialHold $model) => str($model->hold_type)->replace('_', ' ')->title()->toString())
            ->add('blocked_at_label', fn (FinancialHold $model) => $model->blocked_at?->format('d M Y') ?? '-')
            ->add('waived_until_label', fn (FinancialHold $model) => $model->waived_until?->format('d M Y') ?? '-')
            ->add('status_badge', fn (FinancialHold $model) => $this->statusBadge($model))
            ->add('status');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Nomor Tagihan', 'invoice_number')->sortable()->searchable(),
            Column::make('Jenis Tagihan', 'invoice_type_label'),
            Column::make('Target Pemblokiran', 'hold_type_label', 'hold_type')->sortable(),
            Column::make('Tanggal Diblokir', 'blocked_at_label', 'blocked_at')->sortable(),
            Column::make('Penangguhan (Waived)', 'waived_until_label', 'waived_until')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('student_name', 'studentProfile.user.first_name')
                ->operators(['contains']),
            Filter::inputText('nim', 'studentProfile.nim')
                ->operators(['contains']),
            Filter::inputText('invoice_number', 'invoice.invoice_number')
                ->operators(['contains']),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'active', 'name' => 'Aktif Diblokir (Active)'],
                    ['id' => 'waived', 'name' => 'Ditangguhkan Sementara (Waived)'],
                    ['id' => 'released', 'name' => 'Dilepas / Bebas (Released)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('hold_type_label', 'hold_type')
                ->dataSource(collect([
                    ['id' => 'registration', 'name' => 'Pendaftaran Ulang / KRS'],
                    ['id' => 'study_plan', 'name' => 'Rencana Studi (Study Plan)'],
                    ['id' => 'exam_card', 'name' => 'Kartu Ujian (Exam Card)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('blocked_at_label', 'blocked_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.financial.financial-holds.show', ['id' => $rowId]);
    }

    public function actions(FinancialHold $row): array
    {
        if (! ActivePermission::check('financial-hold.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-sm btn-outline-info rounded-pill px-3 py-1 fw-semibold')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(FinancialHold $hold): string
    {
        $class = match ($hold->status) {
            'active' => $hold->isBlocking() ? 'bg-danger text-white' : 'bg-warning text-dark',
            'waived' => 'bg-info text-white',
            'released' => 'bg-success text-white',
            default => 'bg-secondary text-white',
        };

        $label = match ($hold->status) {
            'active' => $hold->isBlocking() ? 'Diblokir' : 'Peringatan',
            'waived' => 'Ditangguhkan',
            'released' => 'Dilepas',
            default => str($hold->status)->title(),
        };

        return '<span class="badge '.$class.' rounded-pill px-3 py-1 fs-8">'.$label.'</span>';
    }
}
