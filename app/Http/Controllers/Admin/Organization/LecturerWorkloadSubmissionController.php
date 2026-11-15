<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\ActivePermission;
use App\Support\Database\PortableOrder;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review laporan BKD dosen — paritas halaman Livewire lama: daftar laporan,
 * detail butir kegiatan, pengembalian untuk revisi, dan penghapusan laporan.
 */
class LecturerWorkloadSubmissionController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draf',
        'submitted' => 'Diajukan',
        'in_approval' => 'Dalam Persetujuan',
        'approved' => 'Disetujui',
        'revision' => 'Revisi',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    private const DELETABLE_STATUSES = ['draft', 'cancelled', 'revision', 'rejected'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'period_id' => 'nullable|integer|exists:lecturer_workload_periods,id',
            'sort' => 'nullable|in:total_sks,status,submitted_at,updated_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = LecturerWorkloadSubmission::query()
            ->with(['period', 'owner', 'lecturerProfile.studyProgram']);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->whereHas('owner', function ($owner) use ($term) {
                    $owner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term);
                })->orWhereHas('period', function ($period) use ($term) {
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
            $query->where('lecturer_workload_period_id', (int) $validated['period_id']);
        }

        $sort = $validated['sort'] ?? 'updated_at';
        $direction = $validated['direction'] ?? 'desc';

        if ($sort === 'status') {
            $query->orderByRaw(PortableOrder::priority('status', [
                'in_approval', 'submitted', 'revision', 'draft', 'approved', 'rejected', 'cancelled',
            ]));
        } else {
            $query->orderBy($sort, $direction);
        }

        $query->orderByDesc('id');

        $perPage = (int) ($validated['perPage'] ?? 10);
        $submissions = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/LecturerWorkloadSubmissions/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Review BKD'),
            'can' => [
                'view' => ActivePermission::check('lecturer-workload-submission.view'),
                'update' => ActivePermission::check('lecturer-workload-submission.update'),
                'delete' => ActivePermission::check('lecturer-workload-submission.delete'),
                'export' => ActivePermission::check('lecturer-workload-submission.viewAny'),
            ],
            'stats' => [
                'total' => LecturerWorkloadSubmission::count(),
                'inApproval' => LecturerWorkloadSubmission::whereIn('status', ['in_approval', 'submitted'])->count(),
                'approved' => LecturerWorkloadSubmission::where('status', 'approved')->count(),
                'averageSks' => round((float) (LecturerWorkloadSubmission::avg('total_sks') ?: 0), 1),
            ],
            'data' => [
                'rows' => collect($submissions->items())->values()->map(fn (LecturerWorkloadSubmission $submission, int $i) => [
                    'id' => $submission->id,
                    'no' => ($submissions->firstItem() ?? 0) + $i,
                    'lecturerName' => $submission->owner?->name ?? '-',
                    'lecturerEmail' => $submission->owner?->email,
                    'periodName' => $submission->period?->name ?? '-',
                    'periodCode' => $submission->period?->code,
                    'programName' => $submission->lecturerProfile?->studyProgram?->name ?? '-',
                    'teachingSks' => (float) $submission->teaching_sks,
                    'structuralSks' => (float) $submission->structural_sks,
                    'tridharmaSks' => (float) $submission->tridharma_sks,
                    'totalSks' => (float) $submission->total_sks,
                    'status' => $submission->status,
                    'statusLabel' => self::STATUSES[$submission->status] ?? ucfirst((string) $submission->status),
                    'submittedAt' => $submission->submitted_at?->format('d M Y H:i') ?? '-',
                    'showUrl' => route('admin.organization.lecturer-workload-submissions.show', $submission->id),
                    'deleteUrl' => route('admin.organization.lecturer-workload-submissions.destroy', $submission->id),
                    'canDelete' => in_array($submission->status, self::DELETABLE_STATUSES, true),
                ])->all(),
                'currentPage' => $submissions->currentPage(),
                'lastPage' => $submissions->lastPage(),
                'perPage' => $submissions->perPage(),
                'total' => $submissions->total(),
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
            'periods' => LecturerWorkloadPeriod::query()
                ->orderByDesc('starts_at')
                ->get(['id', 'name', 'code'])
                ->map(fn (LecturerWorkloadPeriod $period) => [
                    'id' => (string) $period->id,
                    'label' => $period->name.' ('.$period->code.')',
                ])
                ->values()
                ->all(),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-submissions.index'),
                'exportCsv' => route('admin.organization.lecturer-workload-submissions.export', 'csv'),
                'exportXlsx' => route('admin.organization.lecturer-workload-submissions.export', 'xlsx'),
                'exportPdf' => route('admin.organization.lecturer-workload-submissions.export', 'pdf'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $submission = LecturerWorkloadSubmission::query()
            ->with(['period', 'owner', 'lecturerProfile.studyProgram', 'items'])
            ->findOrFail($id);

        return Inertia::render('Admin/Organization/LecturerWorkloadSubmissions/Show', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Detail BKD'),
            'can' => [
                'update' => ActivePermission::check('lecturer-workload-submission.update'),
                'delete' => ActivePermission::check('lecturer-workload-submission.delete'),
            ],
            'submission' => [
                'id' => $submission->id,
                'ownerName' => $submission->owner?->name ?? 'Dosen',
                'ownerEmail' => $submission->owner?->email,
                'periodName' => $submission->period?->name ?? '-',
                'periodCode' => $submission->period?->code ?? '-',
                'periodStatus' => $submission->period?->status,
                'programName' => $submission->lecturerProfile?->studyProgram?->name ?? '-',
                'status' => $submission->status,
                'statusLabel' => self::STATUSES[$submission->status] ?? ucfirst((string) $submission->status),
                'teachingSks' => (float) $submission->teaching_sks,
                'structuralSks' => (float) $submission->structural_sks,
                'tridharmaSks' => (float) $submission->tridharma_sks,
                'totalSks' => (float) $submission->total_sks,
                'lecturerNotes' => $submission->lecturer_notes,
                'reviewNotes' => $submission->review_notes,
                'submittedAt' => $submission->submitted_at?->format('d M Y H:i'),
                'approvedAt' => $submission->approved_at?->format('d M Y H:i'),
                'canRequestRevision' => in_array($submission->status, ['draft', 'in_approval', 'submitted'], true),
                'canDelete' => in_array($submission->status, self::DELETABLE_STATUSES, true),
            ],
            'items' => $submission->items->sortBy('id')->values()->map(fn ($item) => [
                'id' => $item->id,
                'category' => $item->category,
                'categoryLabel' => ucfirst((string) $item->category),
                'sourceCode' => $item->source_code,
                'title' => $item->title,
                'sks' => (float) $item->sks,
            ])->all(),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-submissions.index'),
                'requestRevision' => route('admin.organization.lecturer-workload-submissions.request-revision', $submission->id),
                'destroy' => route('admin.organization.lecturer-workload-submissions.destroy', $submission->id),
            ],
        ]);
    }

    public function requestRevision(Request $request, int $id, ApprovalEngine $approvalEngine)
    {
        $validated = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $submission = LecturerWorkloadSubmission::query()
            ->with(['period', 'approvalRequest'])
            ->findOrFail($id);

        if (! in_array($submission->status, ['draft', 'in_approval', 'submitted'], true)) {
            return back()->with('error', 'Status BKD tidak bisa dikembalikan ke revisi.');
        }

        if (! $submission->period?->isOpen()) {
            return back()->with('error', 'Revisi hanya bisa diminta saat periode BKD masih dibuka.');
        }

        $notes = trim((string) ($validated['review_notes'] ?? '')) ?: 'Dikembalikan untuk revisi oleh admin.';

        if ($submission->approvalRequest && $submission->approvalRequest->status === 'in_progress') {
            $approvalEngine->cancel($submission->approvalRequest, $request->user(), $notes);
        }

        $submission->update([
            'approval_request_id' => null,
            'status' => 'revision',
            'review_notes' => $notes,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'BKD dikembalikan untuk revisi.');
    }

    public function destroy(Request $request, int $id)
    {
        $submission = LecturerWorkloadSubmission::findOrFail($id);

        if (! in_array($submission->status, self::DELETABLE_STATUSES, true)) {
            return back()->with('error', 'Laporan BKD dengan status ini tidak dapat dihapus.');
        }

        $submission->items()->delete();
        $submission->delete();

        return redirect()->route('admin.organization.lecturer-workload-submissions.index')
            ->with('success', 'Laporan BKD berhasil dihapus.');
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
