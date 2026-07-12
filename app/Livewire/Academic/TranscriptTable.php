<?php

namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Support\ActivePermission;
use App\Support\TranscriptSyncService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;

final class TranscriptTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'transcriptTable';

    protected bool $bulkActionEnabled = true;

    protected ?string $customBulkActionLabel = 'Bulk Sync';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->withCount('studyResults')
            ->withCount('transcriptEntries')
            ->withMax('studyResults as latest_cumulative_gpa', 'cumulative_gpa')
            ->orderByDesc('created_at');
    }

    public function relationSearch(): array
    {
        return [
            'user' => ['first_name', 'last_name', 'email'],
            'studyProgram' => ['name', 'code'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('student_name', fn (StudentProfile $model) => $model->user?->name ?? '-')
            ->add('nim')
            ->add('study_program', fn (StudentProfile $model) => $model->studyProgram?->name ?? '-')
            ->add('study_results_count')
            ->add('transcript_entries_count')
            ->add('latest_cumulative_gpa', fn (StudentProfile $model) => $model->latest_cumulative_gpa !== null
                ? number_format((float) $model->latest_cumulative_gpa, 2)
                : '-')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Mahasiswa', 'student_name')
                ->sortable()
                ->searchable(),
            Column::make('NIM', 'nim')
                ->sortable()
                ->searchable(),
            Column::make('Prodi', 'study_program')
                ->sortable()
                ->searchable(),
            Column::make('Semester Result', 'study_results_count')
                ->sortable(),
            Column::make('Transcript Entry', 'transcript_entries_count')
                ->sortable(),
            Column::make('IPK Snapshot', 'latest_cumulative_gpa')
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

                    return $query->whereHas('user', fn (Builder $user) => $user->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                }),
            Filter::inputText('nim', 'nim')
                ->placeholder('Cari NIM...')
                ->builder(function (Builder $query, $value) {
                    $search = is_array($value) ? ($value['value'] ?? '') : (string) $value;
                    if ($search === '') {
                        return $query;
                    }

                    return $query->where('nim', 'like', '%'.$search.'%');
                }),
            Filter::select('study_program', 'study_program_id')
                ->dataSource(StudyProgram::query()->orderBy('name')->get(['id', 'name']))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('study_program_id', $value)),
            Filter::number('study_results_count', 'study_results_count'),
            Filter::number('transcript_entries_count', 'transcript_entries_count'),
            Filter::datepicker('created_at', 'created_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $this->redirectRoute('admin.academic.transcripts.show', ['id' => $rowId]);
    }

    #[On('sync')]
    public function sync($rowId): void
    {
        if (! ActivePermission::check('transcript.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk sinkronisasi transcript.');

            return;
        }

        $studentProfile = StudentProfile::find($rowId);

        if (! $studentProfile) {
            session()->flash('error', 'Data mahasiswa tidak ditemukan.');

            return;
        }

        $service = new TranscriptSyncService;
        $result = $service->syncStudent((int) $studentProfile->id);

        session()->flash('success', 'Sinkronisasi transcript berhasil. Study results: '.$result['study_results'].', transcript entries: '.$result['transcript_entries'].'.');
        $this->dispatch('pg:eventRefresh-transcriptTable');
    }

    public function canCustomBulkAction(): bool
    {
        return ActivePermission::check('transcript.update');
    }

    public function runCustomBulkAction(): void
    {
        if (! ActivePermission::check('transcript.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk sinkronisasi transcript.');

            return;
        }

        $ids = collect($this->checkboxValues)->filter()->values();

        if ($ids->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu mahasiswa terlebih dahulu.');

            return;
        }

        $service = new TranscriptSyncService;
        $synced = 0;

        foreach (StudentProfile::query()->whereKey($ids)->get() as $studentProfile) {
            $service->syncStudent((int) $studentProfile->id);
            $synced++;
        }

        $this->dispatch('pg:eventRefresh-transcriptTable');
        $this->clearBulkSelection();
        session()->flash('success', $synced.' transcript mahasiswa berhasil disinkronkan.');
    }

    public function actions(StudentProfile $row): array
    {
        $actions = [];

        if (ActivePermission::check('transcript.view')) {
            $actions[] = Button::add('show')
                ->slot('<i class="fa fa-eye"></i> <span>Detail</span>')
                ->class('btn btn-sm btn-info d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]);
        }

        if (ActivePermission::check('transcript.update')) {
            $actions[] = Button::add('sync')
                ->slot('<i class="fa fa-rotate"></i> <span>Sync</span>')
                ->class('btn btn-sm btn-primary d-inline-flex align-items-center gap-1')
                ->dispatch('sync', ['rowId' => $row->id]);
        }

        return $actions;
    }
}
