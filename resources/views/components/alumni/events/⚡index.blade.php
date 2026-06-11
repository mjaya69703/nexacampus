<?php

use App\Enums\EventType;
use App\Models\Alumni\AlumniEvent;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $filterType = '';
    public string $filterDate = '';

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $events = AlumniEvent::query()
            ->where('is_published', true)
            ->when($this->filterType, fn ($q) => $q->where('event_type', $this->filterType))
            ->when($this->filterDate === 'upcoming', fn ($q) => $q->where('event_date', '>=', now()))
            ->when($this->filterDate === 'past', fn ($q) => $q->where('event_date', '<', now()))
            ->orderByDesc('event_date')
            ->paginate(10);

        return $this->view()->layout('layouts.app', [
            'menus' => 'Event Alumni',
            'pages' => 'Daftar Event',
        ])->with('events', $events);
    }
};
?>

<div>
    <x-alert />

    {{-- Hero --}}
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-start gap-3">
                <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                    <i class="fas fa-calendar-star"></i>
                </div>
                <div>
                    <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Portal Alumni</div>
                    <h1 class="h2 mb-2" style="font-weight: 800;">Event Alumni</h1>
                    <div style="opacity: 0.9;">Ikuti webinar, networking, career fair, dan event lainnya.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <select wire:model.live="filterType" class="form-select">
                        <option value="">Semua Tipe</option>
                        @foreach (EventType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="filterDate" class="form-select">
                        <option value="">Semua Waktu</option>
                        <option value="upcoming">Mendatang</option>
                        <option value="past">Selesai</option>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <button wire:click="$set('filterType', ''); $set('filterDate', '')" class="btn btn-outline-secondary">
                        <i class="fas fa-rotate-right me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Event List --}}
    @if ($events->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-calendar-xmark fa-3x text-muted mb-3"></i>
                <h4>Tidak ada event ditemukan</h4>
                <p class="text-muted">Coba ubah filter pencarian kamu.</p>
            </div>
        </div>
    @else
        <div class="d-grid gap-3">
            @foreach ($events as $event)
                @php $et = EventType::tryFrom($event->event_type); @endphp
                <a href="{{ route('alumni.events.show', ['id' => $event->id]) }}" class="text-decoration-none">
                    <div class="card">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center">
                                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 12px;">
                                        <div class="h3 mb-0 fw-bold">{{ $event->event_date->format('d') }}</div>
                                        <div class="small">{{ $event->event_date->format('M Y') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="d-flex flex-wrap gap-2 mb-1">
                                        @if ($et)
                                            <span class="badge bg-primary-lt text-primary">{{ $et->label() }}</span>
                                        @endif
                                        @if ($event->is_online)
                                            <span class="badge bg-blue-lt text-blue">Online</span>
                                        @else
                                            <span class="badge bg-secondary-lt text-secondary">Offline</span>
                                        @endif
                                        @if ($event->event_date->isPast())
                                            <span class="badge bg-secondary-lt text-secondary">Selesai</span>
                                        @endif
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1">{{ $event->title }}</h5>
                                    <div class="text-muted small">
                                        <i class="fas fa-clock me-1"></i>{{ $event->event_date->format('H:i') }}
                                        @if ($event->location)
                                            &middot; <i class="fas fa-location-dot me-1"></i>{{ $event->location }}
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3 text-md-end">
                                    @if ($event->max_participants)
                                        @php $slots = $event->availableSlots(); @endphp
                                        <div class="text-muted small mb-1">
                                            {{ $event->participants()->count() }}/{{ $event->max_participants }} peserta
                                        </div>
                                        @if ($slots !== null && $slots <= 0)
                                            <span class="badge bg-danger-lt text-danger">Penuh</span>
                                        @elseif ($slots !== null && $slots <= 10)
                                            <span class="badge bg-warning-lt text-warning">{{ $slots }} slot tersisa</span>
                                        @endif
                                    @endif
                                    @if (! $event->event_date->isPast())
                                        <span class="btn btn-sm btn-primary mt-1">Detail</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $events->links() }}
        </div>
    @endif
</div>
