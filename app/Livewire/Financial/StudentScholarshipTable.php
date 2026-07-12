<?php

namespace App\Livewire\Financial;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\AcademicYear;
use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentScholarship;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentScholarshipTable extends BasePowerGridTable
{
    public string $tableName = 'studentScholarshipTable';

    protected ?string $bulkActionModel = StudentScholarship::class;

    protected ?string $bulkActionPermissionPrefix = 'student-scholarship';

    protected string $bulkActionItemLabel = 'penerima beasiswa';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return StudentScholarship::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'scholarship', 'academicYear'])
            ->orderByRaw("FIELD(status, 'active', 'completed', 'revoked')")
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
            'scholarship' => ['name'],
            'academicYear' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (StudentScholarship $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentScholarship $model) => $model->studentProfile?->nim ?? '-')
            ->add('scholarship_name', fn (StudentScholarship $model) => $model->scholarship?->name ?? '-')
            ->add('academic_year', fn (StudentScholarship $model) => $model->academicYear?->name ?? 'Semua Tahun')
            ->add('semester_label', fn (StudentScholarship $model) => $model->semester ? 'Semester '.$model->semester : 'Semua Semester')
            ->add('status_badge', fn (StudentScholarship $model) => $this->statusBadge($model->status))
            ->add('status');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Beasiswa', 'scholarship_name', 'scholarship_id')->sortable()->searchable(),
            Column::make('Tahun Akademik', 'academic_year', 'academic_year_id')->sortable()->searchable(),
            Column::make('Masa Berlaku', 'semester_label')->sortable(),
            Column::make('Status Pemberian', 'status_badge', 'status'),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('student_name', 'studentProfile.user.first_name')
                ->operators(['contains']),
            Filter::inputText('nim', 'studentProfile.nim')
                ->operators(['contains']),
            Filter::select('scholarship_name', 'scholarship_id')
                ->dataSource(Scholarship::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('academic_year', 'academic_year_id')
                ->dataSource(AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'active', 'name' => 'Aktif Menerima (Active)'],
                    ['id' => 'completed', 'name' => 'Selesai / Lulus (Completed)'],
                    ['id' => 'revoked', 'name' => 'Dicabut / Batal (Revoked)'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.financial.student-scholarships.edit', ['id' => $rowId]);
    }

    public function actions(StudentScholarship $row): array
    {
        if (! ActivePermission::check('student-scholarship.update')) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('<i class="fa fa-edit me-1"></i>Edit Status')
                ->class('btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'active' => 'bg-success text-white',
            'completed' => 'bg-info text-white',
            'revoked' => 'bg-danger text-white',
            default => 'bg-secondary text-white',
        };

        $label = match ($status) {
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'revoked' => 'Dicabut',
            default => str($status)->replace('_', ' ')->title()->toString()
        };

        return '<span class="badge '.$class.' rounded-pill px-3 py-1 fs-8">'.$label.'</span>';
    }
}
