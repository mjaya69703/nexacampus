<?php

use App\Models\Organization\OrganizationalPosition;
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
            'category' => 'staff',
            'scope_type' => 'none',
            'description' => '',
            'is_active' => true,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        OrganizationalPosition::create([
            'name' => $validated['form']['name'],
            'code' => strtoupper($validated['form']['code']),
            'category' => $validated['form']['category'],
            'scope_type' => $validated['form']['scope_type'],
            'description' => $validated['form']['description'] ?: null,
            'is_active' => (bool) $validated['form']['is_active'],
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Jabatan berhasil dibuat.');
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
            'pages' => 'Tambah Jabatan',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', Rule::unique('organizational_positions', 'code')],
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
            <h3 class="card-title mb-0">Tambah Jabatan</h3>
            <a href="{{ route('admin.organization.organizational-positions.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.organizational-positions._form')
        </div>
    </div>
</div>
