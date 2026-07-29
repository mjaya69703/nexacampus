<?php

use App\Models\Publication\PublicationCategory;
use Livewire\Component;

new class extends Component
{
    public PublicationCategory $category;

    public string $name = '';
    public string $slug = '';
    public string $desc = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    public function mount(int $id): void
    {
        $this->category = PublicationCategory::findOrFail($id);
        $this->name = $this->category->name;
        $this->slug = $this->category->slug;
        $this->desc = $this->category->desc ?? '';
        $this->sortOrder = $this->category->sort_order;
        $this->isActive = (bool) $this->category->is_active;
    }

    public function updatedName(): void
    {
        // Only auto-generate slug if it hasn't been manually changed or matches the old name's slug
        $oldSlug = \Illuminate\Support\Str::slug($this->category->name);
        if ($this->slug === $oldSlug) {
            $this->slug = \Illuminate\Support\Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:publication_categories,slug,'.$this->category->id,
            'desc' => 'nullable|string|max:500',
            'sortOrder' => 'required|integer|min:0',
            'isActive' => 'boolean',
        ], [], [
            'name' => 'Nama Kategori',
            'slug' => 'Slug',
            'desc' => 'Deskripsi',
            'sortOrder' => 'Urutan',
            'isActive' => 'Status Aktif',
        ]);

        $this->category->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'desc' => $this->desc,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Kategori berhasil diperbarui!');
        $this->redirectRoute('admin.publication.categories.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Edit Kategori',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Edit Kategori #{{ $category->id }}"
        description="Perbarui nama, slug, deskripsi, urutan penayangan, dan status aktif kategori publikasi."
        icon="tags"
    >
        <a href="{{ route('admin.publication.categories.index') }}" class="btn  btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
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
                            <i class="fa fa-pencil fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Edit Kategori</h4>
                            <div class="text-muted small">Perbarui data kategori publikasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="Contoh: Berita Kampus, Pengumuman Akademik" wire:model.live="name">
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="auto-generated" wire:model.defer="slug">
                        <div class="text-muted small mt-1">Slug akan digenerate otomatis dari nama. Dapat diedit manual.</div>
                        @error('slug')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea class="form-control rounded-3" rows="3" placeholder="Deskripsi singkat tentang kategori ini..." wire:model.defer="desc"></textarea>
                        @error('desc')
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
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-sliders fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Pengaturan Penayangan</h4>
                            <div class="text-muted small">Atur urutan prioritas dan status aktif.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Urutan Tampil (Sort Order)</label>
                        <input type="number" class="form-control rounded-3" wire:model.defer="sortOrder" min="0">
                        <div class="text-muted small mt-1">Urutan terkecil (0, 1, 2) tampil lebih atas.</div>
                        @error('sortOrder')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label fw-semibold" for="isActive">
                                <i class="fa fa-check-circle me-1 text-success"></i>Aktifkan Kategori (Publik)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save">
                    <i class="fa fa-save"></i>
                    <span>Perbarui Kategori</span>
                </button>
                <a href="{{ route('admin.publication.categories.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>
</div>
