<?php

use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public array $form = [];
    public $poster;
    public array $eventTypes = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.events.index');
    }

    public function mount(): void
    {
        $this->eventTypes = EventType::options();
        $this->form = [
            'title' => '',
            'description' => '',
            'event_type' => '',
            'event_date' => '',
            'end_date' => '',
            'location' => '',
            'is_online' => false,
            'meeting_url' => '',
            'max_participants' => '',
            'registration_deadline' => '',
            'is_published' => false,
        ];
    }

    public function createAlumniEvent(): void
    {
        $validated = $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.event_type' => 'required|string',
            'form.event_date' => 'required|date',
            'form.end_date' => 'nullable|date|after_or_equal:form.event_date',
            'form.location' => 'nullable|string|max:255',
            'form.is_online' => 'boolean',
            'form.meeting_url' => 'nullable|url|max:500',
            'form.max_participants' => 'nullable|integer|min:1',
            'form.registration_deadline' => 'nullable|date',
            'form.is_published' => 'boolean',
            'poster' => 'nullable|image|max:2048',
        ]);

        $posterPath = null;
        if ($this->poster) {
            $posterPath = $this->poster->store('alumni-events/posters', 'public');
        }

        AlumniEvent::create(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'poster_path' => $posterPath,
                'created_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Event alumni berhasil ditambahkan.');
        $this->redirectRoute('admin.alumni.events.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Tambah Event Alumni',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Buat Agenda & Event Baru"
        description="Jadwalkan kegiatan seminar, temu kangen, workshop karir, atau reuni untuk mempererat jaringan alumni."
        icon="calendar-plus"
    >
        <a href="{{ route('admin.alumni.events.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-calendar-alt fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Jadwal & Pelaksanaan Event</h4>
                            <div class="text-muted small">Atur jadwal pelaksanaan dan kuota maksimal pendaftaran peserta kegiatan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-info-circle me-2 text-primary"></i>Informasi Umum Kegiatan</h6>
                        </div>
                        <div class="col-lg-8 col-md-6">
                            <label for="title" class="form-label fw-semibold">Judul Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" id="title" class="form-control rounded-3" placeholder="Contoh: Webinar Peluang Karir Era AI & Digital" wire:model.defer="form.title">
                            @error('form.title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="event_type" class="form-label fw-semibold">Tipe Kegiatan <span class="text-danger">*</span></label>
                            <select id="event_type" class="form-control rounded-3" wire:model.defer="form.event_type">
                                <option value="">-- Pilih Tipe --</option>
                                @foreach ($eventTypes as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('form.event_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="poster" class="form-label fw-semibold">Poster / Banner Kegiatan (Maks. 2MB)</label>
                            <input type="file" id="poster" class="form-control rounded-3" wire:model="poster" accept="image/*">
                            <div wire:loading wire:target="poster" class="text-muted small mt-1"><i class="fa fa-spinner fa-spin me-1"></i> Mengunggah poster...</div>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="max_participants" class="form-label fw-semibold">Batas Kuota Peserta (Maksimal)</label>
                            <input type="number" id="max_participants" class="form-control rounded-3" placeholder="Contoh: 150 (Kosongkan jika tanpa batas)" wire:model.defer="form.max_participants">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Deskripsi & Agenda Acara</label>
                            <textarea id="description" class="form-control rounded-3" rows="3" wire:model.defer="form.description" placeholder="Jelaskan pembicara, susunan acara, dan manfaat yang didapatkan peserta..."></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-clock me-2 text-info"></i>Waktu & Pendaftaran</h6>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="event_date" class="form-label fw-semibold">Waktu Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" id="event_date" class="form-control rounded-3" wire:model.defer="form.event_date">
                            @error('form.event_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="end_date" class="form-label fw-semibold">Waktu Selesai</label>
                            <input type="datetime-local" id="end_date" class="form-control rounded-3" wire:model.defer="form.end_date">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="registration_deadline" class="form-label fw-semibold">Batas Pendaftaran</label>
                            <input type="datetime-local" id="registration_deadline" class="form-control rounded-3" wire:model.defer="form.registration_deadline">
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="fa fa-map-marked-alt me-2 text-success"></i>Lokasi & Pelaksanaan</h6>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="location" class="form-label fw-semibold">Tempat / Gedung / Kota</label>
                            <input type="text" id="location" class="form-control rounded-3" placeholder="Contoh: Auditorium Utama Kampus / Zoom Virtual" wire:model.defer="form.location">
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <label for="meeting_url" class="form-label fw-semibold">Tautan Meeting Virtual (URL)</label>
                            <input type="url" id="meeting_url" class="form-control rounded-3" placeholder="https://zoom.us/j/xxxxxxxxx" wire:model.defer="form.meeting_url">
                        </div>
                        <div class="col-12">
                            <div class="border rounded-4 p-3 bg-light bg-opacity-50 d-flex flex-wrap gap-4">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_online" wire:model.defer="form.is_online">
                                    <label class="form-check-label fw-semibold" for="is_online"><i class="fa fa-laptop me-1 text-info"></i> Dilaksanakan Online (Daring)</label>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_published" wire:model.defer="form.is_published">
                                    <label class="form-check-label fw-semibold" for="is_published"><i class="fa fa-globe me-1 text-success"></i> Langsung Publikasi (Published)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="createAlumniEvent">
                            <i class="fa fa-save me-2"></i> Simpan Event Alumni
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Kegiatan</h5>
                            <div class="text-muted small">Kelancaran pendaftaran dimulai dari kejelasan jadwal acara.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2.5">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Aktifkan opsi <strong>Online (Daring)</strong> apabila kegiatan diselenggarakan melalui platform konferensi video.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Batas pendaftaran (registration deadline) otomatis menghentikan pendaftaran saat waktu berakhir.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Status Publikasi</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Draft / Disembunyikan</div>
                    <p class="text-muted small mb-0">Aktifkan toggle 'Publikasi' di atas agar jadwal event langsung dapat dilihat oleh alumni di portal karir.</p>
                </div>
            </div>
        </div>
    </div>
</div>
