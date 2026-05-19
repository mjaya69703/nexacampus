<?php

use App\Models\Publication\Announcement;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new class extends Component
{
    public int $announcementId;
    public array $announcementData = [];

    public function mount(int $id): void
    {
        $this->announcementId = $id;
        $this->loadAnnouncement();
    }

    private function loadAnnouncement(): void
    {
        $user = auth()->user();

        // Lecturer can read announcements targeted to them OR their own
        $announcement = Announcement::queryForLecturer($user)
            ->with('creator')
            ->where('id', $this->announcementId)
            ->first();

        // Fallback: own announcements (even drafts)
        if (! $announcement) {
            $announcement = Announcement::with('creator')
                ->where('id', $this->announcementId)
                ->where('created_by', $user->id)
                ->first();
        }

        if (! $announcement) {
            abort(404, 'Pengumuman tidak ditemukan.');
        }

        // Auto-mark as read
        $announcement->markAsReadBy($user->id);

        $this->announcementData = [
            'id'                  => $announcement->id,
            'title'               => $announcement->title,
            'content'             => $announcement->content,
            'priority'            => $announcement->priority,
            'target_type'         => $announcement->target_type,
            'is_pinned'           => $announcement->is_pinned,
            'is_published'        => $announcement->is_published,
            'published_at'        => $announcement->published_at?->format('d M Y, H:i'),
            'published_at_human'  => $announcement->published_at?->diffForHumans(),
            'creator_name'        => $announcement->creator?->name ?? '-',
            'is_mine'             => $announcement->created_by === $user->id,
            'reads_count'         => $announcement->reads()->count(),
            'attachment_name'     => $announcement->attachment_name,
            'attachment_path'     => $announcement->attachment_path,
            'attachment_type'     => $announcement->attachment_type,
            'attachment_size'     => $announcement->formatted_attachment_size,
        ];
    }

    public function downloadAttachment(): ?BinaryFileResponse
    {
        $announcement = Announcement::find($this->announcementId);

        if (! $announcement || ! $announcement->attachment_path) {
            session()->flash('error', 'Lampiran tidak tersedia.');
            return null;
        }

        if (! Storage::disk('public')->exists($announcement->attachment_path)) {
            session()->flash('error', 'File tidak ditemukan di server.');
            return null;
        }

        return response()->download(
            Storage::disk('public')->path($announcement->attachment_path),
            $announcement->attachment_name ?? 'attachment'
        );
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publikasi',
            'pages' => 'Detail Pengumuman',
        ]);
    }
};
?>

