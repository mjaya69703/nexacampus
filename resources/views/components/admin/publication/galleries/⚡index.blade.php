<?php

use App\Models\Publication\Gallery;
use Livewire\Component;

new class extends Component
{
    public int $totalGalleries = 0;
    public int $publishedCount = 0;

    public function mount(): void
    {
        $this->totalGalleries = Gallery::count();
        $this->publishedCount = Gallery::where('is_published', true)->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Daftar Galeri',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Manajemen Galeri"
        description="Kelola koleksi gambar galeri untuk publikasi kampus, dapat diatur per kategori dan status penayangan."
        icon="image"
    >
        @activecan('gallery.create')
            <a href="{{ route('admin.publication.galleries.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i>
                <span>Tambah Gambar</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-images fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Gambar</div>
                        <div class="fw-bold">{{ $totalGalleries }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Dipublikasi</div>
                        <div class="fw-bold">{{ $publishedCount }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.publication.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan Galeri</h4>
                <div class="text-muted small">Statistik singkat untuk memantau koleksi gambar galeri.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter dan toggle untuk mengelola galeri secara cepat.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total Gambar</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalGalleries }}</div>
                            <i class="fa fa-images fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-3">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Dipublikasi</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ $publishedCount }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data Galeri</h4>
                    <span class="text-muted small">Judul, gambar, kategori, dan status penayangan galeri.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:publication.gallery-table />
        </div>
    </div>
</div>
