<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Publication\Agenda;
use App\Models\Publication\PublicationCategory;
use Illuminate\Support\Str;

new class extends Component
{
    use WithPagination;

    public string $tab = 'upcoming';
    public string $search = '';
    public string $filterCategory = '';
    public string $filterMonth = '';

    protected $queryString = ['tab', 'search', 'filterCategory', 'filterMonth'];

    public function updatingTab(): void { $this->resetPage(); }
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterCategory(): void { $this->resetPage(); }
    public function updatingFilterMonth(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterCategory = '';
        $this->filterMonth = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Agenda::published()->with('category');

        if ($this->tab === 'upcoming') {
            $query->where('event_date', '>=', now()->startOfDay())->orderBy('event_date');
        } else {
            $query->where('event_date', '<', now()->startOfDay())->orderByDesc('event_date');
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterCategory) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $this->filterCategory));
        }

        if ($this->filterMonth) {
            $query->whereYear('event_date', substr($this->filterMonth, 0, 4))
                ->whereMonth('event_date', substr($this->filterMonth, 5, 2));
        }

        $agendas = $query->paginate(10);
        $upcomingCount = Agenda::published()->upcoming()->count();
        $pastCount = Agenda::published()->past()->count();
        $totalEvents = Agenda::published()->count();
        $featuredEvent = Agenda::published()->upcoming()->with('category')->orderBy('event_date')->first();
        $latestEvents = Agenda::published()->upcoming()->with('category')->orderBy('event_date')->limit(4)->get();

        $categories = PublicationCategory::whereHas('agendas', fn ($q) => $q->published())
            ->withCount(['agendas' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        $months = Agenda::published()
            ->whereNotNull('event_date')
            ->selectRaw('YEAR(event_date) as year, MONTH(event_date) as month')
            ->distinct()
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($m) => sprintf('%04d-%02d', $m->year, $m->month))
            ->toArray();

        return $this->view()->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => 'Agenda & Kegiatan',
        ])->with([
            'agendas' => $agendas,
            'categories' => $categories,
            'months' => $months,
            'upcomingCount' => $upcomingCount,
            'pastCount' => $pastCount,
            'totalEvents' => $totalEvents,
            'featuredEvent' => $featuredEvent,
            'latestEvents' => $latestEvents,
        ]);
    }
};
?>

@php
    $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
@endphp

@include('components.root.publication.partials.editorial-styles')

