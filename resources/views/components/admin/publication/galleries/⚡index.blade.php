<?php

use App\Models\Publication\GalleryAlbum;
use Livewire\Component;

new class extends Component
{
    public int $totalAlbums = 0;
    public int $publishedCount = 0;
    public int $totalImages = 0;

    public function mount(): void
    {
        $this->totalAlbums = GalleryAlbum::count();
        $this->publishedCount = GalleryAlbum::where('is_published', true)->count();
        $this->totalImages = \App\Models\Publication\GalleryImage::count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Publication', 'pages' => 'Album Galeri']);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header title="Manajemen Album Galeri" description="Kelola album foto untuk galeri publikasi kampus." icon="image">
        @activecan('gallery.create')
            <a href="{{ route('admin.publication.galleries.create') }}" class="btn btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Album</span>
            </a>
        @endactivecan
        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-image fs-6"></i>
                    <div><div class="small text-white text-opacity-75">Total Album</div><div class="fw-bold">{{ $totalAlbums }}</div></div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div><div class="small text-white text-opacity-75">Dipublikasi</div><div class="fw-bold">{{ $publishedCount }}</div></div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-photo-film fs-6"></i>
                    <div><div class="small text-white text-opacity-75">Total Foto</div><div class="fw-bold">{{ $totalImages }}</div></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.publication.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <livewire:publication.gallery-album-table />
        </div>
    </div>
</div>
