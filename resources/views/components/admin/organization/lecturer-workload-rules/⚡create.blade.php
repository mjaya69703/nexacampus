<?php

use App\Models\Organization\LecturerWorkloadRule;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = ['category' => 'structural', 'source_code' => '', 'name' => '', 'sks_value' => '1', 'maximum_sks' => '', 'is_active' => true, 'description' => ''];

    public function save(): void
    {
        $validated = $this->validate([
            'form.category' => ['required', 'in:teaching,structural,tridharma'],
            'form.source_code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_rules', 'source_code')->where('category', $this->form['category'])],
            'form.name' => ['required', 'string', 'max:255'],
            'form.sks_value' => ['required', 'numeric', 'min:0'],
            'form.maximum_sks' => ['nullable', 'numeric', 'gte:form.sks_value'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string'],
        ])['form'];

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        $validated['source_code'] = strtoupper($validated['source_code']);
        $validated['maximum_sks'] = $validated['maximum_sks'] === '' ? null : $validated['maximum_sks'];
        LecturerWorkloadRule::create($validated);
        session()->flash('success', 'Aturan SKS berhasil dibuat.');
        $this->redirectRoute('admin.organization.lecturer-workload-rules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Aturan SKS']);
    }
};
?>

<div><x-alert /><div class="card"><div class="card-header"><h3 class="card-title mb-0">Tambah Aturan SKS</h3></div><div class="card-body">@include('components.admin.organization.lecturer-workload-rules._form')</div></div></div>
