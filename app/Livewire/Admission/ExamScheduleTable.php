<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Admission\AdmissionExamSchedule;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ExamScheduleTable extends BasePowerGridTable
{
    public string $tableName = 'admissionExamScheduleTable';

    protected ?string $bulkActionModel = AdmissionExamSchedule::class;

    protected ?string $bulkActionPermissionPrefix = 'admission-exam-schedule';

    protected string $bulkActionItemLabel = 'jadwal seleksi';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return AdmissionExamSchedule::query()
            ->with('period')
            ->withCount('participants')
            ->orderByDesc('exam_date')
            ->orderByDesc('exam_time');
    }

    public function relationSearch(): array
    {
        return [
            'period' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('exam_type')
            ->add('period_name', fn (AdmissionExamSchedule $model) => $model->period?->name)
            ->add('exam_date', fn (AdmissionExamSchedule $model) => $model->exam_date?->format('d M Y'))
            ->add('exam_time', fn (AdmissionExamSchedule $model) => $model->exam_time?->format('H:i'))
            ->add('venue')
            ->add('quota')
            ->add('participants_count')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Judul Ujian', 'title')->sortable()->searchable(),
            Column::make('Tipe Ujian', 'exam_type')->sortable()->searchable(),
            Column::make('Periode', 'period_name')->sortable()->searchable(),
            Column::make('Tanggal', 'exam_date')->sortable(),
            Column::make('Waktu', 'exam_time')->sortable(),
            Column::make('Lokasi / Ruangan', 'venue')->sortable()->searchable(),
            Column::make('Kuota', 'quota')->sortable(),
            Column::make('Peserta', 'participants_count')->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(ActivePermission::check('admission-exam-schedule.update'), 'Aktif', 'Nonaktif')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('title')->placeholder('Cari judul / nama ujian...'),
            Filter::inputText('venue')->placeholder('Cari lokasi / ruangan...'),
            Filter::select('period_name', 'admission_period_id')
                ->dataSource(AdmissionPeriod::query()->orderByDesc('created_at')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('admission_period_id', $value)),
            Filter::select('exam_type', 'exam_type')
                ->dataSource(collect([
                    ['id' => 'written_test', 'name' => 'Tes Tertulis (Written Test)'],
                    ['id' => 'interview', 'name' => 'Wawancara (Interview)'],
                    ['id' => 'practical', 'name' => 'Ujian Praktik (Practical)'],
                    ['id' => 'portfolio', 'name' => 'Penilaian Portofolio'],
                    ['id' => 'other', 'name' => 'Lainnya (Other)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::boolean('is_active', 'Aktif', 'Nonaktif'),
            Filter::datepicker('exam_date', 'exam_date'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('admission-exam-schedule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah jadwal seleksi.');
            $this->dispatch('pg:eventRefresh-admissionExamScheduleTable');

            return;
        }

        $schedule = AdmissionExamSchedule::find($id);
        if ($schedule) {
            $schedule->update([
                'is_active' => (bool) $value,
                'updated_by' => auth()->id(),
            ]);
        }

        $this->dispatch('pg:eventRefresh-admissionExamScheduleTable');
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.admission.admission-exam-schedules.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.admission.admission-exam-schedules.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $schedule = AdmissionExamSchedule::find($id);

        if (! $schedule) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus jadwal seleksi?",
                text: "'.$schedule->title.' - Data tidak bisa dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteAdmissionExamSchedule", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteAdmissionExamSchedule')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('admission-exam-schedule.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus jadwal seleksi.');

            return;
        }

        $schedule = AdmissionExamSchedule::find($id);
        if (! $schedule) {
            session()->flash('error', 'Jadwal seleksi tidak ditemukan.');

            return;
        }

        $schedule->update(['deleted_by' => auth()->id()]);
        $schedule->delete();

        session()->flash('success', 'Jadwal seleksi berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-admissionExamScheduleTable');
    }

    public function actions(AdmissionExamSchedule $row): array
    {
        $actions = [];

        if (ActivePermission::check('admission-exam-schedule.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-exam-schedule.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i> Edit')
                ->class('btn btn-outline-primary rounded-pill px-2.5 py-1 text-primary fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-exam-schedule.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> Hapus')
                ->class('btn btn-outline-danger rounded-pill px-2.5 py-1 text-danger fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
