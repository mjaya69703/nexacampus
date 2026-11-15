<?php

namespace App\Http\Controllers\Alumni;

use App\Enums\CampaignStatus;
use App\Enums\EmploymentStatus;
use App\Enums\JobRelevance;
use App\Http\Controllers\Controller;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use App\Support\Alumni\TracerStudyService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Tracer study (portal) — daftar kampanye, detail, dan pengisian survei.
 *
 * Koreksi dari Blade lama:
 * - kampanye hanya muncul untuk alumni yang masuk kriteria sasaran
 *   (tahun lulus / program studi) dan hanya saat periode aktif;
 * - jawaban setiap pertanyaan wajib diisi dan pilihan radio/select
 *   divalidasi terhadap opsi yang tersedia;
 * - snapshots karir memakai nilai enum yang valid dan dibersihkan saat
 *   alumni tidak sedang bekerja;
 * - submit ulang / kampanye yang sudah ditutup ditolak dengan pesan jelas.
 */
class PortalTracerStudyController extends Controller
{
    public function __construct(private readonly TracerStudyService $tracerStudyService) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->alumniProfile()->first();

        if (! $profile) {
            return Inertia::render('Alumni/TracerStudy/Index', [
                'shell' => ShellProps::make($request->user(), 'Tracer Study', 'Tracer Study'),
                'hasProfile' => false,
                'campaigns' => [],
                'urls' => [
                    'index' => route('alumni.tracer-study.index'),
                    'profile' => route('alumni.profile.index'),
                ],
            ]);
        }

        $responded = TracerStudyResponse::query()
            ->where('alumni_profile_id', $profile->id)
            ->pluck('tracer_study_campaign_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Tampilkan kampanye yang sedang aktif ATAU yang sudah pernah diisi
        // (agar alumni tetap bisa melihat jawabannya setelah kampanye ditutup).
        $campaigns = TracerStudyCampaign::query()
            ->with('academicYear:id,name')
            ->where(function ($query) use ($profile): void {
                $query->where(function ($query): void {
                    $query->where('status', CampaignStatus::Active->value)
                        ->whereDate('start_date', '<=', today())
                        ->whereDate('end_date', '>=', today());
                })->orWhereHas('responses', fn ($query) => $query->where('alumni_profile_id', $profile->id));
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (TracerStudyCampaign $campaign) => $this->tracerStudyService->targetsProfile($campaign, $profile))
            ->values();

        return Inertia::render('Alumni/TracerStudy/Index', [
            'shell' => ShellProps::make($request->user(), 'Tracer Study', 'Tracer Study'),
            'hasProfile' => true,
            'campaigns' => $campaigns->map(function (TracerStudyCampaign $campaign) use ($responded): array {
                $hasResponded = in_array((int) $campaign->id, $responded, true);
                $isOpen = $this->tracerStudyService->isOpen($campaign);

                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                    'description' => $campaign->description,
                    'academicYear' => $campaign->academicYear?->name,
                    'startDate' => $campaign->start_date?->format('d M Y'),
                    'endDate' => $campaign->end_date?->format('d M Y'),
                    'questionsCount' => count($campaign->questions ?? []),
                    'hasResponded' => $hasResponded,
                    'isOpen' => $isOpen,
                    'statusLabel' => $hasResponded
                        ? 'Sudah diisi'
                        : ($isOpen ? 'Belum diisi' : 'Ditutup'),
                    'showUrl' => route('alumni.tracer-study.show', $campaign),
                    'fillUrl' => $isOpen && ! $hasResponded
                        ? route('alumni.tracer-study.fill', $campaign)
                        : null,
                ];
            })->all(),
            'urls' => [
                'index' => route('alumni.tracer-study.index'),
                'profile' => route('alumni.profile.edit'),
            ],
        ]);
    }

