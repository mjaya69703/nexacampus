<?php

use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use App\Support\Organization\TridharmaRecordService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public TridharmaRecord $record;
    public array $milestoneForm = ['title' => '', 'due_date' => '', 'progress_percentage' => 0, 'completion_notes' => ''];
    public array $milestoneUpdates = [];
    public array $outputForm = [
        'output_type' => 'article',
        'title' => '',
        'publisher' => '',
        'indexing' => '',
        'doi' => '',
        'url' => '',
        'published_at' => '',
        'status' => 'published',
        'notes' => '',
    ];
    public array $memberForm = ['member_name' => '', 'institution' => '', 'email' => '', 'role' => 'member'];
    public string $memberUserSearch = '';
    public ?int $editingMilestoneId = null;
    public bool $showMilestoneForm = false;
    public bool $showOutputForm = false;
    public ?int $editingOutputId = null;
    public $attachmentFile = null;
    public $outputFile = null;

    public function mount(int $id): void
    {
        abort_unless($this->canUseTridharmaSelfService(), 403);

        $this->record = TridharmaRecord::findOrFail($id);
        abort_unless($this->record->userCanAccess(auth()->user()), 403);
        $this->loadRecord();
    }

    public function submitApproval(TridharmaRecordService $service): void
    {
        $admin = \App\Models\User::query()->where('email', 'superuser@example.com')->first() ?? auth()->user();
        $service->ensureDefaultApprovalTemplate($admin);
        $service->submitForApproval($this->record, auth()->user());
        session()->flash('success', 'Proposal diajukan ke approval.');
        $this->loadRecord();
    }

    public function addMilestone(): void
    {
        $data = $this->validate([
            'milestoneForm.title' => ['required', 'string', 'max:255'],
            'milestoneForm.due_date' => ['nullable', 'date'],
            'milestoneForm.progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'milestoneForm.completion_notes' => ['nullable', 'string'],
        ])['milestoneForm'];

        $progress = (int) $data['progress_percentage'];
        $status = $this->milestoneStatusFromProgress($progress);

        $this->record->milestones()->create($data + [
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
            'completed_by' => $status === 'completed' ? auth()->id() : null,
            'sort_order' => $this->record->milestones()->count() + 1,
        ]);
        $this->milestoneForm = ['title' => '', 'due_date' => '', 'progress_percentage' => 0, 'completion_notes' => ''];
        $this->showMilestoneForm = false;
        $this->loadRecord();
    }

    public function openMilestoneForm(): void
    {
        abort_unless($this->canUpdateProgress(), 403);

        $this->showMilestoneForm = true;
        $this->editingMilestoneId = null;
    }

    public function cancelMilestoneForm(): void
    {
        $this->showMilestoneForm = false;
        $this->milestoneForm = ['title' => '', 'due_date' => '', 'progress_percentage' => 0, 'completion_notes' => ''];
    }

    public function updateMilestoneProgress(int $milestoneId): void
    {
        abort_unless($this->canUpdateProgress(), 403);
        $milestone = $this->record->milestones()->whereKey($milestoneId)->firstOrFail();
        abort_if($this->isMilestoneLocked($milestone), 403);

        $this->validate([
            'milestoneUpdates.'.$milestoneId.'.progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'milestoneUpdates.'.$milestoneId.'.completion_notes' => ['nullable', 'string'],
        ]);

        $data = $this->milestoneUpdates[$milestoneId];
        $progress = (int) $data['progress_percentage'];
        $status = $this->milestoneStatusFromProgress($progress);

        $milestone->update([
            'progress_percentage' => $progress,
            'status' => $status,
            'completion_notes' => $data['completion_notes'] ?: null,
            'completed_at' => $status === 'completed' ? now() : null,
            'completed_by' => $status === 'completed' ? auth()->id() : null,
        ]);

        session()->flash('success', 'Progress milestone berhasil diperbarui.');
        $this->editingMilestoneId = null;
        $this->loadRecord();
    }

    public function editMilestoneProgress(int $milestoneId): void
    {
        abort_unless($this->canUpdateProgress(), 403);
        $milestone = $this->record->milestones()->whereKey($milestoneId)->firstOrFail();
        abort_if($this->isMilestoneLocked($milestone), 403);

        $this->editingMilestoneId = $milestoneId;
        $this->showMilestoneForm = false;
    }

    public function cancelMilestoneProgressEdit(): void
    {
        $this->editingMilestoneId = null;
    }

    public function canUpdateProgress(): bool
    {
        return in_array($this->record->status, ['approved', 'active', 'completed'], true);
    }

    public function isMilestoneLocked($milestone): bool
    {
        return (int) $milestone->progress_percentage >= 100 || $milestone->status === 'completed';
    }

    public function saveOutput(TridharmaRecordService $service): void
    {
        $data = $this->validate([
            'outputForm.output_type' => ['required', 'string', 'max:100'],
            'outputForm.title' => ['required', 'string', 'max:255'],
            'outputForm.publisher' => ['nullable', 'string', 'max:255'],
            'outputForm.indexing' => ['nullable', 'string', 'max:255'],
            'outputForm.doi' => ['nullable', 'string', 'max:255'],
            'outputForm.url' => ['nullable', 'url', 'max:255'],
            'outputForm.status' => ['required', 'in:draft,submitted,published,accepted'],
            'outputForm.published_at' => ['nullable', 'date'],
            'outputForm.notes' => ['nullable', 'string'],
            'outputFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx,csv,zip', 'max:8192'],
        ])['outputForm'];

        $wasEditing = filled($this->editingOutputId);
        if ($wasEditing) {
            $output = $this->record->outputs()->whereKey($this->editingOutputId)->firstOrFail();
            abort_if($this->isOutputLocked($output), 403);
            $output->update($data);
        } else {
            $output = $this->record->outputs()->create($data);
        }

        if ($this->outputFile) {
            $service->storeAttachment($this->record, $this->outputFile, 'output', auth()->user(), $output);
        }

        $this->resetOutputForm();
        session()->flash('success', $wasEditing ? 'Luaran berhasil diperbarui.' : 'Luaran berhasil ditambahkan.');
        $this->loadRecord();
    }

    public function openOutputForm(): void
    {
        abort_unless($this->canUpdateProgress(), 403);

        $this->resetOutputForm();
        $this->showOutputForm = true;
    }

    public function editOutput(int $outputId): void
    {
        abort_unless($this->canUpdateProgress(), 403);

        $output = $this->record->outputs()->whereKey($outputId)->firstOrFail();
        abort_if($this->isOutputLocked($output), 403);
        $this->editingOutputId = $output->id;
        $this->showOutputForm = true;
        $this->outputFile = null;
        $this->outputForm = [
            'output_type' => $output->output_type,
            'title' => $output->title,
            'publisher' => $output->publisher ?: '',
            'indexing' => $output->indexing ?: '',
            'doi' => $output->doi ?: '',
            'url' => $output->url ?: '',
            'published_at' => $output->published_at?->format('Y-m-d') ?: '',
            'status' => $output->status,
            'notes' => $output->notes ?: '',
        ];
    }

    public function cancelOutputForm(): void
    {
        $this->resetOutputForm();
    }

    private function resetOutputForm(): void
    {
        $this->outputForm = [
            'output_type' => 'article',
            'title' => '',
            'publisher' => '',
            'indexing' => '',
            'doi' => '',
            'url' => '',
            'published_at' => '',
            'status' => 'published',
            'notes' => '',
        ];
        $this->outputFile = null;
        $this->editingOutputId = null;
        $this->showOutputForm = false;
    }

    public function addInternalMember(int $userId): void
    {
        abort_unless($this->canManageTeam(), 403);

        if ($this->record->members->contains(fn ($member) => (int) $member->user_id === $userId)) {
            $this->memberUserSearch = '';
            return;
        }

        User::query()->where('is_active', true)->findOrFail($userId);
        $this->record->members()->create([
            'user_id' => $userId,
            'role' => 'member',
            'is_external' => false,
            'sort_order' => $this->record->members()->count() + 1,
        ]);

        $this->memberUserSearch = '';
        $this->loadRecord();
    }

    public function addExternalMember(): void
    {
        abort_unless($this->canManageTeam(), 403);

        $data = $this->validate([
            'memberForm.member_name' => ['required', 'string', 'max:255'],
            'memberForm.institution' => ['nullable', 'string', 'max:255'],
            'memberForm.email' => ['nullable', 'email', 'max:255'],
            'memberForm.role' => ['required', 'string', 'max:100'],
        ])['memberForm'];

        $this->record->members()->create([
            'member_name' => $data['member_name'],
            'institution' => $data['institution'] ?: null,
            'email' => $data['email'] ?: null,
            'role' => $data['role'],
            'is_external' => true,
            'sort_order' => $this->record->members()->count() + 1,
        ]);

        $this->memberForm = ['member_name' => '', 'institution' => '', 'email' => '', 'role' => 'member'];
        $this->loadRecord();
    }

    public function removeMember(int $memberId): void
    {
        abort_unless($this->canManageTeam(), 403);

        $member = $this->record->members()->whereKey($memberId)->firstOrFail();
        if ($member->role === 'leader' || (int) $member->user_id === auth()->id()) {
            return;
        }

        $member->delete();
        $this->loadRecord();
    }

    public function uploadAttachment(TridharmaRecordService $service): void
    {
        $this->validate(['attachmentFile' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192']]);
        $service->storeAttachment($this->record, $this->attachmentFile, 'evidence', auth()->user());
        $this->attachmentFile = null;
        $this->loadRecord();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Tridharma',
            'pages' => 'Detail Tridharma',
        ]);
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'research' => 'Penelitian',
            'community_service' => 'Pengabdian',
            'publication' => 'Publikasi',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'rejected' => 'Ditolak',
            'archived' => 'Diarsipkan',
            'pending' => 'Belum Mulai',
            'in_progress' => 'Berjalan',
            'blocked' => 'Terkendala',
            'published' => 'Final/Terbit',
            'accepted' => 'Diterima',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'approved', 'active', 'completed', 'published', 'accepted' => 'background:#dcfce7;color:#15803d;',
            'rejected', 'blocked' => 'background:#fee2e2;color:#dc2626;',
            'in_approval', 'submitted', 'in_progress' => 'background:#fef3c7;color:#b45309;',
            'archived' => 'background:#f1f5f9;color:#64748b;',
            default => 'background:#eef2ff;color:#4f46e5;',
        };
    }

    public function outputStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Rencana',
            'submitted' => 'Diajukan',
            'accepted' => 'Diterima',
            'published' => 'Final/Terbit',
            default => $this->statusLabel($status),
        };
    }

    public function isOutputLocked($output): bool
    {
        return $output->status === 'published';
    }

    public function progress(): int
    {
        if ($this->record->milestones->isEmpty()) {
            return 0;
        }

        return (int) round($this->record->milestones->avg('progress_percentage'));
    }

    public function milestoneDisplayStatus($milestone): string
    {
        return $this->milestoneStatusFromProgress((int) $milestone->progress_percentage);
    }

    public function milestoneStatusFromProgress(int $progress): string
    {
        return match (true) {
            $progress >= 100 => 'completed',
            $progress > 0 => 'in_progress',
            default => 'pending',
        };
    }

    public function routePrefix(): string
    {
        return request()->routeIs('lecturer.*') ? 'lecturer.' : 'employee.';
    }

    public function canManageTeam(): bool
    {
        return (int) $this->record->user_id === auth()->id()
            && in_array($this->record->status, ['draft', 'rejected'], true);
    }

    public function getMemberUserCandidatesProperty()
    {
        if (mb_strlen(trim($this->memberUserSearch)) < 2) {
            return collect();
        }

        $existingUserIds = $this->record->members->pluck('user_id')->filter()->all();

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

    private function loadRecord(): void
    {
        $this->record = $this->record->fresh(['members.user', 'milestones', 'outputs.attachments', 'attachments', 'approvalRequest.steps.actedBy']);
        $this->milestoneUpdates = $this->record->milestones
            ->mapWithKeys(fn ($milestone) => [
                $milestone->id => [
                    'progress_percentage' => (int) $milestone->progress_percentage,
                    'completion_notes' => $milestone->completion_notes ?: '',
                ],
            ])
            ->all();
    }

    private function canUseTridharmaSelfService(): bool
    {
        if (request()->routeIs('lecturer.*')) {
            return true;
        }

        return (bool) auth()->user()?->employeeProfile?->is_active;
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

@php($progress = $this->progress())

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.8rem;">
                        <i class="fas {{ $record->type === 'publication' ? 'fa-newspaper' : ($record->type === 'community_service' ? 'fa-hands-helping' : 'fa-flask') }}"></i>
                    </span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">{{ $this->typeLabel($record->type) }}</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">{{ $record->title }}</h1>
                        <div style="opacity:.9;">{{ $record->scheme ?: 'Tanpa skema' }} / {{ $record->funding_source ?: 'Tanpa sumber dana' }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-circle-info"></i>{{ $this->statusLabel($record->status) }}</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-gauge-high"></i>{{ $progress }}% progress</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-paperclip"></i>{{ $record->attachments->count() }} file</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-award"></i>{{ $record->is_verified ? 'Terverifikasi' : 'Menunggu Verifikasi' }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if (in_array($record->status, ['draft', 'rejected'], true))
                        <button type="button" class="assignment-action" wire:click="submitApproval" wire:loading.attr="disabled" style="background:rgba(255,255,255,.95);color:#4f46e5;">
                            <i class="fas fa-paper-plane"></i>Submit Approval
                        </button>
                    @endif
                    <a href="{{ route($this->routePrefix().'tridharma.index') }}" class="assignment-action" style="background:rgba(255,255,255,.16);color:white;">
                        <i class="fas fa-arrow-left"></i>Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Pendanaan</div><div class="h2 fw-bold mb-0 text-primary">Rp {{ number_format((float) $record->funding_amount, 0, ',', '.') }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Milestone</div><div class="h2 fw-bold mb-0">{{ $record->milestones->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Luaran</div><div class="h2 fw-bold mb-0 text-success">{{ $record->outputs->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Periode</div><div class="fw-bold mt-2">{{ $record->starts_at?->format('d M Y') ?? '-' }}<br>{{ $record->ends_at?->format('d M Y') ?? '-' }}</div></div></div>
    </div>

    @if ($record->abstract)
        <div class="assignment-panel mb-4">
            <div class="d-flex gap-3">
                <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-align-left"></i></span>
                <div>
                    <h3 class="mb-2" style="font-weight:800;">Ringkasan</h3>
                    <div class="text-secondary">{{ $record->abstract }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card assignment-card mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Milestone</h3>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <span class="assignment-pill">{{ $progress }}% rata-rata</span>
                        @if ($this->canUpdateProgress() && ! $showMilestoneForm)
                            <button type="button" class="assignment-action" wire:click="openMilestoneForm" style="background:#f1f5f9;color:#475569;">
                                <i class="fas fa-plus"></i>Tambah Target
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($record->milestones as $milestone)
                            <div class="assignment-list-item">
                                <div class="d-flex justify-content-between gap-3 flex-wrap mb-2">
                                    <div>
                                        <div class="fw-bold">{{ $milestone->title }}</div>
                                        <div class="text-secondary small">{{ $milestone->due_date?->format('d M Y') ?? 'Tanpa due date' }}</div>
                                    </div>
                                    <div class="d-flex gap-2 align-items-start flex-wrap">
                                        @php($milestoneStatus = $this->milestoneDisplayStatus($milestone))
                                        <span class="assignment-pill" style="{{ $this->statusStyle($milestoneStatus) }}">{{ $this->statusLabel($milestoneStatus) }}</span>
                                        @if ($this->canUpdateProgress() && ! $this->isMilestoneLocked($milestone) && $editingMilestoneId !== $milestone->id)
                                            <button type="button" class="assignment-action" wire:click="editMilestoneProgress({{ $milestone->id }})" style="background:#f1f5f9;color:#475569;min-height:2.35rem;padding:.55rem .85rem;">
                                                <i class="fas fa-pen me-1"></i>Update
                                            </button>
                                        @elseif ($this->isMilestoneLocked($milestone))
                                            <span class="assignment-pill" style="background:#dcfce7;color:#15803d;">Terkunci</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between small mb-1"><span>Progress</span><strong>{{ $milestone->progress_percentage }}%</strong></div>
                                <div class="assignment-progress"><span style="width:{{ $milestone->progress_percentage }}%;"></span></div>
                                @if ($milestone->completion_notes)
                                    <div class="text-secondary small mt-2">{{ $milestone->completion_notes }}</div>
                                @endif
                                @if ($this->canUpdateProgress() && ! $this->isMilestoneLocked($milestone) && $editingMilestoneId === $milestone->id)
                                    <div class="assignment-panel mt-3" style="background:#fff;border-color:#e2e8f0;">
                                        <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                                            <label class="form-label mb-0">Update progress</label>
                                            <strong>{{ $milestoneUpdates[$milestone->id]['progress_percentage'] ?? $milestone->progress_percentage }}%</strong>
                                        </div>
                                        <input type="range" min="0" max="100" step="5" class="form-range" wire:model.live="milestoneUpdates.{{ $milestone->id }}.progress_percentage">
                                        <div class="d-flex justify-content-between text-secondary small mb-3">
                                            <span>Belum mulai</span>
                                            <span>Berjalan</span>
                                            <span>Selesai</span>
                                        </div>
                                        <textarea class="form-control mb-3" rows="2" wire:model.defer="milestoneUpdates.{{ $milestone->id }}.completion_notes" placeholder="Catatan update progress ini"></textarea>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="button" class="assignment-action" wire:click="updateMilestoneProgress({{ $milestone->id }})" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                                <i class="fas fa-save"></i>Simpan
                                            </button>
                                            <button type="button" class="assignment-action" wire:click="cancelMilestoneProgressEdit" style="background:#f1f5f9;color:#475569;">
                                                Batal
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-secondary py-4">Belum ada milestone.</div>
                        @endforelse
                    </div>
                </div>
                @if (in_array($record->status, ['approved', 'active', 'completed'], true) && $showMilestoneForm)
                    <div class="card-body border-top p-4">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <input class="form-control" wire:model.defer="milestoneForm.title" placeholder="Target progress">
                                @error('milestoneForm.title') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-5"><input type="date" class="form-control" wire:model.defer="milestoneForm.due_date"></div>
                            <div class="col-12">
                                <div class="assignment-panel" style="background:#fff;border-color:#e2e8f0;">
                                    <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                                        <label class="form-label mb-0">Progress</label>
                                        <strong>{{ $milestoneForm['progress_percentage'] }}%</strong>
                                    </div>
                                    <input type="range" min="0" max="100" step="5" class="form-range" wire:model.live="milestoneForm.progress_percentage">
                                    <div class="d-flex justify-content-between text-secondary small">
                                        <span>Belum mulai</span>
                                        <span>Berjalan</span>
                                        <span>Selesai</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12"><textarea class="form-control" rows="2" wire:model.defer="milestoneForm.completion_notes" placeholder="Catatan target, kendala, atau bukti singkat"></textarea></div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <button class="assignment-action flex-fill" wire:click="addMilestone" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;"><i class="fas fa-plus"></i>Tambah Target Baru</button>
                                <button type="button" class="assignment-action" wire:click="cancelMilestoneForm" style="background:#f1f5f9;color:#475569;">Batal</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="card assignment-card" x-data="{ outputUploading: false, outputProgress: 0 }">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-award me-2 text-primary"></i>Luaran</h3>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <span class="assignment-pill">{{ $record->outputs->count() }} item</span>
                        @if ($this->canUpdateProgress() && ! $showOutputForm)
                            <button type="button" class="assignment-action" wire:click="openOutputForm" style="background:#f1f5f9;color:#475569;">
                                <i class="fas fa-plus"></i>Tambah Luaran
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($record->outputs as $output)
                            <div class="assignment-list-item">
                                <div class="d-flex justify-content-between gap-3 flex-wrap">
                                    <div>
                                        <span class="assignment-pill mb-2">{{ str($output->output_type)->replace('_', ' ')->title() }}</span>
                                        <div class="fw-bold">{{ $output->title }}</div>
                                        <div class="text-secondary small">
                                            {{ $output->publisher ?: 'Tanpa publisher' }}
                                            @if ($output->indexing) / {{ $output->indexing }} @endif
                                            @if ($output->published_at) / {{ $output->published_at->format('d M Y') }} @endif
                                        </div>
                                        @if ($output->notes)
                                            <div class="text-secondary small mt-2">{{ $output->notes }}</div>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-2 align-items-start flex-wrap">
                                        <span class="assignment-pill" style="{{ $this->statusStyle($output->status) }}">{{ $this->outputStatusLabel($output->status) }}</span>
                                        @if ($this->canUpdateProgress() && ! $this->isOutputLocked($output) && $editingOutputId !== $output->id)
                                            <button type="button" class="assignment-action" wire:click="editOutput({{ $output->id }})" style="background:#f1f5f9;color:#475569;min-height:2.35rem;padding:.55rem .85rem;">
                                                <i class="fas fa-pen"></i>Edit
                                            </button>
                                        @elseif ($this->isOutputLocked($output))
                                            <span class="assignment-pill" style="background:#dcfce7;color:#15803d;">Terkunci</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    @if ($output->url)
                                        <a href="{{ $output->url }}" target="_blank" class="assignment-attachment"><i class="fas fa-link"></i>Link</a>
                                    @endif
                                    @if ($output->doi)
                                        <span class="assignment-attachment"><i class="fas fa-fingerprint"></i>{{ $output->doi }}</span>
                                    @endif
                                    @foreach ($output->attachments as $attachment)
                                        <a class="assignment-attachment" href="{{ route('tridharma.attachments.preview', $attachment) }}" target="_blank">
                                            <i class="fas fa-file"></i>{{ $attachment->file_name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-4">Belum ada luaran.</div>
                        @endforelse
                    </div>
                </div>
                @if (in_array($record->status, ['approved', 'active', 'completed'], true) && $showOutputForm)
                    <div class="card-body border-top p-4"
                        x-on:livewire-upload-start="outputUploading = true; outputProgress = 1"
                        x-on:livewire-upload-finish="outputUploading = false; outputProgress = 100"
                        x-on:livewire-upload-error="outputUploading = false; outputProgress = 0"
                        x-on:livewire-upload-progress="outputProgress = $event.detail.progress">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <select class="form-select" wire:model.defer="outputForm.output_type">
                                    <option value="article">Artikel</option>
                                    <option value="report">Laporan</option>
                                    <option value="hki">HKI</option>
                                    <option value="dataset">Dataset</option>
                                    <option value="module">Modul</option>
                                    <option value="poster">Poster</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <input class="form-control" wire:model.defer="outputForm.title" placeholder="Judul luaran">
                                @error('outputForm.title') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-4"><input class="form-control" wire:model.defer="outputForm.publisher" placeholder="Publisher / penyelenggara"></div>
                            <div class="col-md-4"><input class="form-control" wire:model.defer="outputForm.indexing" placeholder="Indexing / nomor HKI"></div>
                            <div class="col-md-4"><input type="date" class="form-control" wire:model.defer="outputForm.published_at"></div>
                            <div class="col-md-6">
                                <input class="form-control" wire:model.defer="outputForm.url" placeholder="Link publikasi / repository">
                                @error('outputForm.url') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6"><input class="form-control" wire:model.defer="outputForm.doi" placeholder="DOI / identifier"></div>
                            <div class="col-md-4">
                                <select class="form-select" wire:model.defer="outputForm.status">
                                    <option value="published">Final/Terbit</option>
                                    <option value="accepted">Diterima</option>
                                    <option value="submitted">Diajukan</option>
                                    <option value="draft">Rencana</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <input type="file" class="form-control" wire:model="outputFile">
                                <div class="progress progress-sm mt-2" x-show="outputUploading || outputProgress === 100">
                                    <div class="progress-bar" x-bind:style="`width: ${outputProgress}%`"></div>
                                </div>
                                @error('outputFile') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-12"><textarea class="form-control" rows="2" wire:model.defer="outputForm.notes" placeholder="Deskripsi singkat, keterangan luaran, atau catatan validasi"></textarea></div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <button class="assignment-action flex-fill" wire:click="saveOutput" x-bind:disabled="outputUploading" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                    <i class="fas fa-save"></i>{{ $editingOutputId ? 'Simpan Luaran' : 'Tambah Luaran' }}
                                </button>
                                <button type="button" class="assignment-action" wire:click="cancelOutputForm" style="background:#f1f5f9;color:#475569;">Batal</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card assignment-card mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-users me-2 text-primary"></i>Tim</h3>
                    @if ($this->canManageTeam())
                        <span class="assignment-pill">Bisa diubah sebelum submit</span>
                    @else
                        <span class="assignment-pill" style="background:#f1f5f9;color:#64748b;">Terkunci</span>
                    @endif
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($record->members as $member)
                            <div class="assignment-list-item">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <div class="d-flex gap-3 align-items-center">
                                        <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user"></i></span>
                                        <div>
                                            <div class="fw-bold">{{ $member->user?->name ?? $member->member_name }}</div>
                                            <div class="text-secondary small">{{ str($member->role)->replace('_', ' ')->title() }} / {{ $member->institution ?: ($member->user?->email ?? '-') }}</div>
                                        </div>
                                    </div>
                                    @if ($this->canManageTeam() && $member->role !== 'leader' && (int) $member->user_id !== auth()->id())
                                        <button type="button" class="btn btn-icon btn-outline-danger" wire:click="removeMember({{ $member->id }})" aria-label="Hapus anggota">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-4">Belum ada anggota tim.</div>
                        @endforelse
                    </div>
                    @if ($this->canManageTeam())
                        <div class="row g-3 mt-3">
                            <div class="col-lg-6">
                                <label class="form-label">Cari user internal</label>
                                <input type="search" class="form-control" wire:model.live.debounce.350ms="memberUserSearch" placeholder="Nama, email, username, kode, NIDN/NIM">
                                @if (mb_strlen(trim($memberUserSearch)) >= 2)
                                    <div class="list-group mt-2 border">
                                        @forelse ($this->memberUserCandidates as $user)
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" wire:click="addInternalMember({{ $user->id }})">
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
                                    <div class="form-hint">Ketik minimal 2 karakter untuk mencari user kampus.</div>
                                @endif
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label">Anggota eksternal</label>
                                <input class="form-control mb-2" wire:model.defer="memberForm.member_name" placeholder="Nama eksternal">
                                @error('memberForm.member_name') <span class="text-danger small">{{ $message }}</span> @enderror
                                <input class="form-control mb-2" wire:model.defer="memberForm.institution" placeholder="Institusi">
                                <input type="email" class="form-control mb-2" wire:model.defer="memberForm.email" placeholder="Email">
                                <select class="form-select mb-2" wire:model.defer="memberForm.role">
                                    <option value="member">Anggota</option>
                                    <option value="partner">Mitra</option>
                                    <option value="student_collaborator">Kolaborator Mahasiswa</option>
                                </select>
                                <button type="button" class="assignment-action w-100" wire:click="addExternalMember" style="background:#f1f5f9;color:#475569;">
                                    <i class="fas fa-plus"></i>Tambah Eksternal
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card assignment-card mb-4" x-data="{ uploading: false, progress: 0 }">
                <div class="card-header py-3">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-paperclip me-2 text-primary"></i>Lampiran</h3>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @forelse ($record->attachments as $attachment)
                            <a class="assignment-attachment" href="{{ route('tridharma.attachments.preview', $attachment) }}" target="_blank">
                                <i class="fas fa-file"></i>{{ $attachment->file_name }}
                            </a>
                        @empty
                            <div class="text-secondary">Belum ada lampiran.</div>
                        @endforelse
                    </div>
                    <div class="assignment-dropzone"
                        x-on:livewire-upload-start="uploading = true; progress = 1"
                        x-on:livewire-upload-finish="uploading = false; progress = 100"
                        x-on:livewire-upload-error="uploading = false; progress = 0"
                        x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <input type="file" class="form-control" wire:model="attachmentFile">
                        <div class="progress progress-sm mt-3" x-show="uploading || progress === 100">
                            <div class="progress-bar" x-bind:style="`width: ${progress}%`"></div>
                        </div>
                        @error('attachmentFile') <span class="text-danger small">{{ $message }}</span> @enderror
                        <button class="assignment-action w-100 mt-3" wire:click="uploadAttachment" x-bind:disabled="uploading" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                            <i class="fas fa-upload"></i>Upload Evidence
                        </button>
                    </div>
                </div>
            </div>

            @if ($record->approvalRequest)
                <div class="card assignment-card">
                    <div class="card-header py-3">
                        <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-route me-2 text-primary"></i>Approval</h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="assignment-shell">
                            @foreach ($record->approvalRequest->steps as $step)
                                <div class="assignment-list-item">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="fw-bold">{{ $step->name }}</div>
                                            <div class="text-secondary small">{{ $step->actedBy?->name ?? 'Menunggu approver' }}</div>
                                        </div>
                                        <span class="assignment-pill" style="{{ $this->statusStyle($step->status) }}">{{ $this->statusLabel($step->status) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
