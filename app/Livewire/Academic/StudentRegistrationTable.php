<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudentRegistration;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentRegistrationTable extends BasePowerGridTable
{
    public string $tableName = 'studentRegistrationTable';

    protected ?string $bulkActionModel = StudentRegistration::class;

    protected ?string $bulkActionPermissionPrefix = 'student-registration';

    protected string $bulkActionItemLabel = 'registrasi mahasiswa';

    public function setUp(): array
    {
        return $this->powerGridSetUp();
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
