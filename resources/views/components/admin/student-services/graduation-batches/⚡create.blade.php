<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationBatch;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'academic_period_id' => '',
        'study_program_id' => '',
        'name' => '',
        'code' => '',
        'yudisium_date' => '',
        'sk_number' => '',
        'sk_date' => '',
        'status' => 'draft',
        'notes' => '',
    ];

    public $academicPeriods;

    public $studyPrograms;

    public function mount(): void
    {
        $this->academicPeriods = AcademicPeriod::query()
            ->with('academicYear')
            ->where('type', 'Yudisium')
            ->orderByDesc('start_at')
            ->get();
        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get(['id', 'name']);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $validated['study_program_id'] = $validated['study_program_id'] ?: null;
        $validated['yudisium_date'] = $validated['yudisium_date'] ?: null;
        $validated['sk_number'] = $validated['sk_number'] ?: null;
        $validated['sk_date'] = $validated['sk_date'] ?: null;
        $validated['notes'] = $validated['notes'] ?: null;

        GraduationBatch::create($validated);
        session()->flash('success', 'Graduation batch berhasil dibuat.');
        $this->redirectRoute('admin.student-services.graduation-batches.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Buat Batch Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'form.study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:100', Rule::unique('graduation_batches', 'code')],
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
        <div class="card-header">
            <h3 class="card-title mb-0">Buat Batch Yudisium</h3>
        </div>
        <div class="card-body">
            @include('components.admin.student-services.graduation-batches._form')
        </div>
    </div>
</div>
