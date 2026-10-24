<?php

namespace App\Http\Controllers\AcademicLeader;

use App\Http\Controllers\Controller;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\LecturerPerformanceReview;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\AcademicLeaderContext;
use App\Support\Organization\AcademicLeaderOversightService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoring EDOM dan performa dosen dalam scope pimpinan akademik.
 *
 * Koreksi dari Blade lama: jumlah dosen dihitung dari profil dosen unik,
 * bukan jumlah baris review (satu dosen bisa punya banyak periode EDOM);
 * jumlah review tetap ditampilkan terpisah. Pencarian memfilter nama,
 * NIDN, program studi, dan periode evaluasi.
 */
class EdomOversightController extends Controller
{
    public function index(
        Request $request,
        AcademicLeaderContext $context,
        AcademicLeaderOversightService $oversight,
    ): Response {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $periodId = isset($validated['period']) ? (int) $validated['period'] : null;

        $base = $oversight->reviewQuery($request->user());

        $stats = [
            'lecturers' => (clone $base)->distinct()->count('lecturer_profile_id'),
            'reviews' => (clone $base)->count(),
            'responses' => (int) ((clone $base)->sum('edom_response_count') ?: 0),
            'avgEdom' => round((float) ((clone $base)->avg('edom_score') ?: 0), 2),
            'avgFinal' => round((float) ((clone $base)->avg('final_score') ?: 0), 2),
        ];

        $reviews = (clone $base)
            ->when($periodId !== null, fn (Builder $query) => $query->where('edom_period_id', $periodId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like): void {
                    $nested->whereHas('owner', fn (Builder $owner) => $owner
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('username', 'like', $like))
                        ->orWhereHas('lecturerProfile', fn (Builder $profile) => $profile
                            ->where('nidn', 'like', $like)
                            ->orWhereHas('studyProgram', fn (Builder $program) => $program->where('name', 'like', $like)))
                        ->orWhereHas('edomPeriod', fn (Builder $period) => $period->where('name', 'like', $like));
                });
            })
            ->latest('calculated_at')
            ->paginate(12)
            ->withQueryString();

        $periods = EdomPeriod::query()
            ->whereIn('id', (clone $base)->whereNotNull('edom_period_id')->select('edom_period_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (EdomPeriod $period) => ['id' => $period->id, 'name' => $period->name])
            ->all();

        return Inertia::render('AcademicLeader/Edom/Index', [
            'shell' => ShellProps::make($request->user(), 'Pemantauan Akademik', 'EDOM & Performa'),
            'hasScope' => $context->hasScope($request->user()),
            'data' => [
                'rows' => collect($reviews->items())
                    ->map(fn (LecturerPerformanceReview $review) => [
                        'id' => $review->id,
                        'lecturerProfileId' => $review->lecturer_profile_id,
                        'name' => $review->owner?->name ?? '-',
                        'nidn' => $review->lecturerProfile?->nidn ?? '-',
                        'program' => $review->lecturerProfile?->studyProgram?->name ?? '-',
                        'periodName' => $review->edomPeriod?->name ?? '-',
                        'edomScore' => $review->edom_score !== null ? (float) $review->edom_score : null,
                        'finalScore' => $review->final_score !== null ? (float) $review->final_score : null,
                        'teachingCompliance' => $review->teaching_compliance_score !== null ? (float) $review->teaching_compliance_score : null,
                        'attendanceCompliance' => $review->attendance_compliance_score !== null ? (float) $review->attendance_compliance_score : null,
                        'responses' => (int) $review->edom_response_count,
                        'workloadSks' => $review->workload_total_sks !== null ? (float) $review->workload_total_sks : null,
                        'calculatedAt' => $review->calculated_at?->format('d M Y') ?? '-',
                        'url' => route('academic-leader.lecturers.show', $review->lecturer_profile_id),
                    ])
                    ->values()
                    ->all(),
                'currentPage' => $reviews->currentPage(),
                'lastPage' => $reviews->lastPage(),
                'perPage' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
            'filters' => [
                'q' => $search,
                'period' => $periodId,
            ],
            'periods' => $periods,
            'stats' => $stats,
            'urls' => [
                'index' => route('academic-leader.edom.index'),
                'export' => [
                    'csv' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'csv']),
                    'xlsx' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'xlsx']),
                    'pdf' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'pdf']),
                ],
            ],
        ]);
    }
}
