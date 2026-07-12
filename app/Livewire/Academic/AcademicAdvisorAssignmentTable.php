<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudyProgram;
use App\Support\AcademicAdvisorService;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;

final class AcademicAdvisorAssignmentTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'academicAdvisorAssignmentTable';

    protected ?string $bulkActionModel = AcademicAdvisorAssignment::class;

    protected ?string $bulkActionPermissionPrefix = 'academic-advisor-assignment';

    protected string $bulkActionItemLabel = 'penugasan dosen wali';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'lecturerProfile.user', 'academicYear'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile' => ['nim'],
            'lecturerProfile.user' => ['first_name', 'last_name', 'email'],
            'lecturerProfile' => ['nidn', 'nip'],
            'academicYear' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (AcademicAdvisorAssignment $row) => $row->studentProfile?->user?->name ?? '-')
            ->add('student_nim', fn (AcademicAdvisorAssignment $row) => $row->studentProfile?->nim ?? '-')
            ->add('advisor_name', fn (AcademicAdvisorAssignment $row) => $row->lecturerProfile?->user?->name ?? '-')
            ->add('academic_year', fn (AcademicAdvisorAssignment $row) => $row->academicYear?->name ?? '-')
            ->add('start_date')
            ->add('end_date')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'student_nim')->sortable()->searchable(),
            Column::make('Dosen PA', 'advisor_name')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year')->sortable()->searchable(),
            Column::make('Mulai', 'start_date')->sortable(),
            Column::make('Selesai', 'end_date')->sortable(),
            Column::make('Aktif', 'is_active')
                ->toggleable(
                    ActivePermission::check('academic-advisor-assignment.update'),
                    'Ya',
                    'Tidak'
                )
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('student_name', 'student_name')
                ->placeholder('Cari nama mahasiswa...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->whereHas('studentProfile.user', fn (Builder $user) => $user->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                }),
            Filter::inputText('student_nim', 'student_nim')
                ->placeholder('Cari NIM...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->whereHas('studentProfile', fn (Builder $sp) => $sp->where('nim', 'like', '%'.$search.'%'));
                }),
            Filter::select('academic_year', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_year_id', $value)),
            Filter::select('study_program', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studentProfile', fn (Builder $student) => $student->where('study_program_id', $value))),
            Filter::select('advisor_name', 'lecturer_profile_id')
                ->dataSource(LecturerProfile::query()->with('user')->get()->sortBy(fn (LecturerProfile $profile) => $profile->user?->name)->map(fn (LecturerProfile $profile) => [
                    'id' => $profile->id,
                    'name' => $profile->user?->name ?? $profile->nidn ?? 'Dosen',
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('lecturer_profile_id', $value)),
            Filter::datepicker('start_date', 'start_date'),
            Filter::datepicker('end_date', 'end_date'),
            Filter::boolean('is_active', 'is_active'),
        ];
    }

    public function onUpdatedToggleable(string $id, string $field, string $value): void
    {
        if ($field !== 'is_active') {
            return;
        }

        if (! ActivePermission::check('academic-advisor-assignment.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status assignment!');
            $this->dispatch('pg:eventRefresh-academicAdvisorAssignmentTable');

            return;
        }

        $assignment = AcademicAdvisorAssignment::query()->find($id);

        if (! $assignment) {
            session()->flash('error', 'Assignment tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-academicAdvisorAssignmentTable');

            return;
        }

        if ((bool) $value) {
            try {
                app(AcademicAdvisorService::class)->ensureNoActiveConflict(
                    studentProfileId: $assignment->student_profile_id,
                    academicYearId: $assignment->academic_year_id,
                    startDate: $assignment->start_date,
                    endDate: $assignment->end_date,
                    ignoreId: $assignment->id,
                );
            } catch (ValidationException $exception) {
                session()->flash('error', $exception->validator->errors()->first() ?: 'Assignment aktif bentrok dengan assignment lain.');
                $this->dispatch('pg:eventRefresh-academicAdvisorAssignmentTable');

                return;
            }
        }

        $assignment->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-academicAdvisorAssignmentTable');
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.academic-advisor-assignments.show', ['id' => $rowId]);
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.academic-advisor-assignments.edit', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $assignment = AcademicAdvisorAssignment::query()->with('studentProfile.user')->find($id);

        if ($assignment) {
            $studentName = $assignment->studentProfile?->user?->name ?? 'Mahasiswa';

            $this->js('
                Swal.fire({
                    title: "Hapus assignment dosen PA?",
                    text: "'.$studentName.' - Data tidak bisa dikembalikan!",
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
        if (! ActivePermission::check('academic-advisor-assignment.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus assignment dosen PA!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id assignment tidak ditemukan!');

            return;
        }

        $assignment = AcademicAdvisorAssignment::query()->find($id);

        if ($assignment) {
            $assignment->update(['deleted_by' => auth()->id()]);
            $assignment->delete();

            $this->dispatch('pg:eventRefresh-academicAdvisorAssignmentTable');
            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Assignment dosen PA berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(AcademicAdvisorAssignment $row): array
    {
        $actions = [];

        if (ActivePermission::check('academic-advisor-assignment.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> <span>Detail</span>')
                ->class('btn btn-sm btn-info d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('academic-advisor-assignment.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-pencil"></i> <span>Edit</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('academic-advisor-assignment.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i> <span>Hapus</span>')
                ->class('btn btn-sm btn-danger d-inline-flex align-items-center gap-1')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
