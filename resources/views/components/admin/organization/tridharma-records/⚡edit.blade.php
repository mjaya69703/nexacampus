<?php

use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use App\Support\Organization\TridharmaRecordService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public TridharmaRecord $record;
    public array $form = [];
    public $attachmentFile = null;

    public function mount(int $id): void
    {
        $this->record = TridharmaRecord::with(['owner', 'attachments'])->findOrFail($id);
        $this->form = [
            'type' => $this->record->type,
            'title' => $this->record->title,
            'scheme' => $this->record->scheme,
            'abstract' => $this->record->abstract,
            'starts_at' => $this->record->starts_at?->format('Y-m-d') ?? '',
            'ends_at' => $this->record->ends_at?->format('Y-m-d') ?? '',
            'status' => $this->record->status,
            'funding_amount' => $this->record->funding_amount,
            'funding_source' => $this->record->funding_source,
            'admin_notes' => $this->record->admin_notes,
        ];
    }

    public function save(TridharmaRecordService $service): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated, $service) {
            $this->record->update($this->payload($validated['form']));

            if ($this->attachmentFile) {
                $service->storeAttachment($this->record, $this->attachmentFile, 'evidence', auth()->user());
            }
        });

        session()->flash('success', 'Record Tridharma berhasil diperbarui.');
        $this->redirectRoute('admin.organization.tridharma-records.show', ['id' => $this->record->id]);
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.tridharma-records.show', ['id' => $this->record->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Tridharma',
        ]);
    }

    public function getSelectedUserProperty(): ?User
    {
        return $this->record->owner;
    }

    private function rules(): array
    {
        return [
            'form.type' => ['required', 'in:research,community_service,publication'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.scheme' => ['nullable', 'string', 'max:255'],
            'form.abstract' => ['nullable', 'string'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,approved,active,completed,archived,rejected,in_approval'],
            'form.funding_amount' => ['nullable', 'numeric', 'min:0'],
            'form.funding_source' => ['nullable', 'string', 'max:255'],
            'form.admin_notes' => ['nullable', 'string'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192'],
        ];
    }

    private function payload(array $form): array
    {
        $payload = [
            'type' => $form['type'],
            'title' => $form['title'],
            'scheme' => $form['scheme'] ?: null,
            'abstract' => $form['abstract'] ?: null,
            'starts_at' => $form['starts_at'] ?: null,
            'ends_at' => $form['ends_at'] ?: null,
            'status' => $form['status'],
            'funding_amount' => $form['funding_amount'] ?: 0,
            'funding_source' => $form['funding_source'] ?: null,
            'admin_notes' => $form['admin_notes'] ?: null,
            'updated_by' => auth()->id(),
        ];

        if (in_array($form['status'], ['approved', 'active', 'completed'], true) && ! $this->record->approved_at) {
            $payload['approved_by'] = auth()->id();
            $payload['approved_at'] = now();
        }

        if ($form['status'] === 'completed' && ! $this->record->completed_at) {
            $payload['completed_by'] = auth()->id();
            $payload['completed_at'] = now();
        }

        return $payload;
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Tridharma</h3>
            <a href="{{ route('admin.organization.tridharma-records.show', $record->id) }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.tridharma-records._form', ['isEdit' => true])
        </div>
    </div>
</div>
