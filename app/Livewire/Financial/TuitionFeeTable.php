<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\TuitionFee;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TuitionFeeTable extends BasePowerGridTable
{
    public string $tableName = 'tuitionFeeTable';

    protected ?string $bulkActionModel = TuitionFee::class;

    protected ?string $bulkActionPermissionPrefix = 'tuition-fee';

    protected string $bulkActionItemLabel = 'konfigurasi biaya kuliah';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return TuitionFee::query()
            ->with(['academicYear', 'studyProgram'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'academicYear' => ['name', 'code'],
            'studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('academic_year_name', fn (TuitionFee $model) => $model->academicYear?->name ?? '-')
            ->add('study_program_name', fn (TuitionFee $model) => $model->studyProgram?->name ?? '-')
            ->add('semester')
            ->add('base_fee_label', fn (TuitionFee $model) => $this->money($model->base_fee))
            ->add('total_amount', fn (TuitionFee $model) => $this->money($model->totalAmount()))
            ->add('payment_deadline', fn (TuitionFee $model) => $model->payment_deadline?->format('d M Y'))
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Tahun Akademik', 'academic_year_name', 'academic_year_id')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program_name', 'study_program_id')->sortable()->searchable(),
            Column::make('Semester', 'semester')->sortable(),
            Column::make('Biaya Pokok (SPP)', 'base_fee_label'),
            Column::make('Total Tagihan', 'total_amount'),
            Column::make('Batas Pembayaran', 'payment_deadline', 'payment_deadline')->sortable(),
            Column::make('Status Aktif', 'is_active')
                ->toggleable(ActivePermission::check('tuition-fee.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('academic_year_name', 'academic_year_id')
                ->dataSource(
                    AcademicYear::query()
                        ->orderByDesc('start_date')
                        ->get(['id', 'name'])
                        ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
                )
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(
                    StudyProgram::query()
                        ->orderBy('name')
                        ->get(['id', 'name', 'code'])
                        ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name . ' (' . $item->code . ')'])
                )
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::inputText('semester', 'semester')
                ->operators(['contains']),
            Filter::boolean('is_active', 'is_active')
                ->label('Aktif', 'Nonaktif'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('tuition-fee.update')) {
            return;
        }

        TuitionFee::whereKey($id)->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.financial.tuition-fees.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $this->js('
            Swal.fire({
                title: "Hapus konfigurasi biaya ini?",
                text: "Konfigurasi biaya kuliah yang dihapus akan dipindahkan ke tempat sampah.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, Hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteTuitionFee", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteTuitionFee')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('tuition-fee.delete')) {
            return;
        }

        TuitionFee::findOrFail($id)->delete();
        session()->flash('success', 'Konfigurasi biaya kuliah berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-tuitionFeeTable');
    }

    public function actions(TuitionFee $row): array
    {
        $actions = [];

        if (ActivePermission::check('tuition-fee.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit me-1"></i>Edit')
                ->class('btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 me-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('tuition-fee.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash me-1"></i>Hapus')
                ->class('btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
