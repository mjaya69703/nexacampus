<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public ApprovalTemplate $template;
    public array $form = [];
    public array $steps = [];

    public function mount(int $id): void
    {
        $this->template = ApprovalTemplate::with('steps')->findOrFail($id);
        $this->form = [
            'name' => $this->template->name,
            'code' => $this->template->code,
            'module' => $this->template->module,
            'description' => $this->template->description,
            'is_active' => (bool) $this->template->is_active,
        ];
        $this->steps = $this->template->steps->map(fn ($step) => [
            'name' => $step->name,
            'approver_type' => $step->approver_type,
            'approver_role' => $step->approver_role ?: '',
            'approver_permission' => $step->approver_permission ?: '',
            'organizational_position_id' => $step->organizational_position_id ? (string) $step->organizational_position_id : '',
            'work_unit_id' => $step->work_unit_id ? (string) $step->work_unit_id : '',
            'is_required' => (bool) $step->is_required,
            'can_reject' => (bool) $step->can_reject,
            'sla_hours' => $step->sla_hours ?: '',
            'description' => $step->description ?: '',
        ])->values()->all() ?: [$this->defaultStep()];
    }

    public function addStep(): void
    {
        $this->steps[] = $this->defaultStep();
    }

    public function removeStep(int $index): void
    {
        if (count($this->steps) <= 1) {
            return;
        }

        array_splice($this->steps, $index, 1);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $this->validateSteps();

        DB::transaction(function () use ($validated) {
            $this->template->update([
                'name' => $validated['form']['name'],
                'code' => strtoupper($validated['form']['code']),
                'module' => $validated['form']['module'],
                'description' => $validated['form']['description'] ?: null,
                'is_active' => (bool) $validated['form']['is_active'],
                'updated_by' => auth()->id(),
            ]);

            $this->template->steps()->withTrashed()->forceDelete();

            foreach (array_values($validated['steps']) as $index => $step) {
                $this->template->steps()->create($this->stepPayload($step, $index + 1, 'created_by'));
            }
        });

        session()->flash('success', 'Template approval berhasil diperbarui.');
        $this->redirectRoute('admin.organization.approval-templates.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.approval-templates.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Template Approval',
        ]);
    }

    public function getRolesProperty()
    {
        return Role::orderBy('name')->get(['name']);
    }

    public function getPermissionsProperty()
    {
        return Permission::orderBy('name')->get(['name']);
    }

    public function getPositionsProperty()
    {
        return OrganizationalPosition::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('approval_templates', 'code')->ignore($this->template->id)],
            'form.module' => ['required', 'string', 'max:80'],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.approver_type' => ['required', 'in:role,permission,position,work_unit'],
            'steps.*.approver_role' => ['nullable', 'string', 'max:255'],
            'steps.*.approver_permission' => ['nullable', 'string', 'max:255'],
            'steps.*.organizational_position_id' => ['nullable', 'integer', 'exists:organizational_positions,id'],
            'steps.*.work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'steps.*.is_required' => ['boolean'],
            'steps.*.can_reject' => ['boolean'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'steps.*.description' => ['nullable', 'string'],
        ];
    }

    private function validateSteps(): void
    {
        foreach ($this->steps as $index => $step) {
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

    private function stepPayload(array $step, int $order, string $auditField): array
    {
        return [
            'step_order' => $order,
            'name' => $step['name'],
            'approver_type' => $step['approver_type'],
            'approver_role' => $step['approver_type'] === 'role' ? $step['approver_role'] : null,
            'approver_permission' => $step['approver_type'] === 'permission' ? $step['approver_permission'] : null,
            'organizational_position_id' => $step['approver_type'] === 'position' ? $step['organizational_position_id'] : null,
            'work_unit_id' => $step['approver_type'] === 'work_unit' ? $step['work_unit_id'] : null,
            'is_required' => (bool) $step['is_required'],
            'can_reject' => (bool) $step['can_reject'],
            'sla_hours' => $step['sla_hours'] ?: null,
            'description' => $step['description'] ?: null,
            $auditField => auth()->id(),
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Template Approval</h3>
            <a href="{{ route('admin.organization.approval-templates.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.approval-templates._form')
        </div>
    </div>
</div>
