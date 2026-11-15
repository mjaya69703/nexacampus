<?php

namespace App\Http\Controllers\AcademicLeader;

use App\Http\Controllers\Controller;
use App\Models\Academic\LecturerProfile;
use App\Models\Organization\LecturerPerformanceReview;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\AcademicLeaderContext;
use App\Support\Organization\AcademicLeaderOversightService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar dan detail dosen dalam scope jabatan pimpinan akademik.
 *
 * Koreksi dari Blade lama: statistik mengambil data scope penuh, bukan
 * hasil filter; filter status memvalidasi nilai agar URL manual tidak
 * menghasilkan daftar kosong yang membingungkan.
 */
class LecturerOversightController extends Controller
{
    public function index(Request $request, AcademicLeaderOversightService $oversight): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'all', 'none', 'draft', 'revision', 'in_approval', 'approved', 'rejected', 'cancelled',
            ])],
        ]);

        $status = $validated['status'] ?? 'all';
        $needle = mb_strtolower(trim((string) ($validated['q'] ?? '')));
        $allRows = $oversight->lecturerRows($request->user());

        $rows = $allRows
            ->filter(function (array $row) use ($needle, $status): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $row['name'],
                    $row['nidn'],
                    $row['program'],
                    $row['faculty'],
                ]));

                return ($needle === '' || str_contains($haystack, $needle))
                    && ($status === 'all' || $row['workload_status'] === $status);
            })
            ->values()
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'nidn' => $row['nidn'],
                'program' => $row['program'],
                'faculty' => $row['faculty'],
                'activeClasses' => $row['active_classes'],
                'workloadStatus' => $row['workload_status'],
                'workloadStatusLabel' => $row['workload_status_label'],
                'workloadSks' => (float) $row['workload_sks'],
                'workloadPeriod' => $row['workload_period'],
                'performanceScore' => $row['performance_score'] !== null ? (float) $row['performance_score'] : null,
                'edomScore' => $row['edom_score'] !== null ? (float) $row['edom_score'] : null,
                'edomResponses' => $row['edom_responses'],
                'edomPeriod' => $row['edom_period'],
                'url' => route('academic-leader.lecturers.show', $row['id']),
            ])
            ->all();

        $statusOptions = collect(['all', 'none', 'draft', 'revision', 'in_approval', 'approved', 'rejected', 'cancelled'])
            ->mapWithKeys(fn (string $value) => [
                $value => $value === 'all'
                    ? 'Semua status BKD'
                    : $oversight->workloadStatusLabel($value === 'none' ? null : $value),
            ])
            ->all();

        return Inertia::render('AcademicLeader/Lecturers/Index', [
            'shell' => ShellProps::make($request->user(), 'Pemantauan Akademik', 'Dosen Dalam Scope'),
            'rows' => $rows,
            'filters' => [
                'q' => (string) ($validated['q'] ?? ''),
                'status' => $status,
            ],
            'statusOptions' => $statusOptions,
            'stats' => [
                'lecturers' => $allRows->count(),
                'classes' => $allRows->sum('active_classes'),
                'pending' => $allRows->whereIn('workload_status', ['none', 'draft', 'revision', 'in_approval'])->count(),
                'avgScore' => round((float) ($allRows->filter(fn ($row) => $row['performance_score'] !== null)->avg('performance_score') ?: 0), 2),
            ],
            'urls' => [
                'index' => route('academic-leader.lecturers.index'),
                'export' => [
                    'csv' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'csv']),
                    'xlsx' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'xlsx']),
                    'pdf' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'pdf']),
                ],
            ],
        ]);
    }

    public function show(
        Request $request,
        LecturerProfile $lecturerProfile,
        AcademicLeaderContext $context,
    ): Response {
        abort_unless($context->canAccessLecturerProfile($lecturerProfile, $request->user()), 403);

        $profile = $lecturerProfile->load(['user', 'studyProgram.faculty']);

        $workloads = LecturerWorkloadSubmission::query()
            ->with(['period', 'items'])
            ->where('lecturer_profile_id', $profile->id)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $reviews = LecturerPerformanceReview::query()
            ->with(['edomPeriod', 'workloadPeriod'])
            ->where('lecturer_profile_id', $profile->id)
            ->latest('calculated_at')
            ->limit(8)
            ->get();

        $programBenchmark = LecturerPerformanceReview::query()
            ->whereHas('lecturerProfile', fn ($query) => $query->where('study_program_id', $profile->study_program_id))
            ->avg('final_score') ?: 0;
        $facultyBenchmark = LecturerPerformanceReview::query()
            ->whereHas('lecturerProfile', fn ($query) => $query->where('faculty_id', $profile->faculty_id))
            ->avg('final_score') ?: 0;

        return Inertia::render('AcademicLeader/Lecturers/Show', [
            'shell' => ShellProps::make($request->user(), 'Pemantauan Akademik', 'Detail Dosen'),
            'profile' => [
                'id' => $profile->id,
                'name' => $profile->user?->name ?? '-',
                'nidn' => $profile->nidn ?? '-',
                'program' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->studyProgram?->faculty?->name ?? '-',
                'photo' => $profile->user?->photo,
            ],
            'stats' => [
                'latestSks' => (float) ($workloads->first()?->total_sks ?: 0),
                'approvedBkd' => $workloads->where('status', 'approved')->count(),
                'latestScore' => (float) ($reviews->first()?->final_score ?: 0),
                'avgScore' => round((float) ($reviews->avg('final_score') ?: 0), 2),
                'programBenchmark' => round((float) $programBenchmark, 2),
                'facultyBenchmark' => round((float) $facultyBenchmark, 2),
            ],
            'workloads' => $workloads->map(fn (LecturerWorkloadSubmission $submission) => [
                'id' => $submission->id,
                'status' => $submission->status,
                'statusLabel' => $this->workloadStatusLabel($submission->status),
                'periodName' => $submission->period?->name ?? '-',
                'teachingSks' => (float) $submission->teaching_sks,
                'structuralSks' => (float) $submission->structural_sks,
                'tridharmaSks' => (float) $submission->tridharma_sks,
                'totalSks' => (float) $submission->total_sks,
                'updatedAt' => $submission->updated_at?->format('d M Y'),
            ])->values()->all(),
            'reviews' => $reviews->map(fn (LecturerPerformanceReview $review) => [
                'id' => $review->id,
                'edomPeriod' => $review->edomPeriod?->name ?? '-',
                'finalScore' => $review->final_score !== null ? (float) $review->final_score : null,
                'edomScore' => $review->edom_score !== null ? (float) $review->edom_score : null,
                'responses' => (int) $review->edom_response_count,
                'workloadSks' => $review->workload_total_sks !== null ? (float) $review->workload_total_sks : null,
                'calculatedAt' => $review->calculated_at?->format('d M Y') ?? '-',
            ])->values()->all(),
            'urls' => [
                'index' => route('academic-leader.lecturers.index'),
                'dashboard' => route('academic-leader.dashboard.index'),
            ],
        ]);
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
