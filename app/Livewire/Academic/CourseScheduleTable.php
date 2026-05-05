<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\CourseSchedule;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class CourseScheduleTable extends BasePowerGridTable
{
    public string $tableName = 'courseScheduleTable';

    protected ?string $bulkActionModel = CourseSchedule::class;

    protected ?string $bulkActionPermissionPrefix = 'course-schedule';

    protected string $bulkActionItemLabel = 'jadwal kuliah';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return CourseSchedule::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'lecturerProfile.user', 'room.building'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'courseOffering.course' => ['name', 'code'],
            'courseOffering.academicYear' => ['name', 'code'],
            'lecturerProfile.user' => ['first_name', 'last_name', 'email'],
            'room' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('course_name', fn (CourseSchedule $model) => $model->courseOffering?->course?->code.' - '.$model->courseOffering?->course?->name ?? '-')
            ->add('lecturer_name', fn (CourseSchedule $model) => $model->lecturerProfile?->user?->name ?? 'Jadwal Umum')
            ->add('day_of_week')
            ->add('time_range', fn (CourseSchedule $model) => ($model->start_time ? $model->start_time->format('H:i') : '-').' - '.($model->end_time ? $model->end_time->format('H:i') : '-'))
            ->add('room_name', fn (CourseSchedule $model) => $model->room?->name ?? '-')
            ->add('building_name', fn (CourseSchedule $model) => $model->room?->building?->name ?? '-')
            ->add('session_type')
            ->add('delivery_mode')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Mata Kuliah', 'course_name')
                ->sortable()
                ->searchable(),
            Column::make('Dosen', 'lecturer_name')
                ->sortable()
                ->searchable(),
            Column::make('Hari', 'day_of_week')
                ->sortable(),
            Column::make('Waktu', 'time_range')
                ->sortable(),
            Column::make('Ruangan', 'room_name')
                ->sortable(),
            Column::make('Gedung', 'building_name')
                ->sortable(),
            Column::make('Tipe', 'session_type')
                ->sortable(),
            Column::make('Mode', 'delivery_mode')
                ->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(
                    ActivePermission::check('course-schedule.update'),
                    'Ya',
                    'Tidak'
                )
                ->sortable(),
            Column::make('Dibuat', 'created_at')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('course-schedule.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah jadwal kuliah!');
            $this->dispatch('pg:eventRefresh-courseScheduleTable');

            return;
        }

        $schedule = CourseSchedule::find($id);

        if (! $schedule) {
            session()->flash('error', 'Jadwal tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-courseScheduleTable');

            return;
        }

        $schedule->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-courseScheduleTable');
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.course-schedules.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.course-schedules.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $schedule = CourseSchedule::with('courseOffering.course')->find($id);

        if ($schedule) {
            $courseLabel = $schedule->courseOffering?->course?->code.' - '.($schedule->courseOffering?->course?->name ?? 'Mata kuliah');

            $this->js('
                Swal.fire({
                    title: "Hapus jadwal kuliah?",
                    text: "'.$courseLabel.' - Data tidak bisa dikembalikan!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya hapus",
                    cancelButtonText: "Batal"
                }).then((result) => {
                    if (result.isConfirmed) {
                        Livewire.dispatch("deleteItem", {id: '.$id.'})
                    }
                });
            ');
        }
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('course-schedule.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus jadwal kuliah!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id jadwal tidak ditemukan!');

            return;
        }

        $schedule = CourseSchedule::find($id);

        if ($schedule) {
            $schedule->update(['deleted_by' => auth()->id()]);
            $schedule->delete();
            $this->dispatch('pg:eventRefresh-courseScheduleTable');
            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Jadwal kuliah berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(CourseSchedule $row): array
    {
        $actions = [];

        if (ActivePermission::check('course-schedule.update')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('course-schedule.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('course-schedule.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
