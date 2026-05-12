<?php

namespace App\Livewire\Admission;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ApplicationTable extends BasePowerGridTable
{
    public string $tableName = 'admissionApplicationTable';

    protected ?string $bulkActionModel = AdmissionApplication::class;

    protected ?string $bulkActionPermissionPrefix = 'admission-application';

    protected string $bulkActionItemLabel = 'aplikasi admission';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        return AdmissionApplication::query()
            ->with(['period', 'faculty', 'studyProgram'])
            ->withCount(['documents', 'scores', 'examParticipants'])
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'period' => ['name', 'code'],
            'faculty' => ['name', 'code'],
            'studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('application_number')
            ->add('full_name')
            ->add('email')
            ->add('phone')
            ->add('period_name', fn (AdmissionApplication $model) => $model->period?->name)
            ->add('faculty_name', fn (AdmissionApplication $model) => $model->faculty?->name)
            ->add('study_program_name', fn (AdmissionApplication $model) => $model->studyProgram?->name)
            ->add('class_type')
            ->add('status')
            ->add('documents_count')
            ->add('scores_count')
            ->add('exam_participants_count')
            ->add('final_score')
            ->add('submitted_at', fn (AdmissionApplication $model) => $model->submitted_at?->format('d M Y H:i'))
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('No. Application', 'application_number')->sortable()->searchable(),
            Column::make('Applicant', 'full_name')->sortable()->searchable(),
            Column::make('Email', 'email')->sortable()->searchable()->hidden(),
            Column::make('Phone', 'phone')->sortable()->searchable(),
            Column::make('Period', 'period_name')->sortable()->searchable(),
            Column::make('Faculty', 'faculty_name')->sortable()->searchable()->hidden(),
            Column::make('Study Program', 'study_program_name')->sortable()->searchable(),
            Column::make('Class', 'class_type')->sortable(),
            Column::make('Status', 'status')->sortable(),
            Column::make('Final Score', 'final_score')->sortable(),
            Column::make('Docs', 'documents_count')->sortable(),
            Column::make('Scores', 'scores_count')->sortable()->hidden(),
            Column::make('Sessions', 'exam_participants_count')->sortable()->hidden(),
            Column::make('Submitted', 'submitted_at')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'submitted', 'name' => 'Submitted'],
                    ['id' => 'under_review', 'name' => 'Under Review'],
                    ['id' => 'accepted', 'name' => 'Accepted'],
                    ['id' => 'rejected', 'name' => 'Rejected'],
                    ['id' => 'waitlisted', 'name' => 'Waitlisted'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('admission_period_id', 'admission_period_id')
                ->dataSource(AdmissionPeriod::query()->orderByDesc('created_at')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('study_program_id', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('class_type', 'class_type')
                ->dataSource(collect([
                    ['id' => 'regular', 'name' => 'Regular'],
                    ['id' => 'evening', 'name' => 'Evening'],
                    ['id' => 'weekend', 'name' => 'Weekend'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datetimepicker('submitted_at', 'submitted_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.admission.admission-applications.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $application = AdmissionApplication::find($id);

        if (! $application) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus aplikasi admission?",
                text: "'.$application->application_number.' - Data tidak bisa dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteAdmissionApplication", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteAdmissionApplication')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('admission-application.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus aplikasi admission.');

            return;
        }

        $application = AdmissionApplication::find($id);

        if (! $application) {
            session()->flash('error', 'Aplikasi admission tidak ditemukan.');

            return;
        }

        $application->update(['deleted_by' => auth()->id()]);
        $application->delete();

        session()->flash('success', 'Aplikasi admission berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-admissionApplicationTable');
    }

    public function actions(AdmissionApplication $row): array
    {
        $actions = [];

        if (ActivePermission::check('admission-application.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i>')
                ->class('btn btn-info')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('admission-application.delete')) {
            $actions[] = Button::add('delete')
                ->slot('<i class="fa fa-trash"></i>')
                ->class('btn btn-danger')
                ->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }
}
