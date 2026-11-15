<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\EmployeeLeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD pengajuan cuti pegawai — paritas halaman Livewire lama: daftar,
 * formulir pengajuan + submit approval, detail saldo cuti, dan pembatalan.
 */
class EmployeeLeaveRequestController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Diajukan',
        'in_approval' => 'Dalam Persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:draft,submitted,in_approval,approved,rejected,cancelled',
            'leave_type_id' => 'nullable|integer|exists:employee_leave_types,id',
            'sort' => 'nullable|in:request_number,total_days,status,starts_at,ends_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = EmployeeLeaveRequest::query()
            ->with(['employeeProfile.user', 'leaveType', 'approvalRequest']);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->where('request_number', 'like', $term)
                    ->orWhere('reason', 'like', $term)
                    ->orWhereHas('employeeProfile.user', function ($user) use ($term) {
                        $user->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('username', 'like', $term)
                            ->orWhere('code', 'like', $term);
                    })
                    ->orWhereHas('employeeProfile', fn ($profile) => $profile->where('employee_number', 'like', $term))
                    ->orWhereHas('leaveType', function ($type) use ($term) {
                        $type->where('name', 'like', $term)->orWhere('code', 'like', $term);
                    });
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['leave_type_id'] ?? null)) {
            $query->where('employee_leave_type_id', $validated['leave_type_id']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $requests = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/EmployeeLeaveRequests/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Cuti Pegawai'),
            'can' => [
                'create' => ActivePermission::check('employee-leave-request.create'),
                'update' => ActivePermission::check('employee-leave-request.update'),
                'delete' => ActivePermission::check('employee-leave-request.delete'),
                'view' => ActivePermission::check('employee-leave-request.view'),
            ],
            'stats' => [
                'total' => EmployeeLeaveRequest::count(),
                'in_approval' => EmployeeLeaveRequest::whereIn('status', ['submitted', 'in_approval'])->count(),
                'approved' => EmployeeLeaveRequest::where('status', 'approved')->count(),
                'rejected_cancelled' => EmployeeLeaveRequest::whereIn('status', ['rejected', 'cancelled'])->count(),
            ],
            'data' => [
                'rows' => collect($requests->items())->values()->map(fn (EmployeeLeaveRequest $leave, int $i) => [
                    'id' => $leave->id,
                    'no' => ($requests->firstItem() ?? 0) + $i,
                    'requestNumber' => $leave->request_number,
                    'employeeName' => $leave->employeeProfile?->user?->name ?? '-',
                    'employeeNumber' => $leave->employeeProfile?->employee_number,
                    'leaveType' => $leave->leaveType?->name ?? '-',
                    'period' => $leave->starts_at?->format('d M Y').' - '.$leave->ends_at?->format('d M Y'),
                    'totalDays' => (float) $leave->total_days,
                    'status' => $leave->status,
                    'statusLabel' => self::STATUSES[$leave->status] ?? ucwords(str_replace('_', ' ', (string) $leave->status)),
                    'createdAt' => $leave->created_at?->format('d M Y'),
                    'showUrl' => route('admin.organization.employee-leave-requests.show', $leave->id),
                    'deleteUrl' => route('admin.organization.employee-leave-requests.destroy', $leave->id),
                ])->all(),
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'perPage' => $requests->perPage(),
                'total' => $requests->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'leave_type_id' => $validated['leave_type_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => $this->statusOptions(),
                'leaveTypes' => EmployeeLeaveType::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn (EmployeeLeaveType $type) => [
                        'id' => (string) $type->id,
                        'label' => $type->name.($type->code ? ' ('.$type->code.')' : ''),
                    ])
                    ->values()
                    ->all(),
            ],
            'urls' => [
                'index' => route('admin.organization.employee-leave-requests.index'),
                'create' => route('admin.organization.employee-leave-requests.create'),
                'bulkDestroy' => route('admin.organization.employee-leave-requests.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/EmployeeLeaveRequests/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Ajukan Cuti Pegawai'),
            'leaveTypes' => EmployeeLeaveType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'default_days_per_year'])
                ->map(fn (EmployeeLeaveType $type) => [
                    'id' => (string) $type->id,
                    'label' => $type->name.($type->code ? ' ('.$type->code.')' : ''),
                    'defaultDays' => (float) $type->default_days_per_year,
                ])
                ->values()
                ->all(),
            'defaults' => [
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->toDateString(),
            ],
            'urls' => [
                'index' => route('admin.organization.employee-leave-requests.index'),
                'submit' => route('admin.organization.employee-leave-requests.store'),
                'searchEmployees' => route('admin.organization.employee-leave-requests.search-employees'),
            ],
        ]);
    }

    public function store(Request $request, EmployeeLeaveRequestService $service)
    {
        $validated = $request->validate([
            'employee_profile_id' => ['required', 'integer', 'exists:employee_profiles,id'],
            'employee_leave_type_id' => ['required', 'integer', 'exists:employee_leave_types,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:2000'],
            'employee_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = EmployeeProfile::findOrFail($validated['employee_profile_id']);
        $type = EmployeeLeaveType::findOrFail($validated['employee_leave_type_id']);

        try {
            $leave = $service->create($employee, $type, [
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'reason' => $validated['reason'],
                'employee_notes' => ($validated['employee_notes'] ?? null) ?: null,
            ], $request->user()->id);

            $leave = $service->submit($leave, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.organization.employee-leave-requests.show', $leave->id)
            ->with('success', 'Pengajuan cuti pegawai berhasil dibuat.');
    }

    public function show(Request $request, int $id): Response
    {
        $leave = EmployeeLeaveRequest::with([
            'employeeProfile.user',
            'employeeProfile.primaryWorkUnit',
            'leaveType',
            'approvalRequest.steps',
            'approvalRequest.actions.actor',
        ])->findOrFail($id);

        $balance = EmployeeLeaveBalance::query()
            ->where('employee_profile_id', $leave->employee_profile_id)
            ->where('employee_leave_type_id', $leave->employee_leave_type_id)
            ->where('year', $leave->starts_at?->year)
            ->first();

        $canCancel = in_array($leave->status, ['draft', 'submitted', 'in_approval'], true)
            && ActivePermission::check('employee-leave-request.update');

        return Inertia::render('Admin/Organization/EmployeeLeaveRequests/Show', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Detail Cuti Pegawai'),
            'can' => [
                'update' => ActivePermission::check('employee-leave-request.update'),
                'delete' => ActivePermission::check('employee-leave-request.delete'),
            ],
            'leave' => [
                'id' => $leave->id,
                'requestNumber' => $leave->request_number,
                'status' => $leave->status,
                'statusLabel' => self::STATUSES[$leave->status] ?? ucwords(str_replace('_', ' ', (string) $leave->status)),
                'employeeName' => $leave->employeeProfile?->user?->name ?? '-',
                'employeeNumber' => $leave->employeeProfile?->employee_number,
                'employeeEmail' => $leave->employeeProfile?->user?->email,
                'workUnit' => $leave->employeeProfile?->primaryWorkUnit?->name,
                'leaveType' => $leave->leaveType?->name,
                'leaveTypeCode' => $leave->leaveType?->code,
                'startsAt' => $leave->starts_at?->format('d M Y'),
                'endsAt' => $leave->ends_at?->format('d M Y'),
                'startsAtDay' => $leave->starts_at?->translatedFormat('l, d M Y'),
                'endsAtDay' => $leave->ends_at?->translatedFormat('l, d M Y'),
                'totalDays' => (float) $leave->total_days,
                'reason' => $leave->reason,
                'employeeNotes' => $leave->employee_notes,
                'adminNotes' => $leave->admin_notes,
                'createdAt' => $leave->created_at?->format('d M Y H:i'),
                'approval' => $leave->approvalRequest ? [
                    'id' => $leave->approvalRequest->id,
                    'subject' => $leave->approvalRequest->subject,
                    'status' => $leave->approvalRequest->status,
                    'statusLabel' => self::STATUSES[$leave->approvalRequest->status] ?? ucwords(str_replace('_', ' ', (string) $leave->approvalRequest->status)),
                    'url' => route('admin.organization.approval-requests.show', $leave->approvalRequest->id),
                ] : null,
            ],
            'balance' => $balance ? [
                'year' => $balance->year,
                'availableDays' => (float) $balance->availableDays(),
                'allocatedDays' => (float) $balance->allocated_days,
                'usedDays' => (float) $balance->used_days,
                'pendingDays' => (float) $balance->pending_days,
                'carriedOverDays' => (float) $balance->carried_over_days,
            ] : null,
            'canCancel' => $canCancel,
            'urls' => [
                'index' => route('admin.organization.employee-leave-requests.index'),
                'cancel' => route('admin.organization.employee-leave-requests.cancel', $leave->id),
                'delete' => route('admin.organization.employee-leave-requests.destroy', $leave->id),
            ],
        ]);
    }

    public function cancel(Request $request, int $id, EmployeeLeaveRequestService $service)
    {
        $leave = EmployeeLeaveRequest::findOrFail($id);
        $validated = $request->validate(['notes' => 'nullable|string|max:2000']);

        try {
            $service->cancel($leave, $request->user()->id, ($validated['notes'] ?? null) ?: 'Dibatalkan dari admin cuti pegawai.');
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        return back()->with('success', 'Pengajuan cuti berhasil dibatalkan.');
    }

    public function destroy(Request $request, int $id)
    {
        $leave = EmployeeLeaveRequest::findOrFail($id);
        $leave->update(['deleted_by' => $request->user()->id]);
        $leave->delete();

        return redirect()->route('admin.organization.employee-leave-requests.index')
            ->with('success', 'Pengajuan cuti pegawai berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employee_leave_requests,id',
        ]);

        $leaves = EmployeeLeaveRequest::whereIn('id', $validated['ids'])->get();

        foreach ($leaves as $leave) {
            $leave->update(['deleted_by' => $request->user()->id]);
            $leave->delete();
        }

        return back()->with('success', $leaves->count().' pengajuan cuti berhasil dihapus.');
    }

    /**
     * Endpoint pencarian pegawai aktif untuk picker pengajuan cuti.
     */
    public function searchEmployees(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'exclude' => 'nullable|string|max:2000',
        ]);

        $exclude = collect(explode(',', (string) ($validated['exclude'] ?? '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $q = $validated['q'] ?? '';

        $options = EmployeeProfile::query()
            ->with(['user', 'primaryWorkUnit'])
            ->where('is_active', true)
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->when(filled($q), function ($query) use ($q) {
                $search = '%'.$q.'%';

                $query->where(function ($sub) use ($search) {
                    $sub->where('employee_number', 'like', $search)
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search)
                                ->orWhere('email', 'like', $search)
                                ->orWhere('username', 'like', $search)
                                ->orWhere('code', 'like', $search);
                        });
                });
            })
            ->limit(12)
            ->get()
            ->map(fn (EmployeeProfile $profile) => [
                'id' => $profile->id,
                'label' => $profile->user?->name ?? '-',
                'meta' => [
                    'email' => $profile->user?->email,
                    'employee_number' => $profile->employee_number,
                    'work_unit' => $profile->primaryWorkUnit?->name,
                ],
            ])
            ->values()
            ->all();

        return response()->json(['options' => $options]);
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
