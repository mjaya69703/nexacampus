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

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <x-admin.student-services.header
        title="Edit Persyaratan Dokumen: {{ $requirement->label }}"
        description="Perbarui spesifikasi ekstensi, ukuran maksimal, dan status keaktifan dokumen persyaratan yudisium ini."
        icon="folder-open"
    >
        <a href="{{ route('admin.student-services.graduation-document-requirements.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </a>
    </x-admin.student-services.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-folder-open fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Perubahan Dokumen</h4>
                            <div class="text-muted small">Perbarui parameter validasi dan instruksi pengunggahan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    @include('components.admin.student-services.graduation-document-requirements._form')
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Aturan Dokumen</h5>
                            <div class="text-muted small">Dampak perubahan parameter.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Perubahan aturan ukuran atau ekstensi hanya akan berdampak pada pengunggahan dokumen baru oleh mahasiswa.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jika Anda menonaktifkan persyaratan ini, maka form pendaftaran yudisium tidak lagi menagih dokumen tersebut.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Persyaratan</div>
                    <div class="fw-bold fs-5 text-dark mb-2">{{ $requirement->is_active ? 'Aktif' : 'Nonaktif' }} {{ $requirement->is_required ? '(Wajib)' : '(Opsional)' }}</div>
                    <p class="text-muted small mb-0">Lingkup: {{ $requirement->studyProgram?->name ?? 'Global (Semua Prodi)' }}.</p>
                </div>
            </div>
        </div>
    </div>
</div>
