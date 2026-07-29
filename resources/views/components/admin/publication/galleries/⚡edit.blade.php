<?php

use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\GalleryImage;
use App\Models\Publication\PublicationCategory;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public GalleryAlbum $album;
    public string $title = '';
    public string $slug = '';
    public string $description = '';
    public $cover_image = null;
    public ?int $category_id = null;
    public bool $is_published = false;
    public bool $autoSlug = true;

    /** @var array<int, array{id: int, image_path: string, caption: string, sort_order: int}> */
    public array $existingImages = [];

    /** @var array */
    public $newImages = [];

    public function mount(int $id): void
    {
        $this->album = GalleryAlbum::with('images')->findOrFail($id);
        $this->title = $this->album->title;
        $this->slug = $this->album->slug;
        $this->description = $this->album->description ?? '';
        $this->category_id = $this->album->category_id;
        $this->is_published = (bool) $this->album->is_published;

        $this->loadExistingImages();
    }

    public function updatedTitle(): void
    {
        if ($this->autoSlug && $this->title) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function updatedSlug(): void
    {
        $this->autoSlug = false;
    }

    public function updatedNewImages(): void
    {
        $this->validate([
            'newImages.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [], [
            'newImages.*' => 'Foto',
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:gallery_albums,slug,'.$this->album->id,
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'category_id' => 'nullable|exists:publication_categories,id',
            'is_published' => 'boolean',
        ], [], [
            'title' => 'Judul Album',
            'slug' => 'Slug',
            'description' => 'Deskripsi',
            'cover_image' => 'Sampul Album',
            'category_id' => 'Kategori',
            'is_published' => 'Status Publikasi',
        ]);

        $updateData = [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'is_published' => $this->is_published,
            'updated_by' => auth()->id(),
        ];

        if ($this->cover_image) {
            // Delete old cover if exists
            if ($this->album->cover_image_path && \Storage::disk('public')->exists($this->album->cover_image_path)) {
                \Storage::disk('public')->delete($this->album->cover_image_path);
            }
            $updateData['cover_image_path'] = $this->cover_image->store('galleries', 'public');
        }

        $this->album->update($updateData);

        // Save newly uploaded images
        $this->saveNewImages();

        // Reload
        $this->album = GalleryAlbum::with('images')->findOrFail($this->album->id);
        $this->loadExistingImages();

        session()->flash('success', 'Album galeri berhasil diperbarui!');
    }

    public function saveNewImages(): void
    {
        if (empty($this->newImages)) {
            return;
        }

        $maxOrder = GalleryImage::where('gallery_album_id', $this->album->id)->max('sort_order') ?? 0;

        foreach ($this->newImages as $index => $image) {
            if (is_string($image)) {
                continue;
            }

            $path = $image->store('galleries', 'public');

            GalleryImage::create([
                'gallery_album_id' => $this->album->id,
                'image_path' => $path,
                'sort_order' => $maxOrder + $index + 1,
                'created_by' => auth()->id(),
            ]);
        }

        $this->newImages = [];
    }

    public function updateImageCaption(int $imageId, string $caption): void
    {
        $image = GalleryImage::find($imageId);
        if ($image && $image->gallery_album_id === $this->album->id) {
            $image->update(['caption' => $caption]);
        }
    }

    public function removeImage(int $imageId): void
    {
        $image = GalleryImage::find($imageId);

        if ($image && $image->gallery_album_id === $this->album->id) {
            // Delete file from storage
            if ($image->image_path && \Storage::disk('public')->exists($image->image_path)) {
                \Storage::disk('public')->delete($image->image_path);
            }

            $image->delete();

            $this->album = GalleryAlbum::with('images')->findOrFail($this->album->id);
            $this->loadExistingImages();

            $this->js('
                Swal.fire({
                    title: "Dihapus",
                    text: "Foto berhasil dihapus!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    private function loadExistingImages(): void
    {
        $this->existingImages = $this->album->images
            ->sortBy('sort_order')
            ->values()
            ->map(fn (GalleryImage $img) => [
                'id' => $img->id,
                'image_path' => $img->image_path,
                'caption' => $img->caption ?? '',
                'sort_order' => $img->sort_order,
            ])
            ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Edit Album',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Edit Album #{{ $album->id }}"
        description="Perbarui informasi album dan kelola foto-foto di dalamnya."
        icon="image"
    >
        <a href="{{ route('admin.publication.galleries.index') }}" class="btn btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i>
            <span>Kembali ke daftar</span>
        </a>
    </x-admin.publication.header>

    {{-- Album Info --}}
    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-pencil fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi Album</h4>
                            <div class="text-muted small">Perbarui data album galeri.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Judul Album <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="Masukkan judul album..." wire:model.defer="title">
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="auto-slug" wire:model="slug">
                        <div class="text-muted small mt-1">Slug akan terisi otomatis berdasarkan judul.</div>
                        @error('slug')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea class="form-control rounded-3" rows="4" placeholder="Deskripsi album (opsional)..." wire:model.defer="description"></textarea>
                        @error('description')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Sampul Album</label>
                        <div class="border rounded-4 p-4 text-center bg-light bg-opacity-50" style="cursor: pointer;" x-on:click="$refs.coverInput.click()">
                            @if ($cover_image && !is_string($cover_image))
                                <div class="mb-2">
                                    <img src="{{ $cover_image->temporaryUrl() }}" alt="Preview" style="max-height: 200px; border-radius: 8px; object-fit: contain;">
                                </div>
                                <div class="text-success small">
                                    <i class="fa fa-check-circle"></i> File baru siap diunggah
                                </div>
                            @elseif ($album->cover_image_path)
                                <div class="mb-2">
                                    <img src="{{ \Storage::disk('public')->url($album->cover_image_path) }}" alt="Cover" style="max-height: 200px; border-radius: 8px; object-fit: contain;">
                                </div>
                                <div class="text-muted small">
                                    <i class="fa fa-image"></i> Sampul saat ini. Klik untuk mengganti.
                                </div>
                            @else
                                <div class="text-muted py-3">
                                    <i class="fa fa-cloud-upload-alt fs-1 d-block mb-2"></i>
                                    <span>Klik untuk memilih gambar sampul</span>
                                    <div class="small mt-1">Format: JPG, JPEG, PNG, WEBP. Maks: 2MB</div>
                                </div>
                            @endif
                            <input type="file" accept="image/jpg,image/jpeg,image/png,image/webp" class="d-none" wire:model="cover_image" x-ref="coverInput">
                        </div>
                        <div class="text-muted small mt-1">Kosongkan jika tidak ingin mengganti sampul.</div>
                        @error('cover_image')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-layer-group fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Kategori</h4>
                            <div class="text-muted small">Pilih kategori album.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Kategori</label>
                        <select class="form-select rounded-3" wire:model.defer="category_id">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach(\App\Models\Publication\PublicationCategory::orderBy('name')->get() as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-sliders fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Pengaturan Penayangan</h4>
                            <div class="text-muted small">Atur status publikasi album.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="isPublished" wire:model="is_published">
                            <label class="form-check-label fw-semibold" for="isPublished">
                                <i class="fa fa-check-circle me-1 text-success"></i>Publikasikan Album
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save" wire:loading.attr="disabled">
                    <i class="fa fa-save"></i>
                    <span wire:loading.remove wire:target="save">Simpan Album</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
                <a href="{{ route('admin.publication.galleries.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Photo Management --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mt-4">
        <div class="card-header bg-white border-bottom p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="fa fa-photo-film fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-1 text-dark">Foto-foto dalam Album</h4>
                    <div class="text-muted small">Kelola foto-foto dalam album ini. Klik area upload untuk menambah foto baru.</div>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-success rounded-pill px-3 py-2">{{ count($existingImages) }} Foto</span>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            {{-- Upload Area --}}
            <div class="border border-dashed rounded-4 p-5 text-center bg-light bg-opacity-50 mb-4" style="cursor: pointer; border-style: dashed !important;" x-on:click="$refs.imageInput.click()" wire:loading.class="opacity-50">
                <div>
                    <i class="fa fa-cloud-upload-alt fs-1 text-muted d-block mb-2"></i>
                    <p class="text-muted mb-1 fw-semibold">Klik untuk memilih foto atau seret file ke sini</p>
                    <p class="text-muted small mb-0">Format: JPG, JPEG, PNG, WEBP. Maks: 2MB per file. Bisa pilih banyak sekaligus.</p>
                </div>
                <input type="file" accept="image/jpg,image/jpeg,image/png,image/webp" class="d-none" wire:model="newImages" multiple x-ref="imageInput">
            </div>

            @error('newImages.*')
                <div class="text-danger small mt-1 mb-3">{{ $message }}</div>
            @enderror

            {{-- New images preview --}}
            @if ($newImages && count($newImages) > 0)
                <div class="mb-4">
                    <h6 class="fw-bold mb-3">Foto Baru ({{ count($newImages) }})</h6>
                    <div class="row g-3">
                        @foreach ($newImages as $index => $img)
                            @if (!is_string($img))
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="border rounded-3 overflow-hidden position-relative">
                                        <img src="{{ $img->temporaryUrl() }}" alt="Preview" style="width:100%;height:180px;object-fit:cover;">
                                        <div class="p-2 bg-white border-top">
                                            <small class="text-muted d-block text-truncate">{{ $img->getClientOriginalName() }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <p class="text-info small"><i class="fa fa-info-circle"></i> Foto baru akan tersimpan setelah tombol "Simpan Album" diklik.</p>
                    </div>
                </div>
            @endif

            {{-- Existing Images Grid --}}
            @if (count($existingImages) > 0)
                <div>
                    <h6 class="fw-bold mb-3">Foto Saat Ini</h6>
                    <div class="row g-3">
                        @foreach ($existingImages as $img)
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="border rounded-3 overflow-hidden position-relative">
                                    <img src="{{ \Storage::disk('public')->url($img['image_path']) }}" alt="{{ $img['caption'] ?: 'Foto' }}" style="width:100%;height:180px;object-fit:cover;">
                                    <button type="button" class="btn btn-danger position-absolute top-0 end-0 m-1 rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:10px;padding:0;" wire:click="removeImage({{ $img['id'] }})" wire:confirm="Hapus foto ini?">
                                        <i class="fa fa-times"></i>
                                    </button>
                                    <div class="p-2 bg-white border-top">
                                        <input type="text" class="form-control form-control-sm" placeholder="Caption..." value="{{ $img['caption'] }}"
                                            wire:change="updateImageCaption({{ $img['id'] }}, $event.target.value)">
                                        <small class="text-muted d-block mt-1">Urutan: {{ $img['sort_order'] }}</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-image fs-1 d-block mb-2"></i>
                    <p>Belum ada foto dalam album ini. Klik area upload di atas untuk menambah foto.</p>
                </div>
            @endif
        </div>
    </div>
</div>
