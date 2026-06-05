<?php

use App\Models\Organization\UserDevelopmentRecord;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public UserDevelopmentRecord $record;
    public array $form = [];
    public $attachmentFile = null;

    public function mount(int $id): void
    {
        $this->record = UserDevelopmentRecord::with(['user', 'attachments'])->findOrFail($id);
        $this->form = [
            'type' => $this->record->type,
            'title' => $this->record->title,
            'organizer' => $this->record->organizer,
            'credential_number' => $this->record->credential_number,
            'start_date' => $this->record->start_date?->format('Y-m-d') ?? '',
            'end_date' => $this->record->end_date?->format('Y-m-d') ?? '',
            'expires_at' => $this->record->expires_at?->format('Y-m-d') ?? '',
            'cost' => $this->record->cost,
            'description' => $this->record->description,
            'is_verified' => (bool) $this->record->is_verified,
            'verification_notes' => $this->record->verification_notes,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated) {
            $this->record->update($this->recordPayload($validated['form']));
            $this->storeAttachment();
        });

        session()->flash('success', 'Riwayat sertifikasi/pelatihan berhasil diperbarui.');
        $this->redirectRoute('admin.organization.user-development-records.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.user-development-records.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Edit Sertifikasi & Pelatihan',
        ]);
    }

    public function getSelectedUserProperty()
    {
        return $this->record->user;
    }

    private function rules(): array
    {
        return [
            'form.type' => ['required', 'string', 'in:certification,training,workshop,seminar,award,license'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.organizer' => ['nullable', 'string', 'max:255'],
            'form.credential_number' => ['nullable', 'string', 'max:255'],
            'form.start_date' => ['required', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.start_date'],
            'form.expires_at' => ['nullable', 'date', 'after:form.start_date'],
            'form.cost' => ['nullable', 'numeric', 'min:0'],
            'form.description' => ['nullable', 'string'],
            'form.is_verified' => ['boolean'],
            'form.verification_notes' => ['nullable', 'string'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    private function recordPayload(array $form): array
    {
        $isVerified = (bool) $form['is_verified'];
        $wasVerified = (bool) $this->record->is_verified;

        return [
            'type' => $form['type'],
            'title' => $form['title'],
            'organizer' => $form['organizer'] ?: null,
            'credential_number' => $form['credential_number'] ?: null,
            'start_date' => $form['start_date'],
            'end_date' => $form['end_date'] ?: null,
            'expires_at' => $form['expires_at'] ?: null,
            'cost' => $form['cost'] === '' ? null : $form['cost'],
            'description' => $form['description'] ?: null,
            'is_verified' => $isVerified,
            'verified_by' => $isVerified ? ($wasVerified ? $this->record->verified_by : auth()->id()) : null,
            'verified_at' => $isVerified ? ($wasVerified ? $this->record->verified_at : now()) : null,
            'verification_notes' => $form['verification_notes'] ?: null,
        ];
    }

    private function storeAttachment(): void
    {
        if (! $this->attachmentFile) {
            return;
        }

        $originalName = $this->attachmentFile->getClientOriginalName();
        $fileSize = $this->attachmentFile->getSize();
        $filename = 'development_'.$this->record->user_id.'_'.time().'.'.$this->attachmentFile->getClientOriginalExtension();
        $path = $this->attachmentFile->storeAs('private/user-developments', $filename);

        $this->record->attachments()->create([
            'document_type' => 'certificate',
            'file_path' => $path,
            'file_name' => $originalName,
            'file_size' => $fileSize,
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Sertifikasi & Pelatihan</h3>
            <a href="{{ route('admin.organization.user-development-records.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.user-development-records._form', ['isEdit' => true])
        </div>
    </div>
</div>
