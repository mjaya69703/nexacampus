<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD template alur persetujuan — paritas halaman Livewire lama dengan
 * langkah approver dinamis (role, permission, jabatan, unit kerja).
 */
class ApprovalTemplateController extends Controller
{
    private const APPROVER_TYPES = [
        'role' => 'Role',
        'permission' => 'Permission',
        'position' => 'Jabatan Organisasi',
        'work_unit' => 'Unit Kerja',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'module' => 'nullable|string|max:80',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,module,steps_count,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = ApprovalTemplate::query()->withCount('steps');

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('module', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if (filled($validated['module'] ?? null)) {
            $query->where('module', $validated['module']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $templates = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/ApprovalTemplates/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Template Approval'),
            'can' => [
                'create' => ActivePermission::check('approval-template.create'),
                'update' => ActivePermission::check('approval-template.update'),
                'delete' => ActivePermission::check('approval-template.delete'),
            ],
            'stats' => [
                'total' => ApprovalTemplate::count(),
                'active' => ApprovalTemplate::where('is_active', true)->count(),
                'inactive' => ApprovalTemplate::where('is_active', false)->count(),
                'modules' => ApprovalTemplate::query()->distinct()->count('module'),
            ],
            'data' => [
                'rows' => collect($templates->items())->values()->map(fn (ApprovalTemplate $template, int $i) => [
                    'id' => $template->id,
                    'no' => ($templates->firstItem() ?? 0) + $i,
                    'name' => $template->name,
                    'code' => $template->code,
                    'module' => $template->module,
                    'stepsCount' => (int) $template->steps_count,
                    'isActive' => (bool) $template->is_active,
                    'createdAt' => $template->created_at?->format('d M Y'),
                    'editUrl' => route('admin.organization.approval-templates.edit', $template->id),
                    'toggleUrl' => route('admin.organization.approval-templates.toggle', $template->id),
                    'deleteUrl' => route('admin.organization.approval-templates.destroy', $template->id),
                    'canDelete' => ! $template->requests()->exists(),
                ])->all(),
                'currentPage' => $templates->currentPage(),
                'lastPage' => $templates->lastPage(),
                'perPage' => $templates->perPage(),
                'total' => $templates->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'module' => $validated['module'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'modules' => $this->modules(),
            ],
            'urls' => [
                'index' => route('admin.organization.approval-templates.index'),
                'create' => route('admin.organization.approval-templates.create'),
                'bulkDestroy' => route('admin.organization.approval-templates.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/ApprovalTemplates/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Template Approval'),
            'mode' => 'create',
            'template' => [
                'name' => '',
                'code' => '',
                'module' => 'organization',
                'description' => '',
                'is_active' => true,
                'steps' => [$this->defaultStep()],
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.organization.approval-templates.index'),
                'submit' => route('admin.organization.approval-templates.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $request) {
            $template = ApprovalTemplate::create([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'module' => $validated['module'],
                'description' => $validated['description'] ?: null,
                'is_active' => (bool) $validated['is_active'],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            foreach (array_values($validated['steps']) as $index => $step) {
                $template->steps()->create($this->stepPayload($step, $index + 1, $request->user()->id));
            }
        });

        return redirect()->route('admin.organization.approval-templates.index')
            ->with('success', 'Template approval berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $template = ApprovalTemplate::with('steps')->findOrFail($id);

        return Inertia::render('Admin/Organization/ApprovalTemplates/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Template Approval'),
            'mode' => 'edit',
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
                'module' => $template->module,
                'description' => $template->description ?? '',
                'is_active' => (bool) $template->is_active,
                'steps' => $template->steps
                    ->sortBy('step_order')
                    ->values()
                    ->map(fn ($step) => [
                        'name' => $step->name,
                        'approver_type' => $step->approver_type,
                        'approver_role' => $step->approver_role ?? '',
                        'approver_permission' => $step->approver_permission ?? '',
                        'organizational_position_id' => $step->organizational_position_id ? (string) $step->organizational_position_id : '',
                        'work_unit_id' => $step->work_unit_id ? (string) $step->work_unit_id : '',
                        'is_required' => (bool) $step->is_required,
                        'can_reject' => (bool) $step->can_reject,
                        'sla_hours' => $step->sla_hours ? (string) $step->sla_hours : '',
                        'description' => $step->description ?? '',
                    ])
                    ->all(),
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.organization.approval-templates.index'),
                'submit' => route('admin.organization.approval-templates.update', $template->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $template = ApprovalTemplate::findOrFail($id);
        $validated = $this->validatePayload($request, $template);

        DB::transaction(function () use ($template, $validated, $request) {
            $template->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'module' => $validated['module'],
                'description' => $validated['description'] ?: null,
                'is_active' => (bool) $validated['is_active'],
                'updated_by' => $request->user()->id,
            ]);

            $template->steps()->withTrashed()->forceDelete();

            foreach (array_values($validated['steps']) as $index => $step) {
                $template->steps()->create($this->stepPayload($step, $index + 1, $request->user()->id));
            }
        });

        return redirect()->route('admin.organization.approval-templates.index')
            ->with('success', 'Template approval berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        ApprovalTemplate::whereKey($id)->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status template approval berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $template = ApprovalTemplate::findOrFail($id);

        if ($template->requests()->exists()) {
            return back()->with('error', 'Template yang memiliki riwayat pengajuan tidak dapat dihapus.');
        }

        $template->update(['deleted_by' => $request->user()->id]);
        $template->delete();

        return redirect()->route('admin.organization.approval-templates.index')
            ->with('success', 'Template approval berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:approval_templates,id',
        ]);

        $templates = ApprovalTemplate::whereIn('id', $validated['ids'])->get();

        foreach ($templates as $template) {
            $template->update(['deleted_by' => $request->user()->id]);
            $template->delete();
        }

        return back()->with('success', $templates->count().' template approval berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ApprovalTemplate $template = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:80',
                Rule::unique('approval_templates', 'code')->ignore($template?->id),
            ],
            'module' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.approver_type' => ['required', Rule::in(array_keys(self::APPROVER_TYPES))],
            'steps.*.approver_role' => ['nullable', 'string', 'max:255'],
            'steps.*.approver_permission' => ['nullable', 'string', 'max:255'],
            'steps.*.organizational_position_id' => ['nullable', 'integer', 'exists:organizational_positions,id'],
            'steps.*.work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'steps.*.is_required' => ['boolean'],
            'steps.*.can_reject' => ['boolean'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'steps.*.description' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->validateStepTargets($validated['steps']);

        $validated['description'] = ($validated['description'] ?? null) ?: null;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    /**
     * Pastikan target approver sesuai tipe yang dipilih.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function validateStepTargets(array $steps): void
    {
        foreach ($steps as $index => $step) {
            $field = match ($step['approver_type']) {
                'role' => 'approver_role',
                'permission' => 'approver_permission',
                'position' => 'organizational_position_id',
                'work_unit' => 'work_unit_id',
            };

            if (blank($step[$field] ?? null)) {
                throw ValidationException::withMessages([
                    "steps.$index.$field" => 'Target approver wajib diisi.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function stepPayload(array $step, int $order, int $userId): array
    {
        return [
            'step_order' => $order,
            'name' => $step['name'],
            'approver_type' => $step['approver_type'],
            'approver_role' => $step['approver_type'] === 'role' ? $step['approver_role'] : null,
            'approver_permission' => $step['approver_type'] === 'permission' ? $step['approver_permission'] : null,
            'organizational_position_id' => $step['approver_type'] === 'position' ? ($step['organizational_position_id'] ?: null) : null,
            'work_unit_id' => $step['approver_type'] === 'work_unit' ? ($step['work_unit_id'] ?: null) : null,
            'is_required' => (bool) ($step['is_required'] ?? false),
            'can_reject' => (bool) ($step['can_reject'] ?? false),
            'sla_hours' => ($step['sla_hours'] ?? null) ?: null,
            'description' => ($step['description'] ?? null) ?: null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStep(): array
    {
        return [
            'name' => '',
            'approver_type' => 'permission',
            'approver_role' => '',
            'approver_permission' => '',
            'organizational_position_id' => '',
            'work_unit_id' => '',
            'is_required' => true,
            'can_reject' => true,
            'sla_hours' => '',
            'description' => '',
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function formOptions(): array
    {
        return [
            'approverTypes' => collect(self::APPROVER_TYPES)
                ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
                ->values()
                ->all(),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['name'])
                ->map(fn (Role $role) => ['id' => $role->name, 'label' => $role->name])
                ->values()
                ->all(),
            'permissions' => Permission::query()
                ->orderBy('name')
                ->get(['name'])
                ->map(fn (Permission $permission) => ['id' => $permission->name, 'label' => $permission->name])
                ->values()
                ->all(),
            'positions' => OrganizationalPosition::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (OrganizationalPosition $position) => [
                    'id' => (string) $position->id,
                    'label' => $position->name.($position->code ? ' ('.$position->code.')' : ''),
                ])
                ->values()
                ->all(),
            'workUnits' => WorkUnit::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (WorkUnit $unit) => [
                    'id' => (string) $unit->id,
                    'label' => $unit->name.($unit->code ? ' ('.$unit->code.')' : ''),
                ])
                ->values()
                ->all(),
            'modules' => $this->modules(),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function modules(): array
    {
        return ApprovalTemplate::query()
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->filter()
            ->map(fn (string $module) => ['id' => $module, 'label' => ucwords(str_replace(['_', '-'], ' ', $module))])
            ->values()
            ->all();
    }
}
