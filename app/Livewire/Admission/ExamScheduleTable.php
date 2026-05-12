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
            Column::make('Title', 'title')->sortable()->searchable(),
            Column::make('Type', 'exam_type')->sortable()->searchable(),
            Column::make('Period', 'period_name')->sortable()->searchable(),
            Column::make('Date', 'exam_date')->sortable(),
            Column::make('Time', 'exam_time')->sortable(),
            Column::make('Venue', 'venue')->sortable()->searchable(),
            Column::make('Quota', 'quota')->sortable(),
            Column::make('Participants', 'participants_count')->sortable(),
            Column::make('Active', 'is_active')
                ->toggleable(ActivePermission::check('admission-exam-schedule.update'), 'Active', 'Inactive')
                ->sortable(),
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
            Filter::select('exam_type', 'exam_type')
                ->dataSource(collect([
                    ['id' => 'written_test', 'name' => 'Written Test'],
                    ['id' => 'interview', 'name' => 'Interview'],
                    ['id' => 'practical', 'name' => 'Practical'],
                    ['id' => 'portfolio', 'name' => 'Portfolio'],
                    ['id' => 'other', 'name' => 'Other'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datetimepicker('exam_date', 'exam_date'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        abort_unless(ActivePermission::check('admission-exam-schedule.update'), 403);

        AdmissionExamSchedule::whereKey($id)->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);
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
        abort_unless(ActivePermission::check('admission-exam-schedule.delete'), 403);

        $schedule = AdmissionExamSchedule::findOrFail($id);
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
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-exam-schedule.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-exam-schedule.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
