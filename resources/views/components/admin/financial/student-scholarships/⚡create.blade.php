<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentScholarship;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $students = [];
    public array $scholarships = [];
    public array $academicYears = [];
    public string $studentSearch = '';
    public array $selectedStudentProfileIds = [];

    public array $form = [
        'scholarship_id' => '',
        'academic_year_id' => '',
        'semester' => '',
        'start_date' => '',
        'end_date' => '',
        'status' => 'active',
        'notes' => '',
    ];

    public function mount(): void
    {
        $this->loadOptions();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $created = 0;
        $errors = [];

        foreach ($this->selectedStudentProfileIds as $studentProfileId) {
            try {
                StudentScholarship::create([
                    ...$validated,
                    'student_profile_id' => $studentProfileId,
                    'academic_year_id' => $validated['academic_year_id'] ?: null,
                    'semester' => $validated['semester'] ?: null,
                    'start_date' => $validated['start_date'] ?: null,
                    'end_date' => $validated['end_date'] ?: null,
                    'created_by' => auth()->id(),
                ]);
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = 'Student '.$studentProfileId.': '.$exception->getMessage();
            }
        }

        if ($created > 0) {
            session()->flash('success', $created.' scholarship assignment berhasil dibuat.');
        }

        if (! empty($errors)) {
            session()->flash('error', implode(' ', array_slice($errors, 0, 5)));
        }

        $this->redirectRoute('admin.financial.student-scholarships.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Assign Scholarship',
        ]);
    }

    private function loadOptions(): void
    {
        $this->scholarships = Scholarship::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Scholarship $scholarship) => ['id' => $scholarship->id, 'label' => $scholarship->name])
            ->toArray();

        $this->academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name.' ('.$year->code.')'])
            ->toArray();
    }

    private function rules(): array
    {
        return [
            'selectedStudentProfileIds' => ['required', 'array', 'min:1'],
            'selectedStudentProfileIds.*' => ['exists:student_profiles,id'],
            'form.scholarship_id' => [
                'required',
                'exists:scholarships,id',
            ],
            'form.academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'form.semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'form.start_date' => ['nullable', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.start_date'],
            'form.status' => ['required', Rule::in(['active', 'completed', 'revoked'])],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function studentOptions(): array
    {
        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->where('is_active', true)
            ->when($this->studentSearch !== '', function ($query) {
                $search = '%'.$this->studentSearch.'%';

                $query->where(function ($query) use ($search) {
                    $query->where('nim', 'like', $search)
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->orderBy('nim')
            ->limit(25)
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => ($student->nim ?: '-').' - '.$student->user?->name.' ('.$student->studyProgram?->name.')',
            ])
            ->toArray();
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Assign Scholarship</h3>
                <a href="{{ route('admin.financial.student-scholarships.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.financial.student-scholarships._form')
                </form>
            </div>
        </div>
    </div>
</div>
