<?php

use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniEventParticipant;
use App\Models\Alumni\AlumniProfile;
use Livewire\Component;

new class extends Component
{
    public AlumniEvent $event;
    public string $notes = '';

    public function mount($id): void
    {
        $this->event = AlumniEvent::query()
            ->where('is_published', true)
            ->findOrFail($id);

        // Check if already registered
        $profile = AlumniProfile::query()->where('user_id', auth()->id())->first();
        abort_unless($profile, 403, 'Profil alumni belum tersedia.');

        $alreadyRegistered = $this->event->participants()
            ->where('alumni_profile_id', $profile->id)
            ->exists();

        if ($alreadyRegistered) {
            session()->flash('info', 'Kamu sudah terdaftar di event ini.');
            $this->redirectRoute('alumni.events.show', ['id' => $this->event->id]);
        }

        // Check deadline
        if ($this->event->registration_deadline && $this->event->registration_deadline->isPast()) {
            session()->flash('error', 'Pendaftaran event sudah ditutup.');
            $this->redirectRoute('alumni.events.show', ['id' => $this->event->id]);
        }

        // Check capacity
        $slots = $this->event->availableSlots();
        if ($slots !== null && $slots <= 0) {
            session()->flash('error', 'Kuota event sudah penuh.');
            $this->redirectRoute('alumni.events.show', ['id' => $this->event->id]);
        }
    }

    public function confirmRegistration(): void
    {
        $profile = AlumniProfile::query()->where('user_id', auth()->id())->first();
        abort_unless($profile, 422, 'Profil alumni belum tersedia.');

        $validated = $this->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        AlumniEventParticipant::create([
            'alumni_event_id' => $this->event->id,
            'alumni_profile_id' => $profile->id,
            'registered_at' => now(),
            'notes' => $validated['notes'] ?: null,
        ]);

        session()->flash('success', 'Pendaftaran event berhasil!');
        $this->redirectRoute('alumni.events.show', ['id' => $this->event->id]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Event Alumni',
            'pages' => 'Daftar Event: ' . $this->event->title,
        ]);
    }
};
?>

<div>
    <x-alert />

    <a href="{{ route('alumni.events.show', ['id' => $event->id]) }}" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0"><i class="fas fa-user-plus me-2 text-primary"></i>Konfirmasi Pendaftaran Event</h3>
                </div>
                <div class="card-body p-4">
                    {{-- Event Summary --}}
                    <div class="border rounded p-3 mb-4">
                        @php $et = EventType::tryFrom($event->event_type); @endphp
                        <h5 class="fw-bold mb-1">{{ $event->title }}</h5>
                        <div class="text-muted small">
                            <i class="fas fa-calendar me-1"></i>{{ $event->event_date->format('d M Y, H:i') }}
                            @if ($event->location)
                                &middot; <i class="fas fa-location-dot me-1"></i>{{ $event->location }}
                            @endif
                            @if ($et)
                                &middot; {{ $et->label() }}
                            @endif
                        </div>
                    </div>

                    <form wire:submit.prevent="confirmRegistration">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Catatan (Opsional)</label>
                            <textarea wire:model.defer="notes" class="form-control" rows="3" placeholder="Tulis catatan untuk panitia event (opsional)..."></textarea>
                            @error('notes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('alumni.events.show', ['id' => $event->id]) }}" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary fw-semibold">
                                <i class="fas fa-check me-1"></i> Konfirmasi Pendaftaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
