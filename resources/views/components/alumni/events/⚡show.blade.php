<?php

use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniProfile;
use Livewire\Component;

new class extends Component
{
    public AlumniEvent $event;
    public bool $isRegistered = false;
    public bool $hasProfile = false;

    public function mount($id): void
    {
        $this->event = AlumniEvent::query()
            ->where('is_published', true)
            ->findOrFail($id);

        $user = auth()->user();
        $profile = AlumniProfile::query()->where('user_id', $user->id)->first();
        $this->hasProfile = (bool) $profile;

        if ($profile) {
            $this->isRegistered = $this->event->participants()
                ->where('alumni_profile_id', $profile->id)
                ->exists();
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Event Alumni',
            'pages' => $this->event->title,
        ]);
    }
};
?>

<div>
    <x-alert />

    <a href="{{ route('alumni.events.index') }}" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Event
    </a>

    <div class="row g-4">
        {{-- Main Content --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-4 p-lg-5">
                    @php $et = EventType::tryFrom($event->event_type); @endphp

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if ($et)
                            <span class="badge bg-primary-lt text-primary px-3 py-2">{{ $et->label() }}</span>
                        @endif
                        @if ($event->is_online)
                            <span class="badge bg-blue-lt text-blue px-3 py-2">Online</span>
                        @else
                            <span class="badge bg-secondary-lt text-secondary px-3 py-2">Offline</span>
                        @endif
                    </div>

                    <h1 class="h2 fw-bold mb-3">{{ $event->title }}</h1>

                    @if ($event->description)
                        <hr class="my-4">
                        <div style="white-space: pre-wrap; line-height: 1.8;">{{ $event->description }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3">Detail Event</h4>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 40%;">Tanggal</td>
                            <td class="fw-semibold">{{ $event->event_date->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Waktu</td>
                            <td>{{ $event->event_date->format('H:i') }}
                                @if ($event->end_date) - {{ $event->end_date->format('H:i') }} @endif
                            </td>
                        </tr>
                        @if ($event->location)
                            <tr>
                                <td class="text-muted">Lokasi</td>
                                <td>{{ $event->location }}</td>
                            </tr>
                        @endif
                        @if ($event->is_online && $event->meeting_url)
                            <tr>
                                <td class="text-muted">Link</td>
                                <td><a href="{{ $event->meeting_url }}" target="_blank" class="text-primary small">{{ str($event->meeting_url)->limit(30) }}</a></td>
                            </tr>
                        @endif
                        @if ($event->max_participants)
                            <tr>
                                <td class="text-muted">Kuota</td>
                                <td>
                                    {{ $event->participants()->count() }}/{{ $event->max_participants }}
                                    @php $slots = $event->availableSlots(); @endphp
                                    @if ($slots !== null && $slots <= 0)
                                        <span class="badge bg-danger-lt text-danger ms-1">Penuh</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        @if ($event->registration_deadline)
                            <tr>
                                <td class="text-muted">Batas Daftar</td>
                                <td>{{ $event->registration_deadline->format('d M Y, H:i') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if (! $event->event_date->isPast())
                @if ($isRegistered)
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>Kamu sudah terdaftar di event ini.
                    </div>
                @elseif ($hasProfile)
                    @php $slots = $event->availableSlots(); @endphp
                    @php $deadlinePassed = $event->registration_deadline && $event->registration_deadline->isPast(); @endphp

                    @if ($deadlinePassed)
                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i>Pendaftaran sudah ditutup.
                        </div>
                    @elseif ($slots !== null && $slots <= 0)
                        <div class="alert alert-warning">
                            <i class="fas fa-users me-2"></i>Kuota event sudah penuh.
                        </div>
                    @else
                        <a href="{{ route('alumni.events.register', ['id' => $event->id]) }}" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-user-plus me-2"></i> Daftar Event
                        </a>
                    @endif
                @else
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Lengkapi profil alumni terlebih dahulu.
                    </div>
                @endif
            @else
                <div class="alert alert-secondary">
                    <i class="fas fa-flag-checkered me-2"></i>Event ini sudah selesai.
                </div>
            @endif
        </div>
    </div>
</div>
