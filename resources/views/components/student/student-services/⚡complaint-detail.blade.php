<?php

use App\Models\StudentService\StudentComplaint;
use App\Support\StudentService\StudentComplaintService;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public StudentComplaint $complaint;
    public string $replyMessage = '';
    public array $replyAttachments = [];
    public array $editorButtons = ['bold', 'italic', 'underline', '|', 'ul', 'ol', '|', 'link', '|', 'undo', 'redo'];

    public function mount(int $id): void
    {
        $profile = auth()->user()?->studentProfile;
        $this->complaint = StudentComplaint::with($this->relations())->where('student_profile_id', $profile?->id)->findOrFail($id);
    }

    public function refreshComplaint(): void
    {
        $this->reload();
    }

    public function sendReply(StudentComplaintService $service): void
    {
        $this->validate([
            'replyMessage' => ['required', 'string', 'max:5000'],
            'replyAttachments' => ['nullable', 'array', 'max:6'],
            'replyAttachments.*' => ['file', 'extensions:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ]);

        try {
            $service->addMessage($this->complaint, auth()->id(), 'student', $this->replyMessage, $this->replyAttachments);
            $this->replyMessage = '';
            $this->replyAttachments = [];
            $this->dispatch('update-jodit-content', ['student-complaint-reply-'.$this->complaint->id, '']);
            session()->flash('success', 'Balasan berhasil dikirim.');
        } catch (Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function closeTicket(StudentComplaintService $service): void
    {
        $this->complaint = $service->setStatus($this->complaint, 'closed', 'Ditutup oleh mahasiswa.', auth()->id());
        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Student Services', 'pages' => 'Detail Pengaduan']);
    }

    public function attachmentUrl($attachment): string
    {
        return route('student.student-services.complaint-attachments.download', ['attachment' => $attachment->id]);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Masuk',
            'in_review' => 'Direview',
            'waiting_student' => 'Menunggu Kamu',
            'responded' => 'Dibalas',
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

    public function removeReplyAttachment(int $index): void
    {
        unset($this->replyAttachments[$index]);
        $this->replyAttachments = array_values($this->replyAttachments);
    }

    private function reload(): void
    {
        $this->complaint->refresh()->load($this->relations());
    }

    private function relations(): array
    {
        return ['category', 'assignedWorkUnit', 'messages.user', 'messages.attachments', 'histories.changedBy'];
    }
};
?>

@include('components.student.student-services.service-styles')

<div wire:poll.5s="refreshComplaint">
    <x-alert />

    <div class="card service-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position: relative;">
            <div class="d-flex justify-content-between align-items-start gap-4 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,.2); border-radius: 18px; display:flex; align-items:center; justify-content:center; font-size:2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-ticket"></i>
                    </div>
                    <div>
                        <div style="font-size:.9rem; opacity:.88;">Pengaduan {{ $complaint->ticket_number }}</div>
                        <h1 class="h2 mb-2" style="font-weight:800;">{{ $complaint->subject }}</h1>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="info-badge"><i class="fas fa-circle-info me-2"></i>{{ $this->statusLabel($complaint->status) }}</span>
                            <span class="info-badge"><i class="fas fa-tag me-2"></i>{{ $complaint->category?->name ?? '-' }}</span>
                            <span class="info-badge"><i class="fas fa-building me-2"></i>{{ $complaint->assignedWorkUnit?->name ?? 'Belum diarahkan' }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.student-services.complaints') }}" class="action-btn" style="background: rgba(255,255,255,.94); color:#4f46e5;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Status', 'value' => $this->statusLabel($complaint->status), 'icon' => 'fa-circle-check', 'color' => '#4f46e5'],
            ['label' => 'Prioritas', 'value' => $this->priorityLabel($complaint->priority), 'icon' => 'fa-triangle-exclamation', 'color' => '#f59e0b'],
            ['label' => 'SLA', 'value' => $complaint->due_at?->format('d M Y H:i') ?? '-', 'icon' => 'fa-clock', 'color' => $complaint->due_at && $complaint->due_at->isPast() && ! in_array($complaint->status, ['resolved','closed','rejected'], true) ? '#ef4444' : '#10b981'],
        ] as $stat)
            <div class="col-md-4">
                <div class="ticket-stat d-flex align-items-center gap-3">
                    <span class="metric-icon" style="background: {{ $stat['color'] }}22; color: {{ $stat['color'] }};"><i class="fas {{ $stat['icon'] }}"></i></span>
                    <div>
                        <div class="text-muted small">{{ $stat['label'] }}</div>
                        <div class="fw-bold" style="color:#111827;">{{ $stat['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card service-card mb-3">
                <div class="card-header service-section-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="metric-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-comments"></i></span>
                        <div>
                            <h3 class="card-title mb-0" style="font-weight:800;">Percakapan</h3>
                            <small class="text-muted">Halaman ini otomatis refresh setiap 5 detik.</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="ticket-thread">
                    @foreach ($complaint->messages as $message)
                        <div class="ticket-message {{ $message->sender_type === 'student' ? 'mine ms-lg-5' : 'staff me-lg-5' }}">
                            <div class="d-flex justify-content-between gap-3 mb-2 flex-wrap">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="timeline-dot"><i class="fas {{ $message->sender_type === 'student' ? 'fa-user' : 'fa-user-tie' }}"></i></span>
                                    <div>
                                        <div class="fw-bold">{{ $message->sender_type === 'student' ? 'Kamu' : ($message->user?->name ?? 'Staff') }}</div>
                                        <small class="text-muted">{{ $message->sender_type === 'student' ? 'Mahasiswa' : 'Layanan Kampus' }}</small>
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
                        <h3 class="card-title mb-0" style="font-weight:800;">Balas Pengaduan</h3>
                    </div>
                    <div class="card-body p-4">
                        <livewire:jodit-text-editor wire:model.live="replyMessage" identifier="student-complaint-reply-{{ $complaint->id }}" :buttons="$editorButtons" :height="240" />
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
                        <div class="d-flex gap-2 flex-wrap mt-3">
                            <button class="action-btn" style="background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white;" wire:click="sendReply" wire:loading.attr="disabled" wire:target="sendReply,replyAttachments">
                                <i class="fas fa-paper-plane"></i> Kirim Balasan
                            </button>
                            @if ($complaint->status === 'resolved')
                                <button class="btn btn-success" wire:click="closeTicket">Konfirmasi Tutup</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-4">
            <div class="card service-card">
                <div class="card-header service-section-header">
                    <h3 class="card-title mb-0" style="font-weight:800;">Progress</h3>
                </div>
                <div class="card-body p-4">
                    @foreach ($complaint->histories as $history)
                        <div class="status-step">
                            <span class="timeline-dot"><i class="fas fa-check"></i></span>
                            <div>
                                <div class="fw-semibold">{{ $this->statusLabel($history->to_status) }}</div>
                                <small class="text-muted">{{ $history->created_at->format('d M Y H:i') }}</small>
                                @if ($history->notes)<div class="small mt-1">{{ $history->notes }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
