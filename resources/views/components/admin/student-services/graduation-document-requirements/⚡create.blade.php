<?php

use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationDocumentRequirement;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'study_program_id' => '',
        'document_type' => '',
        'label' => '',
        'is_required' => true,
        'allowed_extensions' => 'pdf,jpg,jpeg,png',
        'max_size_kb' => 5120,
        'sort_order' => 0,
        'is_active' => true,
        'description' => '',
    ];

    public $studyPrograms;

    public function mount(): void
    {
        $this->studyPrograms = StudyProgram::orderBy('name')->get(['id', 'name']);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];
        $validated['study_program_id'] = $validated['study_program_id'] ?: null;
        $validated['allowed_extensions'] = $this->sanitizeExtensions($validated['allowed_extensions'] ?? null);
        $validated['description'] = $validated['description'] ?: null;

        GraduationDocumentRequirement::create($validated);
        session()->flash('success', 'Graduation document requirement berhasil dibuat.');
        $this->redirectRoute('admin.student-services.graduation-document-requirements.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Buat Dokumen Yudisium',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'form.document_type' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_\\-]+$/',
                Rule::unique('graduation_document_requirements', 'document_type')
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
        title="Buat Persyaratan Dokumen Baru"
        description="Konfigurasi jenis dokumen, aturan ukuran/ekstensi file yang diizinkan untuk syarat kelengkapan berkas yudisium."
        icon="folder-plus"
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
                            <i class="fa fa-folder-plus fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Dokumen Yudisium</h4>
                            <div class="text-muted small">Lengkapi aturan verifikasi berkas kelulusan.</div>
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
                            <div class="text-muted small">Standarisasi berkas unggahan mahasiswa.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan <strong>Scope Global</strong> jika dokumen tersebut wajib bagi semua prodi di universitas (misal: KTP, Pas Foto).</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan batas ukuran (KB) cukup besar agar scan berkualitas baik tidak terditolak sistem (misal 5120 KB = 5 MB).</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Urutan tampilan menentukan tata letak di form pendaftaran yudisium mahasiswa.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Awal</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Aktif & Wajib</div>
                    <p class="text-muted small mb-0">Dokumen baru ini akan langsung ditagihkan kepada mahasiswa yang mendaftar yudisium berikutnya.</p>
                </div>
            </div>
        </div>
    </div>
</div>
