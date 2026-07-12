<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\EdomPeriod;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EdomPeriodTable extends BasePowerGridTable
{
    public string $tableName = 'edomPeriodTable';

    protected ?string $bulkActionModel = EdomPeriod::class;

    protected ?string $bulkActionPermissionPrefix = 'edom-period';

    protected string $bulkActionItemLabel = 'periode EDOM';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return EdomPeriod::query()->with('academicYear')->latest('starts_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('name')
            ->add('code')
            ->add('academic_year_name', fn (EdomPeriod $model) => $model->academicYear?->name ?? '-')
            ->add('period_range', fn (EdomPeriod $model) => ($model->starts_at?->format('d M Y') ?? '-').' - '.($model->ends_at?->format('d M Y') ?? '-'))
            ->add('minimum_responses')
            ->add('status_badge', fn (EdomPeriod $model) => '<span class="badge '.($model->status === 'open' ? 'bg-success' : ($model->status === 'closed' ? 'bg-secondary' : 'bg-muted')).'">'.str($model->status)->title().'</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year_name')->searchable(),
            Column::make('Periode', 'period_range'),
            Column::make('Min. Respon', 'minimum_responses')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name', 'name')
                ->placeholder('Cari nama periode...')
                ->operators(['contains']),
            Filter::inputText('code', 'code')
                ->placeholder('Cari kode periode...')
                ->operators(['contains']),
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
                ->dataSource(collect([['id' => 'draft', 'name' => 'Draft'], ['id' => 'open', 'name' => 'Dibuka'], ['id' => 'closed', 'name' => 'Ditutup']]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('starts_at', 'starts_at'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.organization.edom-periods.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $period = EdomPeriod::find($id);

        if (! $period) {
            return;
        }

        if ($period->responses()->exists()) {
            $this->js('Swal.fire("Gagal", "Periode EDOM ini tidak dapat dihapus karena sudah memiliki data respons dari mahasiswa.", "error");');
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Periode EDOM?",
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
        if (! ActivePermission::check('edom-period.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus periode EDOM.');
            return;
        }

        $period = EdomPeriod::find($id);

        if (! $period) {
            session()->flash('error', 'Periode EDOM tidak ditemukan.');
            return;
        }

        if ($period->responses()->exists()) {
            session()->flash('error', 'Tidak dapat menghapus periode yang sudah memiliki respons.');
            return;
        }

        $period->delete();
        session()->flash('success', 'Periode EDOM berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-edomPeriodTable');
    }

    public function actions(EdomPeriod $row): array
    {
        $actions = [];

        if (ActivePermission::check('edom-period.update')) {
            $actions[] = Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('edom-period.delete') && ! $row->responses()->exists()) {
            $actions[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
