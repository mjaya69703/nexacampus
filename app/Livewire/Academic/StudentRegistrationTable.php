<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentRegistration;
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

final class StudentRegistrationTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'studentRegistrationTable';

    protected ?string $bulkActionModel = StudentRegistration::class;

    protected ?string $bulkActionPermissionPrefix = 'student-registration';

    protected string $bulkActionItemLabel = 'registrasi mahasiswa';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return StudentRegistration::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear', 'approvedBy'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile.user' => ['name', 'email'],
            'studentProfile.studyProgram' => ['name'],
            'academicYear' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (StudentRegistration $model) => $model->studentProfile?->user?->name)
            ->add('student_nim', fn (StudentRegistration $model) => $model->studentProfile?->nim)
            ->add('study_program_name', fn (StudentRegistration $model) => $model->studentProfile?->studyProgram?->name)
            ->add('academic_year_name', fn (StudentRegistration $model) => $model->academicYear?->name)
            ->add('semester_no')
            ->add('registration_status')
            ->add('academic_status')
            ->add('is_active')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->sortable(),
            Column::make('Student', 'student_name')
                ->sortable()
                ->searchable(),
            Column::make('NIM', 'student_nim')
                ->sortable()
                ->searchable(),
            Column::make('Study Program', 'study_program_name')
                ->sortable()
                ->searchable(),
            Column::make('Academic Year', 'academic_year_name')
                ->sortable()
                ->searchable(),
            Column::make('Semester', 'semester_no')
                ->sortable(),
            Column::make('Status', 'registration_status')
                ->sortable(),
            Column::make('Academic', 'academic_status')
                ->sortable(),
            Column::make('Active', 'is_active')
                ->toggleable(
                    ActivePermission::check('student-registration.update'),
                    'Ya',
                    'Tidak'
                )
                ->sortable(),
            Column::make('Created', 'created_at')
                ->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('academic_year_name', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('academic_year_id', $value)),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studentProfile', fn (Builder $student) => $student->where('study_program_id', $value))),
            Filter::select('semester_no', 'semester_no')
                ->dataSource(collect(range(1, 14))->map(fn (int $semester) => ['id' => $semester, 'name' => 'Semester '.$semester]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('registration_status', 'registration_status')
                ->dataSource(StudentRegistration::query()->select('registration_status')->distinct()->orderBy('registration_status')->pluck('registration_status')->filter()->map(fn (string $status) => ['id' => $status, 'name' => $status]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('academic_status', 'academic_status')
                ->dataSource(StudentRegistration::query()->select('academic_status')->distinct()->orderBy('academic_status')->pluck('academic_status')->filter()->map(fn (string $status) => ['id' => $status, 'name' => $status]))
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

        if (! ActivePermission::check('student-registration.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengubah status registrasi!');
            $this->dispatch('pg:eventRefresh-studentRegistrationTable');

            return;
        }

        $registration = StudentRegistration::find($id);

        if (! $registration) {
            session()->flash('error', 'Registrasi tidak ditemukan!');
            $this->dispatch('pg:eventRefresh-studentRegistrationTable');

            return;
        }

        $registration->update([
            'is_active' => (bool) $value,
            'updated_by' => auth()->id(),
        ]);

        $this->dispatch('pg:eventRefresh-studentRegistrationTable');
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.student-registrations.edit', ['id' => $rowId]);
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.student-registrations.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $registration = StudentRegistration::with('studentProfile.user')->find($id);

        if ($registration) {
            $studentName = $registration->studentProfile?->user?->name ?? 'Mahasiswa';

            $this->js('
                Swal.fire({
                    title: "Hapus registrasi?",
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
        if (! ActivePermission::check('student-registration.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus registrasi!');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id registrasi tidak ditemukan!');

            return;
        }

        $registration = StudentRegistration::find($id);

        if ($registration) {
            $registration->delete();
            $this->dispatch('pg:eventRefresh-studentRegistrationTable');
            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Registrasi berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function actions(StudentRegistration $row): array
    {
        $actions = [];

        if (ActivePermission::check('student-registration.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('student-registration.update')) {
            $actions[] = Button::add('edit')
                ->slot('<i class="fa fa-edit"></i>')
                ->id()
                ->class('btn btn-primary')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('student-registration.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
