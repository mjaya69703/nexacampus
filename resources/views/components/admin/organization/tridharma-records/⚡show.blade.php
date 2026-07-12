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
            'approved', 'active', 'completed', 'published', 'accepted' => 'bg-success text-white',
            'rejected', 'blocked' => 'bg-danger text-white',
            'in_approval', 'submitted', 'in_progress' => 'bg-warning text-dark',
            'archived' => 'bg-secondary text-white',
            default => 'bg-light text-dark',
        };
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="{{ $record->title }}"
        description="Kategori: {{ str($record->type)->replace('_', ' ')->title() }} &bull; Oleh: {{ $record->owner?->name ?? '-' }} &bull; Periode: {{ $record->starts_at?->format('d M Y') ?? '-' }} s.d. {{ $record->ends_at?->format('d M Y') ?? '-' }}"
        icon="award"
    >
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.organization.tridharma-records.edit', $record->id) }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-edit"></i> <span>Edit</span>
            </a>
            <a href="{{ route('admin.organization.tridharma-records.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-info-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Kegiatan</div>
                        <span class="badge {{ $this->statusClass($record->status) }} rounded-pill px-3 py-1 fs-6 mt-1">{{ str($record->status)->replace('_', ' ')->title() }}</span>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-shield-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Verifikasi</div>
                        <span class="badge {{ $record->is_verified ? 'bg-success' : 'bg-warning text-dark' }} rounded-pill px-3 py-1 fs-6 mt-1">{{ $record->is_verified ? 'Terverifikasi' : 'Belum Verifikasi' }}</span>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-money-bill-wave fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Dana Disetujui</div>
                        <div class="fw-bold">Rp {{ number_format((float) $record->funding_amount, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-building-columns fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Sumber Pendanaan</div>
                        <div class="fw-bold">{{ str($record->funding_source ?: 'Mandiri/Internal')->limit(20) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-info-circle text-primary me-2"></i>Abstrak & Aksi Kegiatan</h5>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @if (in_array($record->status, ['draft', 'rejected'], true))
                            <button type="button" class="btn btn-sm btn-warning rounded-pill px-4 fw-medium shadow-sm" wire:click="submitApproval" wire:confirm="Ajukan proposal tridharma ini ke alur persetujuan sekarang?">
                                <i class="fas fa-paper-plane me-1"></i> Submit Approval
                            </button>
                        @endif
                        @if (! $record->is_verified)
                            <div class="input-group input-group-sm" style="max-width: 360px;">
                                <input type="text" class="form-control rounded-start-pill px-3" wire:model.defer="verificationNotes" placeholder="Catatan verifikasi...">
                                <button type="button" class="btn btn-success rounded-end-pill px-3 fw-medium" wire:click="verify" wire:confirm="Verifikasi kegiatan tri dharma ini sekarang?">
                                    <i class="fas fa-check me-1"></i> Verifikasi
                                </button>
                            </div>
                        @endif
                        @if (! in_array($record->status, ['completed', 'archived'], true))
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-medium" wire:click="complete" wire:confirm="Tandai kegiatan ini sebagai selesai sekarang?">
                                <i class="fas fa-flag-checkered me-1"></i> Tandai Selesai
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-medium" wire:click="archive" wire:confirm="Arsipkan kegiatan ini?">
                            <i class="fas fa-box-archive me-1"></i> Arsipkan
                        </button>
                    </div>
                </div>
                @if ($record->abstract)
                    <div class="p-3 bg-light rounded-3 border text-dark">{{ $record->abstract }}</div>
                @else
                    <p class="text-muted small mb-0">Tidak ada uraian abstrak/ringkasan kegiatan untuk rekor ini.</p>
                @endif
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-tasks text-primary me-2"></i>Milestones & Target Tahapan</h5>
                </div>
                <div class="card-body p-0 pt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 small text-secondary">Target Capaian</th>
                                    <th class="py-3 small text-secondary">Jatuh Tempo</th>
                                    <th class="py-3 small text-secondary text-center">Progress</th>
                                    <th class="pe-4 py-3 small text-secondary text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($record->milestones as $milestone)
                                    <tr class="border-bottom">
                                        <td class="ps-4 py-3 fw-medium text-dark">{{ $milestone->title }}</td>
                                        <td class="py-3 text-muted">{{ $milestone->due_date?->format('d M Y') ?? '-' }}</td>
                                        <td class="py-3 text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1">{{ $milestone->progress_percentage }}%</span>
                                        </td>
                                        <td class="pe-4 py-3 text-end">
                                            <span class="badge {{ $this->statusClass($milestone->status) }} rounded-pill px-3 py-1">{{ str($milestone->status)->title() }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada target tahapan milestone.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light bg-opacity-50 border-top p-4">
                    <h6 class="fw-bold text-dark mb-2 fs-6">Tambah Milestone Baru</h6>
                    <div class="row g-2">
                        <div class="col-md-5"><input class="form-control rounded-3 form-control-sm" wire:model.defer="milestoneForm.title" placeholder="Nama target milestone..."></div>
                        <div class="col-md-3"><input type="date" class="form-control rounded-3 form-control-sm" wire:model.defer="milestoneForm.due_date"></div>
                        <div class="col-md-2"><input type="number" min="0" max="100" class="form-control rounded-3 form-control-sm" wire:model.defer="milestoneForm.progress_percentage" placeholder="%"></div>
                        <div class="col-md-2"><button type="button" class="btn btn-sm btn-primary rounded-pill w-100 fw-medium shadow-sm" wire:click="addMilestone">Tambah</button></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-coins text-success me-2"></i>Rincian Anggaran (Budget)</h5>
                </div>
                <div class="card-body p-0 pt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 small text-secondary">Kategori / Keterangan</th>
                                    <th class="py-3 small text-secondary text-end">Rencana</th>
                                    <th class="pe-4 py-3 small text-secondary text-end">Realisasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($record->budgets as $budget)
                                    <tr class="border-bottom">
                                        <td class="ps-4 py-3">
                                            <div class="fw-bold text-dark">{{ $budget->category }}</div>
                                            <span class="small text-muted">{{ $budget->description }}</span>
                                        </td>
                                        <td class="py-3 text-end text-dark">Rp {{ number_format((float) $budget->planned_amount, 0, ',', '.') }}</td>
                                        <td class="pe-4 py-3 text-end text-success fw-bold">Rp {{ number_format((float) $budget->realized_amount, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada rincian anggaran.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light bg-opacity-50 border-top p-4">
                    <h6 class="fw-bold text-dark mb-2 fs-6">Tambah Anggaran Baru</h6>
                    <div class="row g-2">
                        <div class="col-md-4"><input class="form-control rounded-3 form-control-sm" wire:model.defer="budgetForm.category" placeholder="Kategori budget..."></div>
                        <div class="col-md-3"><input type="number" class="form-control rounded-3 form-control-sm" wire:model.defer="budgetForm.planned_amount" placeholder="Rencana (Rp)"></div>
                        <div class="col-md-3"><input type="number" class="form-control rounded-3 form-control-sm" wire:model.defer="budgetForm.realized_amount" placeholder="Realisasi (Rp)"></div>
                        <div class="col-md-2"><button type="button" class="btn btn-sm btn-primary rounded-pill w-100 fw-medium shadow-sm" wire:click="addBudget">Tambah</button></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-book-open text-info me-2"></i>Output / Luaran Ilmiah & HKI</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="list-group list-group-flush border rounded-3 mb-3">
                        @forelse ($record->outputs as $output)
                            <div class="list-group-item py-3 px-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-light text-primary border rounded-pill px-3 py-1">{{ str($output->output_type)->title() }}</span>
                                    <span class="small text-muted">{{ $output->publisher ?: '-' }}</span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">{{ $output->title }}</h6>
                                <span class="small text-secondary">{{ $output->doi ?: $output->url ?: 'Belum ada DOI/Tautan' }}</span>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4 small">Belum ada luaran terdaftar.</div>
                        @endforelse
                    </div>

                    <h6 class="fw-bold text-dark mb-2 fs-6">Tambah Output / Luaran Baru</h6>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <select class="form-select rounded-3 form-select-sm" wire:model.defer="outputForm.output_type">
                                <option value="article">Artikel</option>
                                <option value="report">Laporan</option>
                                <option value="hki">HKI / Paten</option>
                                <option value="dataset">Dataset</option>
                                <option value="module">Modul Ajar</option>
                            </select>
                        </div>
                        <div class="col-md-7"><input class="form-control rounded-3 form-control-sm" wire:model.defer="outputForm.title" placeholder="Judul publikasi/luaran..."></div>
                        <div class="col-md-2"><button type="button" class="btn btn-sm btn-primary rounded-pill w-100 fw-medium shadow-sm" wire:click="addOutput">Tambah</button></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-users text-primary me-2"></i>Tim Peneliti / Pengabdi</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="list-group list-group-flush border rounded-3 mb-3">
                        @forelse ($record->members as $member)
                            <div class="list-group-item py-3 px-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-dark d-block">{{ $member->user?->name ?? $member->member_name }}</span>
                                    <span class="small text-muted">{{ str($member->role)->title() }} &bull; {{ $member->institution ?: ($member->user?->email ?? '-') }}</span>
                                </div>
                                <span class="badge {{ $member->role === 'leader' ? 'bg-primary' : 'bg-light text-secondary border' }} rounded-pill px-3 py-1">{{ str($member->role)->title() }}</span>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4 small">Belum ada anggota tim terdaftar.</div>
                        @endforelse
                    </div>

                    <h6 class="fw-bold text-dark mb-2 fs-6">Tambah Anggota Tim Baru</h6>
                    <div class="mb-2">
                        @if ($this->selectedMemberUser)
                            <div class="border rounded-3 p-2 bg-light d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary rounded-circle p-2">{{ str($this->selectedMemberUser->name)->substr(0, 1)->upper() }}</span>
                                    <div>
                                        <span class="fw-bold d-block small text-dark">{{ $this->selectedMemberUser->name }}</span>
                                        <span class="text-muted d-block" style="font-size: 0.75rem;">{{ $this->selectedMemberUser->email }}</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="clearMemberUser"><i class="fas fa-times"></i></button>
                            </div>
                        @else
                            <input type="search" class="form-control rounded-3 form-control-sm" wire:model.live.debounce.350ms="memberUserSearch" placeholder="Cari user internal (nama/email)...">
                            @error('memberForm.user_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                            @if (mb_strlen(trim($memberUserSearch)) >= 2)
                                <div class="list-group mt-2 border rounded-3 shadow-sm overflow-hidden">
                                    @forelse ($this->memberUserCandidates as $user)
                                        <button type="button" class="list-group-item list-group-item-action py-2 px-3 small d-flex justify-content-between align-items-center" wire:click="selectMemberUser({{ $user->id }})">
                                            <span>
                                                <strong class="text-dark d-block">{{ $user->name }}</strong>
                                                <span class="text-muted">{{ $user->email }}</span>
                                            </span>
                                            <i class="fas fa-plus text-primary"></i>
                                        </button>
                                    @empty
                                        <div class="list-group-item text-muted small py-2">User tidak ditemukan.</div>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                    </div>

                    <select class="form-select rounded-3 form-select-sm mb-2" wire:model.defer="memberForm.role">
                        <option value="leader">Ketua Peneliti/Pengabdi</option>
                        <option value="member">Anggota Tim</option>
                        <option value="partner">Mitra Kerjasama</option>
                        <option value="student_collaborator">Kolaborator Mahasiswa</option>
                    </select>

                    @unless ($this->selectedMemberUser)
                        <input class="form-control rounded-3 form-control-sm mb-2" wire:model.defer="memberForm.member_name" placeholder="Nama anggota eksternal...">
                        @error('memberForm.member_name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <input class="form-control rounded-3 form-control-sm mb-2" wire:model.defer="memberForm.institution" placeholder="Asal institusi eksternal...">
                        <input type="email" class="form-control rounded-3 form-control-sm mb-2" wire:model.defer="memberForm.email" placeholder="Email eksternal...">
                    @endunless

                    <button type="button" class="btn btn-sm btn-primary rounded-pill w-100 fw-medium shadow-sm mt-1" wire:click="addMember">Tambah Anggota</button>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" x-data="{ uploading: false, progress: 0 }">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-paperclip text-secondary me-2"></i>Lampiran Dokumen & Bukti (Evidence)</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="list-group list-group-flush border rounded-3 mb-3">
                        @forelse ($record->attachments as $attachment)
                            <a class="list-group-item list-group-item-action py-3 px-3 d-flex justify-content-between align-items-center" href="{{ route('admin.organization.tridharma-records.attachments.preview', $attachment) }}" target="_blank">
                                <div class="d-flex align-items-center gap-2 min-width-0">
                                    <i class="fas fa-file-alt text-primary fs-5"></i>
                                    <span class="text-truncate text-dark fw-medium small">{{ $attachment->file_name }}</span>
                                </div>
                                <span class="badge bg-light text-secondary rounded-pill px-2 py-1">{{ strtoupper($attachment->document_type) }}</span>
                            </a>
                        @empty
                            <div class="list-group-item text-center text-muted py-4 small">Belum ada lampiran dokumen.</div>
                        @endforelse
                    </div>

                    <div x-on:livewire-upload-start="uploading = true; progress = 1"
                         x-on:livewire-upload-finish="uploading = false; progress = 100"
                         x-on:livewire-upload-error="uploading = false; progress = 0"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <select class="form-select rounded-3 form-select-sm mb-2" wire:model.defer="attachmentType">
                            <option value="proposal">Dokumen Proposal</option>
                            <option value="report">Laporan Akhir</option>
                            <option value="evidence">Bukti Kegiatan (Evidence)</option>
                            <option value="output">Bukti Luaran (Publikasi/HKI)</option>
                        </select>
                        <input type="file" class="form-control rounded-3 form-control-sm mb-2" wire:model="attachmentFile">
                        <div class="progress progress-sm mb-2" x-show="uploading || progress === 100">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" x-bind:style="`width: ${progress}%`"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill w-100 fw-medium shadow-sm" wire:click="uploadAttachment" x-bind:disabled="uploading">Unggah Dokumen</button>
                    </div>
                </div>
            </div>

            @if ($record->approvalRequest)
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                        <h5 class="card-title fw-bold mb-0"><i class="fas fa-history text-info me-2"></i>Alur Persetujuan (Approval Timeline)</h5>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="list-group list-group-flush border rounded-3">
                            @foreach ($record->approvalRequest->steps as $step)
                                <div class="list-group-item py-3 px-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold text-dark d-block small">{{ $step->name }}</span>
                                        <span class="text-muted" style="font-size: 0.75rem;">{{ $step->actedBy?->name ?? 'Menunggu approver' }}</span>
                                    </div>
                                    <span class="badge {{ $this->statusClass($step->status) }} rounded-pill px-3 py-1">{{ str($step->status)->title() }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