@push('styles')
<style>
    .modern-card { border-radius:20px;border:none;box-shadow:0 4px 20px rgba(0,0,0,0.08);background:white; }
    .hero-gradient { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:20px;color:white;position:relative;overflow:hidden; }
    .priority-badge { display:inline-flex;align-items:center;gap:0.35rem;padding:0.4rem 0.85rem;border-radius:20px;font-size:0.82rem;font-weight:700; }
    .action-btn { display:inline-flex;align-items:center;gap:0.4rem;padding:0.6rem 1.2rem;border-radius:10px;font-size:0.9rem;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none; }
    .action-btn:hover { transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.12); }
    .content-body { line-height:1.8;color:#374151;font-size:1rem; }
    .content-body h1,.content-body h2,.content-body h3 { color:#1e293b;font-weight:700;margin-top:1.5rem; }
    .content-body ul,.content-body ol { padding-left:1.5rem; }
    .content-body table { width:100%;border-collapse:collapse; }
    .content-body table th,.content-body table td { border:1px solid #e2e8f0;padding:0.5rem 0.75rem; }
    .content-body table th { background:#f8fafc;font-weight:700; }
</style>
@endpush

<div>
    <x-alert />

    @php $priority = $announcementData['priority'] ?? null; @endphp

    <div class="hero-gradient p-4 mb-4">
        <div style="position:absolute;top:-40%;right:-10%;width:300px;height:300px;background:rgba(255,255,255,0.08);border-radius:50%;"></div>
        <div style="position:relative;z-index:1;">
            <div class="d-flex align-items-start gap-3">
                <div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;">
                    <i class="{{ $priority?->icon() ?? 'fas fa-bullhorn' }}"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @if($announcementData['is_pinned'] ?? false)
                            <span class="priority-badge" style="background:rgba(255,255,255,0.2);">
                                <i class="fas fa-thumbtack"></i> Pinned
                            </span>
                        @endif
                        @if($priority)
                            <span class="priority-badge" style="background:rgba(255,255,255,0.2);">
                                <i class="{{ $priority->icon() }}"></i> {{ $priority->label() }}
                            </span>
                        @endif
                        <span class="priority-badge" style="background:rgba(255,255,255,0.2);">
                            <i class="fas fa-check-circle"></i> Sudah Dibaca
                        </span>
                        @if($announcementData['is_mine'] ?? false)
                            <span class="priority-badge" style="background:rgba(255,255,255,0.2);">
                                <i class="fas fa-user-edit"></i> Milik Saya · {{ $announcementData['reads_count'] }} dibaca
                            </span>
                        @endif
                    </div>
                    <h2 class="mb-2" style="font-weight:800;">{{ $announcementData['title'] ?? '' }}</h2>
                    <div class="d-flex flex-wrap gap-3" style="font-size:0.85rem;opacity:0.9;">
                        <span><i class="fas fa-user me-1"></i>{{ $announcementData['creator_name'] ?? '-' }}</span>
                        <span><i class="fas fa-clock me-1"></i>{{ $announcementData['published_at'] ?? 'Draft' }} {{ $announcementData['published_at_human'] ? '('.$announcementData['published_at_human'].')' : '' }}</span>
                        <span><i class="fas fa-crosshairs me-1"></i>{{ $announcementData['target_type']?->label() ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="modern-card p-4">
                <h5 class="mb-4" style="font-weight:700;color:#1e293b;border-bottom:2px solid #f1f5f9;padding-bottom:0.75rem;">
                    <i class="fas fa-file-alt me-2" style="color:#667eea;"></i>Isi Pengumuman
                </h5>
                <div class="content-body">
                    {!! $announcementData['content'] ?? '' !!}
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            @if($announcementData['attachment_name'] ?? false)
                <div class="modern-card p-4 mb-4">
                    <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-paperclip me-2" style="color:#667eea;"></i>Lampiran</h5>
                    <div class="d-flex align-items-center gap-3 p-3" style="background:#f5f0ff;border-radius:12px;border:2px solid #c4b5fd;">
                        <i class="fas fa-file-alt fa-2x" style="color:#667eea;"></i>
                        <div class="flex-grow-1">
                            <div class="fw-bold" style="font-size:0.9rem;">{{ $announcementData['attachment_name'] }}</div>
                            <div class="text-muted small">{{ strtoupper($announcementData['attachment_type'] ?? '') }} · {{ $announcementData['attachment_size'] }}</div>
                        </div>
                    </div>
                    <button type="button" class="action-btn w-100 justify-content-center mt-3" wire:click="downloadAttachment" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                        <i class="fas fa-download"></i> Download Lampiran
                    </button>
                </div>
            @endif

            <div class="modern-card p-4">
                <h5 class="mb-3" style="font-weight:700;color:#1e293b;"><i class="fas fa-compass me-2" style="color:#667eea;"></i>Navigasi</h5>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('lecturer.announcements.index') }}" class="action-btn w-100 justify-content-center" style="background:#f1f5f9;color:#64748b;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                    @if($announcementData['is_mine'] ?? false)
                        @activecan('announcement.update')
                            <a href="{{ route('lecturer.announcements.edit', $announcementData['id']) }}" class="action-btn w-100 justify-content-center" style="background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:white;">
                                <i class="fas fa-edit"></i> Edit Pengumuman
                            </a>
                        @endactivecan
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
