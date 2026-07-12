<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LecturerWorkloadPeriodTable extends BasePowerGridTable
{
    public string $tableName = 'lecturerWorkloadPeriodTable';

    protected ?string $bulkActionModel = LecturerWorkloadPeriod::class;

    protected ?string $bulkActionPermissionPrefix = 'lecturer-workload-period';

    protected string $bulkActionItemLabel = 'periode BKD';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return LecturerWorkloadPeriod::query()->with(['academicYear', 'academicPeriod'])->latest('starts_at');
    }

    public function relationSearch(): array
    {
        return ['academicYear' => ['name', 'code'], 'academicPeriod' => ['name', 'code']];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('academic_year_name', fn (LecturerWorkloadPeriod $model) => $model->academicYear?->name ?? '-')
            ->add('period_range', fn (LecturerWorkloadPeriod $model) => ($model->starts_at?->format('d M Y') ?? '-').' - '.($model->ends_at?->format('d M Y') ?? '-'))
            ->add('sks_range', fn (LecturerWorkloadPeriod $model) => $model->minimum_sks.' - '.$model->maximum_sks.' SKS')
            ->add('status_badge', fn (LecturerWorkloadPeriod $model) => $this->statusBadge($model->status));
    }

    public function columns(): array
    {
        return [
            Column::make('Nama', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year_name')->searchable(),
            Column::make('Periode', 'period_range'),
            Column::make('Rentang SKS', 'sks_range'),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name', 'name')->placeholder('Cari nama periode BKD...')->operators(['contains']),
            Filter::inputText('code', 'code')->placeholder('Cari kode periode...')->operators(['contains']),
            Filter::inputText('academic_year_name', 'academic_year_name')
                ->placeholder('Cari tahun akademik...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('academicYear', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%')
                                ->orWhere('code', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'open', 'name' => 'Dibuka'],
                    ['id' => 'review', 'name' => 'Review'],
                    ['id' => 'closed', 'name' => 'Ditutup'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('starts_at', 'starts_at'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-workload-periods.edit', ['id' => $rowId]);
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-workload-periods.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $period = LecturerWorkloadPeriod::find($id);

        if (! $period) {
            return;
        }

        if ($period->submissions()->exists()) {
            $this->js('Swal.fire("Gagal", "Tidak dapat menghapus periode yang sudah memiliki data pengajuan BKD dosen.", "error");');
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Periode BKD?",
                text: "Periode \''.$period->name.'\' akan dihapus dari sistem.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, hapus!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteItem", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('lecturer-workload-period.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus periode BKD.');
            return;
        }

        $period = LecturerWorkloadPeriod::find($id);

        if (! $period) {
            session()->flash('error', 'Periode BKD tidak ditemukan.');
            return;
        }

        if ($period->submissions()->exists()) {
            session()->flash('error', 'Tidak dapat menghapus periode yang sudah memiliki data pengajuan BKD dosen.');
            return;
        }

        $period->delete();
        session()->flash('success', 'Periode BKD berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-lecturerWorkloadPeriodTable');
    }

    public function actions(LecturerWorkloadPeriod $row): array
    {
        $buttons = [];

        if (ActivePermission::check('lecturer-workload-period.view')) {
            $buttons[] = Button::add('show')->slot('<i class="fa fa-eye"></i>')->class('btn btn-info')->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('lecturer-workload-period.update')) {
            $buttons[] = Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('lecturer-workload-period.delete')) {
            $buttons[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }

        return $buttons;
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'open' => 'bg-success',
            'review' => 'bg-warning text-dark',
            'closed' => 'bg-secondary',
            default => 'bg-muted',
        };

        $label = match ($status) {
            'open' => 'Dibuka',
            'review' => 'Review',
            'closed' => 'Ditutup',
            default => 'Draft',
        };

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }
}
