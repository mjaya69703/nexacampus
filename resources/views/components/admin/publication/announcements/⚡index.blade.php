<?php

use App\Models\Publication\Announcement;
use App\Models\Publication\AnnouncementRead;
use Livewire\Component;

new class extends Component
{
    public int $totalAnnouncements = 0;

    public int $publishedCount = 0;

    public int $draftCount = 0;

    public int $pinnedCount = 0;

    public int $urgentCount = 0;

    public int $totalReads = 0;

    public function mount(): void
    {
        $this->totalAnnouncements = Announcement::count();
        $this->publishedCount = Announcement::where('is_published', true)->count();
        $this->draftCount = Announcement::where('is_published', false)->count();
        $this->pinnedCount = Announcement::where('is_pinned', true)->count();
        $this->urgentCount = Announcement::where('priority', 'urgent')->where('is_published', true)->count();
        $this->totalReads = AnnouncementRead::count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Daftar Pengumuman',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Manajemen Pengumuman"
        description="Kelola seluruh pengumuman kampus, termasuk prioritas, target penerima, status publikasi, dan penayangan agar informasi tersampaikan tepat sasaran."
        icon="bullhorn"
    >
        @activecan('announcement.create')
            <a href="{{ route('admin.publication.announcements.create') }}" class="btn  btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i>
                <span>Buat Pengumuman</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-bullhorn fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total</div>
                        <div class="fw-bold">{{ $totalAnnouncements }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Published</div>
                        <div class="fw-bold">{{ $publishedCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Draft</div>
                        <div class="fw-bold">{{ $draftCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-thumbtack fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pinned</div>
                        <div class="fw-bold">{{ $pinnedCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-bell fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Urgent</div>
                        <div class="fw-bold">{{ $urgentCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-eye fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Pembaca</div>
                        <div class="fw-bold">{{ $totalReads }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.publication.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Pengumuman</h4>
                <div class="text-muted small">Statistik singkat untuk memantau jangkauan dan status pengumuman.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter dan toggle untuk mengelola pengumuman secara cepat.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalAnnouncements }}</div>
                            <i class="fa fa-bullhorn fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Published</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ $publishedCount }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Draft</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-secondary lh-1">{{ $draftCount }}</div>
                            <i class="fa fa-clock fs-4 text-secondary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Pinned</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ $pinnedCount }}</div>
                            <i class="fa fa-thumbtack fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Urgent</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-danger lh-1">{{ $urgentCount }}</div>
                            <i class="fa fa-bell fs-4 text-danger opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Pembaca</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalReads }}</div>
                            <i class="fa fa-eye fs-4 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Pengumuman</h4>
                    <span class="text-muted small">Judul, target, prioritas, status, dan aksi pengelolaan pengumuman.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:publication.announcement-table />
        </div>
    </div>
</div>
