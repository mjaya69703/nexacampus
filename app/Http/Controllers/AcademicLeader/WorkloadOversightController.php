<?php

namespace App\Http\Controllers\AcademicLeader;

use App\Http\Controllers\Controller;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\AcademicLeaderContext;
use App\Support\Organization\AcademicLeaderOversightService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekap BKD dosen dalam scope pimpinan akademik.
 *
 * Statistik selalu dihitung dari seluruh submission dalam scope,
 * sedangkan filter hanya memengaruhi daftar yang ditampilkan.
 */
class WorkloadOversightController extends Controller
{
    public function index(
        Request $request,
        AcademicLeaderContext $context,
        AcademicLeaderOversightService $oversight,
    ): Response {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                'all', 'draft', 'revision', 'in_approval', 'approved', 'rejected', 'cancelled',
            ])],
        ]);

        $status = $validated['status'] ?? 'all';
        $search = trim((string) ($validated['q'] ?? ''));
        $base = $oversight->workloadQuery($request->user());

        $stats = [
            'total' => (clone $base)->count(),
            'approval' => (clone $base)->where('status', 'in_approval')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'avgSks' => round((float) ((clone $base)->avg('total_sks') ?: 0), 2),
        ];

        $submissions = (clone $base)
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like): void {
                    $nested->whereHas('owner', fn (Builder $owner) => $owner
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('username', 'like', $like))
                        ->orWhereHas('lecturerProfile', fn (Builder $profile) => $profile
                            ->where('nidn', 'like', $like)
                            ->orWhereHas('studyProgram', fn (Builder $program) => $program->where('name', 'like', $like)));
                });
            })
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statusOptions = collect(['all', 'draft', 'revision', 'in_approval', 'approved', 'rejected', 'cancelled'])
            ->mapWithKeys(fn (string $value) => [
                $value => $value === 'all'
                    ? 'Semua status BKD'
                    : $oversight->workloadStatusLabel($value),
            ])
            ->all();

        return Inertia::render('AcademicLeader/Workloads/Index', [
            'shell' => ShellProps::make($request->user(), 'Pemantauan Akademik', 'BKD Dosen'),
            'hasScope' => $context->hasScope($request->user()),
            'data' => [
                'rows' => collect($submissions->items())
                    ->map(fn (LecturerWorkloadSubmission $submission) => [
                        'id' => $submission->id,
                        'lecturerProfileId' => $submission->lecturer_profile_id,
                        'name' => $submission->owner?->name ?? '-',
                        'nidn' => $submission->lecturerProfile?->nidn ?? '-',
                        'program' => $submission->lecturerProfile?->studyProgram?->name ?? '-',
                        'faculty' => $submission->lecturerProfile?->studyProgram?->faculty?->name ?? '-',
                        'periodName' => $submission->period?->name ?? '-',
                        'status' => $submission->status,
                        'statusLabel' => $oversight->workloadStatusLabel($submission->status),
                        'teachingSks' => (float) $submission->teaching_sks,
                        'structuralSks' => (float) $submission->structural_sks,
                        'tridharmaSks' => (float) $submission->tridharma_sks,
                        'totalSks' => (float) $submission->total_sks,
                        'submittedAt' => $submission->submitted_at?->format('d M Y H:i'),
                        'approvedAt' => $submission->approved_at?->format('d M Y H:i'),
                        'url' => route('academic-leader.lecturers.show', $submission->lecturer_profile_id),
                    ])
                    ->values()
                    ->all(),
                'currentPage' => $submissions->currentPage(),
                'lastPage' => $submissions->lastPage(),
                'perPage' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'statusOptions' => $statusOptions,
            'stats' => $stats,
            'urls' => [
                'index' => route('academic-leader.workloads.index'),
                'export' => [
                    'csv' => route('academic-leader.workloads.export', ['format' => 'csv']),
                    'xlsx' => route('academic-leader.workloads.export', ['format' => 'xlsx']),
                    'pdf' => route('academic-leader.workloads.export', ['format' => 'pdf']),
                ],
            ],
        ]);
    }
}
