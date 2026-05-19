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

    protected string $bulkActionItemLabel = 'tuition fee';

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
            Column::make('Academic Year', 'academic_year_name')->sortable()->searchable(),
            Column::make('Study Program', 'study_program_name')->sortable()->searchable(),
            Column::make('Semester', 'semester')->sortable(),
            Column::make('Base Fee', 'base_fee_label'),
            Column::make('Total', 'total_amount'),
            Column::make('Deadline', 'payment_deadline')->sortable(),
            Column::make('Active', 'is_active')
                ->toggleable(ActivePermission::check('tuition-fee.update'), 'Active', 'Inactive')
                ->sortable(),
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
            Filter::select('study_program_id', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        abort_unless(ActivePermission::check('tuition-fee.update'), 403);

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
                title: "Hapus tuition fee?",
                text: "Konfigurasi biaya ini akan dipindahkan ke tempat sampah.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
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
        abort_unless(ActivePermission::check('tuition-fee.delete'), 403);

        TuitionFee::findOrFail($id)->delete();
        session()->flash('success', 'Tuition fee berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-tuitionFeeTable');
    }

    public function actions(TuitionFee $row): array
    {
        $actions = [];

        if (ActivePermission::check('tuition-fee.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('tuition-fee.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
