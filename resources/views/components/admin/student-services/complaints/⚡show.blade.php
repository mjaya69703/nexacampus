<?php

use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentComplaint;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\StudentService\StudentComplaintService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public StudentComplaint $complaint;
    public string $replyMessage = '';
    public array $replyAttachments = [];
    public ?int $assignedWorkUnitId = null;
    public ?int $assignedUserId = null;
    public string $userSearch = '';
    public string $adminNotes = '';
    public array $editorButtons = ['bold', 'italic', 'underline', '|', 'ul', 'ol', '|', 'link', '|', 'undo', 'redo'];

    public function mount(int $id): void
    {
        $this->complaint = StudentComplaint::with($this->relations())->findOrFail($id);
        $this->authorizeScope();
        $this->assignedWorkUnitId = $this->complaint->assigned_work_unit_id;
        $this->assignedUserId = $this->complaint->assigned_user_id;
    }

    public function refreshComplaint(): void
    {
        $this->reload();
    }

    public function assign(StudentComplaintService $service): void
    {
        abort_unless(ActivePermission::check('student-complaint.assign') || ActivePermission::check('student-complaint.update'), 403);
        $this->complaint = $service->assign($this->complaint, $this->assignedWorkUnitId, $this->assignedUserId, $this->adminNotes ?: null, auth()->id());
        session()->flash('success', 'Assignment pengaduan diperbarui.');
        $this->reload();
    }

    public function claimTicket(StudentComplaintService $service): void
    {
        abort_unless(ActivePermission::check('student-complaint.assign') || ActivePermission::check('student-complaint.update'), 403);

        $this->assignedUserId = auth()->id();
        $this->complaint = $service->assign($this->complaint, $this->assignedWorkUnitId, auth()->id(), 'Tiket diambil oleh staff.', auth()->id());
        session()->flash('success', 'Tiket berhasil diambil.');
        $this->reload();
    }

    public function sendReply(StudentComplaintService $service): void
    {
        abort_unless(ActivePermission::check('student-complaint.respond') || ActivePermission::check('student-complaint.update'), 403);
        $this->validate([
            'replyMessage' => ['required', 'string', 'max:5000'],
            'replyAttachments' => ['nullable', 'array', 'max:6'],
            'replyAttachments.*' => ['file', 'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ]);

        try {
            $service->addMessage($this->complaint, auth()->id(), 'admin', $this->replyMessage, $this->replyAttachments);
            $this->replyMessage = '';
            $this->replyAttachments = [];
            $this->dispatch('update-jodit-content', ['admin-complaint-reply-'.$this->complaint->id, '']);
            session()->flash('success', 'Balasan dikirim.');
        } catch (Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function setStatus(string $status, StudentComplaintService $service): void
    {
        abort_unless(ActivePermission::check('student-complaint.resolve') || ActivePermission::check('student-complaint.update'), 403);
        $this->complaint = $service->setStatus($this->complaint, $status, $this->adminNotes ?: null, auth()->id());
        session()->flash('success', 'Status pengaduan diperbarui.');
        $this->reload();
    }

    public function removeReplyAttachment(int $index): void
    {
        unset($this->replyAttachments[$index]);
        $this->replyAttachments = array_values($this->replyAttachments);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Layanan Mahasiswa', 'pages' => 'Detail Pengaduan']);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Masuk',
            'in_review' => 'Direview',
            'waiting_student' => 'Menunggu Mahasiswa',
            'responded' => 'Sudah Dibalas',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
            'rejected' => 'Ditolak',
            'reopened' => 'Dibuka Lagi',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'resolved', 'closed' => 'bg-green-lt text-green',
            'waiting_student' => 'bg-yellow-lt text-yellow',
            'rejected' => 'bg-red-lt text-red',
            'responded' => 'bg-blue-lt text-blue',
            'in_review', 'reopened' => 'bg-indigo-lt text-indigo',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Rendah',
            'high' => 'Tinggi',
            'urgent' => 'Urgent',
            default => 'Normal',
        };
    }

    public function slaLabel(): string
    {
        if (! $this->complaint->due_at) {
            return '-';
        }

        if (in_array($this->complaint->status, ['resolved', 'closed', 'rejected'], true)) {
            return 'Selesai';
        }

        return $this->complaint->due_at->isPast()
            ? 'Lewat SLA '.$this->complaint->due_at->diffForHumans()
            : 'Sisa '.$this->complaint->due_at->diffForHumans(null, true);
    }

    public function attachmentUrl($attachment): string
    {
        return route('admin.student-services.complaint-attachments.download', ['attachment' => $attachment->id]);
    }

    public function getWorkUnitsProperty()
    {
        return WorkUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function getAssignableUsersProperty()
    {
        $search = trim($this->userSearch);

        return User::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'email']);
    }

    private function reload(): void
    {
        $this->complaint->refresh()->load($this->relations());
    }

    private function relations(): array
    {
        return ['studentProfile.user', 'studentProfile.studyProgram', 'category', 'assignedWorkUnit', 'assignedUser', 'messages.user', 'messages.attachments', 'histories.changedBy'];
    }

    private function authorizeScope(): void
    {
        abort_unless(ActivePermission::check('student-complaint.view'), 403);
        if (in_array(session('active_role'), ['superuser', 'admin'], true)) {
            return;
        }
        $unitIds = auth()->user()?->activeWorkUnits()->pluck('work_units.id')->all() ?? [];
        abort_unless($this->complaint->assigned_user_id === auth()->id() || in_array($this->complaint->assigned_work_unit_id, $unitIds, true), 403);
    }
};
?>

@include('components.student.student-services.service-styles')

<div class="w-full" style="width: 100% !important" wire:poll.5s="refreshComplaint">
    <x-alert />

    <x-admin.student-services.header
        title="{{ $complaint->subject }}"
        description="Tiket #{{ $complaint->ticket_number }} • Mahasiswa: {{ $complaint->studentProfile?->user?->name ?? '-' }} ({{ $complaint->studentProfile?->nim ?? '-' }})"
        icon="headset"
    >
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 px-3 py-2 rounded-pill shadow-sm">
                <i class="fa fa-rotate me-1"></i> Auto Refresh 5s
            </span>
            <a href="{{ route('admin.student-services.complaints.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Tiket</div>
                        <div class="fw-bold">{{ $this->statusLabel($complaint->status) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-tag fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Kategori</div>
                        <div class="fw-bold">{{ $complaint->category?->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-building fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Unit Tujuan</div>
                        <div class="fw-bold">{{ $complaint->assignedWorkUnit?->name ?? 'Belum diarahkan' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-triangle-exclamation fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Prioritas & SLA</div>
                        <div class="fw-bold">{{ $this->priorityLabel($complaint->priority) }} • {{ $this->slaLabel() }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                @foreach ([
                    ['label' => 'Mahasiswa Pengadu', 'value' => $complaint->studentProfile?->user?->name ?? '-', 'icon' => 'fa-user-graduate', 'color' => 'primary'],
                    ['label' => 'Prioritas Tiket', 'value' => $this->priorityLabel($complaint->priority), 'icon' => 'fa-triangle-exclamation', 'color' => 'warning'],
                    ['label' => 'Target Resolusi (SLA)', 'value' => $this->slaLabel(), 'icon' => 'fa-clock', 'color' => $complaint->due_at && $complaint->due_at->isPast() && ! in_array($complaint->status, ['resolved','closed','rejected'], true) ? 'danger' : 'success'],
                    ['label' => 'Pesan Terakhir', 'value' => $complaint->last_message_at?->format('d M Y H:i') ?? '-', 'icon' => 'fa-comments', 'color' => 'info'],
                ] as $stat)
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                            <div class="text-muted small mb-1">{{ $stat['label'] }}</div>
                            <div class="d-flex align-items-end justify-content-between">
                                <div class="fs-6 fw-bold text-dark lh-1">{{ $stat['value'] }}</div>
                                <i class="fa {{ $stat['icon'] }} fs-4 text-{{ $stat['color'] }} opacity-75"></i>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card service-card mb-4">
                <div class="card-header service-section-header">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-3">
                            <span class="metric-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-comments"></i></span>
                            <div>
                                <h3 class="card-title mb-0" style="font-weight:800;">Percakapan Tiket</h3>
                                <small class="text-muted">Auto refresh setiap 5 detik, jadi balasan mahasiswa akan masuk tanpa buka ulang halaman.</small>
                            </div>
                        </div>
                        <span class="badge {{ $this->statusClass($complaint->status) }} px-3 py-2">{{ $this->statusLabel($complaint->status) }}</span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="ticket-thread">
                        @foreach ($complaint->messages as $message)
                            <div class="ticket-message {{ $message->sender_type === 'admin' ? 'mine ms-lg-5' : 'staff me-lg-5' }}">
                                <div class="d-flex justify-content-between gap-3 mb-2 flex-wrap">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="timeline-dot"><i class="fas {{ $message->sender_type === 'admin' ? 'fa-user-tie' : 'fa-user-graduate' }}"></i></span>
                                        <div>
                                            <div class="fw-bold">{{ $message->user?->name ?? ucfirst($message->sender_type) }}</div>
                                            <small class="text-muted">{{ $message->sender_type === 'admin' ? 'Staff Kampus' : 'Mahasiswa' }}</small>
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ $message->created_at->format('d M Y H:i') }}</small>
                                </div>
                                <div class="ticket-message-body">{!! $message->message !!}</div>
                                @if ($message->attachments->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        @foreach ($message->attachments as $attachment)
                                            <a href="{{ $this->attachmentUrl($attachment) }}" target="_blank" class="attachment-chip">
                                                <i class="fas fa-paperclip"></i> {{ $attachment->file_name }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if (! in_array($complaint->status, ['closed', 'rejected'], true))
                <div class="card service-card">
                    <div class="card-header service-section-header">
                        <h3 class="card-title mb-0" style="font-weight:800;">Balas Mahasiswa</h3>
                    </div>
                    <div class="card-body p-4">
                        <livewire:jodit-text-editor wire:model.live="replyMessage" identifier="admin-complaint-reply-{{ $complaint->id }}" :buttons="$editorButtons" :height="260" />
                        @error('replyMessage') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <div class="upload-dropzone mt-3">
                            <input type="file" class="form-control" wire:model="replyAttachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                            <div class="form-hint mt-2">Opsional. Maksimal 6 file, masing-masing 5MB.</div>
                            <div wire:loading wire:target="replyAttachments" class="mt-3">
                                <div class="small text-primary mb-1"><i class="fas fa-spinner fa-spin me-1"></i>Mengunggah lampiran...</div>
                                <div class="upload-progress"><span style="width:100%;"></span></div>
                            </div>
                            @if ($replyAttachments)
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    @foreach ($replyAttachments as $index => $file)
                                        <span class="attachment-chip">
                                            <i class="fas fa-paperclip"></i>{{ $file->getClientOriginalName() }}
                                            <button type="button" class="btn btn-sm p-0 ms-1 text-danger" wire:click="removeReplyAttachment({{ $index }})">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @error('replyAttachments') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        @error('replyAttachments.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <button class="action-btn mt-3" style="background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white;" wire:click="sendReply" wire:loading.attr="disabled" wire:target="sendReply,replyAttachments">
                            <i class="fas fa-paper-plane"></i> Kirim Balasan
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="service-shell">
                <div class="card service-card">
                    <div class="card-header service-section-header">
                        <h3 class="card-title mb-0" style="font-weight:800;">Assignment & Status</h3>
                    </div>
                    <div class="card-body p-4 d-grid gap-3">
                        <div class="support-panel">
                            <div class="small text-muted">NIM / Program Studi</div>
                            <div class="fw-bold">{{ $complaint->studentProfile?->nim ?? '-' }}</div>
                            <div class="small text-muted">{{ $complaint->studentProfile?->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <label class="form-label">Unit Kerja</label>
                            <select class="form-select" wire:model.defer="assignedWorkUnitId">
                                <option value="">Pilih unit</option>
                                @foreach ($this->workUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Assign Staff</label>
                            <input type="search" class="form-control mb-2" wire:model.live.debounce.400ms="userSearch" placeholder="Cari nama/email/username/kode">
                            <select class="form-select" wire:model.defer="assignedUserId">
                                <option value="">Belum assign staff</option>
                                @foreach ($this->assignableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Catatan Staff</label>
                            <textarea class="form-control" rows="3" wire:model.defer="adminNotes" placeholder="Catatan status/assignment"></textarea>
                        </div>
                        <button class="btn btn-outline-primary" wire:click="assign"><i class="fas fa-user-check me-1"></i>Simpan Assignment</button>
                        @if ($complaint->assigned_user_id !== auth()->id())
                            <button class="btn btn-primary" wire:click="claimTicket"><i class="fas fa-hand-pointer me-1"></i>Ambil Tiket Ini</button>
                        @endif
                        <div class="d-grid gap-2">
                            <button class="btn btn-warning" wire:click="setStatus('waiting_student')">Tunggu Mahasiswa</button>
                            <button class="btn btn-success" wire:click="setStatus('resolved')">Tandai Selesai</button>
                            <button class="btn btn-secondary" wire:click="setStatus('closed')">Tutup Tiket</button>
                            <button class="btn btn-danger" wire:click="setStatus('rejected')">Tolak</button>
                        </div>
                    </div>
                </div>

                <div class="card service-card">
                    <div class="card-header service-section-header">
                        <h3 class="card-title mb-0" style="font-weight:800;">Riwayat Status</h3>
                    </div>
                    <div class="card-body p-4">
                        @foreach ($complaint->histories as $history)
                            <div class="status-step">
                                <span class="timeline-dot"><i class="fas fa-check"></i></span>
                                <div>
                                    <div class="fw-semibold">{{ $this->statusLabel($history->from_status ?: '-') }} -> {{ $this->statusLabel($history->to_status) }}</div>
                                    <small class="text-muted">{{ $history->created_at->format('d M Y H:i') }} oleh {{ $history->changedBy?->name ?? '-' }}</small>
                                    @if ($history->notes)<div class="small mt-1">{{ $history->notes }}</div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
