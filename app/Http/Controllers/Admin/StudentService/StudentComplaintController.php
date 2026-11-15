<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentComplaint;
use App\Models\StudentService\StudentComplaintAttachment;
use App\Models\StudentService\StudentComplaintCategory;
use App\Models\StudentService\StudentComplaintMessage;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Database\PortableOrder;
use App\Support\Inertia\ShellProps;
use App\Support\StudentService\StudentComplaintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Monitoring & penanganan pengaduan mahasiswa (ticketing).
 */
class StudentComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'student_complaint_category_id' => 'nullable|integer',
            'assigned_work_unit_id' => 'nullable|integer',
            'status' => 'nullable|in:submitted,in_review,waiting_student,responded,resolved,closed,rejected,reopened',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'sort' => 'nullable|in:ticket_number,created_at,priority,status,due_at,last_message_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = $this->scopedQuery($request)
            ->with(['studentProfile.user', 'category', 'assignedWorkUnit', 'assignedUser']);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('ticket_number', 'like', "%{$q}%")
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('studentProfile', fn ($student) => $student->where('nim', 'like', "%{$q}%"))
                    ->orWhereHas('studentProfile.user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['student_complaint_category_id'] ?? null)) {
            $query->where('student_complaint_category_id', $validated['student_complaint_category_id']);
        }

        if (filled($validated['assigned_work_unit_id'] ?? null)) {
            $query->where('assigned_work_unit_id', $validated['assigned_work_unit_id']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['priority'] ?? null)) {
            $query->where('priority', $validated['priority']);
        }

        $sort = $validated['sort'] ?? 'last_message_at';
        $direction = $validated['direction'] ?? 'desc';

        if ($sort === 'status') {
            $query->orderByRaw(PortableOrder::priority('status', ['submitted', 'reopened', 'in_review', 'waiting_student', 'responded', 'resolved', 'closed', 'rejected']));
        } else {
            $query->orderBy($sort, $direction);
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $complaints = $query->paginate($perPage)->withQueryString();

        $statsQuery = $this->scopedQuery($request);

        return Inertia::render('Admin/StudentServices/Complaints/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Pengaduan Mahasiswa'),
            'can' => [
                'view' => ActivePermission::check('student-complaint.view'),
                'assign' => ActivePermission::check('student-complaint.assign') || ActivePermission::check('student-complaint.update'),
            ],
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'open' => (clone $statsQuery)->whereIn('status', ['submitted', 'reopened', 'in_review'])->count(),
                'waitingStudent' => (clone $statsQuery)->where('status', 'waiting_student')->count(),
                'overdue' => (clone $statsQuery)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
                    ->count(),
            ],
            'data' => [
                'rows' => collect($complaints->items())->values()->map(fn (StudentComplaint $complaint, int $i) => [
                    'id' => $complaint->id,
                    'no' => ($complaints->firstItem() ?? 0) + $i,
                    'ticketNumber' => $complaint->ticket_number,
                    'subject' => $complaint->subject,
                    'student' => $complaint->studentProfile?->user?->name ?? '-',
                    'nim' => $complaint->studentProfile?->nim ?? '-',
                    'category' => $complaint->category?->name ?? '-',
                    'workUnit' => $complaint->assignedWorkUnit?->name ?? '-',
                    'assignedUser' => $complaint->assignedUser?->name ?? '-',
                    'priority' => $complaint->priority,
                    'status' => $complaint->status,
                    'dueAt' => $complaint->due_at?->format('d M Y H:i'),
                    'slaLabel' => $this->slaLabel($complaint),
                    'slaTone' => $this->slaTone($complaint),
                    'lastMessageAt' => $complaint->last_message_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.student-services.complaints.show', $complaint->id),
                ])->all(),
                'currentPage' => $complaints->currentPage(),
                'lastPage' => $complaints->lastPage(),
                'perPage' => $complaints->perPage(),
                'total' => $complaints->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'student_complaint_category_id' => $validated['student_complaint_category_id'] ?? '',
                'assigned_work_unit_id' => $validated['assigned_work_unit_id'] ?? '',
                'status' => $validated['status'] ?? '',
                'priority' => $validated['priority'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'categories' => StudentComplaintCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'workUnits' => WorkUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'urls' => [
                'index' => route('admin.student-services.complaints.index'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $complaint = $this->scopedQuery($request)
            ->with([
                'studentProfile.user',
                'studentProfile.studyProgram',
                'category',
                'assignedWorkUnit',
                'assignedUser',
                'messages.user',
                'messages.attachments',
                'histories.changedBy',
            ])
            ->findOrFail($id);

        $canAssign = ActivePermission::check('student-complaint.assign') || ActivePermission::check('student-complaint.update');
        $canRespond = ActivePermission::check('student-complaint.respond') || ActivePermission::check('student-complaint.update');
        $canResolve = ActivePermission::check('student-complaint.resolve') || ActivePermission::check('student-complaint.update');

        return Inertia::render('Admin/StudentServices/Complaints/Show', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Detail Pengaduan'),
            'can' => [
                'assign' => $canAssign,
                'respond' => $canRespond,
                'resolve' => $canResolve,
            ],
            'complaint' => [
                'id' => $complaint->id,
                'ticketNumber' => $complaint->ticket_number,
                'subject' => $complaint->subject,
                'description' => $complaint->description,
                'status' => $complaint->status,
                'priority' => $complaint->priority,
                'student' => $complaint->studentProfile?->user?->name ?? '-',
                'nim' => $complaint->studentProfile?->nim ?? '-',
                'studyProgram' => $complaint->studentProfile?->studyProgram?->name ?? '-',
                'category' => $complaint->category?->name ?? '-',
                'assignedWorkUnitId' => $complaint->assigned_work_unit_id,
                'assignedWorkUnit' => $complaint->assignedWorkUnit?->name ?? '-',
                'assignedUserId' => $complaint->assigned_user_id,
                'assignedUser' => $complaint->assignedUser?->name ?? '-',
                'dueAt' => $complaint->due_at?->format('d M Y H:i'),
                'slaLabel' => $this->slaLabel($complaint),
                'slaTone' => $this->slaTone($complaint),
                'lastMessageAt' => $complaint->last_message_at?->format('d M Y H:i'),
                'resolvedAt' => $complaint->resolved_at?->format('d M Y H:i'),
                'closedAt' => $complaint->closed_at?->format('d M Y H:i'),
                'createdAt' => $complaint->created_at?->format('d M Y H:i'),
                'messages' => $complaint->messages->map(fn (StudentComplaintMessage $message) => [
                    'id' => $message->id,
                    'senderType' => $message->sender_type,
                    'sender' => $message->user?->name ?? ($message->sender_type === 'student' ? 'Mahasiswa' : 'Petugas'),
                    'message' => $message->message,
                    'isInternal' => (bool) $message->is_internal_note,
                    'createdAt' => $message->created_at?->format('d M Y H:i'),
                    'attachments' => $message->attachments->map(fn (StudentComplaintAttachment $attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->file_name,
                        'size' => $attachment->file_size,
                        'url' => route('admin.student-services.complaint-attachments.download', ['attachment' => $attachment->id]),
                    ])->all(),
                ])->all(),
                'histories' => $complaint->histories->map(fn ($history) => [
                    'id' => $history->id,
                    'from' => $history->from_status,
                    'to' => $history->to_status,
                    'notes' => $history->notes,
                    'changedBy' => $history->changedBy?->name,
                    'changedAt' => $history->created_at?->format('d M Y H:i'),
                ])->all(),
            ],
            'workUnits' => WorkUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'assignableUsers' => User::query()
                ->where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->limit(100)
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => trim($user->first_name.' '.$user->last_name),
                    'email' => $user->email,
                ])->all(),
            'statusActions' => [
                ['id' => 'waiting_student', 'name' => 'Tunggu Mahasiswa', 'tone' => 'amber'],
                ['id' => 'resolved', 'name' => 'Tandai Selesai', 'tone' => 'green'],
                ['id' => 'closed', 'name' => 'Tutup Tiket', 'tone' => 'gray'],
                ['id' => 'rejected', 'name' => 'Tolak', 'tone' => 'red'],
            ],
            'urls' => [
                'index' => route('admin.student-services.complaints.index'),
                'assign' => route('admin.student-services.complaints.assign', $complaint->id),
                'claim' => route('admin.student-services.complaints.claim', $complaint->id),
                'reply' => route('admin.student-services.complaints.reply', $complaint->id),
                'setStatus' => route('admin.student-services.complaints.set-status', $complaint->id),
            ],
        ]);
    }

    public function assign(Request $request, int $id)
    {
        abort_unless(
            ActivePermission::check('student-complaint.assign') || ActivePermission::check('student-complaint.update'),
            403,
        );

        $complaint = $this->scopedQuery($request)->findOrFail($id);
        $validated = $request->validate([
            'assigned_work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            app(StudentComplaintService::class)->assign(
                $complaint,
                $validated['assigned_work_unit_id'] ?? null,
                $validated['assigned_user_id'] ?? null,
                $validated['notes'] ?? null,
                $request->user()->id,
            );

            return back()->with('success', 'Assignment pengaduan diperbarui.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function claim(Request $request, int $id)
    {
        abort_unless(
            ActivePermission::check('student-complaint.assign') || ActivePermission::check('student-complaint.update'),
            403,
        );

        $complaint = $this->scopedQuery($request)->findOrFail($id);

        try {
            app(StudentComplaintService::class)->assign(
                $complaint,
                $complaint->assigned_work_unit_id,
                $request->user()->id,
                'Tiket diambil oleh staff.',
                $request->user()->id,
            );

            return back()->with('success', 'Tiket berhasil diambil.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function reply(Request $request, int $id)
    {
        abort_unless(
            ActivePermission::check('student-complaint.respond') || ActivePermission::check('student-complaint.update'),
            403,
        );

        $complaint = $this->scopedQuery($request)->findOrFail($id);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => ['file', 'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt', 'max:5120'],
        ]);

        try {
            app(StudentComplaintService::class)->addMessage(
                $complaint,
                $request->user()->id,
                'admin',
                $validated['message'],
                $request->file('attachments', []),
            );

            return back()->with('success', 'Balasan berhasil dikirim.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function setStatus(Request $request, int $id)
    {
        abort_unless(
            ActivePermission::check('student-complaint.resolve') || ActivePermission::check('student-complaint.update'),
            403,
        );

        $complaint = $this->scopedQuery($request)->findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_review', 'waiting_student', 'resolved', 'closed', 'rejected', 'reopened'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            app(StudentComplaintService::class)->setStatus(
                $complaint,
                $validated['status'],
                $validated['notes'] ?? null,
                $request->user()->id,
            );

            return back()->with('success', 'Status pengaduan berhasil diperbarui.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Batasi pengaduan sesuai role aktif: admin/superuser melihat semua,
     * selain itu hanya tiket yang ditugaskan ke dirinya atau unit kerjanya.
     */
    private function scopedQuery(Request $request): Builder
    {
        $query = StudentComplaint::query();
        $user = $request->user();

        if (in_array(session('active_role'), ['superuser', 'admin'], true)) {
            return $query;
        }

        $unitIds = $user?->activeWorkUnits()->pluck('work_units.id')->all() ?? [];

        return $query->where(function (Builder $sub) use ($unitIds, $user) {
            $sub->where('assigned_user_id', $user?->id ?: 0);

            if ($unitIds !== []) {
                $sub->orWhereIn('assigned_work_unit_id', $unitIds);
            }
        });
    }

    private function slaLabel(StudentComplaint $complaint): string
    {
        if (! $complaint->due_at) {
            return '-';
        }

        if (in_array($complaint->status, ['resolved', 'closed', 'rejected'], true)) {
            return 'Selesai';
        }

        return $complaint->due_at->isPast()
            ? 'Lewat SLA '.$complaint->due_at->diffForHumans()
            : 'Sisa '.$complaint->due_at->diffForHumans(null, true);
    }

    private function slaTone(StudentComplaint $complaint): string
    {
        if (! $complaint->due_at || in_array($complaint->status, ['resolved', 'closed', 'rejected'], true)) {
            return 'gray';
        }

        return $complaint->due_at->isPast() ? 'red' : 'amber';
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['id' => 'submitted', 'name' => 'Masuk'],
            ['id' => 'in_review', 'name' => 'Direview'],
            ['id' => 'waiting_student', 'name' => 'Menunggu Mahasiswa'],
            ['id' => 'responded', 'name' => 'Sudah Dibalas'],
            ['id' => 'resolved', 'name' => 'Selesai'],
            ['id' => 'closed', 'name' => 'Ditutup'],
            ['id' => 'rejected', 'name' => 'Ditolak'],
            ['id' => 'reopened', 'name' => 'Dibuka Lagi'],
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function priorityOptions(): array
    {
        return [
            ['id' => 'low', 'name' => 'Rendah'],
            ['id' => 'normal', 'name' => 'Normal'],
            ['id' => 'high', 'name' => 'Tinggi'],
            ['id' => 'urgent', 'name' => 'Mendesak'],
        ];
    }
}
