<?php

use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationPolicy;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'name' => '',
        'study_program_id' => '',
        'minimum_semester' => 8,
        'minimum_passed_credits' => 144,
        'minimum_gpa' => 2.00,
        'require_active_status' => true,
        'require_no_financial_hold' => true,
        'require_no_incomplete_grade' => true,
        'require_open_yudisium_period' => true,
        'is_active' => true,
        'description' => '',
    ];

    public $studyPrograms;

    public function mount(): void
    {
        $this->studyPrograms = StudyProgram::query()->orderBy('name')->get();
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $this->ensureUniqueActiveScope($validated['study_program_id'] ?: null, (bool) $validated['is_active']);

        GraduationPolicy::create([
            ...$validated,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Graduation policy berhasil dibuat.');
        $this->redirectRoute('admin.student-services.graduation-policies.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Buat Aturan Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.study_program_id' => ['nullable', Rule::exists('study_programs', 'id')],
            'form.minimum_semester' => ['required', 'integer', 'min:1', 'max:20'],
            'form.minimum_passed_credits' => ['required', 'integer', 'min:0', 'max:300'],
            'form.minimum_gpa' => ['required', 'numeric', 'min:0', 'max:4'],
            'form.require_active_status' => ['boolean'],
            'form.require_no_financial_hold' => ['boolean'],
            'form.require_no_incomplete_grade' => ['boolean'],
            'form.require_open_yudisium_period' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function ensureUniqueActiveScope($studyProgramId, bool $isActive): void
    {
        if (! $isActive) {
            return;
        }

        $exists = GraduationPolicy::query()
            ->where('is_active', true)
            ->when($studyProgramId, fn ($query) => $query->where('study_program_id', $studyProgramId), fn ($query) => $query->whereNull('study_program_id'))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'form.study_program_id' => 'Sudah ada policy aktif untuk scope ini.',
            ]);
        }
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Buat Aturan Yudisium</h3>
                <a href="{{ route('admin.student-services.graduation-policies.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.student-services.graduation-policies._form')
                </form>
            </div>
        </div>
    </div>
</div>
