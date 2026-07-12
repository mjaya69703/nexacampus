<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\Faculty;
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
            ->add('faculty_name', fn (AdmissionQuota $model) => $model->faculty?->name ?? 'Semua Fakultas')
            ->add('study_program_name', fn (AdmissionQuota $model) => $model->studyProgram?->name ?? 'Semua Program Studi')
            ->add('class_type', fn (AdmissionQuota $model) => $model->class_type ? ucfirst($model->class_type) : 'Semua Kelas')
            ->add('quota')
            ->add('accepted_count')
            ->add('remaining', fn (AdmissionQuota $model) => max(0, $model->quota - $model->accepted_count))
            ->add('usage', fn (AdmissionQuota $model) => $model->quota > 0 ? round(($model->accepted_count / $model->quota) * 100).'%' : '0%')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Gelombang / Periode', 'period_name')->sortable()->searchable(),
            Column::make('Fakultas', 'faculty_name')->sortable()->searchable()->hidden(),
            Column::make('Program Studi', 'study_program_name')->sortable()->searchable(),
            Column::make('Kelas', 'class_type')->sortable(),
            Column::make('Total Kuota', 'quota')->sortable(),
            Column::make('Diterima', 'accepted_count')->sortable(),
            Column::make('Sisa Kuota', 'remaining'),
            Column::make('Terisi', 'usage'),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('period_name', 'admission_period_id')
                ->dataSource(AdmissionPeriod::query()->orderByDesc('created_at')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('admission_period_id', $value)),
            Filter::select('faculty_name', 'faculty_id')
                ->dataSource(Faculty::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('faculty_id', $value)),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::select('class_type', 'class_type')
                ->dataSource(collect([
                    ['id' => 'regular', 'name' => 'Reguler Pagi'],
                    ['id' => 'evening', 'name' => 'Kelas Malam'],
                    ['id' => 'weekend', 'name' => 'Kelas Akhir Pekan (Weekend)'],
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
                title: "Hapus kuota penerimaan?",
                text: "Kuota ini akan dihapus dari dashboard seleksi.",
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
        if (! ActivePermission::check('admission-quota.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus kuota admission.');

            return;
        }

        $quota = AdmissionQuota::find($id);
        if ($quota) {
            $quota->delete();
        }

        session()->flash('success', 'Kuota admission berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-admissionQuotaTable');
    }

    public function actions(AdmissionQuota $row): array
    {
        $actions = [];

        if (ActivePermission::check('admission-quota.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-quota.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
