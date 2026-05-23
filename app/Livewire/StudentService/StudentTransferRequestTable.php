<?php

namespace App\Livewire\StudentService;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class StudentTransferRequestTable extends BasePowerGridTable
{
    public string $tableName = 'studentTransferRequestTable';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return StudentTransferRequest::query()
            ->with(['studentProfile.user', 'fromStudyProgram', 'toStudyProgram'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'studentProfile' => ['nim'],
            'studentProfile.user' => ['first_name', 'last_name', 'email'],
            'fromStudyProgram' => ['name', 'code'],
            'toStudyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('request_number')
            ->add('student_name', fn (StudentTransferRequest $model) => $model->studentProfile?->user?->name ?? '-')
            ->add('nim', fn (StudentTransferRequest $model) => $model->studentProfile?->nim ?? '-')
            ->add('transfer_type_label', fn (StudentTransferRequest $model) => str($model->transfer_type)->replace('_', ' ')->title()->toString())
            ->add('from_program', fn (StudentTransferRequest $model) => $model->transfer_type === 'class_type'
                ? ucfirst($model->from_class_type ?? '-')
                : ($model->fromStudyProgram?->name ?? '-'))
            ->add('to_program', fn (StudentTransferRequest $model) => $model->transfer_type === 'class_type'
                ? ucfirst($model->to_class_type ?? '-')
                : ($model->toStudyProgram?->name ?? '-'))
            ->add('recommended_semester')
            ->add('status_badge', fn (StudentTransferRequest $model) => $this->statusBadge($model->status))
            ->add('status')
            ->add('created_at_label', fn (StudentTransferRequest $model) => $model->created_at?->format('d M Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make('Request No', 'request_number')->sortable()->searchable(),
            Column::make('Mahasiswa', 'student_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Type', 'transfer_type_label')->sortable(),
            Column::make('From', 'from_program')->sortable()->searchable(),
            Column::make('To', 'to_program')->sortable()->searchable(),
            Column::make('Recommended Semester', 'recommended_semester')->sortable(),
            Column::make('Status', 'status_badge'),
            Column::make('Submitted', 'created_at_label')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('to_study_program_id', 'to_study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'under_review', 'name' => 'Under Review'],
                    ['id' => 'revision_requested', 'name' => 'Perlu Perbaikan'],
                    ['id' => 'approved', 'name' => 'Approved'],
                    ['id' => 'approved_pending_payment', 'name' => 'Menunggu Pembayaran'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'applied', 'name' => 'Applied'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.student-services.transfer-requests.show', ['id' => $rowId]);
    }

    public function actions(StudentTransferRequest $row): array
    {
        if (! ActivePermission::check('transfer-request.view')) {
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
            'applied' => 'bg-success',
            'approved' => 'bg-info',
            'approved_pending_payment', 'revision_requested' => 'bg-warning text-dark',
            'under_review' => 'bg-primary',
            'rejected', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };

        $label = match ($status) {
            'revision_requested' => 'Perlu Perbaikan',
            'approved_pending_payment' => 'Menunggu Pembayaran',
            default => str($status)->replace('_', ' ')->title(),
        };

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }
}
