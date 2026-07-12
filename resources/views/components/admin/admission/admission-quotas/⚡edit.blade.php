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

        session()->flash('success', 'Kuota daya tampung admission berhasil diperbarui.');
        $this->redirectRoute('admin.admission.admission-quotas.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Edit Kuota PMB',
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

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Edit Batasan Kuota PMB"
        description="Perbarui alokasi daya tampung atau batasan kuota mahasiswa baru untuk periode, fakultas, atau program studi terpilih."
        icon="cubes"
    >
        <a href="{{ route('admin.admission.admission-quotas.index') }}" class="btn btn-light rounded-pill px-4 py-2 text-dark fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="fas fa-edit text-primary"></i> Perbarui Formulir Kuota
            </h4>
            <p class="text-muted fs-7 mb-0">Ubah kombinasi filter periode, fakultas, program studi, maupun jumlah kuota maksimal.</p>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                @include('components.admin.admission.admission-quotas._form')
            </form>
        </div>
    </div>
</div>