    public function show(Request $request, TracerStudyCampaign $tracerStudyCampaign): Response
    {
        $profile = $this->profileOrFail($request);

        abort_if($tracerStudyCampaign->status === CampaignStatus::Draft->value, 404);
        abort_unless($this->tracerStudyService->targetsProfile($tracerStudyCampaign, $profile), 404);

        $tracerStudyCampaign->load(['academicYear:id,name']);

        $response = TracerStudyResponse::query()
            ->where('tracer_study_campaign_id', $tracerStudyCampaign->id)
            ->where('alumni_profile_id', $profile->id)
            ->first();

        $isOpen = $this->tracerStudyService->isOpen($tracerStudyCampaign);

        return Inertia::render('Alumni/TracerStudy/Show', [
            'shell' => ShellProps::make($request->user(), 'Tracer Study', $tracerStudyCampaign->title),
            'campaign' => [
                'id' => $tracerStudyCampaign->id,
                'title' => $tracerStudyCampaign->title,
                'description' => $tracerStudyCampaign->description,
                'academicYear' => $tracerStudyCampaign->academicYear?->name,
                'startDate' => $tracerStudyCampaign->start_date?->format('d M Y'),
                'endDate' => $tracerStudyCampaign->end_date?->format('d M Y'),
                'status' => $tracerStudyCampaign->status,
                'statusLabel' => CampaignStatus::tryFrom((string) $tracerStudyCampaign->status)?->label()
                    ?? $tracerStudyCampaign->status,
                'questions' => collect($tracerStudyCampaign->questions ?? [])
                    ->values()
                    ->map(fn (array $question, int $index) => [
                        'id' => $this->questionKey($question, $index),
                        'type' => $question['type'] ?? 'text',
                        'typeLabel' => $this->questionTypeLabel($question['type'] ?? 'text'),
                        'text' => $question['text'] ?? '',
                        'options' => $question['options'] ?? [],
                    ])->all(),
            ],
            'hasResponded' => (bool) $response,
            'submittedAt' => $response?->submitted_at?->format('d M Y, H:i'),
            'canFill' => $isOpen && ! $response && count($tracerStudyCampaign->questions ?? []) > 0,
            'urls' => [
                'index' => route('alumni.tracer-study.index'),
                'fill' => route('alumni.tracer-study.fill', $tracerStudyCampaign),
            ],
        ]);
    }

    public function fill(Request $request, TracerStudyCampaign $tracerStudyCampaign): Response|RedirectResponse
    {
        $profile = $this->profileOrFail($request);

        abort_unless($this->tracerStudyService->targetsProfile($tracerStudyCampaign, $profile), 404);

        if (! $this->tracerStudyService->isOpen($tracerStudyCampaign)) {
            return redirect()
                ->route('alumni.tracer-study.show', $tracerStudyCampaign)
                ->with('error', 'Survei ini sudah tidak aktif.');
        }

        $alreadyResponded = TracerStudyResponse::query()
            ->where('tracer_study_campaign_id', $tracerStudyCampaign->id)
            ->where('alumni_profile_id', $profile->id)
            ->exists();

        if ($alreadyResponded) {
            return redirect()
                ->route('alumni.tracer-study.show', $tracerStudyCampaign)
                ->with('info', 'Kamu sudah mengisi survei ini.');
        }

        $questions = collect($tracerStudyCampaign->questions ?? [])->values();

        if ($questions->isEmpty()) {
            return redirect()
                ->route('alumni.tracer-study.show', $tracerStudyCampaign)
                ->with('error', 'Kampanye ini belum memiliki pertanyaan.');
        }

        return Inertia::render('Alumni/TracerStudy/Fill', [
            'shell' => ShellProps::make($request->user(), 'Tracer Study', 'Isi Survei: '.$tracerStudyCampaign->title),
            'campaign' => [
                'id' => $tracerStudyCampaign->id,
                'title' => $tracerStudyCampaign->title,
                'startDate' => $tracerStudyCampaign->start_date?->format('d M Y'),
                'endDate' => $tracerStudyCampaign->end_date?->format('d M Y'),
                'questions' => $questions->map(fn (array $question, int $index) => [
                    'id' => $this->questionKey($question, $index),
                    'type' => $question['type'] ?? 'text',
                    'text' => $question['text'] ?? '',
                    'options' => $question['options'] ?? [],
                ])->all(),
            ],
            'snapshot' => [
                'employment_status' => $profile->employment_status ?: EmploymentStatus::Unemployed->value,
                'employer_name' => $profile->employer_name ?? '',
                'job_title' => $profile->job_title ?? '',
                'job_relevance' => '',
                'time_to_employment_months' => '',
                'salary_range' => '',
                'further_study' => false,
            ],
            'employmentStatusOptions' => EmploymentStatus::options(),
            'jobRelevanceOptions' => JobRelevance::options(),
            'urls' => [
                'show' => route('alumni.tracer-study.show', $tracerStudyCampaign),
                'submit' => route('alumni.tracer-study.submit', $tracerStudyCampaign),
            ],
        ]);
    }

