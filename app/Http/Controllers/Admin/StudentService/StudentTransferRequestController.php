<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentService\StudentTransferRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Monitoring & workflow pengajuan pindah (prodi/fakultas/kelas).
 */
class StudentTransferRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'transfer_type' => 'nullable|in:study_program,faculty,class_type,internal_transfer,external_transfer',
            'from_study_program_id' => 'nullable|integer',
            'to_study_program_id' => 'nullable|integer',
            'status' => 'nullable|in:submitted,in_approval,under_review,revision_requested,approved,approved_pending_payment,rejected,applied,cancelled',
            'sort' => 'nullable|in:request_number,created_at,status,recommended_semester,transfer_type',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentTransferRequest::query()
            ->with(['studentProfile.user', 'fromStudyProgram', 'toStudyProgram']);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('request_number', 'like', "%{$q}%")
                    ->orWhere('reason', 'like', "%{$q}%")
                    ->orWhereHas('studentProfile', fn ($student) => $student->where('nim', 'like', "%{$q}%"))
                    ->orWhereHas('studentProfile.user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['transfer_type'] ?? null)) {
            $query->where('transfer_type', $validated['transfer_type']);
        }

        if (filled($validated['from_study_program_id'] ?? null)) {
            $query->where(function ($sub) use ($validated) {
                $sub->where('from_study_program_id', $validated['from_study_program_id'])
                    ->orWhereHas('studentProfile', fn ($student) => $student->where('study_program_id', $validated['from_study_program_id']));
            });
        }

        if (filled($validated['to_study_program_id'] ?? null)) {
            $query->where('to_study_program_id', $validated['to_study_program_id']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $requests = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/TransferRequests/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Pengajuan Pindah'),
            'can' => [
                'view' => ActivePermission::check('transfer-request.view'),
                'update' => ActivePermission::check('transfer-request.update'),
            ],
            'stats' => [
                'total' => StudentTransferRequest::count(),
                'waiting' => StudentTransferRequest::whereIn('status', ['submitted', 'in_approval', 'under_review'])->count(),
                'pendingPayment' => StudentTransferRequest::where('status', 'approved_pending_payment')->count(),
                'applied' => StudentTransferRequest::where('status', 'applied')->count(),
            ],
            'data' => [
                'rows' => collect($requests->items())->values()->map(fn (StudentTransferRequest $transfer, int $i) => [
                    'id' => $transfer->id,
                    'no' => ($requests->firstItem() ?? 0) + $i,
                    'number' => $transfer->request_number,
                    'student' => $transfer->studentProfile?->user?->name ?? '-',
                    'nim' => $transfer->studentProfile?->nim ?? '-',
                    'transferType' => $transfer->transfer_type,
                    'fromProgram' => $transfer->transfer_type === 'class_type'
                        ? ucfirst((string) ($transfer->from_class_type ?? '-'))
                        : ($transfer->fromStudyProgram?->name ?? '-'),
                    'toProgram' => $transfer->transfer_type === 'class_type'
                        ? ucfirst((string) ($transfer->to_class_type ?? '-'))
                        : ($transfer->toStudyProgram?->name ?? '-'),
                    'recommendedSemester' => $transfer->recommended_semester,
                    'status' => $transfer->status,
                    'submittedAt' => $transfer->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.student-services.transfer-requests.show', $transfer->id),
                ])->all(),
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'perPage' => $requests->perPage(),
                'total' => $requests->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'transfer_type' => $validated['transfer_type'] ?? '',
                'from_study_program_id' => $validated['from_study_program_id'] ?? '',
                'to_study_program_id' => $validated['to_study_program_id'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'transferTypes' => $this->transferTypes(),
            'statuses' => $this->statusOptions(),
            'urls' => [
                'index' => route('admin.student-services.transfer-requests.index'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $transfer = StudentTransferRequest::query()
            ->with([
                'studentProfile.user',
                'studentProfile.studyProgram.faculty',
                'fromStudyProgram.faculty',
                'toStudyProgram.faculty',
                'histories.changedBy',
                'reviewedBy',
                'approvedBy',
                'appliedBy',
                'transferFeeInvoice',
                'approvalRequest',
            ])
            ->findOrFail($id);

        return Inertia::render('Admin/StudentServices/TransferRequests/Show', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Detail Pengajuan Pindah'),
            'can' => [
                'update' => ActivePermission::check('transfer-request.update'),
            ],
            'transfer' => [
                'id' => $transfer->id,
                'number' => $transfer->request_number,
                'status' => $transfer->status,
                'transferType' => $transfer->transfer_type,
                'student' => $transfer->studentProfile?->user?->name ?? '-',
                'nim' => $transfer->studentProfile?->nim ?? '-',
                'studentProgram' => $transfer->studentProfile?->studyProgram?->name ?? '-',
                'faculty' => $transfer->studentProfile?->studyProgram?->faculty?->name ?? '-',
                'fromProgram' => $transfer->fromStudyProgram?->name ?? '-',
                'fromFaculty' => $transfer->fromStudyProgram?->faculty?->name ?? '-',
                'toProgram' => $transfer->toStudyProgram?->name ?? '-',
                'toFaculty' => $transfer->toStudyProgram?->faculty?->name ?? '-',
                'fromClassType' => $transfer->from_class_type,
                'toClassType' => $transfer->to_class_type,
                'currentSemester' => $transfer->current_semester,
                'recommendedSemester' => $transfer->recommended_semester,
                'reason' => $transfer->reason,
                'studentNotes' => $transfer->student_notes,
                'adminNotes' => $transfer->admin_notes,
                'academicEvaluationNotes' => $transfer->academic_evaluation_notes,
                'creditMappingNotes' => $transfer->credit_mapping_notes,
                'financeNotes' => $transfer->finance_notes,
                'attachmentUrl' => $transfer->attachment_path
                    ? Storage::disk('public')->url($transfer->attachment_path)
                    : null,
                'attachmentName' => $transfer->attachment_name,
                'submittedAt' => $transfer->created_at?->format('d M Y H:i'),
                'updatedAt' => $transfer->updated_at?->format('d M Y H:i'),
                'transferFeeAmount' => (float) $transfer->transfer_fee_amount,
                'transferFeeDueDate' => $transfer->transfer_fee_due_date?->format('Y-m-d'),
                'transferFeeInvoice' => $transfer->transferFeeInvoice ? [
                    'number' => $transfer->transferFeeInvoice->invoice_number,
                    'status' => $transfer->transferFeeInvoice->status,
                    'total' => (float) $transfer->transferFeeInvoice->total_amount,
                ] : null,
                'reviewedBy' => $transfer->reviewedBy?->name,
                'reviewedAt' => $transfer->reviewed_at?->format('d M Y H:i'),
                'approvedBy' => $transfer->approvedBy?->name,
                'approvedAt' => $transfer->approved_at?->format('d M Y H:i'),
                'appliedBy' => $transfer->appliedBy?->name,
                'appliedAt' => $transfer->applied_at?->format('d M Y H:i'),
                'approval' => $transfer->approvalRequest ? [
                    'status' => $transfer->approvalRequest->status,
                    'waitingMessage' => $transfer->approvalRequest->waitingMessage(),
                ] : null,
                'histories' => $transfer->histories
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn ($history) => [
                        'id' => $history->id,
                        'from' => $history->from_status,
                        'to' => $history->to_status,
                        'notes' => $history->notes,
                        'changedBy' => $history->changedBy?->name,
                        'changedAt' => $history->created_at?->format('d M Y H:i'),
                    ])->all(),
            ],
            'urls' => [
                'index' => route('admin.student-services.transfer-requests.index'),
                'markUnderReview' => route('admin.student-services.transfer-requests.mark-under-review', $transfer->id),
                'approve' => route('admin.student-services.transfer-requests.approve', $transfer->id),
                'requestRevision' => route('admin.student-services.transfer-requests.request-revision', $transfer->id),
                'reject' => route('admin.student-services.transfer-requests.reject', $transfer->id),
                'applyTransfer' => route('admin.student-services.transfer-requests.apply-transfer', $transfer->id),
            ],
        ]);
    }

    public function markUnderReview(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        $transfer = StudentTransferRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentTransferRequestService::class)
                ->setStatus($transfer, 'under_review', $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan pindah ditandai sedang direview.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approve(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        $transfer = StudentTransferRequest::findOrFail($id);
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:3000'],
            'academic_evaluation_notes' => ['nullable', 'string', 'max:3000'],
            'credit_mapping_notes' => ['nullable', 'string', 'max:3000'],
            'finance_notes' => ['nullable', 'string', 'max:3000'],
            'recommended_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'transfer_fee_amount' => ['required', 'numeric', 'min:0'],
            'transfer_fee_due_date' => ['nullable', 'date'],
        ]);

        if ((float) $validated['transfer_fee_amount'] > 0 && blank($validated['transfer_fee_due_date'] ?? null)) {
            return back()->with('error', 'Jatuh tempo wajib diisi jika transfer dikenakan biaya.');
        }

        try {
            app(StudentTransferRequestService::class)->approve(
                $transfer,
                [
                    'admin_notes' => $validated['admin_notes'] ?? null,
                    'academic_evaluation_notes' => $validated['academic_evaluation_notes'] ?? null,
                    'credit_mapping_notes' => $validated['credit_mapping_notes'] ?? null,
                    'finance_notes' => $validated['finance_notes'] ?? null,
                    'recommended_semester' => $validated['recommended_semester'] ?? null,
                ],
                $request->user()->id,
                (float) $validated['transfer_fee_amount'],
                $validated['transfer_fee_due_date'] ?? null,
            );

            return back()->with(
                'success',
                (float) $validated['transfer_fee_amount'] > 0
                    ? 'Pengajuan pindah disetujui dan invoice biaya transfer diterbitkan.'
                    : 'Pengajuan pindah berhasil disetujui.',
            );
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function requestRevision(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        $transfer = StudentTransferRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentTransferRequestService::class)
                ->setStatus($transfer, 'revision_requested', $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        $transfer = StudentTransferRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentTransferRequestService::class)
                ->reject($transfer, $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan pindah ditolak.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function applyTransfer(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('transfer-request.update'), 403);

        $transfer = StudentTransferRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentTransferRequestService::class)
                ->applyTransfer($transfer, $request->user()->id, $validated['notes'] ?? null);

            return back()->with('success', 'Transfer mahasiswa berhasil diterapkan.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function transferTypes(): array
    {
        return [
            ['id' => 'study_program', 'name' => 'Pindah Program Studi'],
            ['id' => 'faculty', 'name' => 'Pindah Fakultas'],
            ['id' => 'class_type', 'name' => 'Pindah Kelas'],
            ['id' => 'other', 'name' => 'Lainnya'],
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['id' => 'submitted', 'name' => 'Diajukan'],
            ['id' => 'in_approval', 'name' => 'Menunggu Persetujuan'],
            ['id' => 'under_review', 'name' => 'Sedang Ditinjau'],
            ['id' => 'revision_requested', 'name' => 'Perlu Perbaikan'],
            ['id' => 'approved', 'name' => 'Disetujui'],
            ['id' => 'approved_pending_payment', 'name' => 'Menunggu Pembayaran'],
            ['id' => 'rejected', 'name' => 'Ditolak'],
            ['id' => 'applied', 'name' => 'Sudah Diterapkan'],
            ['id' => 'cancelled', 'name' => 'Dibatalkan'],
        ];
    }
}
