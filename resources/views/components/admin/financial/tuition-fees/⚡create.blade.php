<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\TuitionFee;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $academicYears = [];
    public array $studyPrograms = [];

    public array $form = [
        'academic_year_id' => '',
        'study_program_id' => '',
        'semester' => 1,
        'base_fee' => 0,
        'lab_fee' => 0,
        'library_fee' => 0,
        'activity_fee' => 0,
        'late_penalty_per_day' => 0,
        'payment_deadline' => '',
        'is_active' => 1,
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->loadOptions();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        TuitionFee::create([
            ...$validated,
            'is_active' => (bool) $validated['is_active'],
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Tuition fee berhasil dibuat.');
        $this->redirectRoute('admin.financial.tuition-fees.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Create Tuition Fee',
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
                    ->where('study_program_id', $this->form['study_program_id'] ?? null),
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
                <h3 class="card-title mb-0">Create Tuition Fee</h3>
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
