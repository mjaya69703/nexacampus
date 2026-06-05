<?php

use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentComplaintCategory;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [];

    public function mount(): void
    {
        $this->form = ['name' => '', 'code' => '', 'default_work_unit_id' => '', 'description' => '', 'default_sla_hours' => 48, 'is_active' => true];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:30', Rule::unique('student_complaint_categories', 'code')],
            'form.default_work_unit_id' => ['nullable', 'exists:work_units,id'],
            'form.description' => ['nullable', 'string'],
            'form.default_sla_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'form.is_active' => ['boolean'],
        ]);

        $data = $validated['form'];
        $data['code'] = strtoupper($data['code']);
        $data['default_work_unit_id'] = $data['default_work_unit_id'] ?: null;
        StudentComplaintCategory::create($data);

        session()->flash('success', 'Kategori pengaduan berhasil dibuat.');
        $this->redirectRoute('admin.student-services.complaint-categories.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.student-services.complaint-categories.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Layanan Mahasiswa', 'pages' => 'Buat Kategori Pengaduan']);
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Buat Kategori Pengaduan</h3></div>
        <div class="card-body">@include('components.admin.student-services.complaint-categories._form')</div>
    </div>
</div>
