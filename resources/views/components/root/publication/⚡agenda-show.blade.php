<?php

use Livewire\Component;
use App\Models\Publication\Agenda;
use App\Models\Publication\PublicationCategory;

new class extends Component
{
    public Agenda $event;

    public function mount(string $slug): void
    {
        $this->event = Agenda::published()
            ->with(['creator', 'category'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function render()
    {
        $categories = PublicationCategory::whereHas('agendas', fn ($q) => $q->published())
            ->orderBy('name')
            ->get();

        $latestEvents = Agenda::published()->upcoming()
            ->where('id', '!=', $this->event->id)
            ->with('category')
            ->orderBy('event_date')
            ->limit(4)
            ->get();

        return $this->view()->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => $this->event->title,
        ])->with([
            'categories' => $categories,
            'latestEvents' => $latestEvents,
        ]);
    }
};
?>

@php
    $isUpcoming = $event->event_date && $event->event_date >= now()->startOfDay();
@endphp

@include('components.root.publication.partials.editorial-styles')

<div class="admission-public pub-shell">
    <div class="pub-wrap">
        {{-- Back Navigation --}}
        <a href="{{ route('root.publication.agenda') }}" class="pub-button pub-back d-inline-flex align-items-center">
            <i class="fas fa-arrow-left me-1"></i>
            <span>Kembali ke Daftar Agenda</span>
        </a>

        {{-- Hero Header Section --}}
        <section class="pub-event-hero mb-4">
            <div class="pub-event-date shadow-lg">
                <div>
                    <strong>{{ $event->event_date?->format('d') }}</strong>
                    <span>{{ $event->event_date?->translatedFormat('M Y') }}</span>
                </div>
            </div>
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    @if($event->category)
                        <span class="pub-badge bg-primary bg-opacity-20 text-white border-0">{{ $event->category->name }}</span>
                    @endif
                    <span class="badge {{ $isUpcoming ? 'bg-success text-white' : 'bg-secondary text-white' }} rounded-pill px-3 py-1 fw-bold" style="font-size: .75rem;">
                        <i class="fas {{ $isUpcoming ? 'fa-hourglass-half' : 'fa-check-circle' }} me-1"></i>
                        {{ $isUpcoming ? 'Agenda Mendatang' : 'Agenda Selesai' }}
                    </span>
                </div>
                <h1 class="pub-detail-title mb-3">{{ $event->title }}</h1>
                <div class="pub-meta text-white-50">
                    <span><i class="far fa-calendar-alt text-warning me-1"></i>{{ $event->event_date?->translatedFormat('l, d F Y') }}</span>
                    @if($event->event_time)
                        <span><i class="far fa-clock text-info me-1"></i>{{ substr($event->event_time, 0, 5) }} WIB</span>
                    @endif
                    @if($event->location)
                        <span><i class="fas fa-location-dot text-danger me-1"></i>{{ $event->location }}</span>
                    @endif
                </div>
            </div>
        </section>

        {{-- Detail Grid --}}
        <div class="pub-detail">
            <main class="pub-detail-main">
                <article class="pub-card p-4 p-md-5 mb-4">
                    <h2 class="pub-section-title mb-3"><i class="fas fa-file-lines me-1"></i> Deskripsi & Detail Agenda</h2>
                    <div class="pub-prose">
                        @if($event->description)
                            {!! nl2br(e($event->description)) !!}
                        @else
                            <p class="pub-muted fst-italic mb-0">Deskripsi rinci untuk agenda ini belum tersedia.</p>
                        @endif
                    </div>
                </article>

                @if($event->location)
                    <section class="pub-card p-4">
                        <h2 class="pub-section-title mb-3"><i class="fas fa-map-location-dot me-1"></i> Lokasi Pelaksanaan</h2>
                        <div class="d-flex align-items-center gap-3">
                            <div class="pub-date-tile shadow-sm" style="width: 56px; height: 56px; background: linear-gradient(135deg, #ef4444, #b91c1c);">
                                <i class="fas fa-location-dot fs-4 text-white"></i>
                            </div>
                            <div>
                                <h3 class="pub-card__title mb-1" style="font-size: 1.1rem;">{{ $event->location }}</h3>
                                <p class="pub-muted mb-0 small">
                                    Pastikan hadir tepat waktu di lokasi kegiatan sesuai waktu yang ditentukan.
                                </p>
                            </div>
                        </div>
                    </section>
                @endif
            </main>

            {{-- Sidebar Overview & Widgets --}}
            <aside class="pub-side">
                {{-- Quick Summary Box --}}
                <section class="pub-sidebox">
                    <h2 class="pub-section-title"><i class="fas fa-circle-info me-1"></i> Ringkasan Event</h2>
                    <div class="pub-side-list">
                        <div class="pub-side-item">
                            <div class="pub-date-tile" style="width: 42px; min-height: 42px;"><i class="far fa-calendar-alt"></i></div>
                            <div>
                                <div class="fw-bold text-body small">Hari & Tanggal</div>
                                <div class="pub-muted small">{{ $event->event_date?->translatedFormat('l, d F Y') }}</div>
                            </div>
                        </div>
                        @if($event->event_time)
                            <div class="pub-side-item">
                                <div class="pub-date-tile" style="width: 42px; min-height: 42px; background: linear-gradient(135deg, #06b6d4, #0891b2);"><i class="far fa-clock"></i></div>
                                <div>
                                    <div class="fw-bold text-body small">Waktu Pelaksanaan</div>
                                    <div class="pub-muted small">{{ substr($event->event_time, 0, 5) }} WIB</div>
                                </div>
                            </div>
                        @endif
                        @if($event->location)
                            <div class="pub-side-item">
                                <div class="pub-date-tile" style="width: 42px; min-height: 42px; background: linear-gradient(135deg, #ef4444, #b91c1c);"><i class="fas fa-location-dot"></i></div>
                                <div>
                                    <div class="fw-bold text-body small">Tempat / Lokasi</div>
                                    <div class="pub-muted small">{{ $event->location }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Other Upcoming Agendas --}}
                @if($latestEvents->isNotEmpty())
                    <section class="pub-sidebox">
                        <h2 class="pub-section-title"><i class="fas fa-calendar-week me-1"></i> Agenda Lainnya</h2>
                        <div class="pub-side-list">
                            @foreach($latestEvents as $latest)
                                <a href="{{ route('root.publication.agenda-show', ['slug' => $latest->slug]) }}" class="pub-side-item">
                                    <div class="pub-date-tile">
                                        <strong>{{ $latest->event_date?->format('d') }}</strong>
                                        <span>{{ $latest->event_date?->translatedFormat('M') }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-body pub-line-clamp-2" style="font-size: .88rem; line-height: 1.35;">{{ $latest->title }}</div>
                                        <div class="pub-muted small mt-1">{{ $latest->location ?: 'Lokasi menyusul' }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Categories Chips --}}
                @if($categories->isNotEmpty())
                    <section class="pub-sidebox">
                        <h2 class="pub-section-title"><i class="fas fa-tags me-1"></i> Kategori</h2>
                        <div class="pub-chip-group">
                            @foreach($categories as $category)
                                <a href="{{ route('root.publication.agenda', ['filterCategory' => $category->slug]) }}" class="pub-chip {{ $category->id === $event->category?->id ? 'pub-chip--active' : '' }}">
                                    {{ $category->name }}
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</div>
