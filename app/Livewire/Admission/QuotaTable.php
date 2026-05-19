<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\AdmissionQuota;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class QuotaTable extends BasePowerGridTable
{
    public string $tableName = 'admissionQuotaTable';

    protected ?string $bulkActionModel = AdmissionQuota::class;

    protected ?string $bulkActionPermissionPrefix = 'admission-quota';

    protected string $bulkActionItemLabel = 'quota admission';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        app(AdmissionSelectionService::class)->refreshAcceptedCounts();

        return AdmissionQuota::query()
            ->with(['period', 'faculty', 'studyProgram'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'period' => ['name', 'code'],
            'faculty' => ['name', 'code'],
            'studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('period_name', fn (AdmissionQuota $model) => $model->period?->name)
            ->add('faculty_name', fn (AdmissionQuota $model) => $model->faculty?->name ?? 'All Faculties')
            ->add('study_program_name', fn (AdmissionQuota $model) => $model->studyProgram?->name ?? 'All Programs')
            ->add('class_type', fn (AdmissionQuota $model) => $model->class_type ? ucfirst($model->class_type) : 'All Classes')
            ->add('quota')
            ->add('accepted_count')
            ->add('remaining', fn (AdmissionQuota $model) => max(0, $model->quota - $model->accepted_count))
            ->add('usage', fn (AdmissionQuota $model) => $model->quota > 0 ? round(($model->accepted_count / $model->quota) * 100).'%': '0%')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Period', 'period_name')->sortable()->searchable(),
            Column::make('Faculty', 'faculty_name')->sortable()->searchable()->hidden(),
            Column::make('Study Program', 'study_program_name')->sortable()->searchable(),
            Column::make('Class', 'class_type')->sortable(),
            Column::make('Quota', 'quota')->sortable(),
            Column::make('Accepted', 'accepted_count')->sortable(),
            Column::make('Remaining', 'remaining'),
            Column::make('Usage', 'usage'),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('admission_period_id', 'admission_period_id')
                ->dataSource(AdmissionPeriod::query()->orderByDesc('created_at')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('study_program_id', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('class_type', 'class_type')
                ->dataSource(collect([
                    ['id' => 'regular', 'name' => 'Regular'],
                    ['id' => 'evening', 'name' => 'Evening'],
                    ['id' => 'weekend', 'name' => 'Weekend'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.admission.admission-quotas.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $quota = AdmissionQuota::find($id);

        if (! $quota) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus quota admission?",
                text: "Quota ini akan dihapus dari selection dashboard.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteAdmissionQuota", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteAdmissionQuota')]
    public function deleteItem($id = null): void
    {
        abort_unless(ActivePermission::check('admission-quota.delete'), 403);

        AdmissionQuota::findOrFail($id)->delete();

        session()->flash('success', 'Quota admission berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-admissionQuotaTable');
    }

    public function actions(AdmissionQuota $row): array
    {
        $actions = [];

        if (ActivePermission::check('admission-quota.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-quota.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
