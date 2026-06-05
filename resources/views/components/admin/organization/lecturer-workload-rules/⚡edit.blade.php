<?php

use App\Models\Organization\LecturerWorkloadRule;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadRule $rule;
    public array $form = [];

    public function mount($id): void
    {
        $this->rule = LecturerWorkloadRule::findOrFail($id);
        $this->form = [
            'category' => $this->rule->category,
            'source_code' => $this->rule->source_code,
            'name' => $this->rule->name,
            'sks_value' => (string) $this->rule->sks_value,
            'maximum_sks' => (string) $this->rule->maximum_sks,
            'is_active' => $this->rule->is_active,
            'description' => $this->rule->description,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.category' => ['required', 'in:teaching,structural,tridharma'],
            'form.source_code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_rules', 'source_code')->where('category', $this->form['category'])->ignore($this->rule->id)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.sks_value' => ['required', 'numeric', 'min:0'],
            'form.maximum_sks' => ['nullable', 'numeric', 'gte:form.sks_value'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string'],
        ])['form'];

        $validated['updated_by'] = auth()->id();
        $validated['source_code'] = strtoupper($validated['source_code']);
        $validated['maximum_sks'] = $validated['maximum_sks'] === '' ? null : $validated['maximum_sks'];
        $this->rule->update($validated);
        session()->flash('success', 'Aturan SKS berhasil diperbarui.');
        $this->redirectRoute('admin.organization.lecturer-workload-rules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Aturan SKS']);
    }
};
?>

<div><x-alert /><div class="card"><div class="card-header"><h3 class="card-title mb-0">Edit Aturan SKS</h3></div><div class="card-body">@include('components.admin.organization.lecturer-workload-rules._form')</div></div></div>
