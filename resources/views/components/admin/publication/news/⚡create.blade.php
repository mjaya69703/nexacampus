<?php

use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $slug = '';
    public ?string $categoryId = null;
    public string $excerpt = '';
    public string $content = '';
    public $featuredImage = null;
    public bool $isPublished = false;
    public ?string $publishedAt = null;
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
            'slug' => 'required|string|max:255|unique:news,slug',
            'categoryId' => 'nullable|exists:publication_categories,id',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featuredImage' => 'nullable|image|max:2048',
            'isPublished' => 'boolean',
            'publishedAt' => 'nullable|date',
        ], [], [
            'title' => 'Judul Berita',
            'slug' => 'Slug',
            'categoryId' => 'Kategori',
            'excerpt' => 'Ringkasan',
            'content' => 'Konten',
            'featuredImage' => 'Gambar Utama',
            'isPublished' => 'Status Publikasi',
            'publishedAt' => 'Tanggal Publikasi',
        ]);

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->categoryId,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished
                ? ($this->publishedAt ?: now())
                : null,
            'created_by' => auth()->id(),
        ];

        if ($this->featuredImage) {
            $data['featured_image'] = $this->featuredImage->store('news', 'public');
        }

        News::create($data);

        session()->flash('success', 'Berita berhasil ditambahkan!');
        $this->redirectRoute('admin.publication.news.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Buat Berita',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Buat Berita Baru"
        description="Buat berita baru dengan judul, konten, kategori, dan pengaturan publikasi."
        icon="newspaper"
    >
        <a href="{{ route('admin.publication.news.index') }}" class="btn  btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Berita</h4>
                            <div class="text-muted small">Lengkapi judul, konten, dan informasi berita secara lengkap.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Judul Berita <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="Masukkan judul berita..." wire:model.defer="title">
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="auto-generated-slug" wire:model.defer="slug">
                        <div class="text-muted small mt-1">Slug akan otomatis dibuat dari judul. Edit jika diperlukan.</div>
                        @error('slug')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Ringkasan (Excerpt)</label>
                        <textarea class="form-control rounded-3" rows="3" placeholder="Tuliskan ringkasan singkat berita..." wire:model.defer="excerpt"></textarea>
                        @error('excerpt')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Konten <span class="text-danger">*</span></label>
                        <livewire:jodit-text-editor wire:model.live="content" identifier="news-create-content" :height="400" />
                        @error('content')
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
                            <div class="text-muted small">Pilih kategori berita.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Kategori Berita</label>
                        <select class="form-select rounded-3" wire:model="categoryId">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach(PublicationCategory::orderBy('name')->get() as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-image fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Gambar Utama</h4>
                            <div class="text-muted small">Upload gambar featured berita.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Featured Image</label>
                        <input type="file" class="form-control rounded-3" wire:model="featuredImage" accept="image/*">
                        <div class="text-muted small mt-1">Maksimal 2MB. Format: JPG, PNG, WebP.</div>
                        @error('featuredImage')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror

                        @if($featuredImage)
                            <div class="mt-3 position-relative">
                                <img src="{{ $featuredImage->temporaryUrl() }}" class="img-fluid rounded-3 border" alt="Preview">
                                <button type="button" class="btn  btn-danger position-absolute top-0 end-0 m-2 rounded-circle" wire:click="$set('featuredImage', null)">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        @endif
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Pengaturan Publikasi</h4>
                            <div class="text-muted small">Atur status publikasi dan jadwal tayang.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="isPublished" wire:model="isPublished">
                                <label class="form-check-label fw-semibold" for="isPublished">
                                    <i class="fa fa-check-circle me-1 text-success"></i>Publikasikan Sekarang
                                </label>
                            </div>
                        </div>
                    </div>

                    @if($isPublished)
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Tanggal Publikasi</label>
                            <input type="datetime-local" class="form-control rounded-3" wire:model.defer="publishedAt">
                            <div class="text-muted small mt-1">Kosongi untuk menggunakan waktu sekarang.</div>
                            @error('publishedAt')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save">
                    <i class="fa fa-save"></i>
                    <span>Simpan Berita</span>
                </button>
                <a href="{{ route('admin.publication.news.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>
</div>
