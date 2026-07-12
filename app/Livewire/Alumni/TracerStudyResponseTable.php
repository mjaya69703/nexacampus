<?php

namespace App\Livewire\Alumni;

use App\Enums\EmploymentStatus;
use App\Enums\JobRelevance;
use App\Livewire\BasePowerGridTable;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyResponse;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TracerStudyResponseTable extends BasePowerGridTable
{
    public string $tableName = 'tracerStudyResponseTable';

    protected bool $bulkActionEnabled = false;

    public ?int $campaignId = null;

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true);
    }

    public function datasource(): Builder
    {
        $query = TracerStudyResponse::query()
            ->with(['alumniProfile.studyProgram', 'campaign'])
            ->orderByDesc('submitted_at');

        if ($this->campaignId) {
            $query->where('tracer_study_campaign_id', $this->campaignId);
        }

        return $query;
    }

    public function relationSearch(): array
    {
        return [
            'alumniProfile' => ['nim', 'full_name'],
            'alumniProfile.studyProgram' => ['name', 'code'],
            'campaign' => ['title'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('alumni_name', fn (TracerStudyResponse $m) => $m->alumniProfile?->full_name ?? '-')
            ->add('nim', fn (TracerStudyResponse $m) => $m->alumniProfile?->nim ?? '-')
            ->add('study_program_name', fn (TracerStudyResponse $m) => $m->alumniProfile?->studyProgram?->name ?? '-')
            ->add('graduation_year', fn (TracerStudyResponse $m) => $m->alumniProfile?->graduation_year ?? '-')
            ->add('employment_status_label', fn (TracerStudyResponse $m) => EmploymentStatus::tryFrom($m->employment_status)?->label() ?? $m->employment_status ?? '-')
            ->add('job_relevance_label', fn (TracerStudyResponse $m) => $m->job_relevance
                ? str($m->job_relevance)->replace('_', ' ')->title()->toString()
                : '-'
            )
            ->add('submitted_at_label', fn (TracerStudyResponse $m) => $m->submitted_at?->format('d M Y H:i') ?? '-')
            ->add('campaign_title', fn (TracerStudyResponse $m) => $m->campaign?->title ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Nama Alumni', 'alumni_name')->sortable()->searchable(),
            Column::make('NIM', 'nim')->sortable()->searchable(),
            Column::make('Program Studi', 'study_program_name')->sortable()->searchable(),
            Column::make('Tahun Lulus', 'graduation_year')->sortable(),
            Column::make('Status Kerja', 'employment_status_label')->sortable()->searchable(),
            Column::make('Relevansi', 'job_relevance_label')->sortable(),
            Column::make('Submitted', 'submitted_at_label')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('alumni_name')->placeholder('Cari nama alumni...')
                ->builder(fn (Builder $query, $value) => $query->whereHas('alumniProfile', fn ($q) => $q->where('full_name', 'like', '%'.$value.'%'))),
            Filter::inputText('nim')->placeholder('Cari NIM alumni...')
                ->builder(fn (Builder $query, $value) => $query->whereHas('alumniProfile', fn ($q) => $q->where('nim', 'like', '%'.$value.'%'))),
            Filter::select('study_program_name', 'study_program_id')
                ->dataSource(
                    StudyProgram::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (StudyProgram $sp) => [
                            'id' => $sp->id,
                            'name' => $sp->name,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('alumniProfile', fn ($q) => $q->where('study_program_id', $value))),
            Filter::select('graduation_year', 'graduation_year')
                ->dataSource(
                    AlumniProfile::query()
                        ->select('graduation_year')
                        ->whereNotNull('graduation_year')
                        ->distinct()
                        ->orderByDesc('graduation_year')
                        ->pluck('graduation_year')
                        ->filter()
                        ->map(fn ($year) => [
                            'id' => (string) $year,
                            'name' => (string) $year,
                        ])
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->whereHas('alumniProfile', fn ($q) => $q->where('graduation_year', $value))),
            Filter::select('employment_status_label', 'employment_status')
                ->dataSource(collect(EmploymentStatus::options())
                    ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
                    ->values()
                    ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('employment_status', $value)),
            Filter::select('job_relevance_label', 'job_relevance')
                ->dataSource(collect(JobRelevance::options())
                    ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
                    ->values()
                    ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => $query->where('job_relevance', $value)),
            Filter::datepicker('submitted_at_label', 'submitted_at'),
        ];
    }

    #[On('show')]
    public function show($rowId): void
    {
        $response = TracerStudyResponse::find($rowId);

        if ($response) {
            $this->redirectRoute('admin.alumni.tracer-study-responses.show', ['id' => $response->id]);
        }
    }

    public function actions(TracerStudyResponse $row): array
    {
        if (! ActivePermission::check('tracer-study-response.view')) {
            return [];
        }

        return [
            Button::add('show')
                ->slot('<i class="fa fa-eye"></i> Detail')
                ->class('btn btn-outline-info rounded-pill px-2.5 py-1 text-info fw-medium shadow-sm d-inline-flex align-items-center gap-1')
                ->dispatch('show', ['rowId' => $row->id]),
        ];
    }
}
