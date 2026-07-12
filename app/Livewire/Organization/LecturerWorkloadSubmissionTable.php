<?php

namespace App\Livewire\Organization;

use App\Livewire\BasePowerGridTable;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LecturerWorkloadSubmissionTable extends BasePowerGridTable
{
    public string $tableName = 'lecturerWorkloadSubmissionTable';

    protected bool $bulkActionEnabled = false;

    public function setUp(): array
    {
        return $this->powerGridSetUp();
    }

    public function datasource(): Builder
    {
        return LecturerWorkloadSubmission::query()
            ->with(['period', 'owner', 'lecturerProfile.studyProgram'])
            ->orderByRaw("FIELD(status, 'in_approval', 'submitted', 'revision', 'draft', 'approved', 'rejected', 'cancelled')")
            ->latest('updated_at');
    }

    public function relationSearch(): array
    {
        return ['owner' => ['first_name', 'last_name', 'email', 'username'], 'period' => ['name', 'code']];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('lecturer_name', fn (LecturerWorkloadSubmission $model) => $model->owner?->name ?? '-')
            ->add('period_name', fn (LecturerWorkloadSubmission $model) => $model->period?->name ?? '-')
            ->add('program_name', fn (LecturerWorkloadSubmission $model) => $model->lecturerProfile?->studyProgram?->name ?? '-')
            ->add('total_sks')
            ->add('status_badge', fn (LecturerWorkloadSubmission $model) => $this->statusBadge($model->status))
            ->add('submitted_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Dosen', 'lecturer_name')->searchable(),
            Column::make('Periode', 'period_name')->searchable(),
            Column::make('Program Studi', 'program_name'),
            Column::make('Total SKS', 'total_sks')->sortable(),
            Column::make('Status', 'status_badge', 'status')->sortable(),
            Column::make('Diajukan', 'submitted_at')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('lecturer_name', 'lecturer_name')
                ->placeholder('Cari nama dosen/username...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('owner', function (Builder $sub) use ($value) {
                            $sub->where(function (Builder $q) use ($value) {
                                $q->where('first_name', 'like', '%' . $value . '%')
                                    ->orWhere('last_name', 'like', '%' . $value . '%')
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $value . '%'])
                                    ->orWhere('username', 'like', '%' . $value . '%');
                            });
                        });
                    }
                }),
            Filter::inputText('period_name', 'period_name')
                ->placeholder('Cari periode BKD...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('period', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%')
                                ->orWhere('code', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::inputText('program_name', 'program_name')
                ->placeholder('Cari program studi...')
                ->operators(['contains'])
                ->builder(function (Builder $query, array $values) {
                    $value = $values['value'] ?? null;
                    if (! empty($value)) {
                        $query->whereHas('lecturerProfile.studyProgram', function (Builder $sub) use ($value) {
                            $sub->where('name', 'like', '%' . $value . '%');
                        });
                    }
                }),
            Filter::select('status', 'status')
                ->dataSource(collect([
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'in_approval', 'name' => 'Menunggu Approval'],
                    ['id' => 'approved', 'name' => 'Disetujui'],
                    ['id' => 'revision', 'name' => 'Revisi'],
                    ['id' => 'rejected', 'name' => 'Ditolak'],
                    ['id' => 'cancelled', 'name' => 'Dibatalkan'],
                ]))
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datepicker('submitted_at', 'submitted_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.organization.lecturer-workload-submissions.show', ['id' => $rowId]);
    }

    #[On('delete')]
    public function delete($id): void
    {
        $submission = LecturerWorkloadSubmission::find($id);

        if (! $submission) {
            return;
        }

        $this->js('
            Swal.fire({
                title: "Hapus Laporan BKD?",
                text: "Laporan BKD milik \''.($submission->owner?->name ?? 'Dosen').'\' beserta rincian itemnya akan dihapus permanen.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, hapus!",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.dispatch("deleteItem", {id: '.$id.'})
                }
            });
        ');
    }

    #[On('deleteItem')]
    public function deleteItem($id = null): void
    {
        if (! ActivePermission::check('lecturer-workload-submission.delete')) {
            session()->flash('error', 'Anda tidak memiliki izin menghapus laporan BKD.');
            return;
        }

        $submission = LecturerWorkloadSubmission::find($id);

        if (! $submission) {
            session()->flash('error', 'Laporan BKD tidak ditemukan.');
            return;
        }

        $submission->items()->delete();
        $submission->delete();
        session()->flash('success', 'Laporan BKD berhasil dihapus.');
        $this->dispatch('pg:eventRefresh-lecturerWorkloadSubmissionTable');
    }

    public function actions(LecturerWorkloadSubmission $row): array
    {
        $actions = [];

        if (ActivePermission::check('lecturer-workload-submission.view')) {
            $actions[] = Button::add('show')->slot('<i class="fa fa-eye"></i>')->class('btn btn-primary')->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('lecturer-workload-submission.delete') && in_array($row->status, ['draft', 'cancelled', 'revision', 'rejected'])) {
            $actions[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }

        return $actions;
    }

    private function statusBadge(string $status): string
    {
        $class = match ($status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'revision' => 'bg-info',
            'in_approval', 'submitted' => 'bg-warning text-dark',
            'cancelled' => 'bg-secondary',
            default => 'bg-muted',
        };

        return '<span class="badge '.$class.'">'.str($status)->replace('_', ' ')->title().'</span>';
    }
}
