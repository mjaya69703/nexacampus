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

        session()->flash('success', 'NIM generation rule berhasil dibuat.');
        $this->redirectRoute('admin.admission.nim-generation-rules.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Create NIM Rule',
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

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Create NIM Rule</h3>
                <a href="{{ route('admin.admission.nim-generation-rules.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.admission.nim-generation-rules._form')
                </form>
            </div>
        </div>
    </div>
</div>
