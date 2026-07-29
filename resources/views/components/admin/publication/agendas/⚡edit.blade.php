<?php

use App\Models\Publication\Agenda;
use App\Models\Publication\PublicationCategory;
use Livewire\Component;

new class extends Component
{
    public Agenda $agenda;

    public string $title = '';

    public string $slug = '';

    public ?int $categoryId = null;

    public string $description = '';

    public string $location = '';

    public string $eventDate = '';

    public string $eventTime = '';

    public string $eventEndDate = '';

    public bool $isPublished = false;

    public string $publishedAt = '';

    public function mount(int $id): void
    {
        $this->agenda = Agenda::findOrFail($id);

        $this->title = $this->agenda->title;
        $this->slug = $this->agenda->slug;
        $this->categoryId = $this->agenda->category_id;
        $this->description = $this->agenda->description ?? '';
        $this->location = $this->agenda->location ?? '';
        $this->eventDate = $this->agenda->event_date->format('Y-m-d');
        $this->eventTime = $this->agenda->event_time ? $this->agenda->event_time->format('H:i') : '';
        $this->eventEndDate = $this->agenda->event_end_date?->format('Y-m-d') ?? '';
        $this->isPublished = (bool) $this->agenda->is_published;
        $this->publishedAt = $this->agenda->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:agendas,slug,'.$this->agenda->id,
            'categoryId' => 'nullable|exists:publication_categories,id',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'eventDate' => 'required|date',
            'eventTime' => 'nullable|date_format:H:i',
            'eventEndDate' => 'nullable|date|after_or_equal:eventDate',
            'isPublished' => 'boolean',
            'publishedAt' => 'nullable|date',
        ], [], [
            'title' => 'Judul Agenda',
            'slug' => 'Slug',
            'categoryId' => 'Kategori',
            'description' => 'Deskripsi',
            'location' => 'Lokasi',
            'eventDate' => 'Tanggal Acara',
            'eventTime' => 'Jam Acara',
            'eventEndDate' => 'Tanggal Akhir Acara',
            'isPublished' => 'Status Publikasi',
            'publishedAt' => 'Tanggal Publikasi',
        ]);

        $this->agenda->update([
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->categoryId,
            'description' => $this->description,
            'location' => $this->location,
            'event_date' => $this->eventDate,
            'event_time' => $this->eventTime ? $this->eventTime.':00' : null,
            'event_end_date' => $this->eventEndDate ?: null,
            'is_published' => $this->isPublished,
            'published_at' => $this->isPublished ? ($this->publishedAt ?: now()) : null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Agenda berhasil diperbarui!');
        $this->redirectRoute('admin.publication.agendas.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Edit Agenda',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Edit Agenda #{{ $agenda->id }}"
        description="Perbarui judul, jadwal, lokasi, kategori, dan pengaturan publikasi agenda yang sudah ada."
        icon="calendar-days"
    >
        <a href="{{ route('admin.publication.agendas.index') }}" class="btn  btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
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
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Edit Agenda</h4>
                            <div class="text-muted small">Perbarui data agenda dengan lengkap dan konsisten.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Judul Agenda <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="Masukkan judul agenda..." wire:model.live="title">
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Slug</label>
                        <input type="text" class="form-control rounded-3 bg-light" placeholder="Auto-generated" wire:model="slug">
                        @error('slug')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea class="form-control rounded-3" rows="4" placeholder="Deskripsi singkat agenda atau acara..." wire:model.defer="description"></textarea>
                        @error('description')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Acara <span class="text-danger">*</span></label>
                            <input type="date" class="form-control rounded-3" wire:model.defer="eventDate">
                            @error('eventDate')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jam Acara</label>
                            <input type="time" class="form-control rounded-3" wire:model.defer="eventTime">
                            @error('eventTime')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Akhir (Opsional)</label>
                            <input type="date" class="form-control rounded-3" wire:model.defer="eventEndDate">
                            @error('eventEndDate')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lokasi</label>
                            <input type="text" class="form-control rounded-3" placeholder="Contoh: Aula Kampus, Ruang 201" wire:model.defer="location">
                            @error('location')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
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
                            <div class="text-muted small">Pilih kategori agenda.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Kategori Agenda</label>
                        <select class="form-select rounded-3" wire:model.defer="categoryId">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach(\App\Models\Publication\PublicationCategory::orderBy('name')->get() as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
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
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-paper-plane fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Publikasi</h4>
                            <div class="text-muted small">Atur status dan jadwal publikasi.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="border rounded-4 p-3 bg-light bg-opacity-50 mb-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="isPublished" wire:model.live="isPublished">
                            <label class="form-check-label fw-semibold" for="isPublished">Publikasikan</label>
                        </div>
                    </div>

                    @if($isPublished)
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Tanggal Publikasi</label>
                            <input type="datetime-local" class="form-control rounded-3" wire:model.defer="publishedAt">
                        </div>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save">
                    <i class="fa fa-save"></i>
                    <span>Perbarui Agenda</span>
                </button>
                <a href="{{ route('admin.publication.agendas.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>
</div>