    public function submit(Request $request, TracerStudyCampaign $tracerStudyCampaign): RedirectResponse
    {
        $profile = $this->profileOrFail($request);

        abort_unless($this->tracerStudyService->targetsProfile($tracerStudyCampaign, $profile), 404);

        if (! $this->tracerStudyService->isOpen($tracerStudyCampaign)) {
            return redirect()
                ->route('alumni.tracer-study.show', $tracerStudyCampaign)
                ->with('error', 'Survei ini sudah tidak aktif.');
        }

        $alreadyResponded = TracerStudyResponse::query()
            ->where('tracer_study_campaign_id', $tracerStudyCampaign->id)
            ->where('alumni_profile_id', $profile->id)
            ->exists();

        if ($alreadyResponded) {
            return redirect()
                ->route('alumni.tracer-study.show', $tracerStudyCampaign)
                ->with('info', 'Kamu sudah mengisi survei ini.');
        }

        $validated = $request->validate([
            'snapshot.employment_status' => ['required', Rule::in(array_keys(EmploymentStatus::options()))],
            'snapshot.employer_name' => ['nullable', 'string', 'max:255'],
            'snapshot.job_title' => ['nullable', 'string', 'max:255'],
            'snapshot.job_relevance' => ['nullable', Rule::in(array_keys(JobRelevance::options()))],
            'snapshot.time_to_employment_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'snapshot.salary_range' => ['nullable', 'string', 'max:100'],
            'snapshot.further_study' => ['boolean'],
            'answers' => ['required', 'array'],
        ]);

        $snapshot = $validated['snapshot'];
        $answers = $this->validateAnswers(
            $tracerStudyCampaign,
            $request->input('answers', []),
        );

        $employed = in_array($snapshot['employment_status'], [
            EmploymentStatus::Working->value,
            EmploymentStatus::Entrepreneur->value,
        ], true);

        $employmentSnapshot = [
            'employment_status' => $snapshot['employment_status'],
            'employer_name' => $employed && filled($snapshot['employer_name'] ?? null) ? $snapshot['employer_name'] : null,
            'job_title' => $employed && filled($snapshot['job_title'] ?? null) ? $snapshot['job_title'] : null,
            'job_relevance' => $employed && filled($snapshot['job_relevance'] ?? null) ? $snapshot['job_relevance'] : null,
            'time_to_employment_months' => $employed && filled($snapshot['time_to_employment_months'] ?? null)
                ? (int) $snapshot['time_to_employment_months']
                : null,
            'salary_range' => $employed && filled($snapshot['salary_range'] ?? null) ? $snapshot['salary_range'] : null,
            'further_study' => (bool) ($snapshot['further_study'] ?? false),
        ];

        try {
            DB::transaction(function () use ($tracerStudyCampaign, $profile, $answers, $employmentSnapshot): void {
                // Kunci kampanye agar dua submit bersamaan tidak lolos
                // pengecekan duplikat dan menabrak unique index.
                TracerStudyCampaign::query()
                    ->whereKey($tracerStudyCampaign->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (TracerStudyResponse::query()
                    ->where('tracer_study_campaign_id', $tracerStudyCampaign->id)
                    ->where('alumni_profile_id', $profile->id)
                    ->exists()) {
                    throw new RuntimeException('Kamu sudah mengisi survei ini.');
                }

                $this->tracerStudyService->submitResponse(
                    $tracerStudyCampaign,
                    $profile,
                    $answers,
                    $employmentSnapshot,
                );
            });
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'answers' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('alumni.tracer-study.show', $tracerStudyCampaign)
            ->with('success', 'Terima kasih! Jawaban tracer study kamu berhasil disimpan.');
    }

    private function profileOrFail(Request $request): AlumniProfile
    {
        $profile = $request->user()->alumniProfile()->first();

        abort_unless($profile, 404, 'Profil alumni belum tersedia.');

        return $profile;
    }

    /**
     * Pastikan semua pertanyaan terjawab dan jawaban pilihan sesuai opsi.
     *
     * @param  array<string|int, mixed>  $answers
     * @return array<string, string>
     */
    private function validateAnswers(TracerStudyCampaign $campaign, array $answers): array
    {
        $questions = collect($campaign->questions ?? [])->values();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'answers' => 'Kampanye ini belum memiliki pertanyaan.',
            ]);
        }

        $clean = [];

        foreach ($questions as $index => $question) {
            $id = $this->questionKey($question, $index);
            $type = $question['type'] ?? 'text';
            $raw = $answers[$id] ?? null;
            $value = is_scalar($raw) ? trim((string) $raw) : '';

            if ($value === '') {
                throw ValidationException::withMessages([
                    "answers.{$id}" => 'Jawaban untuk pertanyaan ini wajib diisi.',
                ]);
            }

            if (mb_strlen($value) > 5000) {
                throw ValidationException::withMessages([
                    "answers.{$id}" => 'Jawaban maksimal 5000 karakter.',
                ]);
            }

            if (in_array($type, ['radio', 'select'], true)) {
                $options = array_map(fn ($option) => trim((string) $option), $question['options'] ?? []);

                if (! in_array($value, $options, true)) {
                    throw ValidationException::withMessages([
                        "answers.{$id}" => 'Pilihan jawaban tidak valid.',
                    ]);
                }
            }

            $clean[$id] = $value;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function questionKey(array $question, int $index): string
    {
        $id = trim((string) ($question['id'] ?? ''));

        return $id !== '' ? $id : 'q_'.($index + 1);
    }

    private function questionTypeLabel(string $type): string
    {
        return match ($type) {
            'radio' => 'Pilihan Ganda',
            'select' => 'Dropdown',
            'textarea' => 'Teks Panjang',
            default => 'Teks',
        };
    }
}
