<?php

use App\Models\Organization\LecturerPerformanceRubric;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public LecturerPerformanceRubric $rubric;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->rubric = LecturerPerformanceRubric::findOrFail($id);
        $this->form = $this->rubric->only([
            'code', 'name', 'edom_weight', 'teaching_weight', 'attendance_weight', 'workload_weight',
            'minimum_responses', 'target_workload_sks', 'is_active', 'notes',
        ]);
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.code' => ['required', 'string', 'max:50', Rule::unique('lecturer_performance_rubrics', 'code')->ignore($this->rubric->id)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.edom_weight' => ['required', 'numeric', 'min:0'],
            'form.teaching_weight' => ['required', 'numeric', 'min:0'],
            'form.attendance_weight' => ['required', 'numeric', 'min:0'],
            'form.workload_weight' => ['required', 'numeric', 'min:0'],
            'form.minimum_responses' => ['required', 'integer', 'min:1'],
            'form.target_workload_sks' => ['required', 'numeric', 'min:1'],
            'form.is_active' => ['boolean'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        if ($data['is_active']) {
            LecturerPerformanceRubric::query()->whereKeyNot($this->rubric->id)->update(['is_active' => false]);
        }

        $data['code'] = strtoupper($data['code']);
        $data['updated_by'] = auth()->id();

        $this->rubric->update($data);
        session()->flash('success', 'Rubrik performa berhasil diperbarui.');
        $this->redirectRoute('admin.organization.lecturer-performance-rubrics.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Rubrik Performa']);
    }
};
?>

<div>
    <x-alert />
    <form wire:submit.prevent="save" class="card">
        <div class="card-header"><h3 class="card-title mb-0">Edit Rubrik Performa</h3></div>
        <div class="card-body">@include('components.admin.organization.lecturer-performance-rubrics._form')</div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('admin.organization.lecturer-performance-rubrics.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</div>
