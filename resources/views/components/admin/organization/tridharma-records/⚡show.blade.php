<?php

use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Organization\TridharmaRecordService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public TridharmaRecord $record;
    public array $memberForm = [];
    public array $milestoneForm = [];
    public array $budgetForm = [];
    public array $outputForm = [];
    public string $verificationNotes = '';
    public $attachmentFile = null;
    public string $attachmentType = 'evidence';
    public string $memberUserSearch = '';

    public function mount(int $id): void
    {
        $this->record = TridharmaRecord::findOrFail($id);
        $this->resetForms();
        $this->loadRecord();
    }

    public function submitApproval(TridharmaRecordService $service): void
    {
        try {
            $service->ensureDefaultApprovalTemplate(auth()->user());
            $service->submitForApproval($this->record, auth()->user());
            session()->flash('success', 'Proposal Tridharma diajukan ke approval.');
            $this->loadRecord();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function verify(): void
    {
        abort_unless(ActivePermission::check('tridharma-record.verify'), 403);

        $this->record->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'verification_notes' => $this->verificationNotes ?: null,
        ]);
        $this->verificationNotes = '';
        session()->flash('success', 'Record Tridharma berhasil diverifikasi.');
        $this->loadRecord();
    }

    public function complete(): void
    {
        abort_unless(ActivePermission::check('tridharma-record.complete'), 403);

        $this->record->update([
            'status' => 'completed',
            'completed_by' => auth()->id(),
            'completed_at' => now(),
        ]);
        session()->flash('success', 'Record Tridharma ditandai selesai.');
        $this->loadRecord();
    }

    public function archive(): void
    {
        abort_unless(ActivePermission::check('tridharma-record.update'), 403);

        $this->record->update(['status' => 'archived', 'updated_by' => auth()->id()]);
        session()->flash('success', 'Record Tridharma diarsipkan.');
        $this->loadRecord();
    }

    public function addMember(): void
    {
        $data = $this->validate([
            'memberForm.user_id' => ['nullable', 'exists:users,id'],
            'memberForm.member_name' => ['nullable', 'string', 'max:255'],
            'memberForm.institution' => ['nullable', 'string', 'max:255'],
            'memberForm.email' => ['nullable', 'email', 'max:255'],
            'memberForm.role' => ['required', 'string', 'max:100'],
        ])['memberForm'];

        if (blank($data['user_id']) && blank($data['member_name'])) {
            $this->addError('memberForm.member_name', 'Pilih user internal atau isi nama anggota eksternal.');
            return;
        }

        $this->record->members()->create([
            'user_id' => $data['user_id'] ?: null,
            'member_name' => $data['member_name'] ?: null,
            'institution' => $data['institution'] ?: null,
            'email' => $data['email'] ?: null,
            'role' => $data['role'],
            'is_external' => blank($data['user_id']),
        ]);
        $this->memberForm = ['user_id' => '', 'member_name' => '', 'institution' => '', 'email' => '', 'role' => 'member'];
        $this->memberUserSearch = '';
        $this->loadRecord();
    }

    public function selectMemberUser(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->memberForm['user_id'] = $user->id;
        $this->memberForm['member_name'] = '';
        $this->memberForm['institution'] = '';
        $this->memberForm['email'] = '';
        $this->memberUserSearch = '';
    }

    public function clearMemberUser(): void
    {
        $this->memberForm['user_id'] = '';
    }

    public function addMilestone(): void
    {
        $data = $this->validate([
            'milestoneForm.title' => ['required', 'string', 'max:255'],
            'milestoneForm.due_date' => ['nullable', 'date'],
            'milestoneForm.status' => ['required', 'in:pending,in_progress,completed,blocked'],
            'milestoneForm.progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'milestoneForm.description' => ['nullable', 'string'],
        ])['milestoneForm'];

        $this->record->milestones()->create($data + ['sort_order' => $this->record->milestones()->count() + 1]);
        $this->milestoneForm = ['title' => '', 'due_date' => '', 'status' => 'pending', 'progress_percentage' => 0, 'description' => ''];
        $this->loadRecord();
    }

    public function addBudget(): void
    {
        $data = $this->validate([
            'budgetForm.category' => ['required', 'string', 'max:255'],
            'budgetForm.description' => ['nullable', 'string', 'max:255'],
            'budgetForm.planned_amount' => ['required', 'numeric', 'min:0'],
            'budgetForm.realized_amount' => ['required', 'numeric', 'min:0'],
            'budgetForm.notes' => ['nullable', 'string'],
        ])['budgetForm'];

        $this->record->budgets()->create($data);
        $this->budgetForm = ['category' => '', 'description' => '', 'planned_amount' => 0, 'realized_amount' => 0, 'notes' => ''];
        $this->loadRecord();
    }

    public function addOutput(): void
    {
        $data = $this->validate([
            'outputForm.output_type' => ['required', 'string', 'max:100'],
            'outputForm.title' => ['required', 'string', 'max:255'],
            'outputForm.publisher' => ['nullable', 'string', 'max:255'],
            'outputForm.indexing' => ['nullable', 'string', 'max:255'],
            'outputForm.doi' => ['nullable', 'string', 'max:255'],
            'outputForm.url' => ['nullable', 'url', 'max:255'],
            'outputForm.published_at' => ['nullable', 'date'],
            'outputForm.status' => ['required', 'in:draft,submitted,published,accepted'],
            'outputForm.notes' => ['nullable', 'string'],
        ])['outputForm'];

        $this->record->outputs()->create($data);
        $this->outputForm = ['output_type' => 'article', 'title' => '', 'publisher' => '', 'indexing' => '', 'doi' => '', 'url' => '', 'published_at' => '', 'status' => 'draft', 'notes' => ''];
        $this->loadRecord();
    }

    public function uploadAttachment(TridharmaRecordService $service): void
    {
        $this->validate([
            'attachmentType' => ['required', 'string', 'max:50'],
            'attachmentFile' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192'],
        ]);

        $service->storeAttachment($this->record, $this->attachmentFile, $this->attachmentType, auth()->user());
        $this->attachmentFile = null;
        $this->attachmentType = 'evidence';
        session()->flash('success', 'Lampiran berhasil diunggah.');
        $this->loadRecord();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Detail Tridharma',
        ]);
    }

    public function getSelectedMemberUserProperty(): ?User
    {
        return filled($this->memberForm['user_id'] ?? null)
            ? User::query()->find($this->memberForm['user_id'])
            : null;
    }

    public function getMemberUserCandidatesProperty()
    {
        if (mb_strlen(trim($this->memberUserSearch)) < 2) {
            return collect();
        }

        $existingUserIds = $this->record->members
            ->pluck('user_id')
            ->filter()
            ->all();

        return User::query()
            ->where('is_active', true)
            ->when($existingUserIds !== [], fn ($query) => $query->whereNotIn('id', $existingUserIds))
            ->where(function ($query) {
                $search = '%'.trim($this->memberUserSearch).'%';
                $query->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('identity_number', 'like', $search);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code', 'identity_number']);
    }

    private function resetForms(): void
    {
        $this->memberForm = ['user_id' => '', 'member_name' => '', 'institution' => '', 'email' => '', 'role' => 'member'];
        $this->milestoneForm = ['title' => '', 'due_date' => '', 'status' => 'pending', 'progress_percentage' => 0, 'description' => ''];
        $this->budgetForm = ['category' => '', 'description' => '', 'planned_amount' => 0, 'realized_amount' => 0, 'notes' => ''];
        $this->outputForm = ['output_type' => 'article', 'title' => '', 'publisher' => '', 'indexing' => '', 'doi' => '', 'url' => '', 'published_at' => '', 'status' => 'draft', 'notes' => ''];
    }

    private function loadRecord(): void
    {
        $this->record = $this->record->fresh([
            'owner', 'members.user', 'milestones.completedBy', 'budgets', 'outputs', 'attachments.uploadedBy',
            'approvalRequest.steps.actedBy', 'approvalRequest.actions.user',
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'approved', 'active', 'completed', 'published', 'accepted' => 'bg-success',
            'rejected', 'blocked' => 'bg-danger',
            'in_approval', 'submitted', 'in_progress' => 'bg-warning text-dark',
            'archived' => 'bg-secondary',
            default => 'bg-muted',
        };
    }
};
?>

<div>
    <x-alert />
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title mb-0">{{ $record->title }}</h3>
                        <small class="text-muted">{{ str($record->type)->replace('_', ' ')->title() }} oleh {{ $record->owner?->name }}</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.organization.tridharma-records.edit', $record->id) }}" class="btn btn-primary"><i class="fas fa-edit me-1"></i> Edit</a>
                        <a href="{{ route('admin.organization.tridharma-records.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2"><div class="subheader">Status</div><span class="badge {{ $this->statusClass($record->status) }}">{{ str($record->status)->replace('_', ' ')->title() }}</span></div>
                        <div class="col-md-2"><div class="subheader">Verifikasi</div><span class="badge {{ $record->is_verified ? 'bg-success' : 'bg-warning text-dark' }}">{{ $record->is_verified ? 'Terverifikasi' : 'Belum' }}</span></div>
                        <div class="col-md-3"><div class="subheader">Dana</div><div>Rp {{ number_format((float) $record->funding_amount, 0, ',', '.') }}</div></div>
                        <div class="col-md-3"><div class="subheader">Sumber Dana</div><div>{{ $record->funding_source ?: '-' }}</div></div>
                        <div class="col-md-2"><div class="subheader">Periode</div><div>{{ $record->starts_at?->format('d M Y') ?? '-' }} - {{ $record->ends_at?->format('d M Y') ?? '-' }}</div></div>
                    </div>
                    @if ($record->abstract)
                        <div class="mt-3 border rounded p-3 bg-light">{{ $record->abstract }}</div>
                    @endif
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    @if (in_array($record->status, ['draft', 'rejected'], true))
                        <button type="button" class="btn btn-warning" wire:click="submitApproval"><i class="fas fa-paper-plane me-1"></i> Submit Approval</button>
                    @endif
                    @if (! $record->is_verified)
                        <div class="input-group" style="max-width: 420px;">
                            <input type="text" class="form-control" wire:model.defer="verificationNotes" placeholder="Catatan verifikasi">
                            <button type="button" class="btn btn-success" wire:click="verify"><i class="fas fa-check me-1"></i> Verify</button>
                        </div>
                    @endif
                    @if (! in_array($record->status, ['completed', 'archived'], true))
                        <button type="button" class="btn btn-outline-success" wire:click="complete"><i class="fas fa-flag-checkered me-1"></i> Complete</button>
                    @endif
                    <button type="button" class="btn btn-outline-secondary" wire:click="archive"><i class="fas fa-box-archive me-1"></i> Archive</button>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Milestone</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead><tr><th>Target</th><th>Due</th><th>Progress</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($record->milestones as $milestone)
                                <tr><td>{{ $milestone->title }}</td><td>{{ $milestone->due_date?->format('d M Y') ?? '-' }}</td><td>{{ $milestone->progress_percentage }}%</td><td><span class="badge {{ $this->statusClass($milestone->status) }}">{{ str($milestone->status)->title() }}</span></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <div class="row g-2">
                        <div class="col-md-5"><input class="form-control" wire:model.defer="milestoneForm.title" placeholder="Target milestone"></div>
                        <div class="col-md-3"><input type="date" class="form-control" wire:model.defer="milestoneForm.due_date"></div>
                        <div class="col-md-2"><input type="number" min="0" max="100" class="form-control" wire:model.defer="milestoneForm.progress_percentage"></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" wire:click="addMilestone">Tambah</button></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Budget</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead><tr><th>Kategori</th><th>Rencana</th><th>Realisasi</th></tr></thead>
                        <tbody>
                            @foreach ($record->budgets as $budget)
                                <tr><td>{{ $budget->category }}<div class="text-secondary">{{ $budget->description }}</div></td><td>Rp {{ number_format((float) $budget->planned_amount, 0, ',', '.') }}</td><td>Rp {{ number_format((float) $budget->realized_amount, 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <div class="row g-2">
                        <div class="col-md-4"><input class="form-control" wire:model.defer="budgetForm.category" placeholder="Kategori"></div>
                        <div class="col-md-3"><input type="number" class="form-control" wire:model.defer="budgetForm.planned_amount" placeholder="Rencana"></div>
                        <div class="col-md-3"><input type="number" class="form-control" wire:model.defer="budgetForm.realized_amount" placeholder="Realisasi"></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" wire:click="addBudget">Tambah</button></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Output / Luaran</h3></div>
                <div class="list-group list-group-flush">
                    @foreach ($record->outputs as $output)
                        <div class="list-group-item">
                            <div class="fw-bold">{{ $output->title }}</div>
                            <div class="text-secondary">{{ str($output->output_type)->title() }} · {{ $output->publisher ?: '-' }} · {{ $output->doi ?: $output->url }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="card-body border-top">
                    <div class="row g-2">
                        <div class="col-md-3"><select class="form-select" wire:model.defer="outputForm.output_type"><option value="article">Artikel</option><option value="report">Laporan</option><option value="hki">HKI</option><option value="dataset">Dataset</option><option value="module">Modul</option></select></div>
                        <div class="col-md-7"><input class="form-control" wire:model.defer="outputForm.title" placeholder="Judul luaran"></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" wire:click="addOutput">Tambah</button></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Tim</h3></div>
                <div class="list-group list-group-flush">
                    @foreach ($record->members as $member)
                        <div class="list-group-item">
                            <div class="fw-bold">{{ $member->user?->name ?? $member->member_name }}</div>
                            <div class="text-secondary">{{ $member->role }} · {{ $member->institution ?: ($member->user?->email ?? '-') }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="card-body border-top">
                    <div class="mb-2">
                        <label class="form-label">User internal</label>
                        @if ($this->selectedMemberUser)
                            <div class="border rounded p-2 d-flex align-items-center gap-2">
                                <span class="avatar avatar-sm">{{ str($this->selectedMemberUser->name)->substr(0, 1)->upper() }}</span>
                                <div class="flex-fill">
                                    <div class="fw-bold">{{ $this->selectedMemberUser->name }}</div>
                                    <div class="text-secondary small">{{ $this->selectedMemberUser->email }}{{ $this->selectedMemberUser->code ? ' - '.$this->selectedMemberUser->code : '' }}</div>
                                </div>
                                <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="clearMemberUser" aria-label="Hapus pilihan user">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        @else
                            <input type="search" class="form-control" wire:model.live.debounce.350ms="memberUserSearch" placeholder="Cari nama, email, username, kode, NIDN/NIM">
                            @error('memberForm.user_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                            @if (mb_strlen(trim($memberUserSearch)) >= 2)
                                <div class="list-group mt-2 border">
                                    @forelse ($this->memberUserCandidates as $user)
                                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" wire:click="selectMemberUser({{ $user->id }})">
                                            <span>
                                                <span class="fw-bold">{{ $user->name }}</span>
                                                <span class="d-block text-secondary small">{{ $user->email }}{{ $user->code ? ' - '.$user->code : '' }}</span>
                                            </span>
                                            <i class="fas fa-plus text-secondary"></i>
                                        </button>
                                    @empty
                                        <div class="list-group-item text-secondary">Tidak ada user yang cocok.</div>
                                    @endforelse
                                </div>
                            @else
                                <div class="form-hint">Ketik minimal 2 karakter untuk mencari user internal.</div>
                            @endif
                        @endif
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <select class="form-select" wire:model.defer="memberForm.role">
                                <option value="leader">Ketua</option>
                                <option value="member">Anggota</option>
                                <option value="partner">Mitra</option>
                                <option value="student_collaborator">Kolaborator Mahasiswa</option>
                            </select>
                        </div>
                    </div>
                    @unless ($this->selectedMemberUser)
                        <input class="form-control mb-2" wire:model.defer="memberForm.member_name" placeholder="Nama eksternal">
                        @error('memberForm.member_name') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                        <input class="form-control mb-2" wire:model.defer="memberForm.institution" placeholder="Institusi">
                        <input type="email" class="form-control mb-2" wire:model.defer="memberForm.email" placeholder="Email eksternal">
                    @endunless
                    <button class="btn btn-primary w-100" wire:click="addMember">Tambah Anggota</button>
                </div>
            </div>

            <div class="card mb-3" x-data="{ uploading: false, progress: 0 }">
                <div class="card-header"><h3 class="card-title mb-0">Lampiran</h3></div>
                <div class="list-group list-group-flush">
                    @foreach ($record->attachments as $attachment)
                        <a class="list-group-item list-group-item-action d-flex gap-2 align-items-center" href="{{ route('admin.organization.tridharma-records.attachments.preview', $attachment) }}" target="_blank">
                            <i class="fas fa-paperclip text-secondary"></i>
                            <span class="flex-fill text-truncate">{{ $attachment->file_name }}</span>
                            <span class="badge bg-light text-secondary">{{ $attachment->document_type }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="card-body border-top"
                    x-on:livewire-upload-start="uploading = true; progress = 1"
                    x-on:livewire-upload-finish="uploading = false; progress = 100"
                    x-on:livewire-upload-error="uploading = false; progress = 0"
                    x-on:livewire-upload-progress="progress = $event.detail.progress">
                    <select class="form-select mb-2" wire:model.defer="attachmentType"><option value="proposal">Proposal</option><option value="report">Laporan</option><option value="evidence">Evidence</option><option value="output">Output</option></select>
                    <input type="file" class="form-control mb-2" wire:model="attachmentFile">
                    <div class="progress progress-sm mb-2" x-show="uploading || progress === 100"><div class="progress-bar" x-bind:style="`width: ${progress}%`"></div></div>
                    <button class="btn btn-primary w-100" wire:click="uploadAttachment" x-bind:disabled="uploading">Upload</button>
                </div>
            </div>

            @if ($record->approvalRequest)
                <div class="card">
                    <div class="card-header"><h3 class="card-title mb-0">Approval Timeline</h3></div>
                    <div class="list-group list-group-flush">
                        @foreach ($record->approvalRequest->steps as $step)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-bold">{{ $step->name }}</div>
                                        <div class="text-secondary">{{ $step->actedBy?->name ?? 'Menunggu approver' }}</div>
                                    </div>
                                    <span class="badge {{ $this->statusClass($step->status) }}">{{ str($step->status)->title() }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
