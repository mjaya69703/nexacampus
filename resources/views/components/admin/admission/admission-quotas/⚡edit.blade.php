<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\AdmissionQuota;
use Livewire\Component;

new class extends Component
{
    public AdmissionQuota $quotaModel;

    public array $periods = [];
    public array $faculties = [];
    public array $studyPrograms = [];

    public array $form = [];

    public function mount($id): void
    {
        $this->quotaModel = AdmissionQuota::findOrFail($id);
        $this->form = [
            'admission_period_id' => $this->quotaModel->admission_period_id,
            'faculty_id' => $this->quotaModel->faculty_id ?: '',
            'study_program_id' => $this->quotaModel->study_program_id ?: '',
            'class_type' => $this->quotaModel->class_type ?: '',
            'quota' => $this->quotaModel->quota,
        ];
        $this->loadOptions();
    }

    public function updatedFormFacultyId(): void
    {
        $this->form['study_program_id'] = '';
        $this->loadStudyPrograms();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.admission_period_id' => 'required|exists:admission_periods,id',
            'form.faculty_id' => 'nullable|exists:faculties,id',
            'form.study_program_id' => 'nullable|exists:study_programs,id',
            'form.class_type' => 'nullable|in:regular,evening,weekend',
            'form.quota' => 'required|integer|min:1',
        ])['form'];

        $this->quotaModel->update([
            ...$validated,
            'faculty_id' => $validated['faculty_id'] ?: null,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'class_type' => $validated['class_type'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Quota admission berhasil diperbarui.');
        $this->redirectRoute('admin.admission.admission-quotas.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Edit Admission Quota',
        ]);
    }

    private function loadOptions(): void
    {
        $this->periods = AdmissionPeriod::query()->orderByDesc('created_at')->get(['id', 'name', 'code'])
            ->map(fn ($period) => ['id' => $period->id, 'label' => $period->name.' ('.$period->code.')'])
            ->toArray();
        $this->faculties = Faculty::query()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn ($faculty) => ['id' => $faculty->id, 'label' => $faculty->name.' ('.$faculty->code.')'])
            ->toArray();
        $this->loadStudyPrograms();
    }

    private function loadStudyPrograms(): void
    {
        $this->studyPrograms = StudyProgram::query()
            ->when($this->form['faculty_id'] ?? null, fn ($query, $facultyId) => $query->where('faculty_id', $facultyId))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($program) => ['id' => $program->id, 'label' => $program->name.' ('.$program->code.')'])
            ->toArray();
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit Admission Quota</h3>
                <a href="{{ route('admin.admission.admission-quotas.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.admission.admission-quotas._form')
                </form>
            </div>
        </div>
    </div>
</div>
