<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use App\Support\Admission\AdmissionStatusService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perankingan & keputusan seleksi PMB.
 *
 * Paritas Blade lama: peringkat dihitung dari skor akhir, kuota per
 * prodi/kelas dihormati saat menerima, dan keputusan massal melewati
 * pendaftar yang kuotanya sudah penuh.
 */
class AdmissionSelectionController extends Controller
{
    public const DECISION_STATUSES = [
        'accepted' => 'Terima (Accepted)',
        'waitlisted' => 'Cadangan (Waitlisted)',
        'rejected' => 'Tolak (Rejected)',
        'under_review' => 'Kembalikan ke Seleksi (Under Review)',
    ];

    public function index(Request $request, AdmissionSelectionService $selectionService): Response
    {
        $validated = $request->validate([
            'period_id' => 'nullable|integer|exists:admission_periods,id',
            'study_program_id' => 'nullable|integer|exists:study_programs,id',
            'class_type' => ['nullable', Rule::in(array_keys(AdmissionApplicationController::CLASS_TYPES))],
        ]);

        $filters = [
            'period_id' => $validated['period_id'] ?? null,
            'study_program_id' => $validated['study_program_id'] ?? null,
            'class_type' => $validated['class_type'] ?? null,
        ];

        $rows = $selectionService->rankedRows($filters)->map(fn (AdmissionApplication $application) => [
            'id' => $application->id,
            'rank' => (int) $application->rank_position,
            'applicationNumber' => $application->application_number,
            'fullName' => $application->full_name,
            'studyProgram' => $application->studyProgram?->name,
            'classType' => $application->class_type,
            'classTypeLabel' => AdmissionApplicationController::CLASS_TYPES[$application->class_type] ?? ucfirst((string) $application->class_type),
            'finalScore' => $application->final_score,
            'scoresCount' => (int) $application->scores_count,
            'quotaLimit' => $application->quota_limit !== null ? (int) $application->quota_limit : null,
            'quotaUsed' => $application->quota_used !== null ? (int) $application->quota_used : null,
            'quotaFull' => $application->quota_limit !== null && $application->quota_used >= $application->quota_limit,
            'status' => $application->status,
            'statusLabel' => AdmissionApplicationController::STATUSES[$application->status] ?? ucfirst((string) $application->status),
            'decisionUrl' => route('admin.admission.admission-selection.decision', $application),
        ])->values()->all();

        return Inertia::render('Admin/Admission/Selection/Index', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Perankingan & Seleksi'),
            'can' => [
                'update' => ActivePermission::check('admission-selection.update'),
            ],
            'stats' => [
                'candidates' => AdmissionApplication::where('status', '!=', 'submitted')->count(),
                'accepted' => AdmissionApplication::where('status', 'accepted')->count(),
                'waitlisted' => AdmissionApplication::where('status', 'waitlisted')->count(),
                'rejected' => AdmissionApplication::where('status', 'rejected')->count(),
            ],
            'data' => [
                'rows' => $rows,
                'total' => count($rows),
            ],
            'filters' => [
                'period_id' => $filters['period_id'] ?? '',
                'study_program_id' => $filters['study_program_id'] ?? '',
                'class_type' => $filters['class_type'] ?? '',
            ],
            'options' => [
                'periods' => $this->periodOptions(),
                'studyPrograms' => $this->studyProgramOptions(),
                'classTypes' => collect(AdmissionApplicationController::CLASS_TYPES)
                    ->map(fn (string $label, string $value) => ['id' => $value, 'label' => $label])
                    ->values()
                    ->all(),
                'decisions' => collect(self::DECISION_STATUSES)
                    ->map(fn (string $label, string $value) => ['id' => $value, 'label' => $label])
                    ->values()
                    ->all(),
            ],
            'urls' => [
                'index' => route('admin.admission.admission-selection.index'),
                'bulkDecision' => route('admin.admission.admission-selection.bulk-decision'),
            ],
        ]);
    }

    public function updateDecision(
        Request $request,
        AdmissionApplication $application,
        AdmissionStatusService $statusService,
        AdmissionSelectionService $selectionService,
    ) {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::DECISION_STATUSES))],
        ]);

        $quota = $selectionService->quotaFor($application);

        if ($validated['status'] === 'accepted'
            && $quota
            && $quota->accepted_count >= $quota->quota
            && $application->status !== 'accepted') {
            return back()->with('error', 'Kuota untuk program studi & kelas ini sudah penuh. Gunakan status cadangan (waitlist) atau tambah kuota.');
        }

        $statusService->change(
            $application,
            $validated['status'],
            'Selection decision updated from ranking dashboard.',
            $request->user()->id,
        );

        $selectionService->refreshAcceptedCounts($application->admission_period_id);

        return back()->with('success', 'Keputusan seleksi pendaftar berhasil diperbarui.');
    }

    public function bulkDecision(
        Request $request,
        AdmissionStatusService $statusService,
        AdmissionSelectionService $selectionService,
    ) {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:admission_applications,id',
            'status' => ['required', Rule::in(array_keys(self::DECISION_STATUSES))],
        ]);

        $updated = 0;
        $errors = [];
        $periods = [];

        foreach (AdmissionApplication::whereKey($validated['ids'])->get() as $application) {
            $quota = $selectionService->quotaFor($application);

            if ($validated['status'] === 'accepted'
                && $quota
                && $quota->accepted_count >= $quota->quota
                && $application->status !== 'accepted') {
                $errors[] = $application->application_number.' dilewati: kuota penuh.';

                continue;
            }

            $statusService->change(
                $application,
                $validated['status'],
                'Bulk selection decision updated.',
                $request->user()->id,
            );

            $periods[$application->admission_period_id] = true;
            $updated++;
        }

        foreach (array_keys($periods) as $periodId) {
            $selectionService->refreshAcceptedCounts($periodId);
        }

        $response = back();

        if ($updated > 0) {
            $response->with('success', $updated.' pendaftar berhasil diperbarui status seleksinya.');
        }

        if ($errors) {
            $response->with('error', implode(' ', $errors));
        }

        return $response;
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function periodOptions(): array
    {
        return AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (AdmissionPeriod $period) => [
                'id' => $period->id,
                'label' => $period->name.' ('.$period->code.')',
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function studyProgramOptions(): array
    {
        return StudyProgram::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'label' => $program->name.' ('.$program->code.')',
            ])
            ->all();
    }
}
