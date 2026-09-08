<?php

namespace App\Http\Controllers\AcademicLeader;

use App\Http\Controllers\Controller;
use App\Models\Organization\ApprovalStep;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\AcademicLeaderContext;
use App\Support\Organization\AcademicLeaderOversightService;
use App\Support\Organization\ApprovalEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DashboardController extends Controller
{
    public function index(Request $request, AcademicLeaderContext $context, AcademicLeaderOversightService $oversight): Response
    {
        $user = $request->user();
        $hasScope = $context->hasScope();

        $stats = [
            'bkd' => 0,
            'bkdApproval' => 0,
            'avgSks' => 0,
            'avgPerformance' => 0,
            'lecturers' => 0,
            'classes' => 0,
            'problemClasses' => 0,
            'alerts' => 0,
        ];
        $pendingApprovals = [];
        $waitingOnOthers = [];
        $problemClasses = [];
        $attentionLecturers = [];
        $submissions = [];
        $reviews = [];

        if ($hasScope) {
            $overview = $oversight->dashboardStats();
            $stats = array_merge($stats, [
                'bkd' => $this->scopedSubmissions()->count(),
                'bkdApproval' => $this->scopedSubmissions()->where('status', 'in_approval')->count(),
                'avgSks' => round((float) $this->scopedSubmissions()->avg('total_sks'), 1),
                'avgPerformance' => round((float) $this->scopedReviews()->avg('final_score'), 2),
                'lecturers' => $overview['lecturers'] ?? 0,
                'classes' => $overview['classes'] ?? 0,
                'problemClasses' => $overview['problem_classes'] ?? 0,
                'alerts' => $overview['alerts'] ?? 0,
            ]);

            $pendingApprovals = $this->pendingApprovals($request);
            $problemClasses = $this->problemClasses($oversight);
            $attentionLecturers = $this->attentionLecturers($oversight);
            $waitingOnOthers = $this->waitingOnOthers($pendingApprovals);

            $submissions = $this->scopedSubmissions()->latest('updated_at')->limit(6)->get()
                ->map(fn ($submission) => [
                    'id' => $submission->id,
                    'ownerName' => $submission->owner?->name ?? '-',
                    'programName' => $submission->lecturerProfile?->studyProgram?->name,
                    'periodName' => $submission->period?->name,
                    'totalSks' => (float) $submission->total_sks,
                    'status' => $submission->status,
                    'statusLabel' => $this->workloadStatusLabel($submission->status),
                    'url' => route('academic-leader.workloads.index'),
                ])->values()->all();

            $reviews = $this->scopedReviews()->latest('calculated_at')->limit(6)->get()
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'ownerName' => $review->owner?->name ?? '-',
                    'programName' => $review->lecturerProfile?->studyProgram?->name,
                    'finalScore' => $review->final_score !== null ? (float) $review->final_score : null,
                    'edomScore' => $review->edom_score !== null ? (float) $review->edom_score : null,
                    'responses' => $review->edom_response_count,
                    'url' => route('academic-leader.edom.index'),
                ])->values()->all();
        }

        return Inertia::render('AcademicLeader/Dashboard', [
            'shell' => ShellProps::make($user, 'Pemantauan Akademik', 'Dashboard Pimpinan'),
            'hasScope' => $hasScope,
            'scopeLabel' => $this->scopeLabel($context),
            'stats' => $stats,
            'pendingApprovals' => $pendingApprovals,
            'waitingOnOthers' => $waitingOnOthers,
            'problemClasses' => $problemClasses,
            'attentionLecturers' => $attentionLecturers,
            'submissions' => $submissions,
            'reviews' => $reviews,
            'urls' => [
                'lecturers' => route('academic-leader.lecturers.index'),
                'classes' => route('academic-leader.classes.index'),
                'workloads' => route('academic-leader.workloads.index'),
                'reports' => route('academic-leader.reports.index'),
                'edom' => route('academic-leader.edom.index'),
            ],
        ]);
    }

    public function approveStep(Request $request, ApprovalEngine $engine, int $stepId): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $step = ApprovalStep::with('request')->findOrFail($stepId);
        abort_unless($step->status === 'current', 422, 'Tahap ini sudah diproses.');
        abort_unless($engine->canUserActOnStep($request->user(), $step), 403);

        $engine->approve($step->request, $request->user(), $validated['notes'] ?? null);

        return redirect()->route('academic-leader.dashboard.index')->with('success', 'Pengajuan disetujui.');
    }

    public function rejectStep(Request $request, ApprovalEngine $engine, int $stepId): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['required', 'string', 'max:2000']]);

        $step = ApprovalStep::with('request')->findOrFail($stepId);
        abort_unless($step->status === 'current', 422, 'Tahap ini sudah diproses.');
        abort_unless($engine->canUserActOnStep($request->user(), $step), 403);

        $engine->reject($step->request, $request->user(), $validated['notes']);

        return redirect()->route('academic-leader.dashboard.index')->with('success', 'Pengajuan dikembalikan untuk revisi.');
    }

    /**
     * Antrean keputusan saya: step berstatus current pada request aktif
     * yang approvable-nya BKD dalam scope dan boleh saya proses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingApprovals(Request $request): array
    {
        $user = $request->user();
        $engine = app(ApprovalEngine::class);

        $candidates = ApprovalStep::query()
            ->with(['request', 'templateStep'])
            ->where('status', 'current')
            ->whereHas('request', fn ($query) => $query->whereIn('status', ['submitted', 'in_progress']))
            ->latest()
            ->limit(30)
            ->get();

        $rows = [];

        foreach ($candidates as $step) {
            $approvable = $step->request?->approvable;

            if (! $approvable instanceof LecturerWorkloadSubmission) {
                continue;
            }

            if (! $this->submissionInScope($approvable)) {
                continue;
            }

            if (! $engine->canUserActOnStep($user, $step)) {
                continue;
            }

            $approvable->loadMissing(['owner', 'period', 'lecturerProfile.studyProgram']);

            $rows[] = [
                'stepId' => $step->id,
                'submissionId' => $approvable->id,
                'stepName' => $step->name ?? $step->templateStep?->name ?? 'Tahap approval',
                'subject' => $step->request?->subject ?? '-',
                'ownerName' => $approvable->owner?->name ?? '-',
                'programName' => $approvable->lecturerProfile?->studyProgram?->name,
                'totalSks' => (float) $approvable->total_sks,
                'dueLabel' => $step->due_at?->format('d M Y H:i'),
                'isOverdue' => $step->due_at !== null && $step->due_at->isPast(),
            ];

            if (count($rows) >= 8) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Pengajuan in_approval dalam scope yang tahap current-nya BUKAN
     * di meja saya (menunggu kaprodi/dekan lain atau tendik).
     * Menjawab kebingungan "kok 0 antrean tapi ada yang menunggu".
     *
     * @param array<int, array<string, mixed>> $pendingApprovals
     * @return array<int, array<string, mixed>>
     */
    private function waitingOnOthers(array $pendingApprovals): array
    {
        $mine = collect($pendingApprovals)->pluck('submissionId')->filter()->all();

        return $this->scopedSubmissions()
            ->where('status', 'in_approval')
            ->when($mine !== [], fn ($query) => $query->whereNotIn('lecturer_workload_submissions.id', $mine))
            ->with('approvalRequest.steps')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function ($submission) {
                $step = $submission->approvalRequest?->steps
                    ->firstWhere('status', 'current');

                return [
                    'ownerName' => $submission->owner?->name ?? '-',
                    'programName' => $submission->lecturerProfile?->studyProgram?->name,
                    'totalSks' => (float) $submission->total_sks,
                    'stepName' => $step?->name ?? 'Tahap approval',
                    'dueLabel' => $step?->due_at?->format('d M Y'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function problemClasses(AcademicLeaderOversightService $oversight): array
    {
        return collect($oversight->alerts())
            ->filter(fn ($alert) => in_array($alert['level'] ?? null, ['danger', 'warning'], true))
            ->take(8)
            ->map(fn ($alert) => [
                'level' => $alert['level'],
                'title' => $alert['title'],
                'description' => $alert['description'] ?? '',
                'target' => $alert['target'] ?? '',
                'url' => ($alert['target'] ?? '') === 'lecturers'
                    ? route('academic-leader.lecturers.index')
                    : route('academic-leader.classes.index'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attentionLecturers(AcademicLeaderOversightService $oversight): array
    {
        return collect($oversight->lecturerRows())
            ->filter(fn ($row) => in_array($row['workload_status'], ['none', 'draft', 'revision'], true)
                || ($row['performance_score'] !== null && (float) $row['performance_score'] < 70))
            ->take(6)
            ->map(fn ($row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'programName' => $row['program'],
                'workloadLabel' => $row['workload_status_label'],
                'score' => $row['performance_score'] !== null ? (float) $row['performance_score'] : null,
                'url' => route('academic-leader.lecturers.show', $row['id']),
            ])
            ->values()
            ->all();
    }

    private function scopedSubmissions()
    {
        $context = app(AcademicLeaderContext::class);
        $facultyIds = $context->facultyIds();
        $programIds = $context->studyProgramIds();

        return LecturerWorkloadSubmission::query()
            ->with(['owner', 'period', 'lecturerProfile.studyProgram'])
            ->whereHas('lecturerProfile', function ($query) use ($facultyIds, $programIds) {
                $query->where(function ($nested) use ($facultyIds, $programIds) {
                    if ($programIds) {
                        $nested->orWhereIn('study_program_id', $programIds);
                    }
                    if ($facultyIds) {
                        $nested->orWhereIn('faculty_id', $facultyIds);
                    }
                    if (! $programIds && ! $facultyIds) {
                        $nested->whereRaw('1 = 0');
                    }
                });
            });
    }

    private function scopedReviews()
    {
        $context = app(AcademicLeaderContext::class);
        $facultyIds = $context->facultyIds();
        $programIds = $context->studyProgramIds();

        return \App\Models\Organization\LecturerPerformanceReview::query()
            ->with(['owner', 'edomPeriod', 'lecturerProfile.studyProgram'])
            ->whereHas('lecturerProfile', function ($query) use ($facultyIds, $programIds) {
                $query->where(function ($nested) use ($facultyIds, $programIds) {
                    if ($programIds) {
                        $nested->orWhereIn('study_program_id', $programIds);
                    }
                    if ($facultyIds) {
                        $nested->orWhereIn('faculty_id', $facultyIds);
                    }
                    if (! $programIds && ! $facultyIds) {
                        $nested->whereRaw('1 = 0');
                    }
                });
            });
    }

    private function submissionInScope(LecturerWorkloadSubmission $submission): bool
    {
        $context = app(AcademicLeaderContext::class);
        $profile = $submission->lecturerProfile()->withTrashed()->first() ?? $submission->lecturerProfile;

        if (! $profile) {
            return false;
        }

        $facultyIds = $context->facultyIds();
        $programIds = $context->studyProgramIds();

        return ($programIds !== [] && in_array($profile->study_program_id, $programIds, true))
            || ($facultyIds !== [] && in_array($profile->faculty_id, $facultyIds, true));
    }

    private function scopeLabel(AcademicLeaderContext $context): ?string
    {
        if (! $context->hasScope()) {
            return null;
        }

        $parts = [];
        if ($context->studyProgramIds() !== []) {
            $parts[] = count($context->studyProgramIds()).' program studi';
        }
        if ($context->facultyIds() !== []) {
            $parts[] = count($context->facultyIds()).' fakultas';
        }

        return implode(' · ', $parts) ?: 'Tersedia';
    }

    private function workloadStatusLabel(?string $status): string
    {
        return match ($status) {
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => 'Draf',
        };
    }
}
