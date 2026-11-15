<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\ApprovalRequest;
use App\Models\Organization\ApprovalTemplate;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD & pemrosesan permohonan approval — paritas halaman Livewire lama
 * termasuk penjagaan keputusan khusus modul (cuti, pindah, yudisium).
 */
class ApprovalRequestController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Diajukan',
        'in_progress' => 'In Progress',
        'in_approval' => 'Dalam Persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    private const STEP_STATUSES = [
        'pending' => 'Menunggu',
        'current' => 'Sedang Berjalan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'skipped' => 'Dilewati',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:draft,submitted,in_progress,in_approval,approved,rejected,cancelled',
            'template_id' => 'nullable|integer|exists:approval_templates,id',
            'sort' => 'nullable|in:subject,reference,status,current_step_order,submitted_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = ApprovalRequest::query()->with(['template', 'requester']);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->where('subject', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('template', function ($template) use ($term) {
                        $template->where('name', 'like', $term)->orWhere('code', 'like', $term);
                    })
                    ->orWhereHas('requester', function ($user) use ($term) {
                        $user->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('username', 'like', $term)
                            ->orWhere('code', 'like', $term);
                    });
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['template_id'] ?? null)) {
            $query->where('approval_template_id', $validated['template_id']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $requests = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/ApprovalRequests/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Approval Request'),
            'can' => [
                'create' => ActivePermission::check('approval-request.create'),
                'update' => ActivePermission::check('approval-request.update'),
                'delete' => ActivePermission::check('approval-request.delete'),
                'view' => ActivePermission::check('approval-request.view'),
            ],
            'stats' => [
                'total' => ApprovalRequest::count(),
                'in_progress' => ApprovalRequest::whereIn('status', ['submitted', 'in_progress', 'in_approval'])->count(),
                'approved' => ApprovalRequest::where('status', 'approved')->count(),
                'rejected' => ApprovalRequest::whereIn('status', ['rejected', 'cancelled'])->count(),
            ],
            'data' => [
                'rows' => collect($requests->items())->values()->map(fn (ApprovalRequest $approval, int $i) => [
                    'id' => $approval->id,
                    'no' => ($requests->firstItem() ?? 0) + $i,
                    'subject' => $approval->subject,
                    'reference' => $approval->reference,
                    'templateName' => $approval->template?->name ?? '-',
                    'templateCode' => $approval->template?->code,
                    'requesterName' => $approval->requester?->name ?? '-',
                    'status' => $approval->status,
                    'statusLabel' => self::STATUSES[$approval->status] ?? ucwords(str_replace('_', ' ', (string) $approval->status)),
                    'currentStep' => $approval->current_step_order,
                    'submittedAt' => $approval->submitted_at?->format('d M Y H:i'),
                    'createdAt' => $approval->created_at?->format('d M Y'),
                    'showUrl' => route('admin.organization.approval-requests.show', $approval->id),
                    'deleteUrl' => route('admin.organization.approval-requests.destroy', $approval->id),
                    'canDelete' => in_array($approval->status, ['draft', 'cancelled', 'rejected'], true),
                ])->all(),
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'perPage' => $requests->perPage(),
                'total' => $requests->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'template_id' => $validated['template_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => $this->statusOptions(),
                'templates' => ApprovalTemplate::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn (ApprovalTemplate $template) => [
                        'id' => (string) $template->id,
                        'label' => $template->name.($template->code ? ' ('.$template->code.')' : ''),
                    ])
                    ->values()
                    ->all(),
            ],
            'urls' => [
                'index' => route('admin.organization.approval-requests.index'),
                'create' => route('admin.organization.approval-requests.create'),
                'bulkDestroy' => route('admin.organization.approval-requests.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/ApprovalRequests/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Buat Request Approval'),
            'templates' => ApprovalTemplate::query()
                ->where('is_active', true)
                ->orderBy('module')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'module'])
                ->map(fn (ApprovalTemplate $template) => [
                    'id' => (string) $template->id,
                    'label' => $template->name.' (Kode: '.$template->code.')',
                    'module' => $template->module,
                ])
                ->values()
                ->all(),
            'urls' => [
                'index' => route('admin.organization.approval-requests.index'),
                'submit' => route('admin.organization.approval-requests.store'),
            ],
        ]);
    }

    public function store(Request $request, ApprovalEngine $engine)
    {
        $validated = $request->validate([
            'approval_template_id' => ['required', 'integer', 'exists:approval_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $template = ApprovalTemplate::findOrFail($validated['approval_template_id']);

        $approval = $engine->submitFromTemplate(
            template: $template,
            subject: $validated['subject'],
            requester: $request->user(),
            reference: ($validated['reference'] ?? null) ?: null,
            notes: ($validated['notes'] ?? null) ?: null,
            createdBy: $request->user()->id,
        );

        return redirect()->route('admin.organization.approval-requests.show', $approval->id)
            ->with('success', 'Request approval berhasil dibuat.');
    }

    public function show(Request $request, int $id): Response
    {
        $approval = ApprovalRequest::with([
            'template',
            'requester',
            'approvable',
            'steps.actedBy',
            'steps.organizationalPosition',
            'steps.workUnit',
            'actions.user',
        ])->findOrFail($id);

        $dedicated = $this->dedicatedReview($approval);
        $step = $approval->currentStep();
        $canAct = ! $dedicated && $step
            ? app(ApprovalEngine::class)->canUserActOnStep($request->user(), $step)
            : false;

        return Inertia::render('Admin/Organization/ApprovalRequests/Show', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Detail Approval'),
            'can' => [
                'update' => ActivePermission::check('approval-request.update'),
                'delete' => ActivePermission::check('approval-request.delete'),
            ],
            'approval' => [
                'id' => $approval->id,
                'subject' => $approval->subject,
                'reference' => $approval->reference,
                'notes' => $approval->notes,
                'status' => $approval->status,
                'statusLabel' => self::STATUSES[$approval->status] ?? ucwords(str_replace('_', ' ', (string) $approval->status)),
                'templateName' => $approval->template?->name,
                'templateCode' => $approval->template?->code,
                'requesterName' => $approval->requester?->name,
                'requesterEmail' => $approval->requester?->email,
                'submittedAt' => $approval->submitted_at?->format('d M Y H:i'),
                'completedAt' => $approval->completed_at?->format('d M Y H:i'),
                'currentStepOrder' => $approval->current_step_order,
                'waitingMessage' => $approval->waitingMessage(),
                'approvableType' => $approval->approvable_type,
                'approvableLabel' => $approval->approvable_type ? class_basename($approval->approvable_type) : null,
                'steps' => $approval->steps->map(fn ($item) => [
                    'id' => $item->id,
                    'order' => $item->step_order,
                    'name' => $item->name,
                    'status' => $item->status,
                    'statusLabel' => self::STEP_STATUSES[$item->status] ?? ucwords(str_replace('_', ' ', (string) $item->status)),
                    'approverLabel' => $this->approverLabel($item),
                    'isRequired' => (bool) $item->is_required,
                    'canReject' => (bool) $item->can_reject,
                    'dueAt' => $item->due_at?->format('d M Y H:i'),
                    'actedByName' => $item->actedBy?->name,
                    'actedAt' => $item->acted_at?->format('d M Y H:i'),
                    'notes' => $item->notes,
                ])->values()->all(),
                'actions' => $approval->actions->map(fn ($action) => [
                    'id' => $action->id,
                    'action' => $action->action,
                    'actionLabel' => ucwords(str_replace('_', ' ', (string) $action->action)),
                    'userName' => $action->user?->name,
                    'notes' => $action->notes,
                    'createdAt' => $action->created_at?->format('d M Y H:i'),
                ])->values()->all(),
            ],
            'review' => [
                'dedicated' => $dedicated !== null,
                'url' => $dedicated['url'] ?? null,
                'label' => $dedicated['label'] ?? null,
                'canAct' => $canAct,
                'active' => in_array($approval->status, ['submitted', 'in_progress', 'in_approval'], true),
            ],
            'urls' => [
                'index' => route('admin.organization.approval-requests.index'),
                'approve' => route('admin.organization.approval-requests.approve', $approval->id),
                'reject' => route('admin.organization.approval-requests.reject', $approval->id),
                'cancel' => route('admin.organization.approval-requests.cancel', $approval->id),
            ],
        ]);
    }

    public function approve(Request $request, int $id, ApprovalEngine $engine)
    {
        return $this->decide($request, $id, $engine, 'approve');
    }

    public function reject(Request $request, int $id, ApprovalEngine $engine)
    {
        return $this->decide($request, $id, $engine, 'reject');
    }

    public function cancel(Request $request, int $id, ApprovalEngine $engine)
    {
        return $this->decide($request, $id, $engine, 'cancel');
    }

    public function destroy(Request $request, int $id)
    {
        $approval = ApprovalRequest::findOrFail($id);

        if (! in_array($approval->status, ['draft', 'cancelled', 'rejected'], true)) {
            return back()->with('error', 'Hanya pengajuan draf, dibatalkan, atau ditolak yang dapat dihapus.');
        }

        $approval->update(['deleted_by' => $request->user()->id]);
        $approval->delete();

        return redirect()->route('admin.organization.approval-requests.index')
            ->with('success', 'Pengajuan approval berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:approval_requests,id',
        ]);

        $requests = ApprovalRequest::whereIn('id', $validated['ids'])->get();
        $deleted = 0;

        foreach ($requests as $approval) {
            if (! in_array($approval->status, ['draft', 'cancelled', 'rejected'], true)) {
                continue;
            }

            $approval->update(['deleted_by' => $request->user()->id]);
            $approval->delete();
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->with('error', 'Tidak ada pengajuan yang memenuhi syarat untuk dihapus.');
        }

        return back()->with('success', $deleted.' pengajuan approval berhasil dihapus.');
    }

    /**
     * Proses keputusan approval dengan penjagaan halaman review khusus modul.
     */
    private function decide(Request $request, int $id, ApprovalEngine $engine, string $action)
    {
        $approval = ApprovalRequest::with('approvable')->findOrFail($id);

        if ($this->dedicatedReview($approval) !== null) {
            $message = $action === 'cancel'
                ? 'Perubahan workflow harus diproses dari halaman review modul.'
                : 'Keputusan harus diproses dari halaman review modul agar data operasionalnya lengkap.';

            return back()->with('error', $message);
        }

        $validated = $request->validate(['notes' => 'nullable|string|max:5000']);
        $notes = ($validated['notes'] ?? null) ?: null;

        try {
            $engine->{$action}($approval, $request->user(), $notes);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        $message = match ($action) {
            'approve' => 'Step approval berhasil disetujui.',
            'reject' => 'Request approval berhasil ditolak.',
            'cancel' => 'Request approval berhasil dibatalkan.',
        };

        return back()->with('success', $message);
    }

    /**
     * @return array{url: string, label: string}|null
     */
    private function dedicatedReview(ApprovalRequest $approval): ?array
    {
        return match (true) {
            $approval->approvable instanceof StudentLeaveApplication => [
                'url' => route('admin.student-services.leave-applications.show', ['id' => $approval->approvable_id]),
                'label' => 'Buka Review Cuti',
            ],
            $approval->approvable instanceof StudentTransferRequest => [
                'url' => route('admin.student-services.transfer-requests.show', ['id' => $approval->approvable_id]),
                'label' => 'Buka Review Pindah',
            ],
            $approval->approvable instanceof GraduationApplication => [
                'url' => route('admin.student-services.graduation-applications.show', ['id' => $approval->approvable_id]),
                'label' => 'Buka Review Yudisium',
            ],
            default => null,
        };
    }

    private function approverLabel($step): string
    {
        return match ($step->approver_type) {
            'role' => 'Role: '.($step->approver_role ?: '-'),
            'permission' => 'Permission: '.($step->approver_permission ?: '-'),
            'position' => 'Jabatan: '.($step->organizationalPosition?->name ?: '-'),
            'work_unit' => 'Unit: '.($step->workUnit?->name ?: '-'),
            'user' => 'User: '.($step->approverUser?->name ?: '-'),
            default => ucwords(str_replace('_', ' ', (string) $step->approver_type)),
        };
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(self::STATUSES)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
