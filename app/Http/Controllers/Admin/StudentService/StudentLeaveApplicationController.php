<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\StudentLeaveApplication;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentService\StudentLeaveApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Monitoring & workflow pengajuan cuti akademik.
 */
class StudentLeaveApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'academic_year_id' => 'nullable|integer',
            'study_program_id' => 'nullable|integer',
            'reason_category' => 'nullable|string|max:30',
            'status' => 'nullable|in:submitted,in_approval,under_review,revision_requested,approved,approved_pending_payment,rejected,activated,returned,cancelled',
            'sort' => 'nullable|in:application_number,created_at,status,semester,duration_semesters',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentLeaveApplication::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear']);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('application_number', 'like', "%{$q}%")
                    ->orWhere('reason', 'like', "%{$q}%")
                    ->orWhereHas('studentProfile', fn ($student) => $student->where('nim', 'like', "%{$q}%"))
                    ->orWhereHas('studentProfile.user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['academic_year_id'] ?? null)) {
            $query->where('academic_year_id', $validated['academic_year_id']);
        }

        if (filled($validated['study_program_id'] ?? null)) {
            $query->whereHas('studentProfile', fn ($student) => $student->where('study_program_id', $validated['study_program_id']));
        }

        if (filled($validated['reason_category'] ?? null)) {
            $query->where('reason_category', $validated['reason_category']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $applications = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/LeaveApplications/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Pengajuan Cuti'),
            'can' => [
                'view' => ActivePermission::check('leave-application.view'),
                'update' => ActivePermission::check('leave-application.update'),
            ],
            'stats' => [
                'total' => StudentLeaveApplication::count(),
                'waiting' => StudentLeaveApplication::whereIn('status', ['submitted', 'in_approval', 'under_review'])->count(),
                'pendingPayment' => StudentLeaveApplication::where('status', 'approved_pending_payment')->count(),
                'active' => StudentLeaveApplication::where('status', 'activated')->count(),
            ],
            'data' => [
                'rows' => collect($applications->items())->values()->map(fn (StudentLeaveApplication $application, int $i) => [
                    'id' => $application->id,
                    'no' => ($applications->firstItem() ?? 0) + $i,
                    'number' => $application->application_number,
                    'student' => $application->studentProfile?->user?->name ?? '-',
                    'nim' => $application->studentProfile?->nim ?? '-',
                    'studyProgram' => $application->studentProfile?->studyProgram?->name ?? '-',
                    'academicYear' => $application->academicYear?->name ?? '-',
                    'semester' => $application->semester,
                    'durationSemesters' => $application->duration_semesters,
                    'reasonCategory' => $application->reason_category,
                    'status' => $application->status,
                    'submittedAt' => $application->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.student-services.leave-applications.show', $application->id),
                ])->all(),
                'currentPage' => $applications->currentPage(),
                'lastPage' => $applications->lastPage(),
                'perPage' => $applications->perPage(),
                'total' => $applications->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'academic_year_id' => $validated['academic_year_id'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'reason_category' => $validated['reason_category'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])->all(),
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'reasonCategories' => $this->reasonCategories(),
            'statuses' => $this->statusOptions(),
            'urls' => [
                'index' => route('admin.student-services.leave-applications.index'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $application = StudentLeaveApplication::query()
            ->with([
                'studentProfile.user',
                'studentProfile.studyProgram.faculty',
                'academicYear',
                'histories.changedBy',
                'reviewedBy',
                'approvedBy',
                'activatedBy',
                'returnedBy',
                'leaveFeeInvoice',
                'approvalRequest',
            ])
            ->findOrFail($id);

        return Inertia::render('Admin/StudentServices/LeaveApplications/Show', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Detail Pengajuan Cuti'),
            'can' => [
                'update' => ActivePermission::check('leave-application.update'),
            ],
            'application' => [
                'id' => $application->id,
                'number' => $application->application_number,
                'status' => $application->status,
                'student' => $application->studentProfile?->user?->name ?? '-',
                'nim' => $application->studentProfile?->nim ?? '-',
                'studyProgram' => $application->studentProfile?->studyProgram?->name ?? '-',
                'faculty' => $application->studentProfile?->studyProgram?->faculty?->name ?? '-',
                'academicYear' => $application->academicYear?->name ?? '-',
                'semester' => $application->semester,
                'durationSemesters' => $application->duration_semesters,
                'reasonCategory' => $application->reason_category,
                'reason' => $application->reason,
                'studentNotes' => $application->student_notes,
                'adminNotes' => $application->admin_notes,
                'attachmentUrl' => $application->attachment_path
                    ? Storage::disk('public')->url($application->attachment_path)
                    : null,
                'attachmentName' => $application->attachment_name,
                'submittedAt' => $application->created_at?->format('d M Y H:i'),
                'updatedAt' => $application->updated_at?->format('d M Y H:i'),
                'leaveFeeAmount' => (float) $application->leave_fee_amount,
                'leaveFeeDueDate' => $application->leave_fee_due_date?->format('Y-m-d'),
                'leaveFeeInvoice' => $application->leaveFeeInvoice ? [
                    'number' => $application->leaveFeeInvoice->invoice_number,
                    'status' => $application->leaveFeeInvoice->status,
                    'total' => (float) $application->leaveFeeInvoice->total_amount,
                ] : null,
                'reviewedBy' => $application->reviewedBy?->name,
                'reviewedAt' => $application->reviewed_at?->format('d M Y H:i'),
                'approvedBy' => $application->approvedBy?->name,
                'approvedAt' => $application->approved_at?->format('d M Y H:i'),
                'activatedBy' => $application->activatedBy?->name,
                'activatedAt' => $application->activated_at?->format('d M Y H:i'),
                'returnedBy' => $application->returnedBy?->name,
                'returnedAt' => $application->returned_at?->format('d M Y H:i'),
                'approval' => $application->approvalRequest ? [
                    'status' => $application->approvalRequest->status,
                    'waitingMessage' => $application->approvalRequest->waitingMessage(),
                ] : null,
                'histories' => $application->histories
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
                'index' => route('admin.student-services.leave-applications.index'),
                'markUnderReview' => route('admin.student-services.leave-applications.mark-under-review', $application->id),
                'approve' => route('admin.student-services.leave-applications.approve', $application->id),
                'requestRevision' => route('admin.student-services.leave-applications.request-revision', $application->id),
                'reject' => route('admin.student-services.leave-applications.reject', $application->id),
                'activate' => route('admin.student-services.leave-applications.activate', $application->id),
                'returnToActive' => route('admin.student-services.leave-applications.return-to-active', $application->id),
            ],
        ]);
    }

    public function markUnderReview(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        $application = StudentLeaveApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentLeaveApplicationService::class)
                ->setStatus($application, 'under_review', $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan cuti ditandai sedang direview.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approve(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        $application = StudentLeaveApplication::findOrFail($id);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
            'leave_fee_amount' => ['required', 'numeric', 'min:0'],
            'leave_fee_due_date' => ['nullable', 'date'],
        ]);

        if ((float) $validated['leave_fee_amount'] > 0 && blank($validated['leave_fee_due_date'] ?? null)) {
            return back()->with('error', 'Jatuh tempo wajib diisi jika cuti dikenakan biaya.');
        }

        try {
            app(StudentLeaveApplicationService::class)->approve(
                $application,
                $validated['notes'] ?? null,
                $request->user()->id,
                (float) $validated['leave_fee_amount'],
                $validated['leave_fee_due_date'] ?? null,
            );

            return back()->with(
                'success',
                (float) $validated['leave_fee_amount'] > 0
                    ? 'Pengajuan cuti disetujui dan invoice biaya cuti diterbitkan.'
                    : 'Pengajuan cuti berhasil disetujui.',
            );
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function requestRevision(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        $application = StudentLeaveApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentLeaveApplicationService::class)
                ->setStatus($application, 'revision_requested', $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        $application = StudentLeaveApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentLeaveApplicationService::class)
                ->reject($application, $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan cuti ditolak.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function activate(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        $application = StudentLeaveApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentLeaveApplicationService::class)
                ->activate($application, $request->user()->id, $validated['notes'] ?? null);

            return back()->with('success', 'Status cuti mahasiswa berhasil diaktifkan.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function returnToActive(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('leave-application.update'), 403);

        $application = StudentLeaveApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(StudentLeaveApplicationService::class)
                ->returnToActive($application, $request->user()->id, $validated['notes'] ?? null);

            return back()->with('success', 'Mahasiswa berhasil dikembalikan ke status aktif.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function reasonCategories(): array
    {
        return [
            ['id' => 'health', 'name' => 'Kesehatan'],
            ['id' => 'financial', 'name' => 'Keuangan'],
            ['id' => 'personal', 'name' => 'Pribadi / Keluarga'],
            ['id' => 'work', 'name' => 'Pekerjaan'],
            ['id' => 'academic', 'name' => 'Akademik'],
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
            ['id' => 'activated', 'name' => 'Cuti Aktif'],
            ['id' => 'returned', 'name' => 'Kembali Aktif'],
            ['id' => 'cancelled', 'name' => 'Dibatalkan'],
        ];
    }
}
