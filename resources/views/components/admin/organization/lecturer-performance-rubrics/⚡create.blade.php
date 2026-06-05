<?php

use App\Models\Organization\LecturerPerformanceRubric;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'code' => '',
        'name' => '',
        'edom_weight' => 40,
        'teaching_weight' => 30,
        'attendance_weight' => 20,
        'workload_weight' => 10,
        'minimum_responses' => 3,
        'target_workload_sks' => 12,
        'is_active' => true,
        'notes' => '',
    ];

    public function save(): void
    {
        $data = $this->validate([
            'form.code' => ['required', 'string', 'max:50', 'unique:lecturer_performance_rubrics,code'],
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
            LecturerPerformanceRubric::query()->update(['is_active' => false]);
        }

        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        LecturerPerformanceRubric::create($data);
        session()->flash('success', 'Rubrik performa berhasil dibuat.');
        $this->redirectRoute('admin.organization.lecturer-performance-rubrics.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Tambah Rubrik Performa']);
    }
};
?>

<div>
    <x-alert />
    <form wire:submit.prevent="save" class="card">
        <div class="card-header"><h3 class="card-title mb-0">Tambah Rubrik Performa</h3></div>
        <div class="card-body">@include('components.admin.organization.lecturer-performance-rubrics._form')</div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('admin.organization.lecturer-performance-rubrics.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</div>
