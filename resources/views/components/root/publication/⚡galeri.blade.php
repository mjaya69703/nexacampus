<?php

use Livewire\Component;
use App\Models\Publication\GalleryAlbum;

new class extends Component
{
    public array $albums = [];
    public ?int $selectedAlbumId = null;
    public array $selectedImages = [];
    public int $selectedImageIndex = 0;

    public function mount(): void
    {
        $albums = GalleryAlbum::published()
            ->with(['images', 'category'])
            ->latest()
            ->get();

        $this->albums = $albums->map(fn ($album) => [
            'id' => $album->id,
            'title' => $album->title,
            'description' => $album->description,
            'cover' => $album->cover_image_path
                ? \Storage::disk('public')->url($album->cover_image_path)
                : ($album->images->first()
                    ? \Storage::disk('public')->url($album->images->first()->image_path)
                    : null),
            'image_count' => $album->images->count(),
            'category' => $album->category?->name ?? 'Umum',
        ])->toArray();
    }

    public function selectAlbum(int $albumId): void
    {
        $album = GalleryAlbum::with('images')->find($albumId);

        if (! $album) {
            return;
        }

        $this->selectedAlbumId = $albumId;
        $this->selectedImages = $album->images->map(fn ($image) => [
            'path' => \Storage::disk('public')->url($image->image_path),
            'caption' => $image->caption,
        ])->toArray();
        $this->selectedImageIndex = 0;
    }

    public function closeAlbum(): void
    {
        $this->selectedAlbumId = null;
        $this->selectedImages = [];
    }

    public function nextImage(): void
    {
        if ($this->selectedImageIndex < count($this->selectedImages) - 1) {
            $this->selectedImageIndex++;
        }
    }

    public function prevImage(): void
    {
        if ($this->selectedImageIndex > 0) {
            $this->selectedImageIndex--;
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => 'Galeri Kampus',
        ]);
    }
};
?>

@include('components.root.publication.partials.editorial-styles')

<div class="admission-public pub-shell">
    <div class="pub-wrap">
        {{-- Hero Banner --}}
        <section class="pub-hero mb-5">
            <div>
                <div class="pub-kicker">
                    <span>Dokumentasi Visual</span>
                </div>
                <h1 class="pub-title">
                    Galeri & Momen Kampus <span class="text-accent">Terpadu</span>
                </h1>
                <p class="pub-lede">
                    Koleksi album foto kegiatan civitas akademika, fasilitas modern, upacara wisuda, dan momen berkesan di lingkungan NexaCampus.
                </p>
            </div>
            <aside class="pub-hero-panel">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="pub-hero-panel__label">Koleksi Album Terbit</div>
                    <span class="badge bg-success bg-opacity-20 text-white rounded-pill px-2.5 py-1 small fw-bold"><i class="fas fa-camera me-1"></i> Dokumentasi</span>
                </div>
                <div class="pub-hero-panel__number">{{ count($albums) }}</div>
                <p class="pub-hero-panel__note">Klik salah satu album di bawah untuk menampilkan seluruh foto dalam mode tayangan layar penuh.</p>
            </aside>
        </section>

        {{-- Album Gallery Grid --}}
        @if(count($albums) > 0)
            <div class="pub-gallery-grid">
                @foreach($albums as $album)
                    <button type="button" class="pub-gallery-card text-start border-0 p-0 shadow-sm" wire:click="selectAlbum({{ $album['id'] }})">
                        <div class="pub-gallery-card__media">
                            @if($album['cover'])
                                <img src="{{ $album['cover'] }}" alt="{{ $album['title'] }}" loading="lazy">
                            @else
                                <div class="pub-gallery-placeholder">
                                    <i class="fas fa-images fa-3x opacity-50"></i>
                                </div>
                            @endif
                        </div>
                        <div class="pub-gallery-card__body">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <span class="pub-badge bg-primary bg-opacity-90 text-white border-0">{{ $album['category'] }}</span>
                                <span class="badge bg-black bg-opacity-60 text-white rounded-pill px-3 py-1.5 backdrop-blur fw-bold" style="font-size: .72rem;">
                                    <i class="fas fa-images me-1"></i>{{ $album['image_count'] }} Foto
                                </span>
                            </div>
                            <h2>{{ $album['title'] }}</h2>
                            @if($album['description'])
                                <p class="pub-line-clamp-2">{{ \Illuminate\Support\Str::limit($album['description'], 130) }}</p>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @else
            <div class="pub-empty text-center py-5 my-4">
                <div style="width:72px;height:72px;border-radius:22px;background:rgba(59,130,246,.1);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#3b82f6;">
                    <i class="fas fa-camera-retro"></i>
                </div>
                <h3 class="h4 mb-2 fw-bolder text-body">Belum Ada Album Foto</h3>
                <p class="pub-muted mb-0" style="max-width: 450px; margin: 0 auto; font-size: .9rem;">
                    Album dokumentasi foto kampus akan langsung tampil di halaman ini segera setelah dipublikasikan oleh humas institusi.
                </p>
            </div>
        @endif
    </div>

    {{-- Glassmorphic Lightbox Modal --}}
    @if($selectedAlbumId && count($selectedImages) > 0)
        <div class="pub-lightbox" role="dialog" aria-modal="true" aria-label="Galeri Foto Kampus">
            {{-- Close Button --}}
            <button type="button" class="pub-lightbox-button pub-lightbox-button--close" wire:click="closeAlbum()" aria-label="Tutup galeri" title="Tutup (Esc)">
                <i class="fas fa-xmark fs-5"></i>
            </button>

            {{-- Previous Button --}}
            @if($selectedImageIndex > 0)
                <button type="button" class="pub-lightbox-button pub-lightbox-button--prev" wire:click="prevImage()" aria-label="Foto Sebelumnya" title="Foto Sebelumnya">
                    <i class="fas fa-chevron-left fs-5"></i>
                </button>
            @endif

            {{-- Next Button --}}
            @if($selectedImageIndex < count($selectedImages) - 1)
                <button type="button" class="pub-lightbox-button pub-lightbox-button--next" wire:click="nextImage()" aria-label="Foto Berikutnya" title="Foto Berikutnya">
                    <i class="fas fa-chevron-right fs-5"></i>
                </button>
            @endif

            <div class="pub-lightbox__frame">
                <div class="pub-lightbox__image">
                    @if($selectedImages[$selectedImageIndex]['path'])
                        <img src="{{ $selectedImages[$selectedImageIndex]['path'] }}" alt="Foto galeri {{ $selectedImageIndex + 1 }}">
                    @else
                        <div class="pub-gallery-placeholder rounded-4 p-5 text-white">
                            <i class="fas fa-image fa-3x mb-2"></i>
                            <p>Gambar tidak dapat dimuat</p>
                        </div>
                    @endif
                </div>
                <div class="pub-lightbox-caption">
                    <div class="d-inline-block bg-white bg-opacity-15 text-white px-3 py-1 rounded-pill small fw-bold mb-2 backdrop-blur">
                        <i class="fas fa-camera me-1"></i> Foto {{ $selectedImageIndex + 1 }} dari {{ count($selectedImages) }}
                    </div>
                    @if($selectedImages[$selectedImageIndex]['caption'])
                        <p class="mb-0 text-white-50 lead" style="font-size: .95rem; line-height: 1.5;">{{ $selectedImages[$selectedImageIndex]['caption'] }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
