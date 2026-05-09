<?php

use App\Enums\AnnouncementPriority;
use App\Models\Publication\Announcement;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterPriority = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public int $perPage = 10;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterPriority(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterPriority = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $query = Announcement::queryForStudent($user)->with('creator');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('content', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterDateFrom) {
            $query->whereDate('published_at', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->whereDate('published_at', '<=', $this->filterDateTo);
        }

        $announcements = $query->paginate($this->perPage);

        $unreadCount = Announcement::unreadCountForStudent($user);

        return $this->view()->layout('layouts.app', [
            'menus' => 'Publikasi',
            'pages' => 'Pengumuman',
        ])->with(compact('announcements', 'unreadCount'));
    }
};
?>

@push('styles')
<style>
    .modern-card { border-radius:20px;border:none;box-shadow:0 4px 20px rgba(0,0,0,0.08);background:white; }
    .announcement-item { border-radius:16px;padding:1.25rem;border:2px solid transparent;transition:all 0.3s ease;background:#f8fafc;margin-bottom:0.75rem; }
    .announcement-item:hover { border-color:#667eea;background:#f5f0ff;transform:translateY(-2px);box-shadow:0 8px 24px rgba(102,66,244,0.1); }
    .announcement-item.unread { border-left:4px solid #667eea;background:#f5f0ff; }
    .announcement-item.pinned { border-left:4px solid #f59e0b; }
    .priority-badge { display:inline-flex;align-items:center;gap:0.35rem;padding:0.3rem 0.7rem;border-radius:20px;font-size:0.78rem;font-weight:700; }
    .filter-btn { padding:0.5rem 1rem;border-radius:10px;border:2px solid #e2e8f0;background:white;font-weight:600;font-size:0.85rem;cursor:pointer;transition:all 0.2s; }
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
                    <p style="opacity:0.9;margin:0;">Semua pengumuman yang ditujukan untukmu.</p>
                </div>
                @if($unreadCount > 0)
                    <div style="background:rgba(255,255,255,0.2);backdrop-filter:blur(10px);border-radius:16px;padding:1rem 1.5rem;text-align:center;">
                        <div style="font-size:2rem;font-weight:800;">{{ $unreadCount }}</div>
                        <div style="font-size:0.85rem;opacity:0.9;">Belum Dibaca</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="modern-card p-4 mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold"><i class="fas fa-search me-2" style="color:#667eea;"></i>Cari</label>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Cari judul atau isi..." style="border-radius:12px;border:2px solid #e2e8f0;">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold"><i class="fas fa-flag me-2" style="color:#667eea;"></i>Prioritas</label>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="filter-btn {{ $filterPriority === '' ? 'active' : '' }}" wire:click="$set('filterPriority', '')">Semua</button>
                    @foreach(\App\Enums\AnnouncementPriority::cases() as $p)
                        <button type="button" class="filter-btn {{ $filterPriority === $p->value ? 'active' : '' }}" wire:click="$set('filterPriority', '{{ $p->value }}')">{{ $p->label() }}</button>
                    @endforeach
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold"><i class="fas fa-calendar me-2" style="color:#667eea;"></i>Rentang Tanggal</label>
                <div class="d-flex gap-2">
                    <input type="date" class="form-control" wire:model.live="filterDateFrom" style="border-radius:10px;border:2px solid #e2e8f0;">
                    <input type="date" class="form-control" wire:model.live="filterDateTo" style="border-radius:10px;border:2px solid #e2e8f0;">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold"><i class="fas fa-list me-2" style="color:#667eea;"></i>Per Halaman</label>
                <select class="form-select" wire:model.live="perPage" style="border-radius:10px;border:2px solid #e2e8f0;">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        @if($search || $filterPriority || $filterDateFrom || $filterDateTo)
            <div class="mt-3 pt-3" style="border-top:2px solid #e2e8f0;">
                <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters" style="border-radius:10px;font-weight:600;">
                    <i class="fas fa-times me-2"></i>Reset Filter
                </button>
            </div>
        @endif
    </div>

    {{-- List --}}
    <div class="modern-card p-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="mb-0" style="font-weight:700;color:#1e293b;"><i class="fas fa-list me-2" style="color:#667eea;"></i>Daftar Pengumuman</h4>
            <span class="badge" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:0.5rem 1rem;border-radius:20px;">
                {{ $announcements->total() }} Pengumuman
            </span>
        </div>

        @forelse($announcements as $announcement)
            @php
                $isRead = $announcement->isReadBy(auth()->id());
                $priority = $announcement->priority;
            @endphp
            <div class="announcement-item {{ !$isRead ? 'unread' : '' }} {{ $announcement->is_pinned ? 'pinned' : '' }}">
                <div class="d-flex align-items-start gap-3">
                    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="{{ $priority->icon() }}" style="color:white;font-size:1.2rem;"></i>
                    </div>

                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            @if($announcement->is_pinned)
                                <span class="priority-badge" style="background:#fef3c7;color:#d97706;">
                                    <i class="fas fa-thumbtack"></i> Pinned
                                </span>
                            @endif
                            <span class="priority-badge {{ $priority->badgeClass() }}">
                                <i class="{{ $priority->icon() }}"></i> {{ $priority->label() }}
                            </span>
                            @if(!$isRead)
                                <span class="priority-badge" style="background:#eef2ff;color:#6366f1;">
                                    <i class="fas fa-circle" style="font-size:0.5rem;"></i> Baru
                                </span>
                            @endif
                        </div>
                        <h5 class="mb-1" style="font-weight:700;color:#1e293b;">{{ $announcement->title }}</h5>
                        <div class="text-muted mb-3" style="font-size:0.9rem;">
                            {!! Str::limit(strip_tags($announcement->content), 180) !!}
                        </div>

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex flex-wrap gap-3" style="font-size:0.82rem;color:#64748b;">
                                <span><i class="fas fa-user me-1"></i>{{ $announcement->creator?->name ?? '-' }}</span>
                                <span><i class="fas fa-clock me-1"></i>{{ $announcement->published_at?->diffForHumans() }}</span>
                                <span><i class="fas fa-crosshairs me-1"></i>{{ $announcement->target_type->label() }}</span>
                                @if($announcement->attachment_name)
                                    <span><i class="fas fa-paperclip me-1"></i>{{ $announcement->attachment_name }}</span>
                                @endif
                            </div>
                            <a href="{{ route('student.announcements.show', $announcement->id) }}" class="action-btn" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                <i class="fas fa-eye"></i> Baca
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <i class="fas fa-inbox" style="font-size:4rem;color:#cbd5e1;"></i>
                <div class="mt-3" style="font-size:1.1rem;color:#64748b;font-weight:500;">
                    @if($search || $filterPriority || $filterDateFrom || $filterDateTo)
                        Tidak ada pengumuman yang sesuai filter.
                    @else
                        Belum ada pengumuman untukmu.
                    @endif
                </div>
            </div>
        @endforelse

        @if($announcements->hasPages())
            <div class="mt-4">{{ $announcements->links() }}</div>
        @endif
    </div>
</div>
