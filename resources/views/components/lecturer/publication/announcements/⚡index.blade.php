<?php

use App\Enums\AnnouncementPriority;
use App\Models\Publication\Announcement;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // 'inbox' = announcements received, 'mine' = announcements I created
    public string $tab = 'inbox';
    public string $search = '';
    public string $filterPriority = '';
    public int $perPage = 10;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterPriority(): void { $this->resetPage(); }
    public function updatedTab(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterPriority = '';
        $this->resetPage();
    }

    public function deleteAnnouncement(int $id): void
    {
        $user = auth()->user();
        $announcement = Announcement::where('id', $id)
            ->where('created_by', $user->id)
            ->first();

        if (! $announcement) {
            session()->flash('error', 'Pengumuman tidak ditemukan atau bukan milik Anda.');
            return;
        }

        if ($announcement->attachment_path && Storage::disk('public')->exists($announcement->attachment_path)) {
            Storage::disk('public')->delete($announcement->attachment_path);
        }

        $announcement->update(['deleted_by' => $user->id]);
        $announcement->delete();

        session()->flash('success', 'Pengumuman berhasil dihapus.');
    }

    public function confirmDelete(int $id): void
    {
        $this->js('
            Swal.fire({
                title: "Hapus pengumuman?",
                text: "Data tidak bisa dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya hapus",
                cancelButtonText: "Batal",
                confirmButtonColor: "#dc3545"
            }).then((result) => {
                if (result.isConfirmed) {
                    $wire.deleteAnnouncement('.$id.')
                }
            });
        ');
    }

    public function render()
    {
        $user = auth()->user();

        // Inbox: announcements targeted to this lecturer (global + lecturer + course offerings)
        $inboxQuery = Announcement::queryForLecturer($user)->with('creator');

        // Mine: announcements created by this lecturer
        $mineQuery = Announcement::query()
            ->with('creator')
            ->where('created_by', $user->id)
            ->orderByDesc('created_at');

        // Apply filters to active tab
        $activeQuery = $this->tab === 'inbox' ? $inboxQuery : $mineQuery;

        if ($this->search) {
            $activeQuery->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('content', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterPriority) {
            $activeQuery->where('priority', $this->filterPriority);
        }

        $announcements = $activeQuery->paginate($this->perPage);

        $inboxCount  = Announcement::queryForLecturer($user)->count();
        $mineCount   = Announcement::query()->where('created_by', $user->id)->count();
        $unreadCount = Announcement::unreadCountForLecturer($user);

        return $this->view()->layout('layouts.app', [
            'menus' => 'Publikasi',
            'pages' => 'Pengumuman',
        ])->with(compact('announcements', 'inboxCount', 'mineCount', 'unreadCount'));
    }
};
?>

@push('styles')
<style>
    .modern-card { border-radius:20px;border:none;box-shadow:0 4px 20px rgba(0,0,0,0.08);background:white; }
    .announcement-item { border-radius:16px;padding:1.25rem;border:2px solid transparent;transition:all 0.3s ease;background:#f8fafc;margin-bottom:0.75rem; }
    .announcement-item:hover { border-color:var(--app-primary);background:#f5f0ff;transform:translateY(-2px);box-shadow:0 8px 24px rgba(102,66,244,0.1); }
    .announcement-item.unread { border-left:4px solid var(--app-primary);background:#f5f0ff; }
    .announcement-item.pinned { border-left:4px solid #f59e0b; }
    .priority-badge { display:inline-flex;align-items:center;gap:0.35rem;padding:0.3rem 0.7rem;border-radius:20px;font-size:0.78rem;font-weight:700; }
    .tab-btn { padding:0.6rem 1.25rem;border-radius:12px;border:2px solid #e2e8f0;background:white;font-weight:600;font-size:0.9rem;cursor:pointer;transition:all 0.2s;color:#64748b; }
    .tab-btn.active { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border-color:transparent; }
    .filter-btn { padding:0.45rem 0.9rem;border-radius:10px;border:2px solid #e2e8f0;background:white;font-weight:600;font-size:0.82rem;cursor:pointer;transition:all 0.2s; }
    .filter-btn.active { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border-color:transparent; }
    .action-btn { display:inline-flex;align-items:center;gap:0.4rem;padding:0.5rem 1rem;border-radius:10px;font-size:0.85rem;font-weight:600;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none; }
    .action-btn:hover { transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.12); }
</style>
@endpush

<div>
    <x-alert />

    {{-- Hero --}}
    <div class="modern-card mb-4" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:2rem;position:relative;overflow:hidden;">
        <div style="position:absolute;top:-40%;right:-10%;width:300px;height:300px;background:rgba(255,255,255,0.08);border-radius:50%;"></div>
        <div style="position:relative;z-index:1;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-bullhorn" style="font-size:1.5rem;"></i>
                        <span style="font-weight:600;font-size:0.9rem;">Publikasi</span>
                    </div>
                    <h1 class="mb-1" style="font-weight:800;font-size:1.8rem;">Pengumuman</h1>
                    <p style="opacity:0.9;margin:0;">Kelola dan pantau pengumuman kelas Anda.</p>
                </div>
                <div class="d-flex gap-3 flex-wrap">
                    @if($unreadCount > 0)
                        <div style="background:rgba(255,255,255,0.2);backdrop-filter:blur(10px);border-radius:16px;padding:1rem 1.5rem;text-align:center;">
                            <div style="font-size:2rem;font-weight:800;">{{ $unreadCount }}</div>
                            <div style="font-size:0.85rem;opacity:0.9;">Belum Dibaca</div>
                        </div>
                    @endif
                    @activecan('announcement.create')
                        <a href="{{ route('lecturer.announcements.create') }}" class="action-btn" style="background:white;color:#667eea;align-self:center;">
                            <i class="fas fa-plus"></i> Buat Pengumuman
                        </a>
                    @endactivecan
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="modern-card p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex gap-2">
                <button type="button" class="tab-btn {{ $tab === 'inbox' ? 'active' : '' }}" wire:click="$set('tab', 'inbox')">
                    <i class="fas fa-inbox me-2"></i>Diterima
                    <span class="badge ms-1" style="background:{{ $tab === 'inbox' ? 'rgba(255,255,255,0.3)' : '#e2e8f0' }};color:{{ $tab === 'inbox' ? 'white' : '#64748b' }};">{{ $inboxCount }}</span>
                </button>
                <button type="button" class="tab-btn {{ $tab === 'mine' ? 'active' : '' }}" wire:click="$set('tab', 'mine')">
                    <i class="fas fa-paper-plane me-2"></i>Dibuat Saya
                    <span class="badge ms-1" style="background:{{ $tab === 'mine' ? 'rgba(255,255,255,0.3)' : '#e2e8f0' }};color:{{ $tab === 'mine' ? 'white' : '#64748b' }};">{{ $mineCount }}</span>
                </button>
            </div>

            {{-- Filters --}}
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Cari pengumuman..." style="border-radius:10px;border:2px solid #e2e8f0;width:200px;">
                <div class="d-flex gap-1">
                    <button type="button" class="filter-btn {{ $filterPriority === '' ? 'active' : '' }}" wire:click="$set('filterPriority', '')">Semua</button>
                    @foreach(\App\Enums\AnnouncementPriority::cases() as $p)
                        <button type="button" class="filter-btn {{ $filterPriority === $p->value ? 'active' : '' }}" wire:click="$set('filterPriority', '{{ $p->value }}')">{{ $p->label() }}</button>
                    @endforeach
                </div>
                <select class="form-select" wire:model.live="perPage" style="border-radius:10px;border:2px solid #e2e8f0;width:80px;">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="modern-card p-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="mb-0" style="font-weight:700;color:#1e293b;">
                <i class="fas fa-{{ $tab === 'inbox' ? 'inbox' : 'paper-plane' }} me-2" style="color:#667eea;"></i>
                {{ $tab === 'inbox' ? 'Pengumuman Diterima' : 'Pengumuman Saya' }}
            </h4>
            <span class="badge" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:0.5rem 1rem;border-radius:20px;">
                {{ $announcements->total() }} Pengumuman
            </span>
        </div>

        @forelse($announcements as $announcement)
            @php
                $isRead = $announcement->isReadBy(auth()->id());
                $priority = $announcement->priority;
                $isMine = $announcement->created_by === auth()->id();
            @endphp
            <div class="announcement-item {{ !$isRead && $tab === 'inbox' ? 'unread' : '' }} {{ $announcement->is_pinned ? 'pinned' : '' }}">
                <div class="d-flex align-items-start gap-3">
                    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="{{ $priority->icon() }}" style="color:white;font-size:1.2rem;"></i>
                    </div>

                    <div class="flex-grow-1">
                        <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    @if($announcement->is_pinned)
                                        <span class="priority-badge" style="background:#fef3c7;color:#d97706;">
                                            <i class="fas fa-thumbtack"></i> Pinned
                                        </span>
                                    @endif
                                    <span class="priority-badge {{ $priority->badgeClass() }}">
                                        <i class="{{ $priority->icon() }}"></i> {{ $priority->label() }}
                                    </span>
                                    @if(!$announcement->is_published)
                                        <span class="priority-badge" style="background:#f1f5f9;color:#64748b;">
                                            <i class="fas fa-eye-slash"></i> Draft
                                        </span>
                                    @endif
                                    @if(!$isRead && $tab === 'inbox')
                                        <span class="priority-badge" style="background:#eef2ff;color:#6366f1;">
                                            <i class="fas fa-circle" style="font-size:0.5rem;"></i> Baru
                                        </span>
                                    @endif
                                </div>
                                <h5 class="mb-1" style="font-weight:700;color:#1e293b;">{{ $announcement->title }}</h5>
                                <div class="text-muted" style="font-size:0.9rem;">
                                    {!! Str::limit(strip_tags($announcement->content), 160) !!}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
                            <div class="d-flex flex-wrap gap-3" style="font-size:0.82rem;color:#64748b;">
                                <span><i class="fas fa-user me-1"></i>{{ $announcement->creator?->name ?? '-' }}</span>
                                <span><i class="fas fa-clock me-1"></i>
                                    {{ $announcement->published_at?->diffForHumans() ?? 'Belum dipublikasi' }}
                                </span>
                                <span><i class="fas fa-crosshairs me-1"></i>{{ $announcement->target_type->label() }}</span>
                                @if($announcement->attachment_name)
                                    <span><i class="fas fa-paperclip me-1"></i>{{ $announcement->attachment_name }}</span>
                                @endif
                                @if($tab === 'mine')
                                    <span><i class="fas fa-eye me-1"></i>{{ $announcement->reads()->count() }} dibaca</span>
                                @endif
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                {{-- Inbox: show read button --}}
                                @if($tab === 'inbox')
                                    <a href="{{ route('lecturer.announcements.show', $announcement->id) }}" class="action-btn" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                        <i class="fas fa-eye"></i> Baca
                                    </a>
                                @endif

                                {{-- Mine: show edit/delete --}}
                                @if($tab === 'mine' || $isMine)
                                    @activecan('announcement.update')
                                        <a href="{{ route('lecturer.announcements.edit', $announcement->id) }}" class="action-btn" style="background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:white;">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    @endactivecan
                                    @activecan('announcement.delete')
                                        <button type="button" class="action-btn" wire:click="confirmDelete({{ $announcement->id }})" style="background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);color:white;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endactivecan
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <i class="fas fa-{{ $tab === 'inbox' ? 'inbox' : 'paper-plane' }}" style="font-size:4rem;color:#cbd5e1;"></i>
                <div class="mt-3" style="font-size:1.1rem;color:#64748b;font-weight:500;">
                    @if($tab === 'inbox')
                        Belum ada pengumuman yang ditujukan untukmu.
                    @else
                        Belum ada pengumuman yang kamu buat.
                    @endif
                </div>
                @if($tab === 'mine')
                    @activecan('announcement.create')
                        <a href="{{ route('lecturer.announcements.create') }}" class="action-btn mt-3" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;display:inline-flex;">
                            <i class="fas fa-plus"></i> Buat Pengumuman Pertama
                        </a>
                    @endactivecan
                @endif
            </div>
        @endforelse

        @if($announcements->hasPages())
            <div class="mt-4">{{ $announcements->links() }}</div>
        @endif
    </div>
</div>
