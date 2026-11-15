<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeLeaveType;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD jenis cuti pegawai — paritas halaman Livewire lama.
 */
class EmployeeLeaveTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'requires_approval' => 'nullable|in:0,1',
            'is_paid' => 'nullable|in:0,1',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,default_days_per_year,requires_approval,is_paid,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = EmployeeLeaveType::query()->with('approvalTemplate');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhereHas('approvalTemplate', fn ($template) => $template->where('name', 'like', "%{$q}%")));
        }

        foreach (['requires_approval', 'is_paid', 'is_active'] as $booleanFilter) {
            if (isset($validated[$booleanFilter]) && $validated[$booleanFilter] !== '') {
                $query->where($booleanFilter, (bool) $validated[$booleanFilter]);
            }
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $types = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/EmployeeLeaveTypes/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Jenis Cuti Pegawai'),
            'can' => [
                'create' => ActivePermission::check('employee-leave-type.create'),
                'update' => ActivePermission::check('employee-leave-type.update'),
                'delete' => ActivePermission::check('employee-leave-type.delete'),
            ],
            'stats' => [
                'total' => EmployeeLeaveType::count(),
                'active' => EmployeeLeaveType::where('is_active', true)->count(),
                'paid' => EmployeeLeaveType::where('is_paid', true)->count(),
                'requiresApproval' => EmployeeLeaveType::where('requires_approval', true)->count(),
            ],
            'data' => [
                'rows' => collect($types->items())->values()->map(fn (EmployeeLeaveType $type, int $i) => [
                    'id' => $type->id,
                    'no' => ($types->firstItem() ?? 0) + $i,
                    'name' => $type->name,
                    'code' => $type->code,
                    'defaultDaysPerYear' => (float) $type->default_days_per_year,
                    'requiresApproval' => (bool) $type->requires_approval,
                    'isPaid' => (bool) $type->is_paid,
                    'isActive' => (bool) $type->is_active,
                    'templateName' => $type->approvalTemplate?->name,
                    'editUrl' => route('admin.organization.employee-leave-types.edit', $type->id),
                    'toggleUrl' => route('admin.organization.employee-leave-types.toggle', $type->id),
                    'deleteUrl' => route('admin.organization.employee-leave-types.destroy', $type->id),
                ])->all(),
                'currentPage' => $types->currentPage(),
                'lastPage' => $types->lastPage(),
                'perPage' => $types->perPage(),
                'total' => $types->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'requires_approval' => $validated['requires_approval'] ?? '',
                'is_paid' => $validated['is_paid'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.organization.employee-leave-types.index'),
                'create' => route('admin.organization.employee-leave-types.create'),
                'bulkDestroy' => route('admin.organization.employee-leave-types.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/EmployeeLeaveTypes/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Jenis Cuti Pegawai'),
            'mode' => 'create',
            'leaveType' => [
                'name' => '',
                'code' => '',
                'approval_template_id' => '',
                'default_days_per_year' => 0,
                'requires_approval' => true,
                'is_paid' => true,
                'is_active' => true,
                'description' => '',
            ],
            'templates' => $this->templates(),
            'urls' => [
                'index' => route('admin.organization.employee-leave-types.index'),
                'submit' => route('admin.organization.employee-leave-types.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        EmployeeLeaveType::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.employee-leave-types.index')
            ->with('success', 'Jenis cuti pegawai berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $leaveType = EmployeeLeaveType::findOrFail($id);

        return Inertia::render('Admin/Organization/EmployeeLeaveTypes/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Jenis Cuti Pegawai'),
            'mode' => 'edit',
            'leaveType' => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'code' => $leaveType->code,
                'approval_template_id' => $leaveType->approval_template_id ?? '',
                'default_days_per_year' => (float) $leaveType->default_days_per_year,
                'requires_approval' => (bool) $leaveType->requires_approval,
                'is_paid' => (bool) $leaveType->is_paid,
                'is_active' => (bool) $leaveType->is_active,
                'description' => $leaveType->description ?? '',
            ],
            'templates' => $this->templates(),
            'urls' => [
                'index' => route('admin.organization.employee-leave-types.index'),
                'submit' => route('admin.organization.employee-leave-types.update', $leaveType->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $leaveType = EmployeeLeaveType::findOrFail($id);
        $validated = $this->validatePayload($request, $leaveType);

        $leaveType->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.employee-leave-types.index')
            ->with('success', 'Jenis cuti pegawai berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $leaveType = EmployeeLeaveType::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $leaveType->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status jenis cuti berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $leaveType = EmployeeLeaveType::findOrFail($id);

        if ($leaveType->requests()->exists() || $leaveType->balances()->exists()) {
            return back()->with('error', 'Jenis cuti tidak dapat dihapus karena masih digunakan dalam pengajuan cuti atau saldo cuti pegawai.');
        }

        $leaveType->update(['deleted_by' => $request->user()->id]);
        $leaveType->delete();

        return redirect()->route('admin.organization.employee-leave-types.index')
            ->with('success', 'Jenis cuti "'.$leaveType->name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employee_leave_types,id',
        ]);

        $types = EmployeeLeaveType::whereIn('id', $validated['ids'])->get();

        foreach ($types as $type) {
            $type->update(['deleted_by' => $request->user()->id]);
            $type->delete();
        }

        return back()->with('success', $types->count().' jenis cuti berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?EmployeeLeaveType $leaveType = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('employee_leave_types', 'code')->ignore($leaveType?->id)],
            'approval_template_id' => ['nullable', 'exists:approval_templates,id'],
            'default_days_per_year' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['boolean'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['approval_template_id'] = ($validated['approval_template_id'] ?? null) ?: null;
        $validated['default_days_per_year'] = $validated['default_days_per_year'] ?? 0;
        $validated['requires_approval'] = (bool) ($validated['requires_approval'] ?? false);
        $validated['is_paid'] = (bool) ($validated['is_paid'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['description'] = ($validated['description'] ?? null) ?: null;

        return $validated;
    }

    /**
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function templates(): array
    {
        return ApprovalTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (ApprovalTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
            ])
            ->all();
    }
}
