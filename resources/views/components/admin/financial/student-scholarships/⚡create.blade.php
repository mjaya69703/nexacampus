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
                $errors[] = 'Student ID '.$studentProfileId.': '.$exception->getMessage();
            }
        }

        if ($created > 0) {
            session()->flash('success', $created.' alokasi beasiswa mahasiswa berhasil dibuat.');
        }

        if (! empty($errors)) {
            session()->flash('error', implode(' ', array_slice($errors, 0, 5)));
        }

        $this->redirectRoute('admin.financial.student-scholarships.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Alokasikan Beasiswa',
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

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Alokasikan Beasiswa ke Mahasiswa"
        description="Pilih satu atau beberapa mahasiswa untuk menerima potongan atau pembebasan biaya dari program beasiswa aktif."
        icon="user-plus"
    >
        <a href="{{ route('admin.financial.student-scholarships.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-user-graduate fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Formulir Alokasi Beasiswa</h4>
                    <span class="text-muted small">Lengkapi parameter program, periode aktif, dan catatan penugasan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <form wire:submit.prevent="save">
                @include('components.admin.financial.student-scholarships._form')
            </form>
        </div>
    </div>
</div>
