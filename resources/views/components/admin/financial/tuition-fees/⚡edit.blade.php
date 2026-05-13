<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\TuitionFee;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public TuitionFee $tuitionFee;
    public array $academicYears = [];
    public array $studyPrograms = [];
    public array $form = [];

    public function mount($id): void
    {
        $this->tuitionFee = TuitionFee::findOrFail($id);
        $this->form = [
            'academic_year_id' => $this->tuitionFee->academic_year_id,
            'study_program_id' => $this->tuitionFee->study_program_id,
            'semester' => $this->tuitionFee->semester,
            'base_fee' => $this->tuitionFee->base_fee,
            'lab_fee' => $this->tuitionFee->lab_fee,
            'library_fee' => $this->tuitionFee->library_fee,
            'activity_fee' => $this->tuitionFee->activity_fee,
            'late_penalty_per_day' => $this->tuitionFee->late_penalty_per_day,
            'payment_deadline' => $this->tuitionFee->payment_deadline?->format('Y-m-d'),
            'is_active' => (int) $this->tuitionFee->is_active,
            'notes' => $this->tuitionFee->notes,
        ];
        $this->loadOptions();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        $this->tuitionFee->update([
            ...$validated,
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Tuition fee berhasil diperbarui.');
        $this->redirectRoute('admin.financial.tuition-fees.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Edit Tuition Fee',
        ]);
    }

    private function loadOptions(): void
    {
        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name.' ('.$year->code.')'])
            ->toArray();

        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn (StudyProgram $program) => ['id' => $program->id, 'label' => $program->name.' ('.$program->code.')'])
            ->toArray();
    }

    private function rules(): array
    {
        return [
            'form.academic_year_id' => 'required|exists:academic_years,id',
            'form.study_program_id' => 'required|exists:study_programs,id',
            'form.semester' => [
                'required',
                'integer',
                'min:1',
                'max:14',
                Rule::unique('tuition_fees', 'semester')
                    ->where('academic_year_id', $this->form['academic_year_id'] ?? null)
                    ->where('study_program_id', $this->form['study_program_id'] ?? null)
                    ->ignore($this->tuitionFee->id),
            ],
            'form.base_fee' => 'required|numeric|min:0',
            'form.lab_fee' => 'nullable|numeric|min:0',
            'form.library_fee' => 'nullable|numeric|min:0',
            'form.activity_fee' => 'nullable|numeric|min:0',
            'form.late_penalty_per_day' => 'nullable|numeric|min:0',
            'form.payment_deadline' => 'required|date',
            'form.is_active' => 'required|boolean',
            'form.notes' => 'nullable|string|max:1000',
        ];
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit Tuition Fee</h3>
                <a href="{{ route('admin.financial.tuition-fees.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.financial.tuition-fees._form')
                </form>
            </div>
        </div>
    </div>
</div>
