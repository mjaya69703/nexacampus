<?php

use App\Models\Admission\NimGenerationRule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'name' => 'Default NIM Rule',
        'pattern' => '{yy}{program_code}{sequence}',
        'sequence_scope' => 'study_program_year',
        'sequence_padding' => 4,
        'sequence_start' => 1,
        'is_active' => true,
        'description' => '',
    ];

    public string $preview = '';

    public function mount(): void
    {
        $this->refreshPreview();
    }

    public function updatedForm(): void
    {
        $this->refreshPreview();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.pattern' => 'required|string|max:255',
            'form.sequence_scope' => 'required|in:global,year,period,faculty,study_program,class_type,study_program_year',
            'form.sequence_padding' => 'required|integer|min:1|max:10',
            'form.sequence_start' => 'required|integer|min:1',
            'form.is_active' => 'boolean',
            'form.description' => 'nullable|string',
        ])['form'];

        if ($validated['is_active']) {
            NimGenerationRule::query()->update(['is_active' => false]);
        }

        NimGenerationRule::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Aturan penomoran NIM berhasil dibuat.');
        $this->redirectRoute('admin.admission.nim-generation-rules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Tambah Aturan NIM',
        ]);
    }

    private function refreshPreview(): void
    {
        $this->preview = strtr($this->form['pattern'] ?: '', [
            '{year}' => (string) now()->year,
            '{yy}' => now()->format('y'),
            '{period_code}' => 'ADM'.now()->year.'W1',
            '{faculty_code}' => 'ENG',
            '{program_code}' => 'IF',
            '{class_type}' => 'REGULAR',
            '{sequence}' => str_pad((string) ($this->form['sequence_start'] ?? 1), (int) ($this->form['sequence_padding'] ?? 4), '0', STR_PAD_LEFT),
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Tambah Aturan Penomoran NIM"
        description="Rancang pola pembuatan Nomor Induk Mahasiswa (NIM) otomatis menggunakan kombinasi token tahun, program studi, dan nomor urut."
        icon="barcode"
    >
        <a href="{{ route('admin.admission.nim-generation-rules.index') }}" class="btn btn-light rounded-pill px-4 py-2 text-dark fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="fas fa-magic text-primary"></i> Formulir Pola NIM Baru
            </h4>
            <p class="text-muted fs-7 mb-0">Tentukan format string pola serta kaidah reset urutan sequence untuk calon mahasiswa yang lulus.</p>
        </div>
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                @include('components.admin.admission.nim-generation-rules._form')
            </form>
        </div>
    </div>
</div>
