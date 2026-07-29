<?php

use App\Models\Publication\Gallery;
use App\Models\Publication\PublicationCategory;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $description = '';
    public $image_path = null;
    public string $image_alt = '';
    public ?int $category_id = null;
    public bool $is_published = false;

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_path' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'image_alt' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:publication_categories,id',
            'is_published' => 'boolean',
        ], [], [
            'title' => 'Judul',
            'description' => 'Deskripsi',
            'image_path' => 'Gambar',
            'image_alt' => 'Alt Text',
            'category_id' => 'Kategori',
            'is_published' => 'Status Publikasi',
        ]);

        $path = $this->image_path->store('galleries', 'public');

        Gallery::create([
            'title' => $this->title,
            'description' => $this->description,
            'image_path' => $path,
            'image_alt' => $this->image_alt,
            'category_id' => $this->category_id,
            'is_published' => $this->is_published,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Gambar galeri berhasil ditambahkan!');
        $this->redirectRoute('admin.publication.galleries.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Tambah Gambar',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Tambah Gambar Baru"
        description="Unggah gambar baru untuk koleksi galeri publikasi kampus."
        icon="image"
    >
        <a href="{{ route('admin.publication.galleries.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Galeri</h4>
                            <div class="text-muted small">Lengkapi informasi gambar galeri.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Judul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="Masukkan judul gambar..." wire:model.defer="title">
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea class="form-control rounded-3" rows="4" placeholder="Deskripsi gambar (opsional)..." wire:model.defer="description"></textarea>
                        @error('description')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Upload Gambar <span class="text-danger">*</span></label>
                        <div class="border rounded-4 p-4 text-center bg-light bg-opacity-50" style="cursor: pointer;" wire:click="$refs.imageInput.click()">
                            @if ($image_path)
                                <div class="mb-2">
                                    <img src="{{ $image_path->temporaryUrl() }}" alt="Preview" style="max-height: 200px; border-radius: 8px; object-fit: contain;">
                                </div>
                                <div class="text-success small">
                                    <i class="fa fa-check-circle"></i> File siap diunggah
                                </div>
                            @else
                                <div class="text-muted py-3">
                                    <i class="fa fa-cloud-upload-alt fs-1 d-block mb-2"></i>
                                    <span>Klik untuk memilih gambar atau seret file ke sini</span>
                                    <div class="small mt-1">Format: JPG, JPEG, PNG, WEBP. Maks: 2MB</div>
                                </div>
                            @endif
                            <input type="file" accept="image/jpg,image/jpeg,image/png,image/webp" class="d-none" wire:model="image_path" x-ref="imageInput">
                        </div>
                        @error('image_path')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Alt Text (SEO)</label>
                        <input type="text" class="form-control rounded-3" placeholder="Teks alternatif untuk gambar..." wire:model.defer="image_alt">
                        <div class="text-muted small mt-1">Teks alternatif untuk aksesibilitas dan SEO.</div>
                        @error('image_alt')
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
                            <div class="text-muted small">Pilih kategori galeri.</div>
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
                            <div class="text-muted small">Atur status publikasi gambar.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="isPublished" wire:model="is_published">
                            <label class="form-check-label fw-semibold" for="isPublished">
                                <i class="fa fa-check-circle me-1 text-success"></i>Publikasikan Gambar
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save">
                    <i class="fa fa-save"></i>
                    <span>Simpan Gambar</span>
                </button>
                <a href="{{ route('admin.publication.galleries.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>
</div>
