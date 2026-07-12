<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AdmissionPeriodTable extends BasePowerGridTable
{
    public string $tableName = 'admissionPeriodTable';

    protected ?string $bulkActionModel = AdmissionPeriod::class;

    protected ?string $bulkActionPermissionPrefix = 'admission-period';

    protected string $bulkActionItemLabel = 'periode admission';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return AdmissionPeriod::query()
            ->with('academicYear')
            ->withCount(['applications', 'documentRequirements'])
            ->orderByDesc('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('code')
            ->add('academic_year_name', fn (AdmissionPeriod $model) => $model->academicYear?->name ?? $model->academic_year)
            ->add('academic_year')
            ->add('wave')
            ->add('opens_at', fn (AdmissionPeriod $model) => $model->opens_at?->format('d M Y'))
            ->add('closes_at', fn (AdmissionPeriod $model) => $model->closes_at?->format('d M Y'))
            ->add('applications_count')
            ->add('document_requirements_count')
            ->add('is_active')
            ->add('is_published')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'id')->sortable(),
            Column::make('Nama Periode', 'name')->sortable()->searchable(),
            Column::make('Kode', 'code')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year_name'),
            Column::make('Gelombang', 'wave')->sortable(),
            Column::make('Buka', 'opens_at')->sortable(),
            Column::make('Tutup', 'closes_at')->sortable(),
            Column::make('Pendaftar', 'applications_count')->sortable(),
            Column::make('Syarat Dokumen', 'document_requirements_count')->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(ActivePermission::check('admission-period.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::make('Publikasi', 'is_published')
                ->toggleable(ActivePermission::check('admission-period.update'), 'Published', 'Draft')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('name')->placeholder('Cari nama periode / gelombang...'),
            Filter::inputText('code')->placeholder('Cari kode periode...'),
            Filter::select('academic_year_name', 'academic_year_id')
                ->dataSource(
                    AcademicYear::query()
                        ->orderByDesc('start_date')
                        ->get(['id', 'name'])
                        ->map(fn ($ay) => ['id' => $ay->id, 'name' => $ay->name])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_year_id', $value)),
            Filter::boolean('is_active', 'Aktif', 'Nonaktif'),
            Filter::boolean('is_published', 'Published', 'Draft'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if (! in_array($field, ['is_active', 'is_published'], true)) {
            return;
        }

        if (! ActivePermission::check('admission-period.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah periode admission.');
            $this->dispatch('pg:eventRefresh-admissionPeriodTable');

            return;
        }

        $period = AdmissionPeriod::find($id);

        if (! $period) {
            session()->flash('error', 'Periode admission tidak ditemukan.');
            $this->dispatch('pg:eventRefresh-admissionPeriodTable');

            return;
        }

        $period->update([
            $field => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-admissionPeriodTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.admission.admission-periods.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $period = AdmissionPeriod::withCount('applications')->find($id);

        if (! $period) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus periode admission?",
                text: "'.$period->name.' - Data tidak bisa dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteAdmissionPeriod", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteAdmissionPeriod')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('admission-period.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus periode admission.');

            return;
        }

        $period = AdmissionPeriod::withCount('applications')->find($id);

        if (! $period) {
            session()->flash('error', 'Periode admission tidak ditemukan.');

            return;
        }

        if ($period->applications_count > 0) {
            session()->flash('error', 'Periode admission sudah memiliki aplikasi dan tidak bisa dihapus.');

            return;
        }

        $period->update(['deleted_by' => auth()->id()]);
        $period->delete();

        session()->flash('success', 'Periode admission berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-admissionPeriodTable');
    }

    public function actions(AdmissionPeriod $row): array
    {
        $actions = [];

        if (ActivePermission::check('admission-period.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-period.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
