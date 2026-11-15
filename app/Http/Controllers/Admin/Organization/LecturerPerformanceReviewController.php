<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Academic\LecturerProfile;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\LecturerPerformanceReview;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\EdomService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Review performa dosen — rekap skor EDOM, kepatuhan mengajar, absensi,
 * dan BKD; termasuk hitung ulang massal serta publikasi hasil evaluasi.
 */
class LecturerPerformanceReviewController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draf',
        'calculated' => 'Dihitung',
        'published' => 'Dipublikasikan',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'period_id' => 'nullable|integer|exists:edom_periods,id',
            'sort' => 'nullable|in:edom_score,edom_response_count,final_score,status,calculated_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = LecturerPerformanceReview::query()
            ->with(['owner', 'edomPeriod', 'workloadPeriod', 'lecturerProfile.studyProgram']);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->whereHas('owner', function ($owner) use ($term) {
                    $owner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term);
                })->orWhereHas('edomPeriod', function ($period) use ($term) {
                    $period->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term);
                })->orWhereHas('lecturerProfile.studyProgram', function ($program) use ($term) {
                    $program->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['period_id'] ?? null)) {
            $query->where('edom_period_id', (int) $validated['period_id']);
        }

        $sort = $validated['sort'] ?? 'calculated_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction)->orderByDesc('id');

        $perPage = (int) ($validated['perPage'] ?? 10);
        $reviews = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/LecturerPerformanceReviews/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Review Performa'),
            'can' => [
                'view' => ActivePermission::check('lecturer-performance-review.view'),
                'update' => ActivePermission::check('lecturer-performance-review.update'),
                'delete' => ActivePermission::check('lecturer-performance-review.delete'),
            ],
            'stats' => [
                'total' => LecturerPerformanceReview::count(),
                'published' => LecturerPerformanceReview::where('status', 'published')->count(),
                'averageScore' => round((float) (LecturerPerformanceReview::avg('final_score') ?: 0), 1),
            ],
            'data' => [
                'rows' => collect($reviews->items())->values()->map(fn (LecturerPerformanceReview $review, int $i) => [
                    'id' => $review->id,
                    'no' => ($reviews->firstItem() ?? 0) + $i,
                    'lecturerName' => $review->owner?->name ?? '-',
                    'lecturerEmail' => $review->owner?->email,
                    'periodName' => $review->edomPeriod?->name ?? '-',
                    'periodCode' => $review->edomPeriod?->code,
                    'programName' => $review->lecturerProfile?->studyProgram?->name ?? '-',
                    'edomScore' => $review->edom_score !== null ? (float) $review->edom_score : null,
                    'responseCount' => (int) $review->edom_response_count,
                    'finalScore' => $review->final_score !== null ? (float) $review->final_score : null,
                    'status' => $review->status,
                    'statusLabel' => self::STATUSES[$review->status] ?? ucfirst((string) $review->status),
                    'calculatedAt' => $review->calculated_at?->format('d M Y H:i') ?? '-',
                    'showUrl' => route('admin.organization.lecturer-performance-reviews.show', $review->id),
                    'deleteUrl' => route('admin.organization.lecturer-performance-reviews.destroy', $review->id),
                    'canDelete' => $review->status !== 'published',
                ])->all(),
                'currentPage' => $reviews->currentPage(),
                'lastPage' => $reviews->lastPage(),
                'perPage' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'period_id' => (string) ($validated['period_id'] ?? ''),
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'statuses' => $this->optionList(self::STATUSES),
            'periods' => EdomPeriod::query()
                ->orderByDesc('starts_at')
                ->get(['id', 'name', 'code'])
                ->map(fn (EdomPeriod $period) => [
                    'id' => (string) $period->id,
                    'label' => $period->name.' ('.$period->code.')',
                ])
                ->values()
                ->all(),
            'urls' => [
                'index' => route('admin.organization.lecturer-performance-reviews.index'),
                'calculateAll' => route('admin.organization.lecturer-performance-reviews.calculate-all'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $review = LecturerPerformanceReview::query()
            ->with(['owner', 'edomPeriod', 'workloadPeriod', 'lecturerProfile.studyProgram'])
            ->findOrFail($id);

        return Inertia::render('Admin/Organization/LecturerPerformanceReviews/Show', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Detail Review Performa'),
            'can' => [
                'update' => ActivePermission::check('lecturer-performance-review.update'),
                'delete' => ActivePermission::check('lecturer-performance-review.delete'),
            ],
            'review' => [
                'id' => $review->id,
                'ownerName' => $review->owner?->name ?? 'Dosen',
                'ownerEmail' => $review->owner?->email,
                'periodName' => $review->edomPeriod?->name ?? '-',
                'periodCode' => $review->edomPeriod?->code,
                'workloadPeriodName' => $review->workloadPeriod?->name ?? '-',
                'programName' => $review->lecturerProfile?->studyProgram?->name ?? '-',
                'status' => $review->status,
                'statusLabel' => self::STATUSES[$review->status] ?? ucfirst((string) $review->status),
                'edomScore' => $review->edom_score !== null ? (float) $review->edom_score : null,
                'responseCount' => (int) $review->edom_response_count,
                'teachingComplianceScore' => $review->teaching_compliance_score !== null ? (float) $review->teaching_compliance_score : null,
                'attendanceComplianceScore' => $review->attendance_compliance_score !== null ? (float) $review->attendance_compliance_score : null,
                'workloadTotalSks' => $review->workload_total_sks !== null ? (float) $review->workload_total_sks : null,
                'finalScore' => $review->final_score !== null ? (float) $review->final_score : null,
                'calculatedAt' => $review->calculated_at?->format('d M Y H:i'),
                'publishedAt' => $review->published_at?->format('d M Y H:i'),
                'canPublish' => $review->status !== 'published',
            ],
            'urls' => [
                'index' => route('admin.organization.lecturer-performance-reviews.index'),
                'publish' => route('admin.organization.lecturer-performance-reviews.publish', $review->id),
            ],
        ]);
    }

    public function calculateAll(Request $request, EdomService $edomService)
    {
        if (! EdomPeriod::query()->exists()) {
            return back()->with('error', 'Belum ada periode EDOM yang dapat digunakan untuk menghitung performa.');
        }

        $calculated = 0;
        $skipped = 0;

        LecturerProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->get()
            ->each(function (LecturerProfile $profile) use ($edomService, &$calculated, &$skipped) {
                if (! $profile->user) {
                    $skipped++;

                    return;
                }

                try {
                    $edomService->calculatePerformance($profile->user);
                    $calculated++;
                } catch (Throwable) {
                    $skipped++;
                }
            });

        $message = 'Review performa berhasil dihitung ulang untuk '.$calculated.' dosen.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' dosen dilewati karena data evaluasi belum lengkap.';
        }

        return back()->with('success', $message);
    }

    public function publish(Request $request, int $id)
    {
        $review = LecturerPerformanceReview::findOrFail($id);

        $review->update([
            'status' => 'published',
            'published_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Review performa dipublikasikan.');
    }

    public function destroy(Request $request, int $id)
    {
        $review = LecturerPerformanceReview::findOrFail($id);

        if ($review->status === 'published') {
            return back()->with('error', 'Review performa yang sudah dipublikasikan tidak dapat dihapus.');
        }

        $review->delete();

        return redirect()->route('admin.organization.lecturer-performance-reviews.index')
            ->with('success', 'Data evaluasi berhasil dihapus.');
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return Collection::make($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
