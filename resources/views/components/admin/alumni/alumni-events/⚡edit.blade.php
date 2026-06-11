<?php

use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $eventId;
    public array $form = [];
    public $poster;
    public ?string $existingPoster = null;

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.events.index');
    }

    public function mount($id): void
    {
        $event = AlumniEvent::findOrFail($id);
        $this->eventId = (int) $id;
        $this->existingPoster = $event->poster_path;

        $this->form = [
            'title' => $event->title,
            'description' => $event->description ?? '',
            'event_type' => $event->event_type,
            'event_date' => $event->event_date?->format('Y-m-d\TH:i') ?? '',
            'end_date' => $event->end_date?->format('Y-m-d\TH:i') ?? '',
            'location' => $event->location ?? '',
            'is_online' => (bool) $event->is_online,
            'meeting_url' => $event->meeting_url ?? '',
            'max_participants' => $event->max_participants ?? '',
            'registration_deadline' => $event->registration_deadline?->format('Y-m-d\TH:i') ?? '',
            'is_published' => (bool) $event->is_published,
        ];
    }

    public function updateAlumniEvent(): void
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

        $event = AlumniEvent::findOrFail($this->eventId);
        $posterPath = $this->existingPoster;

        if ($this->poster) {
            $posterPath = $this->poster->store('alumni-events/posters', 'public');
        }

        $event->update(array_merge(
            collect($validated['form'])->filter(fn ($v) => $v !== '')->toArray(),
            [
                'poster_path' => $posterPath,
                'updated_by' => auth()->id(),
            ]
        ));

        session()->flash('success', 'Event alumni berhasil diperbarui.');
        $this->redirectRoute('admin.alumni.events.index');
    }

    public function render()
    {
        $eventTypes = EventType::options();

        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Edit Event Alumni',
            'eventTypes' => $eventTypes,
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header"><h3 class="card-title">Edit Event: {{ $form['title'] ?? '' }}</h3></div>
        <div class="card-body row">
            <div class="form-group col-lg-8 col-md-6 col-sm-12 mt-2">
                <label for="title">Judul Event <span class="text-danger">*</span></label>
                <input type="text" id="title" class="form-control" wire:model.defer="form.title">
                @error('form.title') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="event_type">Tipe Event <span class="text-danger">*</span></label>
                <select id="event_type" class="form-control" wire:model.defer="form.event_type">
                    @foreach ($eventTypes as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="event_date">Tanggal Mulai <span class="text-danger">*</span></label>
                <input type="datetime-local" id="event_date" class="form-control" wire:model.defer="form.event_date">
                @error('form.event_date') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="end_date">Tanggal Selesai</label>
                <input type="datetime-local" id="end_date" class="form-control" wire:model.defer="form.end_date">
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="registration_deadline">Deadline Pendaftaran</label>
                <input type="datetime-local" id="registration_deadline" class="form-control" wire:model.defer="form.registration_deadline">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="location">Lokasi</label>
                <input type="text" id="location" class="form-control" wire:model.defer="form.location">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="max_participants">Maks Peserta</label>
                <input type="number" id="max_participants" class="form-control" wire:model.defer="form.max_participants">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="meeting_url">URL Meeting</label>
                <input type="url" id="meeting_url" class="form-control" wire:model.defer="form.meeting_url">
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="poster">Poster</label>
                @if ($existingPoster)
                    <div class="mb-2"><small class="text-muted">Poster saat ini tersedia</small></div>
                @endif
                <input type="file" id="poster" class="form-control" wire:model="poster" accept="image/*">
            </div>
            <div class="form-group col-12 mt-2">
                <label for="description">Deskripsi</label>
                <textarea id="description" class="form-control" rows="3" wire:model.defer="form.description"></textarea>
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_online" class="form-check-input" type="checkbox" wire:model.defer="form.is_online">
                    <label for="is_online" class="form-check-label">Online</label>
                </div>
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_published" class="form-check-input" type="checkbox" wire:model.defer="form.is_published">
                    <label for="is_published" class="form-check-label">Published</label>
                </div>
            </div>
            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateAlumniEvent">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
