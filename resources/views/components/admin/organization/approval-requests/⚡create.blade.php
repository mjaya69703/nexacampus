<?php

use App\Models\Organization\ApprovalTemplate;
use App\Support\Organization\ApprovalEngine;
use Livewire\Component;

new class extends Component
{
    public array $form = [];

    public function mount(): void
    {
        $this->form = [
            'approval_template_id' => '',
            'subject' => '',
            'reference' => '',
            'notes' => '',
        ];
    }

    public function save(ApprovalEngine $engine): void
    {
        $validated = $this->validate([
            'form.approval_template_id' => ['required', 'exists:approval_templates,id'],
            'form.subject' => ['required', 'string', 'max:255'],
            'form.reference' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string'],
        ]);

        $template = ApprovalTemplate::findOrFail($validated['form']['approval_template_id']);

        $request = $engine->submitFromTemplate(
            template: $template,
            subject: $validated['form']['subject'],
            requester: auth()->user(),
            reference: $validated['form']['reference'] ?: null,
            notes: $validated['form']['notes'] ?: null,
            createdBy: auth()->id(),
        );

        session()->flash('success', 'Request approval berhasil dibuat.');
        $this->redirectRoute('admin.organization.approval-requests.show', ['id' => $request->id]);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.approval-requests.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Buat Request Approval',
        ]);
    }

    public function getTemplatesProperty()
    {
        return ApprovalTemplate::where('is_active', true)->orderBy('module')->orderBy('name')->get(['id', 'name', 'code', 'module']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Buat Request Approval</h3>
            <a href="{{ route('admin.organization.approval-requests.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            <div class="row row-cards">
                <div class="col-lg-6">
                    <label class="form-label required">Template</label>
                    <select class="form-select" wire:model.defer="form.approval_template_id">
                        <option value="">Pilih template</option>
                        @foreach ($this->templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }} - {{ $template->code }}</option>
                        @endforeach
                    </select>
                    @error('form.approval_template_id') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="col-lg-6">
                    <label class="form-label">Referensi</label>
                    <input type="text" class="form-control" wire:model.defer="form.reference" placeholder="Nomor dokumen atau kode internal">
                    @error('form.reference') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label required">Subject</label>
                    <input type="text" class="form-control" wire:model.defer="form.subject" placeholder="Judul request approval">
                    @error('form.subject') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea class="form-control" rows="4" wire:model.defer="form.notes"></textarea>
                    @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="col-12 d-flex align-items-center justify-content-end gap-2">
                    <button type="button" class="btn btn-primary" wire:click="save">
                        <i class="fas fa-paper-plane me-2"></i> Submit
                    </button>
                    <button type="button" class="btn btn-secondary" wire:click="cancel">
                        <i class="fas fa-times me-2"></i> Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
