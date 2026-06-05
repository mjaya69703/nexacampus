<?php

use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationDocumentRequirement;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public GraduationDocumentRequirement $requirement;

    public array $form = [];

    public $studyPrograms;

    public function mount($id): void
    {
        $this->requirement = GraduationDocumentRequirement::findOrFail($id);
        $this->studyPrograms = StudyProgram::orderBy('name')->get(['id', 'name']);
        $this->form = [
            'study_program_id' => $this->requirement->study_program_id,
            'document_type' => $this->requirement->document_type,
            'label' => $this->requirement->label,
            'is_required' => $this->requirement->is_required,
            'allowed_extensions' => $this->requirement->allowed_extensions,
            'max_size_kb' => $this->requirement->max_size_kb,
            'sort_order' => $this->requirement->sort_order,
            'is_active' => $this->requirement->is_active,
            'description' => $this->requirement->description,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $validated['study_program_id'] = $validated['study_program_id'] ?: null;
        $validated['allowed_extensions'] = $this->sanitizeExtensions($validated['allowed_extensions'] ?? null);
        $validated['description'] = $validated['description'] ?: null;

        $this->requirement->update($validated);
        session()->flash('success', 'Graduation document requirement berhasil diperbarui.');
        $this->redirectRoute('admin.student-services.graduation-document-requirements.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Edit Dokumen Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'form.document_type' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_\\-]+$/',
                Rule::unique('graduation_document_requirements', 'document_type')
                    ->ignore($this->requirement->id)
                    ->where(fn ($query) => $query->where('study_program_id', $this->form['study_program_id'] ?: null)),
            ],
            'form.label' => ['required', 'string', 'max:255'],
            'form.is_required' => ['boolean'],
            'form.allowed_extensions' => ['nullable', 'string', 'max:255'],
            'form.max_size_kb' => ['nullable', 'integer', 'min:1', 'max:51200'],
            'form.sort_order' => ['nullable', 'integer', 'min:0'],
            'form.is_active' => ['boolean'],
            'form.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function sanitizeExtensions(?string $value): ?string
    {
        $extensions = collect(explode(',', (string) $value))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter(fn (string $extension) => in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true))
            ->unique()
            ->values();

        return $extensions->isEmpty() ? 'pdf,jpg,jpeg,png' : $extensions->implode(',');
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">Edit Dokumen Yudisium</h3>
        </div>
        <div class="card-body">
            @include('components.admin.student-services.graduation-document-requirements._form')
        </div>
    </div>
</div>