<div class="admission-public pub-shell">
    <div class="pub-wrap">
        {{-- Hero Header Section --}}
        <section class="pub-hero mb-4">
            <div>
                <div class="pub-kicker">
                    <span>Agenda & Kalender Akademik</span>
                </div>
                <h1 class="pub-title">
                    Tanggal Penting & Event Kampus <span class="text-accent">Terpadu</span>
                </h1>
                <p class="pub-lede">
                    Pantau agenda akademik, seminar nasional, workshop, dan kegiatan penting kampus yang sedang berlangsung maupun mendatang.
                </p>
            </div>
            <aside class="pub-hero-panel">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="pub-hero-panel__label">Total Event Terbit</div>
                    <span class="badge bg-primary bg-opacity-20 text-white rounded-pill px-2.5 py-1 small fw-bold"><i class="fas fa-calendar-check me-1"></i> Live</span>
                </div>
                <div class="pub-hero-panel__number">{{ $totalEvents }}</div>
                <p class="pub-hero-panel__note">
                    <i class="fas fa-clock text-info me-1"></i> <strong>{{ $upcomingCount }}</strong> agenda mendatang dan <strong>{{ $pastCount }}</strong> arsip agenda.
                </p>
            </aside>
        </section>

        {{-- Featured Event Spotlight (If available on upcoming tab) --}}
        @if($tab === 'upcoming' && $featuredEvent && request()->page <= 1 && !$search && !$filterCategory && !$filterMonth)
            <a href="{{ route('root.publication.agenda-show', ['slug' => $featuredEvent->slug]) }}" class="pub-card pub-feature mb-4">
                <div class="pub-feature__body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold small">
                            <i class="fas fa-star me-1"></i> Agenda Terdekat Utama
                        </span>
                        @if($featuredEvent->category)
                            <span class="pub-badge">{{ $featuredEvent->category->name }}</span>
                        @endif
                    </div>
                    <h2>{{ $featuredEvent->title }}</h2>
                    <p class="pub-card__excerpt pub-line-clamp-3">{{ Str::limit(strip_tags($featuredEvent->description ?? ''), 220) }}</p>
                    <div class="pub-meta mt-3">
                        <span><i class="far fa-calendar-alt text-primary me-1"></i>{{ $featuredEvent->event_date?->translatedFormat('l, d F Y') }}</span>
                        @if($featuredEvent->event_time)
                            <span><i class="far fa-clock text-info me-1"></i>{{ substr($featuredEvent->event_time, 0, 5) }} WIB</span>
                        @endif
                        @if($featuredEvent->location)
                            <span><i class="fas fa-location-dot text-danger me-1"></i>{{ $featuredEvent->location }}</span>
                        @endif
                    </div>
                </div>
                <div class="pub-feature__media d-flex align-items-center justify-content-center p-4">
                    <div class="pub-event-date shadow-lg">
                        <div>
                            <strong>{{ $featuredEvent->event_date?->format('d') }}</strong>
                            <span>{{ $featuredEvent->event_date?->translatedFormat('M Y') }}</span>
                        </div>
                    </div>
                </div>
            </a>
        @endif

        <div class="pub-layout pub-layout--reverse">
            {{-- Sidebar Controls & Filters --}}
            <aside class="pub-side">
                {{-- Periode Tab Selection --}}
                <section class="pub-sidebox">
                    <h2 class="pub-section-title"><i class="fas fa-layer-group me-1"></i> Periode Event</h2>
                    <div class="pub-chip-group">
                        <button type="button" class="pub-chip flex-fill text-center {{ $tab === 'upcoming' ? 'pub-chip--active' : '' }}" wire:click="$set('tab', 'upcoming')">
                            <i class="fas fa-calendar-day me-1"></i> Mendatang ({{ $upcomingCount }})
                        </button>
                        <button type="button" class="pub-chip flex-fill text-center {{ $tab === 'past' ? 'pub-chip--active' : '' }}" wire:click="$set('tab', 'past')">
                            <i class="fas fa-box-archive me-1"></i> Arsip ({{ $pastCount }})
                        </button>
                    </div>
                </section>

                {{-- Filter & Search Box --}}
                <section class="pub-filter">
                    <h2 class="pub-section-title"><i class="fas fa-filter me-1"></i> Cari & Filter</h2>
                    <div class="pub-form-row">
                        <div class="position-relative">
                            <input type="search" class="pub-input ps-5" placeholder="Cari nama agenda..." wire:model.live.debounce.300ms="search">
                            <i class="fas fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        </div>

                        <select class="pub-select" wire:model.live="filterMonth">
                            <option value="">Semua Bulan & Tahun</option>
                            @foreach($months as $month)
                                @php $year = substr($month, 0, 4); $monthNumber = substr($month, 5, 2); @endphp
                                <option value="{{ $month }}">{{ $bulan[$monthNumber] ?? $monthNumber }} {{ $year }}</option>
                            @endforeach
                        </select>

                        @if($search || $filterCategory || $filterMonth)
                            <button type="button" class="pub-button w-100" wire:click="clearFilters">
                                <i class="fas fa-rotate-right me-1 text-danger"></i>
                                <span>Reset Filter</span>
                            </button>
                        @endif
                    </div>
                </section>

                {{-- Category Filter Chips --}}
                @if($categories->isNotEmpty())
                    <section class="pub-sidebox">
                        <h2 class="pub-section-title"><i class="fas fa-tags me-1"></i> Kategori Event</h2>
                        <div class="pub-chip-group">
                            <button type="button" class="pub-chip {{ $filterCategory === '' ? 'pub-chip--active' : '' }}" wire:click="$set('filterCategory', '')">
                                Semua
                            </button>
                            @foreach($categories as $category)
                                <button type="button" class="pub-chip {{ $filterCategory === $category->slug ? 'pub-chip--active' : '' }}" wire:click="$set('filterCategory', '{{ $category->slug }}')">
                                    {{ $category->name }} ({{ $category->agendas_count }})
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Upcoming Quick List Widget --}}
                <section class="pub-sidebox">
                    <h2 class="pub-section-title"><i class="fas fa-clock-rotate-left me-1"></i> Agenda Mendatang</h2>
                    <div class="pub-side-list">
                        @forelse($latestEvents as $event)
                            <a href="{{ route('root.publication.agenda-show', ['slug' => $event->slug]) }}" class="pub-side-item">
                                <div class="pub-date-tile">
                                    <strong>{{ $event->event_date?->format('d') }}</strong>
                                    <span>{{ $event->event_date?->translatedFormat('M') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-bold text-body pub-line-clamp-2" style="font-size: .88rem; line-height: 1.35;">{{ $event->title }}</div>
                                    <div class="pub-muted small mt-1"><i class="fas fa-location-dot me-1 text-danger"></i>{{ $event->location ?: 'Lokasi menyusul' }}</div>
                                </div>
                            </a>
                        @empty
                            <p class="pub-muted mb-0 small text-center py-2">Belum ada agenda mendatang lainnya.</p>
                        @endforelse
                    </div>
                </section>
            </aside>

            {{-- Main Content Section --}}
            <main class="pub-main">
                @if($agendas->isEmpty())
                    <div class="pub-empty text-center py-5">
                        <div style="width:64px;height:64px;border-radius:20px;background:rgba(59,130,246,.1);margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#3b82f6;">
                            <i class="fas fa-calendar-xmark"></i>
                        </div>
                        <h3 class="h4 mb-2 fw-bolder text-body">Belum Ada Agenda Ditemukan</h3>
                        <p class="pub-muted mb-4" style="max-width: 420px; margin: 0 auto; font-size: .9rem;">
                            Tidak ada agenda kegiatan yang sesuai dengan kriteria pencarian, kategori, atau filter bulan yang dipilih.
                        </p>
                        <button class="pub-button pub-button--accent px-4" wire:click="clearFilters">
                            <i class="fas fa-rotate-right me-1"></i> Reset Semua Filter
                        </button>
                    </div>
                @else
                    <div class="pub-grid">
                        @foreach($agendas as $agenda)
                            <a href="{{ route('root.publication.agenda-show', ['slug' => $agenda->slug]) }}" class="pub-card pub-agenda-card">
                                <div class="pub-date-tile shadow-sm">
                                    <strong>{{ $agenda->event_date?->format('d') }}</strong>
                                    <span>{{ $agenda->event_date?->translatedFormat('M') }}</span>
                                </div>
                                <div class="pub-card__body">
                                    <div class="d-flex flex-wrap gap-1.5 align-items-center">
                                        @if($agenda->category)
                                            <span class="pub-badge">{{ $agenda->category->name }}</span>
                                        @endif
                                        @if($agenda->event_date?->isToday())
                                            <span class="badge bg-success text-white rounded-pill px-2 py-0.5" style="font-size: .68rem;">Hari Ini</span>
                                        @endif
                                    </div>
                                    <h3 class="pub-card__title pub-line-clamp-2" style="font-size: 1rem;">{{ $agenda->title }}</h3>
                                    <p class="pub-card__excerpt pub-line-clamp-2">{{ Str::limit(strip_tags($agenda->description ?? ''), 120) }}</p>
                                    <div class="pub-meta mt-auto pt-2 border-top">
                                        @if($agenda->event_time)
                                            <span><i class="far fa-clock text-info me-1"></i>{{ substr($agenda->event_time, 0, 5) }} WIB</span>
                                        @endif
                                        @if($agenda->location)
                                            <span><i class="fas fa-location-dot text-danger me-1"></i>{{ Str::limit($agenda->location, 25) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- Pagination Controls --}}
                    <div class="mt-4 d-flex justify-content-center">
                        {{ $agendas->links() }}
                    </div>
                @endif
            </main>
        </div>
    </div>
</div>
