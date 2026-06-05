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

    public function save(TridharmaRecordService $service): void
    {
        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated, $service) {
            $owner = User::with(['lecturerProfile', 'employeeProfile'])->findOrFail($validated['selectedUserId']);
            $record = TridharmaRecord::create($this->payload($validated['form'], $owner));

            if ($this->attachmentFile) {
                $service->storeAttachment($record, $this->attachmentFile, 'proposal', auth()->user());
            }
        });

        session()->flash('success', 'Record Tridharma berhasil dibuat.');
        $this->redirectRoute('admin.organization.tridharma-records.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.tridharma-records.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Tambah Tridharma',
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
            ->where(function ($query) {
                $query->whereHas('lecturerProfile')
                    ->orWhereHas('employeeProfile', fn ($query) => $query->where('is_active', true));
            })
            ->when(filled($this->userSearch), function ($query) {
                $search = '%'.$this->userSearch.'%';
                $query->where(fn ($query) => $query
                    ->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('code', 'like', $search));
            })
            ->orderBy('first_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code']);
    }

    private function emptyForm(): array
    {
        return [
            'type' => 'research',
            'title' => '',
            'scheme' => '',
            'abstract' => '',
            'starts_at' => '',
            'ends_at' => '',
            'status' => 'draft',
            'funding_amount' => 0,
            'funding_source' => '',
            'admin_notes' => '',
        ];
    }

    private function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
            'form.type' => ['required', 'in:research,community_service,publication'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.scheme' => ['nullable', 'string', 'max:255'],
            'form.abstract' => ['nullable', 'string'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.status' => ['required', 'in:draft,approved,active,completed,archived'],
            'form.funding_amount' => ['nullable', 'numeric', 'min:0'],
            'form.funding_source' => ['nullable', 'string', 'max:255'],
            'form.admin_notes' => ['nullable', 'string'],
            'attachmentFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192'],
        ];
    }

    private function payload(array $form, User $owner): array
    {
        return [
            'user_id' => $owner->id,
            'lecturer_profile_id' => $owner->lecturerProfile?->id,
            'employee_profile_id' => $owner->employeeProfile?->id,
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
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'approved_by' => in_array($form['status'], ['approved', 'active', 'completed'], true) ? auth()->id() : null,
            'approved_at' => in_array($form['status'], ['approved', 'active', 'completed'], true) ? now() : null,
            'completed_by' => $form['status'] === 'completed' ? auth()->id() : null,
            'completed_at' => $form['status'] === 'completed' ? now() : null,
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Tambah Tridharma</h3>
            <a href="{{ route('admin.organization.tridharma-records.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.tridharma-records._form', ['isEdit' => false])
        </div>
    </div>
</div>
