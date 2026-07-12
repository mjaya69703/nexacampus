<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;

final class AttendanceSessionTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'attendanceSessionTable';

    protected ?string $bulkActionModel = AttendanceSession::class;

    protected ?string $bulkActionPermissionPrefix = 'attendance-session';

    protected string $bulkActionItemLabel = 'sesi absensi';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return AttendanceSession::query()
            ->with([
                'courseOffering.course',
                'courseOffering.studyProgram',
                'lecturerProfile.user',
                'records',
            ])
            ->withCount('records')
            ->orderByDesc('meeting_date')
            ->orderBy('meeting_no');
    }

    public function relationSearch(): array
    {
        return [
            'courseOffering.course' => ['code', 'name'],
            'courseOffering.studyProgram' => ['name', 'code'],
            'lecturerProfile.user' => ['first_name', 'last_name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('meeting_no')
            ->add('meeting_date')
            ->add('course_label', fn (AttendanceSession $model) => ($model->courseOffering?->course?->code ?? '-').' - '.($model->courseOffering?->course?->name ?? '-'))
            ->add('study_program', fn (AttendanceSession $model) => $model->courseOffering?->studyProgram?->name ?? '-')
            ->add('lecturer_name', fn (AttendanceSession $model) => $model->lecturerProfile?->user?->name ?? '-')
            ->add('start_time', fn (AttendanceSession $model) => $model->start_time?->format('H:i') ?? '-')
            ->add('end_time', fn (AttendanceSession $model) => $model->end_time?->format('H:i') ?? '-')
            ->add('status')
            ->add('records_count')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Pertemuan', 'meeting_no')->sortable(),
            Column::make('Tanggal', 'meeting_date')->sortable(),
            Column::make('Mata Kuliah', 'course_label')->sortable()->searchable(),
            Column::make('Prodi', 'study_program')->sortable()->searchable(),
            Column::make('Dosen', 'lecturer_name')->sortable()->searchable(),
            Column::make('Jam Mulai', 'start_time')->sortable(),
            Column::make('Jam Selesai', 'end_time')->sortable(),
            Column::make('Status', 'status')->sortable(),
            Column::make('Absensi', 'records_count')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('lecturer_name', 'lecturer_name')
                ->placeholder('Cari nama dosen...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->whereHas('lecturerProfile.user', fn (Builder $user) => $user->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                }),
            Filter::select('course_label', 'course_offering_id')
                ->dataSource(CourseOffering::query()->with('course')->orderByDesc('created_at')->get()->map(fn (CourseOffering $offering) => [
                    'id' => $offering->id,
                    'name' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-').' / '.$offering->label,
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('course_offering_id', $value)),
            Filter::select('study_program', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('courseOffering', fn (Builder $offering) => $offering->where('study_program_id', $value))),
            Filter::select('status', 'status')
                ->dataSource(AttendanceSession::query()->select('status')->distinct()->orderBy('status')->pluck('status')->filter()->map(fn (string $status) => ['id' => $status, 'name' => $status]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('meeting_date', 'meeting_date'),
            Filter::number('meeting_no', 'meeting_no'),
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $session = AttendanceSession::query()->find($rowId);

        if (! $session) {
            return;
        }

        $this->redirectRoute('admin.academic.attendance-sessions.show', ['offeringId' => $session->course_offering_id, 'id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->show($rowId);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $session = AttendanceSession::with('courseOffering.course')->find($id);

        if ($session) {
            $courseLabel = ($session->courseOffering?->course?->code ?? '-').' - '.($session->courseOffering?->course?->name ?? 'Sesi');

            $this->js('
                Swal.fire({
                    title: "Hapus sesi absensi?",
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
        if (! ActivePermission::check('attendance-session.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus sesi absensi!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id sesi tidak ditemukan!');

            return;
        }

        $session = AttendanceSession::find($id);

        if ($session) {
            $session->update(['deleted_by' => auth()->id()]);
            $session->delete();

            $this->dispatch('pg:eventRefresh-attendanceSessionTable');
            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Sesi absensi berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(AttendanceSession $row): array
    {
        $actions = [];

        if (ActivePermission::check('attendance-session.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('attendance-session.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('attendance-session.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
