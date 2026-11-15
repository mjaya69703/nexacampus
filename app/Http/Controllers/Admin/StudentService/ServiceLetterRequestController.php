<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\ServiceLetterType;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentService\ServiceLetterRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Monitoring & workflow pengajuan surat mahasiswa.
 */
class ServiceLetterRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'service_letter_type_id' => 'nullable|integer',
            'study_program_id' => 'nullable|integer',
            'status' => 'nullable|in:submitted,in_approval,under_review,revision_requested,approved,rejected,issued,cancelled',
            'sort' => 'nullable|in:request_number,created_at,status',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = ServiceLetterRequest::query()
            ->with(['letterType', 'studentProfile.user', 'studentProfile.studyProgram']);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('request_number', 'like', "%{$q}%")
                    ->orWhere('purpose', 'like', "%{$q}%")
                    ->orWhereHas('studentProfile', fn ($student) => $student->where('nim', 'like', "%{$q}%"))
                    ->orWhereHas('studentProfile.user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('letterType', fn ($type) => $type->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['service_letter_type_id'] ?? null)) {
            $query->where('service_letter_type_id', $validated['service_letter_type_id']);
        }

        if (filled($validated['study_program_id'] ?? null)) {
            $query->whereHas('studentProfile', fn ($student) => $student->where('study_program_id', $validated['study_program_id']));
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $requests = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/LetterRequests/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Pengajuan Surat'),
            'can' => [
                'view' => ActivePermission::check('service-letter-request.view'),
                'update' => ActivePermission::check('service-letter-request.update'),
            ],
            'stats' => [
                'total' => ServiceLetterRequest::count(),
                'waiting' => ServiceLetterRequest::whereIn('status', ['submitted', 'in_approval', 'under_review'])->count(),
                'approved' => ServiceLetterRequest::where('status', 'approved')->count(),
                'issued' => ServiceLetterRequest::where('status', 'issued')->count(),
            ],
            'data' => [
                'rows' => collect($requests->items())->values()->map(fn (ServiceLetterRequest $letterRequest, int $i) => [
                    'id' => $letterRequest->id,
                    'no' => ($requests->firstItem() ?? 0) + $i,
                    'requestNumber' => $letterRequest->request_number,
                    'letterType' => $letterRequest->letterType?->name ?? '-',
                    'studentName' => $letterRequest->studentProfile?->user?->name ?? '-',
                    'nim' => $letterRequest->studentProfile?->nim ?? '-',
                    'studyProgram' => $letterRequest->studentProfile?->studyProgram?->name ?? '-',
                    'purpose' => $letterRequest->purpose,
                    'status' => $letterRequest->status,
                    'submittedAt' => $letterRequest->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.student-services.letter-requests.show', $letterRequest->id),
                ])->all(),
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'perPage' => $requests->perPage(),
                'total' => $requests->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'service_letter_type_id' => $validated['service_letter_type_id'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'letterTypes' => ServiceLetterType::query()->orderBy('name')->get(['id', 'name'])->all(),
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.letter-requests.index'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $serviceLetterRequest = ServiceLetterRequest::query()->findOrFail($id);
        $serviceLetterRequest->load([
            'letterType',
            'studentProfile.user',
            'studentProfile.studyProgram.faculty',
            'histories.changedBy',
            'reviewedBy',
            'approvedBy',
            'issuedBy',
            'approvalRequest',
        ]);

        return Inertia::render('Admin/StudentServices/LetterRequests/Show', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Detail Pengajuan Surat'),
            'can' => [
                'update' => ActivePermission::check('service-letter-request.update'),
                'issue' => ActivePermission::check('service-letter-request.update') && $serviceLetterRequest->status === 'approved',
            ],
            'request' => [
                'id' => $serviceLetterRequest->id,
                'number' => $serviceLetterRequest->request_number,
                'status' => $serviceLetterRequest->status,
                'letterType' => $serviceLetterRequest->letterType?->name ?? '-',
                'letterTypeCode' => $serviceLetterRequest->letterType?->code,
                'fulfillmentMode' => $serviceLetterRequest->letterType?->fulfillment_mode,
                'student' => $serviceLetterRequest->studentProfile?->user?->name ?? '-',
                'nim' => $serviceLetterRequest->studentProfile?->nim ?? '-',
                'studyProgram' => $serviceLetterRequest->studentProfile?->studyProgram?->name ?? '-',
                'faculty' => $serviceLetterRequest->studentProfile?->studyProgram?->faculty?->name ?? '-',
                'purpose' => $serviceLetterRequest->purpose,
                'requestData' => collect($serviceLetterRequest->request_data ?? [])
                    ->map(fn ($value, $key) => ['key' => $key, 'value' => is_array($value) ? json_encode($value) : (string) $value])
                    ->values()
                    ->all(),
                'studentNotes' => $serviceLetterRequest->student_notes,
                'adminNotes' => $serviceLetterRequest->admin_notes,
                'attachmentUrl' => $serviceLetterRequest->attachment_path
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($serviceLetterRequest->attachment_path)
                    : null,
                'attachmentName' => $serviceLetterRequest->attachment_name,
                'submittedAt' => $serviceLetterRequest->created_at?->format('d M Y H:i'),
                'updatedAt' => $serviceLetterRequest->updated_at?->format('d M Y H:i'),
                'reviewedBy' => $serviceLetterRequest->reviewedBy?->name,
                'reviewedAt' => $serviceLetterRequest->reviewed_at?->format('d M Y H:i'),
                'approvedBy' => $serviceLetterRequest->approvedBy?->name,
                'approvedAt' => $serviceLetterRequest->approved_at?->format('d M Y H:i'),
                'issuedBy' => $serviceLetterRequest->issuedBy?->name,
                'issuedAt' => $serviceLetterRequest->issued_at?->format('d M Y H:i'),
                'isDownloadable' => $serviceLetterRequest->isDownloadable(),
                'downloadUrl' => $serviceLetterRequest->isDownloadable()
                    ? route('admin.student-services.letter-requests.download', ['request' => $serviceLetterRequest->id])
                    : null,
                'approval' => $serviceLetterRequest->approvalRequest ? [
                    'status' => $serviceLetterRequest->approvalRequest->status,
                    'waitingMessage' => $serviceLetterRequest->approvalRequest->waitingMessage(),
                ] : null,
                'histories' => $serviceLetterRequest->histories->sortByDesc('created_at')->values()->map(fn ($history) => [
                    'id' => $history->id,
                    'from' => $history->from_status,
                    'to' => $history->to_status,
                    'notes' => $history->notes,
                    'changedBy' => $history->changedBy?->name,
                    'changedAt' => $history->created_at?->format('d M Y H:i'),
                ])->all(),
            ],
            'issueMethods' => $this->issueMethods($serviceLetterRequest->letterType?->fulfillment_mode),
            'urls' => [
                'index' => route('admin.student-services.letter-requests.index'),
                'markUnderReview' => route('admin.student-services.letter-requests.mark-under-review', $serviceLetterRequest->id),
                'approve' => route('admin.student-services.letter-requests.approve', $serviceLetterRequest->id),
                'requestRevision' => route('admin.student-services.letter-requests.request-revision', $serviceLetterRequest->id),
                'reject' => route('admin.student-services.letter-requests.reject', $serviceLetterRequest->id),
                'issue' => route('admin.student-services.letter-requests.issue', $serviceLetterRequest->id),
            ],
        ]);
    }

    public function markUnderReview(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);

        $serviceLetterRequest = ServiceLetterRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        app(ServiceLetterRequestService::class)
            ->setStatus($serviceLetterRequest, 'under_review', $validated['notes'] ?? null, $request->user()->id);

        return back()->with('success', 'Pengajuan ditandai sedang direview.');
    }

    public function approve(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);

        $serviceLetterRequest = ServiceLetterRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(ServiceLetterRequestService::class)
                ->approve($serviceLetterRequest, $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan surat berhasil disetujui.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function requestRevision(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);

        $serviceLetterRequest = ServiceLetterRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        app(ServiceLetterRequestService::class)
            ->setStatus($serviceLetterRequest, 'revision_requested', $validated['notes'] ?? null, $request->user()->id);

        return back()->with('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
    }

    public function reject(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);

        $serviceLetterRequest = ServiceLetterRequest::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(ServiceLetterRequestService::class)
                ->reject($serviceLetterRequest, $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan surat ditolak.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function issue(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('service-letter-request.update'), 403);

        $serviceLetterRequest = ServiceLetterRequest::findOrFail($id);
        $allowedMethods = collect($this->issueMethods($serviceLetterRequest->letterType?->fulfillment_mode))
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'issue_method' => ['required', Rule::in($allowedMethods ?: ['auto_generate'])],
            'final_file' => [$request->input('issue_method') === 'manual_upload' ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        try {
            app(ServiceLetterRequestService::class)->issue(
                $serviceLetterRequest,
                $validated['issue_method'],
                $request->file('final_file'),
                $request->user()->id,
            );

            return back()->with('success', 'Surat berhasil diterbitkan.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function issueMethods(?string $mode): array
    {
        return match ($mode) {
            'manual_upload' => [['id' => 'manual_upload', 'name' => 'Unggah Manual']],
            'hybrid' => [
                ['id' => 'auto_generate', 'name' => 'Auto Generate PDF'],
                ['id' => 'manual_upload', 'name' => 'Unggah Manual'],
            ],
            default => [['id' => 'auto_generate', 'name' => 'Auto Generate PDF']],
        };
    }
}
