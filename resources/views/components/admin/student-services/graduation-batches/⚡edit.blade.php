<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationBatch;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public GraduationBatch $batch;

    public array $form = [];

    public $academicPeriods;

    public $studyPrograms;

    public function mount($id): void
    {
        $this->batch = GraduationBatch::findOrFail($id);
        $this->academicPeriods = AcademicPeriod::query()
            ->with('academicYear')
            ->where('type', 'Yudisium')
            ->orderByDesc('start_at')
            ->get();
        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get(['id', 'name']);
        $this->form = [
            'academic_period_id' => $this->batch->academic_period_id,
            'study_program_id' => $this->batch->study_program_id,
            'name' => $this->batch->name,
            'code' => $this->batch->code,
            'yudisium_date' => $this->batch->yudisium_date?->format('Y-m-d'),
            'sk_number' => $this->batch->sk_number,
            'sk_date' => $this->batch->sk_date?->format('Y-m-d'),
            'status' => $this->batch->status,
            'notes' => $this->batch->notes,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $validated['study_program_id'] = $validated['study_program_id'] ?: null;
        $validated['yudisium_date'] = $validated['yudisium_date'] ?: null;
        $validated['sk_number'] = $validated['sk_number'] ?: null;
        $validated['sk_date'] = $validated['sk_date'] ?: null;
        $validated['notes'] = $validated['notes'] ?: null;

        $this->batch->update($validated);
        session()->flash('success', 'Graduation batch berhasil diperbarui.');
        $this->redirectRoute('admin.student-services.graduation-batches.show', ['id' => $this->batch->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Edit Batch Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'form.study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:100', Rule::unique('graduation_batches', 'code')->ignore($this->batch->id)],
            'form.yudisium_date' => ['nullable', 'date'],
            'form.sk_number' => ['nullable', 'string', 'max:255'],
            'form.sk_date' => ['nullable', 'date'],
            'form.status' => ['required', 'in:draft,open,review,finalized,cancelled'],
            'form.notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Batch Yudisium</h3>
            <a href="{{ route('admin.student-services.graduation-batches.show', ['id' => $batch->id]) }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.student-services.graduation-batches._form')
        </div>
    </div>
</div>
