<?php

use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeLeaveType;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];

    public function mount(): void
    {
        $this->form = [
            'name' => '',
            'code' => '',
            'approval_template_id' => '',
            'default_days_per_year' => 0,
            'requires_approval' => true,
            'is_paid' => true,
            'is_active' => true,
            'description' => '',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        EmployeeLeaveType::create($this->payload($validated['form'], 'created_by'));
        session()->flash('success', 'Jenis cuti pegawai berhasil dibuat.');
        $this->redirectRoute('admin.organization.employee-leave-types.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-leave-types.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Jenis Cuti Pegawai']);
    }

    public function getTemplatesProperty()
    {
        return ApprovalTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('employee_leave_types', 'code')],
            'form.approval_template_id' => ['nullable', 'exists:approval_templates,id'],
            'form.default_days_per_year' => ['nullable', 'numeric', 'min:0'],
            'form.requires_approval' => ['boolean'],
            'form.is_paid' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string'],
        ];
    }

    private function payload(array $form, string $auditField): array
    {
        return [
            'name' => $form['name'],
            'code' => strtoupper($form['code']),
            'approval_template_id' => $form['approval_template_id'] ?: null,
            'default_days_per_year' => $form['default_days_per_year'] ?: 0,
            'requires_approval' => (bool) $form['requires_approval'],
            'is_paid' => (bool) $form['is_paid'],
            'is_active' => (bool) $form['is_active'],
            'description' => $form['description'] ?: null,
            $auditField => auth()->id(),
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Tambah Jenis Cuti Pegawai</h3>
            <a href="{{ route('admin.organization.employee-leave-types.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.employee-leave-types._form')
        </div>
    </div>
</div>
