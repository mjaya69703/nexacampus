<?php

use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\PublicationCategory;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $slug = '';
    public string $description = '';
    public $cover_image = null;
    public ?int $category_id = null;
    public bool $is_published = false;
    public bool $autoSlug = true;

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

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:gallery_albums,slug',
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

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'is_published' => $this->is_published,
            'created_by' => auth()->id(),
        ];

        if ($this->cover_image) {
            $data['cover_image_path'] = $this->cover_image->store('galleries', 'public');
        }

        GalleryAlbum::create($data);

        session()->flash('success', 'Album galeri berhasil ditambahkan!');
        $this->redirectRoute('admin.publication.galleries.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Tambah Album',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Tambah Album Baru"
        description="Buat album foto baru untuk galeri publikasi kampus."
        icon="image"
    >
        <a href="{{ route('admin.publication.galleries.index') }}" class="btn btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i>
            <span>Kembali ke daftar</span>
        </a>
    </x-admin.publication.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-pen-nib fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Album</h4>
                            <div class="text-muted small">Lengkapi informasi album galeri.</div>
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
                            @if ($cover_image)
                                <div class="mb-2">
                                    <img src="{{ $cover_image->temporaryUrl() }}" alt="Preview" style="max-height: 200px; border-radius: 8px; object-fit: contain;">
                                </div>
                                <div class="text-success small">
                                    <i class="fa fa-check-circle"></i> File siap diunggah
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
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save">
                    <i class="fa fa-save"></i>
                    <span>Simpan Album</span>
                </button>
                <a href="{{ route('admin.publication.galleries.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>
</div>
