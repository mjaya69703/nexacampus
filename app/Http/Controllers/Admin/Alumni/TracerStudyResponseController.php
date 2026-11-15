<?php

namespace App\Http\Controllers\Admin\Alumni;

use App\Enums\EmploymentStatus;
use App\Enums\JobRelevance;
use App\Http\Controllers\Controller;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use App\Support\ActivePermission;
use App\Support\Alumni\TracerStudyExportService;
use App\Support\Inertia\ShellProps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar & detail respon Tracer Study — Inertia React.
 *
 * Koreksi dari Blade lama:
 * - statistik memakai nilai enum yang benar (`working` + `entrepreneur`,
 *   `relevant`); dulu memakai `employed`/`freelance`/`highly_related`
 *   sehingga kartu ringkasan selalu 0;
 * - filter berjalan di query (bukan hanya menyembunyikan baris tabel);
 * - ekspor memakai service yang menghasilkan XLSX/PDF asli.
 */
class TracerStudyResponseController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $this->validateIndex($request);

        $sort = $validated['sort'] ?? 'submitted_at';
        $direction = $validated['direction'] ?? 'desc';
        $query = $this->filteredQuery($validated);
        $this->applySort($query, $sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 15);
        $responses = $query->paginate($perPage)->withQueryString();

        $campaign = filled($validated['campaign'] ?? null)
            ? TracerStudyCampaign::withTrashed()->find((int) $validated['campaign'])
            : null;

        $statsQuery = $this->filteredQuery($validated);
        $stats = $this->stats($statsQuery);

        return Inertia::render('Admin/Alumni/TracerResponse/Index', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Respon Tracer Study'),
            'can' => [
                'view' => ActivePermission::check('tracer-study-response.view'),
                'export' => ActivePermission::check('tracer-study-response.viewAny'),
            ],
            'campaign' => [
                'id' => $campaign?->id,
                'title' => $campaign?->title,
                'status' => $campaign?->status,
                'url' => $campaign
                    ? route('admin.alumni.tracer-study-campaigns.show', $campaign)
                    : null,
            ],
            'stats' => $stats,
            'data' => [
                'rows' => collect($responses->items())->values()->map(fn (TracerStudyResponse $response, int $i) => [
                    'id' => $response->id,
                    'no' => ($responses->firstItem() ?? 0) + $i,
                    'campaignTitle' => $response->campaign?->title,
                    'alumniName' => $response->alumniProfile?->full_name ?? '-',
                    'nim' => $response->alumniProfile?->nim ?? '-',
                    'studyProgram' => $response->alumniProfile?->studyProgram?->name ?? '-',
                    'graduationYear' => $response->alumniProfile?->graduation_year,
                    'employmentStatus' => $response->employment_status,
                    'employmentStatusLabel' => EmploymentStatus::tryFrom((string) $response->employment_status)?->label()
                        ?? ($response->employment_status ?? '-'),
                    'employerName' => $response->employer_name,
                    'jobTitle' => $response->job_title,
                    'jobRelevance' => $response->job_relevance,
                    'jobRelevanceLabel' => JobRelevance::tryFrom((string) $response->job_relevance)?->label()
                        ?? ($response->job_relevance ?? '-'),
                    'timeToEmployment' => $response->time_to_employment_months,
                    'furtherStudy' => (bool) $response->further_study,
                    'submittedAt' => $response->submitted_at?->format('d M Y H:i'),
                    'showUrl' => ActivePermission::check('tracer-study-response.view')
                        ? route('admin.alumni.tracer-study-responses.show', $response)
                        : null,
                ])->all(),
                'currentPage' => $responses->currentPage(),
                'lastPage' => $responses->lastPage(),
                'perPage' => $responses->perPage(),
                'total' => $responses->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'campaign' => $validated['campaign'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'graduation_year' => $validated['graduation_year'] ?? '',
                'employment_status' => $validated['employment_status'] ?? '',
                'job_relevance' => $validated['job_relevance'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'employmentStatusOptions' => EmploymentStatus::options(),
            'jobRelevanceOptions' => JobRelevance::options(),
            'campaignOptions' => $this->campaignOptions(),
            'studyProgramOptions' => $this->studyProgramOptions(),
            'graduationYearOptions' => $this->graduationYearOptions(),
            'urls' => [
                'index' => route('admin.alumni.tracer-study-responses.index'),
                'export' => route('admin.alumni.tracer-study-responses.export'),
            ],
        ]);
    }

    public function show(Request $request, TracerStudyResponse $tracerStudyResponse): Response
    {
        $tracerStudyResponse->load([
            'alumniProfile.studyProgram:id,name',
            'campaign:id,title,questions,total_sent,total_responded,status',
        ]);

        $campaign = $tracerStudyResponse->campaign;
        $questions = collect($campaign?->questions ?? [])
            ->values()
            ->map(fn (array $question, int $index) => [
                'id' => (string) ($question['id'] ?? 'q_'.($index + 1)),
                'type' => $question['type'] ?? 'text',
                'text' => $question['text'] ?? '',
            ])
            ->all();

        $answers = $tracerStudyResponse->answers ?? [];

        return Inertia::render('Admin/Alumni/TracerResponse/Show', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Detail Respon Tracer Study'),
            'response' => [
                'id' => $tracerStudyResponse->id,
                'campaignId' => $campaign?->id,
                'campaignTitle' => $campaign?->title,
                'alumniName' => $tracerStudyResponse->alumniProfile?->full_name ?? '-',
                'nim' => $tracerStudyResponse->alumniProfile?->nim ?? '-',
                'studyProgram' => $tracerStudyResponse->alumniProfile?->studyProgram?->name ?? '-',
                'graduationYear' => $tracerStudyResponse->alumniProfile?->graduation_year,
                'employmentStatus' => $tracerStudyResponse->employment_status,
                'employmentStatusLabel' => EmploymentStatus::tryFrom((string) $tracerStudyResponse->employment_status)?->label()
                    ?? ($tracerStudyResponse->employment_status ?? '-'),
                'employerName' => $tracerStudyResponse->employer_name,
                'jobTitle' => $tracerStudyResponse->job_title,
                'jobRelevance' => $tracerStudyResponse->job_relevance,
                'jobRelevanceLabel' => JobRelevance::tryFrom((string) $tracerStudyResponse->job_relevance)?->label()
                    ?? ($tracerStudyResponse->job_relevance ?? '-'),
                'timeToEmployment' => $tracerStudyResponse->time_to_employment_months,
                'salaryRange' => $tracerStudyResponse->salary_range,
                'furtherStudy' => (bool) $tracerStudyResponse->further_study,
                'submittedAt' => $tracerStudyResponse->submitted_at?->format('d M Y H:i'),
            ],
            'answers' => collect($questions)->map(fn (array $question) => [
                'id' => $question['id'],
                'type' => $question['type'],
                'text' => $question['text'],
                'answer' => $this->formatAnswer($answers[$question['id']] ?? null),
            ])->all(),
            'urls' => [
                'index' => route('admin.alumni.tracer-study-responses.index', ['campaign' => $campaign?->id]),
                'campaign' => $campaign
                    ? route('admin.alumni.tracer-study-campaigns.show', $campaign)
                    : null,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $format = (string) $request->query('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $validated = $this->validateIndex($request);
        $query = $this->filteredQuery($validated);
        $this->applySort($query, $validated['sort'] ?? 'submitted_at', $validated['direction'] ?? 'desc');

        $responses = $query->get();
        $campaign = filled($validated['campaign'] ?? null)
            ? TracerStudyCampaign::withTrashed()->find((int) $validated['campaign'])
            : null;

        $title = $campaign?->title ?? 'Semua Kampanye';
        $questions = $campaign?->questions ?? $this->collectQuestions($responses);

        return app(TracerStudyExportService::class)->streamResponses(
            $responses,
            $questions,
            'Respon '.$title,
            $format,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIndex(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'campaign' => 'nullable|integer|exists:tracer_study_campaigns,id',
            'study_program_id' => 'nullable|integer|exists:study_programs,id',
            'graduation_year' => 'nullable|integer|min:1990|max:2100',
            'employment_status' => ['nullable', Rule::in(array_keys(EmploymentStatus::options()))],
            'job_relevance' => ['nullable', Rule::in(array_keys(JobRelevance::options()))],
            'sort' => 'nullable|in:id,submitted_at,employment_status,employer_name,job_title',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function filteredQuery(array $validated): Builder
    {
        $query = TracerStudyResponse::query()
            ->with([
                'campaign:id,title',
                'alumniProfile:id,full_name,nim,study_program_id,graduation_year',
                'alumniProfile.studyProgram:id,name',
            ]);

        if (filled($validated['campaign'] ?? null)) {
            $query->where('tracer_study_campaign_id', $validated['campaign']);
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function (Builder $sub) use ($q) {
                $sub->whereHas('alumniProfile', function (Builder $profile) use ($q) {
                    $profile->where('full_name', 'like', "%{$q}%")
                        ->orWhere('nim', 'like', "%{$q}%");
                })->orWhere('employer_name', 'like', "%{$q}%")
                    ->orWhere('job_title', 'like', "%{$q}%");
            });
        }

        if (filled($validated['study_program_id'] ?? null)) {
            $query->whereHas('alumniProfile', fn (Builder $sub) => $sub->where('study_program_id', $validated['study_program_id']));
        }

        if (filled($validated['graduation_year'] ?? null)) {
            $query->whereHas('alumniProfile', fn (Builder $sub) => $sub->where('graduation_year', $validated['graduation_year']));
        }

        if (filled($validated['employment_status'] ?? null)) {
            $query->where('employment_status', $validated['employment_status']);
        }

        if (filled($validated['job_relevance'] ?? null)) {
            $query->where('job_relevance', $validated['job_relevance']);
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
    }

    /**
     * @return array<string, int|float>
     */
    private function stats(Builder $query): array
    {
        $total = (clone $query)->count();
        $working = (clone $query)->where('employment_status', EmploymentStatus::Working->value)->count();
        $entrepreneur = (clone $query)->where('employment_status', EmploymentStatus::Entrepreneur->value)->count();
        $studying = (clone $query)->where('employment_status', EmploymentStatus::Studying->value)->count();
        $unemployed = (clone $query)->where('employment_status', EmploymentStatus::Unemployed->value)->count();
        $relevant = (clone $query)->where('job_relevance', JobRelevance::Relevant->value)->count();
        $furtherStudy = (clone $query)->where('further_study', true)->count();
        $avgTime = (clone $query)->whereNotNull('time_to_employment_months')->avg('time_to_employment_months');

        return [
            'total' => $total,
            'working' => $working,
            'entrepreneur' => $entrepreneur,
            'studying' => $studying,
            'unemployed' => $unemployed,
            'employed' => $working + $entrepreneur,
            'employedPercentage' => $total > 0 ? round((($working + $entrepreneur) / $total) * 100, 1) : 0,
            'relevant' => $relevant,
            'relevantPercentage' => $total > 0 ? round(($relevant / $total) * 100, 1) : 0,
            'furtherStudy' => $furtherStudy,
            'avgTimeToEmployment' => $avgTime !== null ? round((float) $avgTime, 1) : null,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, TracerStudyResponse>  $responses
     * @return array<int, array<string, mixed>>
     */
    private function collectQuestions($responses): array
    {
        $questions = [];

        foreach ($responses as $response) {
            foreach (array_keys($response->answers ?? []) as $id) {
                if (! isset($questions[$id])) {
                    $questions[$id] = ['id' => (string) $id, 'type' => 'text', 'text' => (string) $id];
                }
            }
        }

        return array_values($questions);
    }

    private function formatAnswer(mixed $answer): string
    {
        if (is_array($answer)) {
            return implode(', ', array_map(fn ($value) => (string) $value, $answer));
        }

        return filled($answer) ? (string) $answer : '-';
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function campaignOptions(): array
    {
        return TracerStudyCampaign::withTrashed()
            ->orderByDesc('created_at')
            ->get(['id', 'title'])
            ->map(fn (TracerStudyCampaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function studyProgramOptions(): array
    {
        return StudyProgram::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (StudyProgram $program) => ['id' => $program->id, 'name' => $program->name])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function graduationYearOptions(): array
    {
        return AlumniProfile::query()
            ->whereNotNull('graduation_year')
            ->distinct()
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }
}
