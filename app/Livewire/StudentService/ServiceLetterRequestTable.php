<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\ServiceLetterType;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ServiceLetterRequestTable extends BasePowerGridTable
{
    public string $tableName = 'serviceLetterRequestTable';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return ServiceLetterRequest::query()
            ->with(['letterType', 'studentProfile.user', 'studentProfile.studyProgram'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'letterType' => ['name', 'code'],
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'studentProfile.studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('request_number')
            ->add('letter_type', fn (ServiceLetterRequest $model) => $model->letterType?->name ?? '-')
            ->add('student_name', fn (ServiceLetterRequest $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (ServiceLetterRequest $model) => $model->studentProfile?->nim ?? '-')
            ->add('study_program_name', fn (ServiceLetterRequest $model) => $model->studentProfile?->studyProgram?->name ?? '-')
            ->add('purpose')
            ->add('status_badge', fn (ServiceLetterRequest $model) => $this->statusBadge($model->status))
            ->add('created_at_label', fn (ServiceLetterRequest $model) => $model->created_at?->format('d M Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('Request No', 'request_number')->sortable()->searchable(),
            Column::make('Letter Type', 'letter_type')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program_name')->sortable()->searchable(),
            Column::make('Purpose', 'purpose')->searchable()->hidden(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Submitted', 'created_at_label', 'created_at')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('request_number')->placeholder('Cari No Request...'),
            Filter::select('letter_type', 'service_letter_type_id')
                ->dataSource(ServiceLetterType::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('service_letter_type_id', $value)),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('studentProfile', fn (Builder $student) => $student->where('study_program_id', $value))),
            Filter::select('status_badge', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'in_approval', 'name' => 'Menunggu Approval'],
                    ['id' => 'under_review', 'name' => 'Under Review'],
                    ['id' => 'revision_requested', 'name' => 'Revision Requested'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'issued', 'name' => 'Issued'],
                    ['id' => 'cancelled', 'name' => 'Cancelled'],
                ]))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('status', $value)),
            Filter::datepicker('created_at_label', 'created_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.student-services.letter-requests.show', ['id' => $rowId]);
    }

    public function actions(ServiceLetterRequest $row): array
    {
        if (! ActivePermission::check('service-letter-request.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-primary')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'issued' => 'bg-success',
            'approved' => 'bg-info',
            'under_review' => 'bg-primary',
            'revision_requested', 'in_approval' => 'bg-warning text-dark',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };

        $label = match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'in_approval' => 'Menunggu Approval',
            default => str($status)->replace('_', ' ')->title(),
        };

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }
}
