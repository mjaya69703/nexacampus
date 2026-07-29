<?php

use App\Models\Publication\PublicationCategory;
use Livewire\Component;

new class extends Component
{
    public int $totalCategories = 0;
    public int $activeCount = 0;
    public int $totalNews = 0;
    public int $totalAgendas = 0;
    public int $totalGalleries = 0;
    public int $totalKonten = 0;

    public function mount(): void
    {
        $this->totalCategories = PublicationCategory::count();
        $this->activeCount = PublicationCategory::where('is_active', true)->count();
        $this->totalNews = \App\Models\Publication\News::count();
        $this->totalAgendas = \App\Models\Publication\Agenda::count();
        $this->totalGalleries = \App\Models\Publication\GalleryAlbum::count();
        $this->totalKonten = $this->totalNews + $this->totalAgendas + $this->totalGalleries;
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Daftar Kategori',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Manajemen Kategori Publikasi"
        description="Kelola kategori untuk berita, agenda, dan galeri. Kategori digunakan untuk mengelompokkan konten publikasi di seluruh modul."
        icon="tags"
    >
        @activecan('publication-category.create')
            <a href="{{ route('admin.publication.categories.create') }}" class="btn  btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i>
                <span>Tambah Kategori</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Kategori</div>
                        <div class="fw-bold">{{ $totalCategories }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-newspaper fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Berita</div>
                        <div class="fw-bold">{{ $totalNews }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Agenda</div>
                        <div class="fw-bold">{{ $totalAgendas }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-images fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Galeri</div>
                        <div class="fw-bold">{{ $totalGalleries }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Aktif</div>
                        <div class="fw-bold">{{ $activeCount }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.publication.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Kategori</h4>
                <div class="text-muted small">Statistik singkat untuk memantau sebaran kategori dan konten publikasi.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter dan toggle untuk mengelola kategori secara cepat.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Kategori</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalCategories }}</div>
                            <i class="fa fa-tags fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ $activeCount }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Berita</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-primary lh-1">{{ $totalNews }}</div>
                            <i class="fa fa-newspaper fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Agenda</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ $totalAgendas }}</div>
                            <i class="fa fa-calendar fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Galeri</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-secondary lh-1">{{ $totalGalleries }}</div>
                            <i class="fa fa-images fs-4 text-secondary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Konten</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-warning lh-1">{{ $totalKonten }}</div>
                            <i class="fa fa-layer-group fs-4 text-warning opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Kategori</h4>
                    <span class="text-muted small">Nama, slug, deskripsi, urutan, dan status penayangan kategori.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:publication.publication-category-table />
        </div>
    </div>
</div>
