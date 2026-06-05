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

    public array $form = [
        'type' => 'research',
        'title' => '',
        'scheme' => '',
        'abstract' => '',
        'starts_at' => '',
        'ends_at' => '',
        'funding_amount' => 0,
        'funding_source' => '',
    ];
    public $proposalFile = null;
    public array $teamMembers = [];
    public string $memberUserSearch = '';
    public array $memberForm = [
        'member_name' => '',
        'institution' => '',
        'email' => '',
        'role' => 'member',
    ];

    public function mount(): void
    {
        abort_unless($this->canUseTridharmaSelfService(), 403);
        $user = auth()->user();
        $this->teamMembers = [[
            'kind' => 'internal',
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'institution' => '',
            'role' => 'leader',
            'locked' => true,
        ]];
    }

    public function saveDraft(TridharmaRecordService $service): void
    {
        $record = $this->createRecord($service, submit: false);
        session()->flash('success', 'Draft Tridharma berhasil dibuat.');
        $this->redirectRoute($this->routePrefix().'tridharma.show', ['id' => $record->id]);
    }

    public function submit(TridharmaRecordService $service): void
    {
        $record = $this->createRecord($service, submit: true);
        session()->flash('success', 'Proposal Tridharma berhasil diajukan.');
        $this->redirectRoute($this->routePrefix().'tridharma.show', ['id' => $record->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Tridharma',
            'pages' => 'Tambah Tridharma',
        ]);
    }

    public function routePrefix(): string
    {
        return request()->routeIs('lecturer.*') ? 'lecturer.' : 'employee.';
    }

    public function addInternalMember(int $userId): void
    {
        if (collect($this->teamMembers)->contains(fn ($member) => ($member['user_id'] ?? null) === $userId)) {
            $this->memberUserSearch = '';
            return;
        }

        $user = User::query()->where('is_active', true)->findOrFail($userId);
        $this->teamMembers[] = [
            'kind' => 'internal',
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'institution' => '',
            'role' => 'member',
            'locked' => false,
        ];
        $this->memberUserSearch = '';
    }

    public function addExternalMember(): void
    {
        $data = $this->validate([
            'memberForm.member_name' => ['required', 'string', 'max:255'],
            'memberForm.institution' => ['nullable', 'string', 'max:255'],
            'memberForm.email' => ['nullable', 'email', 'max:255'],
            'memberForm.role' => ['required', 'string', 'max:100'],
        ])['memberForm'];

        $this->teamMembers[] = [
            'kind' => 'external',
            'user_id' => null,
            'name' => $data['member_name'],
            'email' => $data['email'] ?: '',
            'institution' => $data['institution'] ?: '',
            'role' => $data['role'],
            'locked' => false,
        ];
        $this->memberForm = ['member_name' => '', 'institution' => '', 'email' => '', 'role' => 'member'];
    }

    public function removeTeamMember(int $index): void
    {
        if (($this->teamMembers[$index]['locked'] ?? false) === true) {
            return;
        }

        unset($this->teamMembers[$index]);
        $this->teamMembers = array_values($this->teamMembers);
    }

    public function getMemberUserCandidatesProperty()
    {
        if (mb_strlen(trim($this->memberUserSearch)) < 2) {
            return collect();
        }

        $selectedIds = collect($this->teamMembers)->pluck('user_id')->filter()->all();

        return User::query()
            ->where('is_active', true)
            ->when($selectedIds !== [], fn ($query) => $query->whereNotIn('id', $selectedIds))
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

    private function createRecord(TridharmaRecordService $service, bool $submit): TridharmaRecord
    {
        $validated = $this->validate([
            'form.type' => ['required', 'in:research,community_service,publication'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.scheme' => ['nullable', 'string', 'max:255'],
            'form.abstract' => ['nullable', 'string'],
            'form.starts_at' => ['nullable', 'date'],
            'form.ends_at' => ['nullable', 'date', 'after_or_equal:form.starts_at'],
            'form.funding_amount' => ['nullable', 'numeric', 'min:0'],
            'form.funding_source' => ['nullable', 'string', 'max:255'],
            'proposalFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192'],
        ]);

        return DB::transaction(function () use ($validated, $service, $submit) {
            $user = auth()->user()->load(['lecturerProfile', 'employeeProfile']);
            $record = TridharmaRecord::create([
                'user_id' => $user->id,
                'lecturer_profile_id' => $user->lecturerProfile?->id,
                'employee_profile_id' => $user->employeeProfile?->id,
                'type' => $validated['form']['type'],
                'title' => $validated['form']['title'],
                'scheme' => $validated['form']['scheme'] ?: null,
                'abstract' => $validated['form']['abstract'] ?: null,
                'starts_at' => $validated['form']['starts_at'] ?: null,
                'ends_at' => $validated['form']['ends_at'] ?: null,
                'funding_amount' => $validated['form']['funding_amount'] ?: 0,
                'funding_source' => $validated['form']['funding_source'] ?: null,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($this->teamMembers as $index => $member) {
                $record->members()->create([
                    'user_id' => $member['user_id'] ?: null,
                    'member_name' => $member['kind'] === 'external' ? $member['name'] : null,
                    'institution' => $member['institution'] ?: null,
                    'email' => $member['kind'] === 'external' ? ($member['email'] ?: null) : null,
                    'role' => $member['role'],
                    'is_external' => $member['kind'] === 'external',
                    'sort_order' => $index + 1,
                ]);
            }

            if ($this->proposalFile) {
                $service->storeAttachment($record, $this->proposalFile, 'proposal', $user);
            }

            if ($submit) {
                $admin = \App\Models\User::query()->where('email', 'superuser@example.com')->first() ?? $user;
                $service->ensureDefaultApprovalTemplate($admin);
                $service->submitForApproval($record, $user);
            }

            return $record->fresh();
        });
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

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.8rem;">
                        <i class="fas fa-paper-plane"></i>
                    </span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Form Pengajuan</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Ajukan Tridharma</h1>
                        <div style="opacity:.9;">Simpan sebagai draft atau langsung kirim ke approval setelah proposal siap.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-user-check"></i>Anda otomatis menjadi ketua</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-lock"></i>File disimpan privat</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route($this->routePrefix().'tridharma.index') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;">
                    <i class="fas fa-arrow-left"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card assignment-card" x-data="{ uploading: false, progress: 0 }">
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="assignment-shell">
                        <div class="assignment-panel">
                            <div class="d-flex gap-3 align-items-start mb-3">
                                <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-pen-nib"></i></span>
                                <div>
                                    <h3 class="mb-1" style="font-weight:800;">Identitas Kegiatan</h3>
                                    <div class="text-secondary">Kategori, judul, dan ringkasan kegiatan.</div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label required">Tipe</label>
                                    <select class="form-select" wire:model.defer="form.type">
                                        <option value="research">Penelitian</option>
                                        <option value="community_service">Pengabdian</option>
                                        <option value="publication">Publikasi</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label required">Judul</label>
                                    <input class="form-control" wire:model.defer="form.title" placeholder="Judul kegiatan atau luaran">
                                    @error('form.title') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Abstrak / Deskripsi</label>
                                    <textarea class="form-control" rows="5" wire:model.defer="form.abstract" placeholder="Ringkasan tujuan, metode, mitra, atau rencana luaran"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="assignment-panel">
                            <div class="d-flex gap-3 align-items-start mb-3">
                                <span class="assignment-icon" style="background:#ecfdf5;color:#15803d;"><i class="fas fa-calendar-check"></i></span>
                                <div>
                                    <h3 class="mb-1" style="font-weight:800;">Skema dan Pendanaan</h3>
                                    <div class="text-secondary">Periode, sumber dana, dan nominal rencana.</div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Skema</label>
                                    <input class="form-control" wire:model.defer="form.scheme" placeholder="Hibah internal, mandiri, institusi">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mulai</label>
                                    <input type="date" class="form-control" wire:model.defer="form.starts_at">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Selesai</label>
                                    <input type="date" class="form-control" wire:model.defer="form.ends_at">
                                    @error('form.ends_at') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Sumber Dana</label>
                                    <input class="form-control" wire:model.defer="form.funding_source" placeholder="LPPM, fakultas, mitra, mandiri">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Nominal Dana</label>
                                    <input type="number" min="0" class="form-control" wire:model.defer="form.funding_amount">
                                </div>
                            </div>
                        </div>

                        <div class="assignment-panel">
                            <div class="d-flex gap-3 align-items-start mb-3">
                                <span class="assignment-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-users"></i></span>
                                <div>
                                    <h3 class="mb-1" style="font-weight:800;">Tim Pengusul</h3>
                                    <div class="text-secondary">Susun anggota sebelum proposal dikirim ke approval.</div>
                                </div>
                            </div>

                            <div class="assignment-shell mb-3">
                                @foreach ($teamMembers as $index => $member)
                                    <div class="assignment-list-item">
                                        <div class="d-flex justify-content-between align-items-center gap-3">
                                            <div>
                                                <div class="d-flex flex-wrap gap-2 mb-2">
                                                    <span class="assignment-pill">{{ str($member['role'])->replace('_', ' ')->title() }}</span>
                                                    <span class="assignment-pill" style="{{ $member['kind'] === 'internal' ? 'background:#dcfce7;color:#15803d;' : 'background:#fef3c7;color:#b45309;' }}">{{ $member['kind'] === 'internal' ? 'Internal' : 'Eksternal' }}</span>
                                                </div>
                                                <div class="fw-bold">{{ $member['name'] }}</div>
                                                <div class="text-secondary small">{{ $member['institution'] ?: ($member['email'] ?: '-') }}</div>
                                            </div>
                                            @unless ($member['locked'])
                                                <button type="button" class="btn btn-icon btn-outline-danger" wire:click="removeTeamMember({{ $index }})" aria-label="Hapus anggota">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endunless
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="row g-3">
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
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="assignment-panel h-100"
                        x-on:livewire-upload-start="uploading = true; progress = 1"
                        x-on:livewire-upload-finish="uploading = false; progress = 100"
                        x-on:livewire-upload-error="uploading = false; progress = 0"
                        x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <div class="d-flex gap-3 align-items-start mb-3">
                            <span class="assignment-icon" style="background:#fff7ed;color:#c2410c;"><i class="fas fa-file-upload"></i></span>
                            <div>
                                <h3 class="mb-1" style="font-weight:800;">Proposal Awal</h3>
                                <div class="text-secondary">PDF, dokumen, gambar, atau spreadsheet pendukung.</div>
                            </div>
                        </div>
                        <div class="assignment-dropzone">
                            <input type="file" class="form-control" wire:model="proposalFile">
                            <div class="progress progress-sm mt-3" x-show="uploading || progress === 100">
                                <div class="progress-bar" x-bind:style="`width: ${progress}%`"></div>
                            </div>
                            @error('proposalFile') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="button" class="assignment-action" wire:click="submit" x-bind:disabled="uploading" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                <i class="fas fa-paper-plane"></i>Submit Approval
                            </button>
                            <button type="button" class="assignment-action" wire:click="saveDraft" x-bind:disabled="uploading" wire:loading.attr="disabled" style="background:#f1f5f9;color:#475569;">
                                <i class="fas fa-save"></i>Simpan Draft
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
