<?php

use App\Models\Organization\OrganizationalPosition;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public OrganizationalPosition $position;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->position = OrganizationalPosition::findOrFail($id);
        $this->form = [
            'name' => $this->position->name,
            'code' => $this->position->code,
            'category' => $this->position->category,
            'scope_type' => $this->position->scope_type,
            'description' => $this->position->description,
            'is_active' => (bool) $this->position->is_active,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $this->position->update([
            'name' => $validated['form']['name'],
            'code' => strtoupper($validated['form']['code']),
            'category' => $validated['form']['category'],
            'scope_type' => $validated['form']['scope_type'],
            'description' => $validated['form']['description'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Jabatan berhasil diperbarui.');
        $this->redirectRoute('admin.organization.organizational-positions.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.organizational-positions.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Jabatan',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', Rule::unique('organizational_positions', 'code')->ignore($this->position->id)],
            'form.category' => ['required', 'string', 'in:executive,faculty,study_program,work_unit,staff,academic'],
            'form.scope_type' => ['required', 'string', 'in:none,faculty,study_program,work_unit'],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Jabatan</h3>
            <a href="{{ route('admin.organization.organizational-positions.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.organizational-positions._form')
        </div>
    </div>
</div>
