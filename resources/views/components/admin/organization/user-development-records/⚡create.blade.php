<?php

use App\Models\Organization\UserDevelopmentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [];
    public ?int $selectedUserId = null;
    public string $userSearch = '';
    public $attachmentFile = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->selectedUserId = null;
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated) {
            $record = UserDevelopmentRecord::create($this->recordPayload($validated['form']) + [
                'user_id' => $validated['selectedUserId'],
            ]);

            $this->storeAttachment($record);
        });

        session()->flash('success', 'Riwayat sertifikasi/pelatihan berhasil dibuat.');
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
            'pages' => 'Tambah Sertifikasi & Pelatihan',
        ]);
    }

    public function getSelectedUserProperty(): ?User
    {
        return $this->selectedUserId ? User::find($this->selectedUserId) : null;
    }

    public function getSearchableUsersProperty()
    {
        return User::query()
            ->where('is_active', true)
            ->when(filled($this->userSearch), function ($query) {
                $search = '%'.$this->userSearch.'%';
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhere('identity_number', 'like', $search);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code', 'identity_number']);
    }

    private function emptyForm(): array
    {
        return [
            'type' => 'certification',
            'title' => '',
            'organizer' => '',
            'credential_number' => '',
            'start_date' => '',
            'end_date' => '',
            'expires_at' => '',
            'cost' => '',
            'description' => '',
            'is_verified' => false,
            'verification_notes' => '',
        ];
    }

    private function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
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
            'verified_by' => $isVerified ? auth()->id() : null,
            'verified_at' => $isVerified ? now() : null,
            'verification_notes' => $form['verification_notes'] ?: null,
        ];
    }

    private function storeAttachment(UserDevelopmentRecord $record): void
    {
        if (! $this->attachmentFile) {
            return;
        }

        $originalName = $this->attachmentFile->getClientOriginalName();
        $fileSize = $this->attachmentFile->getSize();
        $filename = 'development_'.$record->user_id.'_'.time().'.'.$this->attachmentFile->getClientOriginalExtension();
        $path = $this->attachmentFile->storeAs('private/user-developments', $filename);

        $record->attachments()->create([
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
            <h3 class="card-title mb-0">Tambah Sertifikasi & Pelatihan</h3>
            <a href="{{ route('admin.organization.user-development-records.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.user-development-records._form', ['isEdit' => false])
        </div>
    </div>
</div>
