<?php

use App\Models\StudentService\ServiceLetterType;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $form = [
        'name' => '',
        'code' => '',
        'description' => '',
        'fulfillment_mode' => 'auto_generate',
        'template_key' => '',
        'requires_financial_clearance' => false,
        'clearance_hold_type' => '',
        'required_fields' => 'purpose',
        'requires_attachment' => false,
        'allowed_extensions' => 'pdf,jpg,jpeg,png',
        'max_file_size_kb' => 2048,
        'signer_name' => '',
        'signer_position' => '',
        'is_active' => 1,
    ];

    public function save(): void
    {
        $validated = $this->validate($this->rules())['form'];

        ServiceLetterType::create([
            ...$validated,
            'code' => str($validated['code'])->upper()->replace(' ', '_')->toString(),
            'required_fields' => $this->requiredFieldsArray($validated['required_fields']),
            'requires_financial_clearance' => (bool) $validated['requires_financial_clearance'],
            'requires_attachment' => (bool) $validated['requires_attachment'],
            'is_active' => (bool) $validated['is_active'],
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Letter type berhasil dibuat.');
        $this->redirectRoute('admin.student-services.letter-types.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Buat Jenis Surat',
        ]);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('service_letter_types', 'code')],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.fulfillment_mode' => ['required', Rule::in(['auto_generate', 'manual_upload', 'hybrid'])],
            'form.template_key' => ['nullable', 'string', 'max:120'],
            'form.requires_financial_clearance' => ['boolean'],
            'form.clearance_hold_type' => ['nullable', Rule::in(['registration', 'study_plan', 'exam_card', 'transcript', 'graduation'])],
            'form.required_fields' => ['nullable', 'string', 'max:500'],
            'form.requires_attachment' => ['boolean'],
            'form.allowed_extensions' => ['nullable', 'string', 'max:255'],
            'form.max_file_size_kb' => ['nullable', 'integer', 'min:1', 'max:20480'],
            'form.signer_name' => ['nullable', 'string', 'max:255'],
            'form.signer_position' => ['nullable', 'string', 'max:255'],
            'form.is_active' => ['required', 'boolean'],
        ];
    }

    private function requiredFieldsArray(?string $fields): array
    {
        return collect(explode(',', (string) $fields))->map(fn ($field) => trim($field))->filter()->values()->all();
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Buat Jenis Surat</h3>
                <a href="{{ route('admin.student-services.letter-types.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    @include('components.admin.student-services.letter-types._form')
                </form>
            </div>
        </div>
    </div>
</div>
