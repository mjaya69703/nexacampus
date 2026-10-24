<?php

namespace App\Http\Controllers\Admin\Alumni;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Support\ActivePermission;
use App\Support\Alumni\TracerStudyService;
use App\Support\Inertia\ShellProps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Kampanye Tracer Study — kit CRUD shared (Inertia React).
 *
 * Koreksi dari Blade lama:
 * - pertanyaan menyimpan id yang stabil (dulu id hilang saat validasi sehingga
 *   jawaban tidak bisa dipetakan kembali ke pertanyaan);
 * - status aktif menghitung `total_sent` dari sasaran alumni, sehingga response
 *   rate tidak lagi selalu 0%;
 * - sasaran tahun lulus & program studi akhirnya bisa diatur dari UI;
 * - total_responded selalu disinkronkan dengan jumlah respon nyata.
 */
class TracerStudyCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $this->validateIndex($request);
        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query = $this->filteredQuery($validated, applySort: false);
        $this->applySort($query, $sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $campaigns = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Alumni/TracerStudy/Index', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Tracer Study'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('tracer-study-campaign.create'),
                'update' => ActivePermission::check('tracer-study-campaign.update'),
                'delete' => ActivePermission::check('tracer-study-campaign.delete'),
                'restore' => ActivePermission::any(['tracer-study-campaign.update', 'tracer-study-campaign.delete']),
                'activate' => ! $isTrash && ActivePermission::check('tracer-study-campaign.update'),
                'view' => ActivePermission::check('tracer-study-campaign.view'),
                'responses' => ActivePermission::check('tracer-study-response.viewAny'),
            ],
            'stats' => [
                'total' => TracerStudyCampaign::count(),
                'draft' => TracerStudyCampaign::where('status', CampaignStatus::Draft->value)->count(),
                'active' => TracerStudyCampaign::where('status', CampaignStatus::Active->value)->count(),
                'closed' => TracerStudyCampaign::where('status', CampaignStatus::Closed->value)->count(),
                'responses' => (int) TracerStudyCampaign::query()->sum('total_responded'),
                'trashed' => TracerStudyCampaign::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($campaigns->items())->values()->map(fn (TracerStudyCampaign $campaign, int $i) => [
                    'id' => $campaign->id,
                    'no' => ($campaigns->firstItem() ?? 0) + $i,
                    'title' => $campaign->title,
                    'academicYear' => $campaign->academicYear?->name,
                    'startDate' => $campaign->start_date?->format('d M Y'),
                    'endDate' => $campaign->end_date?->format('d M Y'),
                    'status' => $campaign->status,
                    'statusLabel' => CampaignStatus::tryFrom($campaign->status)?->label() ?? $campaign->status,
                    'totalSent' => (int) $campaign->total_sent,
                    'totalResponded' => (int) $campaign->total_responded,
                    'responsesCount' => (int) ($campaign->responses_count ?? 0),
                    'responseRate' => $campaign->responseRate(),
                    'questionsCount' => count($campaign->questions ?? []),
                    'targetYears' => $campaign->target_graduation_years ?? [],
                    'targetPrograms' => $this->targetProgramNames($campaign),
                    'createdAt' => $campaign->created_at?->format('d M Y H:i'),
                    'showUrl' => $isTrash ? null : route('admin.alumni.tracer-study-campaigns.show', $campaign),
                    'editUrl' => $isTrash ? null : route('admin.alumni.tracer-study-campaigns.edit', $campaign),
                    'deleteUrl' => route('admin.alumni.tracer-study-campaigns.destroy', $campaign),
                    'restoreUrl' => route('admin.alumni.tracer-study-campaigns.restore', $campaign->id),
                    'forceUrl' => route('admin.alumni.tracer-study-campaigns.force-destroy', $campaign->id),
                    'activateUrl' => route('admin.alumni.tracer-study-campaigns.activate', $campaign),
                    'responsesUrl' => route('admin.alumni.tracer-study-responses.index', ['campaign' => $campaign->id]),
                    'analyticsUrl' => route('admin.alumni.tracer-study.analytics', ['tracerStudyCampaign' => $campaign]),
                ])->all(),
                'currentPage' => $campaigns->currentPage(),
                'lastPage' => $campaigns->lastPage(),
                'perPage' => $campaigns->perPage(),
                'total' => $campaigns->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'academic_year_id' => $validated['academic_year_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'statusOptions' => CampaignStatus::options(),
            'academicYearOptions' => AcademicYear::query()
                ->orderByDesc('start_date')
                ->get(['id', 'name'])
                ->map(fn (AcademicYear $year) => ['id' => $year->id, 'name' => $year->name])
                ->all(),
            'urls' => [
                'index' => route('admin.alumni.tracer-study-campaigns.index'),
                'create' => route('admin.alumni.tracer-study-campaigns.create'),
                'export' => route('admin.alumni.tracer-study-campaigns.export'),
                'bulkDestroy' => route('admin.alumni.tracer-study-campaigns.bulk-destroy'),
                'bulkRestore' => route('admin.alumni.tracer-study-campaigns.bulk-restore'),
                'bulkForceDestroy' => route('admin.alumni.tracer-study-campaigns.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Alumni/TracerStudy/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Tambah Kampanye Tracer Study'),
            'mode' => 'create',
            'campaign' => $this->defaultCampaign(),
            'statusOptions' => CampaignStatus::options(),
            'academicYearOptions' => $this->academicYearOptions(),
            'studyProgramOptions' => $this->studyProgramOptions(),
            'yearOptions' => $this->yearOptions(),
            'urls' => [
                'index' => route('admin.alumni.tracer-study-campaigns.index'),
                'submit' => route('admin.alumni.tracer-study-campaigns.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCampaign($request);

        $campaign = TracerStudyCampaign::create([
            ...$this->campaignPayload($validated),
            'created_by' => $request->user()->id,
        ]);

        app(TracerStudyService::class)->syncCounters($campaign);

        return redirect()->route('admin.alumni.tracer-study-campaigns.index')
            ->with('success', 'Kampanye tracer study "'.$campaign->title.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, TracerStudyCampaign $tracerStudyCampaign): Response
    {
        $tracerStudyCampaign->loadCount('responses');

        $questions = collect($tracerStudyCampaign->questions ?? [])
            ->values()
            ->map(fn (array $question, int $index) => [
                'id' => (string) ($question['id'] ?? 'q_'.($index + 1)),
                'type' => $question['type'] ?? 'text',
                'text' => $question['text'] ?? '',
                'options' => implode("\n", $question['options'] ?? []),
            ])
            ->all();

        return Inertia::render('Admin/Alumni/TracerStudy/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Edit Kampanye Tracer Study'),
            'mode' => 'edit',
            'campaign' => [
                'id' => $tracerStudyCampaign->id,
                ...$this->defaultCampaign(),
                'title' => $tracerStudyCampaign->title,
                'description' => $tracerStudyCampaign->description ?? '',
                'academic_year_id' => $tracerStudyCampaign->academic_year_id,
                'target_graduation_years' => $tracerStudyCampaign->target_graduation_years ?? [],
                'target_study_program_ids' => $tracerStudyCampaign->target_study_program_ids ?? [],
                'start_date' => $tracerStudyCampaign->start_date?->toDateString() ?? '',
                'end_date' => $tracerStudyCampaign->end_date?->toDateString() ?? '',
                'status' => $tracerStudyCampaign->status,
                'questions' => $questions,
                'total_sent' => (int) $tracerStudyCampaign->total_sent,
                'total_responded' => (int) $tracerStudyCampaign->total_responded,
                'responses_count' => (int) $tracerStudyCampaign->responses_count,
            ],
            'statusOptions' => CampaignStatus::options(),
            'academicYearOptions' => $this->academicYearOptions(),
            'studyProgramOptions' => $this->studyProgramOptions(),
            'yearOptions' => $this->yearOptions(),
            'urls' => [
                'index' => route('admin.alumni.tracer-study-campaigns.index'),
                'submit' => route('admin.alumni.tracer-study-campaigns.update', $tracerStudyCampaign),
            ],
        ]);
    }

    public function update(Request $request, TracerStudyCampaign $tracerStudyCampaign)
    {
        $validated = $this->validateCampaign($request);

        // Status tidak boleh mundur: kampanye yang sudah berjalan tidak bisa
        // dikembalikan ke draft, dan kampanye yang sudah ditutup tidak bisa
        // dibuka kembali (data respon & counter jadi tidak konsisten).
        $currentStatus = $tracerStudyCampaign->status;
        $nextStatus = $validated['status'];

        if ($currentStatus === CampaignStatus::Active->value && $nextStatus === CampaignStatus::Draft->value) {
            throw ValidationException::withMessages([
                'status' => 'Kampanye yang sudah aktif tidak dapat dikembalikan ke draft.',
            ]);
        }

        if ($currentStatus === CampaignStatus::Closed->value && $nextStatus !== CampaignStatus::Closed->value) {
            throw ValidationException::withMessages([
                'status' => 'Kampanye yang sudah ditutup tidak dapat dibuka kembali.',
            ]);
        }

        $tracerStudyCampaign->update([
            ...$this->campaignPayload($validated),
            'updated_by' => $request->user()->id,
        ]);

        app(TracerStudyService::class)->syncCounters($tracerStudyCampaign);

        return redirect()->route('admin.alumni.tracer-study-campaigns.index')
            ->with('success', 'Kampanye tracer study "'.$tracerStudyCampaign->title.'" berhasil diperbarui.');
    }

    public function show(Request $request, TracerStudyCampaign $tracerStudyCampaign): Response
    {
        $tracerStudyCampaign->load([
            'academicYear:id,name',
            'createdByUser:id,first_name,last_name',
            'updatedByUser:id,first_name,last_name',
        ]);
        $tracerStudyCampaign->loadCount('responses');

        $recentResponses = $tracerStudyCampaign->responses()
            ->with(['alumniProfile:id,full_name,nim'])
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Alumni/TracerStudy/Show', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Detail Tracer Study'),
            'can' => [
                'update' => ActivePermission::check('tracer-study-campaign.update'),
                'delete' => ActivePermission::check('tracer-study-campaign.delete'),
                'activate' => ActivePermission::check('tracer-study-campaign.update')
                    && $tracerStudyCampaign->status === CampaignStatus::Draft->value,
                'responses' => ActivePermission::check('tracer-study-response.viewAny'),
            ],
            'campaign' => [
                'id' => $tracerStudyCampaign->id,
                'title' => $tracerStudyCampaign->title,
                'description' => $tracerStudyCampaign->description,
                'academicYear' => $tracerStudyCampaign->academicYear?->name,
                'targetYears' => $tracerStudyCampaign->target_graduation_years ?? [],
                'targetPrograms' => $this->targetProgramNames($tracerStudyCampaign),
                'startDate' => $tracerStudyCampaign->start_date?->format('d M Y'),
                'endDate' => $tracerStudyCampaign->end_date?->format('d M Y'),
                'status' => $tracerStudyCampaign->status,
                'statusLabel' => CampaignStatus::tryFrom($tracerStudyCampaign->status)?->label() ?? $tracerStudyCampaign->status,
                'totalSent' => (int) $tracerStudyCampaign->total_sent,
                'totalResponded' => (int) $tracerStudyCampaign->total_responded,
                'responsesCount' => (int) $tracerStudyCampaign->responses_count,
                'responseRate' => $tracerStudyCampaign->responseRate(),
                'questions' => collect($tracerStudyCampaign->questions ?? [])->values()->map(fn (array $question, int $index) => [
                    'id' => (string) ($question['id'] ?? 'q_'.($index + 1)),
                    'type' => $question['type'] ?? 'text',
                    'text' => $question['text'] ?? '',
                    'options' => $question['options'] ?? [],
                ])->all(),
                'createdAt' => $tracerStudyCampaign->created_at?->format('d M Y H:i'),
                'updatedAt' => $tracerStudyCampaign->updated_at?->format('d M Y H:i'),
                'createdBy' => $tracerStudyCampaign->createdByUser?->name,
                'updatedBy' => $tracerStudyCampaign->updatedByUser?->name,
            ],
            'recentResponses' => $recentResponses->map(fn ($response) => [
                'id' => $response->id,
                'name' => $response->alumniProfile?->full_name ?? '-',
                'nim' => $response->alumniProfile?->nim ?? '-',
                'submittedAt' => $response->submitted_at?->format('d M Y H:i'),
                'url' => route('admin.alumni.tracer-study-responses.show', $response),
            ])->all(),
            'urls' => [
                'index' => route('admin.alumni.tracer-study-campaigns.index'),
                'edit' => ActivePermission::check('tracer-study-campaign.update')
                    ? route('admin.alumni.tracer-study-campaigns.edit', $tracerStudyCampaign)
                    : null,
                'destroy' => route('admin.alumni.tracer-study-campaigns.destroy', $tracerStudyCampaign),
                'activate' => route('admin.alumni.tracer-study-campaigns.activate', $tracerStudyCampaign),
                'responses' => route('admin.alumni.tracer-study-responses.index', ['campaign' => $tracerStudyCampaign->id]),
                'analytics' => route('admin.alumni.tracer-study.analytics', ['tracerStudyCampaign' => $tracerStudyCampaign]),
            ],
        ]);
    }

    public function activate(Request $request, TracerStudyCampaign $tracerStudyCampaign)
    {
        try {
            app(TracerStudyService::class)->sendCampaign($tracerStudyCampaign);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $tracerStudyCampaign->refresh();

        return redirect()->route('admin.alumni.tracer-study-campaigns.show', $tracerStudyCampaign)
            ->with('success', 'Kampanye diaktifkan untuk '.$tracerStudyCampaign->total_sent.' alumni sasaran.');
    }

    public function destroy(Request $request, TracerStudyCampaign $tracerStudyCampaign)
    {
        $title = $tracerStudyCampaign->title;
        $tracerStudyCampaign->update(['updated_by' => $request->user()->id]);
        $tracerStudyCampaign->delete();

        return redirect()->route('admin.alumni.tracer-study-campaigns.index')
            ->with('success', 'Kampanye tracer study "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:tracer_study_campaigns,id',
        ]);

        $campaigns = TracerStudyCampaign::whereIn('id', $validated['ids'])->get();

        foreach ($campaigns as $campaign) {
            $campaign->update(['updated_by' => $request->user()->id]);
            $campaign->delete();
        }

        return back()->with('success', $campaigns->count().' kampanye tracer study berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $campaign = TracerStudyCampaign::withTrashed()->findOrFail($id);
        $campaign->restore();

        return back()->with('success', 'Kampanye tracer study "'.$campaign->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $campaign = TracerStudyCampaign::withTrashed()->findOrFail($id);
        $title = $campaign->title;
        $campaign->forceDelete();

        return back()->with('success', 'Kampanye tracer study "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:tracer_study_campaigns,id',
        ]);

        $count = 0;

        foreach (TracerStudyCampaign::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $campaign) {
            $campaign->restore();
            $count++;
        }

        return back()->with('success', $count.' kampanye tracer study berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:tracer_study_campaigns,id',
        ]);

        $count = 0;

        foreach (TracerStudyCampaign::withTrashed()->whereIn('id', $validated['ids'])->get() as $campaign) {
            $campaign->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' kampanye tracer study dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = $this->filteredQuery($request->all(), applySort: false);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $campaigns = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Judul', 'Tahun Akademik', 'Mulai', 'Selesai', 'Status', 'Target Tahun Lulus', 'Target Program Studi', 'Terkirim', 'Direspon', 'Response Rate (%)', 'Jumlah Pertanyaan', 'Dibuat'],
        ], null, 'A1');

        foreach ($campaigns as $i => $campaign) {
            $sheet->fromArray([
                $campaign->title,
                $campaign->academicYear?->name,
                $campaign->start_date?->format('Y-m-d'),
                $campaign->end_date?->format('Y-m-d'),
                CampaignStatus::tryFrom($campaign->status)?->label() ?? $campaign->status,
                implode(', ', $campaign->target_graduation_years ?? []),
                implode(', ', $this->targetProgramNames($campaign)),
                (int) $campaign->total_sent,
                (int) $campaign->total_responded,
                $campaign->responseRate(),
                count($campaign->questions ?? []),
                $campaign->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'tracer-study-campaigns-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIndex(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(array_keys(CampaignStatus::options()))],
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'sort' => 'nullable|in:id,title,status,start_date,end_date,total_sent,total_responded,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function filteredQuery(array $validated, bool $applySort = true): Builder
    {
        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $query = TracerStudyCampaign::query()
            ->with(['academicYear:id,name'])
            ->withCount('responses');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['academic_year_id'] ?? null)) {
            $query->where('academic_year_id', $validated['academic_year_id']);
        }

        if ($applySort) {
            $this->applySort($query, $validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc');
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'target_graduation_years' => 'nullable|array',
            'target_graduation_years.*' => 'integer|min:1990|max:2100',
            'target_study_program_ids' => 'nullable|array',
            'target_study_program_ids.*' => 'integer|exists:study_programs,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => ['required', Rule::in(array_keys(CampaignStatus::options()))],
            'questions' => 'nullable|array',
            'questions.*.id' => 'nullable|string|max:100',
            'questions.*.type' => 'required|in:text,textarea,radio,select',
            'questions.*.text' => 'required|string|max:1000',
            'questions.*.options' => 'nullable|string|max:5000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function campaignPayload(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'academic_year_id' => $validated['academic_year_id'] ?? null,
            'target_graduation_years' => filled($validated['target_graduation_years'] ?? [])
                ? array_values(array_map('intval', $validated['target_graduation_years']))
                : null,
            'target_study_program_ids' => filled($validated['target_study_program_ids'] ?? [])
                ? array_values(array_map('intval', $validated['target_study_program_ids']))
                : null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
            'questions' => $this->normalizeQuestions($validated['questions'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array{id: string, type: string, text: string, options: array<int, string>}>
     */
    private function normalizeQuestions(array $questions): array
    {
        $seenIds = [];

        return collect($questions)
            ->values()
            ->map(function (array $question, int $index) use (&$seenIds) {
                $id = trim((string) ($question['id'] ?? ''));

                if ($id === '') {
                    $id = 'q_'.($index + 1).'_'.Str::lower(Str::random(6));
                }

                // Cegah dua pertanyaan berbagi id yang sama (jawaban akan tertukar).
                while (in_array($id, $seenIds, true)) {
                    $id .= '_'.Str::lower(Str::random(4));
                }

                $seenIds[] = $id;

                $type = (string) $question['type'];
                $options = [];

                if (in_array($type, ['radio', 'select'], true)) {
                    $rawOptions = $question['options'] ?? [];
                    $options = is_array($rawOptions)
                        ? $rawOptions
                        : preg_split('/\r\n|\r|\n/', (string) $rawOptions);

                    $options = collect($options)
                        ->map(fn ($option) => trim((string) $option))
                        ->filter()
                        ->values()
                        ->all();
                }

                return [
                    'id' => $id,
                    'type' => $type,
                    'text' => trim((string) $question['text']),
                    'options' => $options,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function targetProgramNames(TracerStudyCampaign $campaign): array
    {
        $ids = $campaign->target_study_program_ids ?? [];

        if (empty($ids)) {
            return [];
        }

        return StudyProgram::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function academicYearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'name' => $year->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function studyProgramOptions(): array
    {
        return StudyProgram::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'code' => $program->code,
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $years = AlumniProfile::query()
            ->whereNotNull('graduation_year')
            ->distinct()
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year')
            ->map(fn ($year) => (int) $year)
            ->all();

        if ($years === []) {
            $years = [(int) now()->year];
        }

        return $years;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultCampaign(): array
    {
        return [
            'title' => '',
            'description' => '',
            'academic_year_id' => '',
            'target_graduation_years' => [],
            'target_study_program_ids' => [],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'status' => CampaignStatus::Draft->value,
            'questions' => [],
            'total_sent' => 0,
            'total_responded' => 0,
            'responses_count' => 0,
        ];
    }
}
