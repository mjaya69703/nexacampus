<?php

use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\WorkUnit;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public EmployeeProfile $profile;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->profile = EmployeeProfile::with('user')->findOrFail($id);
        $this->form = [
            'employee_number' => $this->profile->employee_number,
            'employment_type' => $this->profile->employment_type,
            'employment_status' => $this->profile->employment_status,
            'join_date' => $this->profile->join_date?->format('Y-m-d') ?? '',
            'end_date' => $this->profile->end_date?->format('Y-m-d') ?? '',
            'primary_work_unit_id' => $this->profile->primary_work_unit_id ? (string) $this->profile->primary_work_unit_id : '',
            'notes' => $this->profile->notes,
            'is_active' => (bool) $this->profile->is_active,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $this->profile->update([
            'employee_number' => $validated['form']['employee_number'] ?: null,
            'employment_type' => $validated['form']['employment_type'],
            'employment_status' => $validated['form']['employment_status'],
            'join_date' => $validated['form']['join_date'] ?: null,
            'end_date' => $validated['form']['end_date'] ?: null,
            'primary_work_unit_id' => $validated['form']['primary_work_unit_id'] ?: null,
            'notes' => $validated['form']['notes'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Profil pegawai berhasil diperbarui.');
        $this->redirectRoute('admin.organization.employee-profiles.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-profiles.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Pegawai',
        ]);
    }

    public function getSelectedUserProperty()
    {
        return $this->profile->user;
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function rules(): array
    {
        return [
            'form.employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employee_profiles', 'employee_number')->ignore($this->profile->id)],
            'form.employment_type' => ['required', 'string', 'in:lecturer,tendik,admin_staff,contract,guest,staff'],
            'form.employment_status' => ['required', 'string', 'in:active,inactive,suspended,resigned'],
            'form.join_date' => ['nullable', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.join_date'],
            'form.primary_work_unit_id' => ['nullable', 'exists:work_units,id'],
            'form.notes' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Pegawai</h3>
            <a href="{{ route('admin.organization.employee-profiles.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.employee-profiles._form')
        </div>
    </div>
</div>
