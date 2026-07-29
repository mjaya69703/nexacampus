<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use Illuminate\Support\Str;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sort = 'terbaru';
    public string $filterCategory = '';

    protected $queryString = ['search', 'sort', 'filterCategory'];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingSort(): void { $this->resetPage(); }
    public function updatingFilterCategory(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->sort = 'terbaru';
        $this->filterCategory = '';
        $this->resetPage();
    }

    public function readingTime(string $content): string
    {
        $chars = mb_strlen(strip_tags($content));
        $minutes = max(1, (int) ceil($chars / 1000));

        return $minutes . ' menit';
    }

    public function render()
    {
        $featuredNews = News::published()
            ->with('category')
            ->orderByDesc('published_at')
            ->first();

        $query = News::published()->with('category');

        if ($featuredNews) {
            $query->where('id', '!=', $featuredNews->id);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('content', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterCategory) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $this->filterCategory));
        }

        $this->sort === 'terlama'
            ? $query->orderBy('published_at')
            : $query->orderByDesc('published_at');

        $news = $query->paginate(9);

        $categories = PublicationCategory::withCount(['news' => fn ($q) => $q->published()])
            ->whereHas('news', fn ($q) => $q->published())
            ->orderBy('name')
            ->get();

        $totalNews = News::published()->count();
        $latestNews = News::published()->orderByDesc('published_at')->limit(5)->get(['title', 'slug', 'published_at']);

        return $this->view()
            ->layout('layouts.home', [
                'menus' => 'Publikasi',
                'pages' => 'Berita Kampus',
            ])
            ->with([
                'news' => $news,
                'categories' => $categories,
                'totalNews' => $totalNews,
                'latestNews' => $latestNews,
                'featuredNews' => $featuredNews,
            ]);
    }
};
?>

@include('components.root.publication.partials.editorial-styles')

<div class="admission-public pub-shell">
    <div class="pub-wrap">
        {{-- Hero Banner Section --}}
        <section class="pub-hero mb-4">
            <div>
                <div class="pub-kicker">
                    <span>Portal Berita & Kabar Kampus</span>
                </div>
                <h1 class="pub-title">
                    Informasi & Prestasi <span class="text-accent">NexaCampus</span>
                </h1>
                <p class="pub-lede">
                    Ikuti kabar terkini seputar akademis, pencapaian mahasiswa, berita riset, kegiatan institusi, dan liputan khusus lingkungan civitas akademika.
                </p>
            </div>
            <aside class="pub-hero-panel">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="pub-hero-panel__label">Total Artikel Terbit</div>
                    <span class="badge bg-primary bg-opacity-20 text-white rounded-pill px-2.5 py-1 small fw-bold"><i class="fas fa-newspaper me-1"></i> Warta</span>
                </div>
                <div class="pub-hero-panel__number">{{ $totalNews }}</div>
                <p class="pub-hero-panel__note">Artikel tersusun rapi dari yang terbaru, dapat dikelompokkan berdasarkan kategori atau kata kunci.</p>
            </aside>
        </section>

        {{-- Featured News Hero Showcase (If on first page with no search/filter) --}}
        @if($featuredNews && request()->page <= 1 && !$search && !$filterCategory)
            <a href="{{ route('root.publication.news-show', ['slug' => $featuredNews->slug]) }}" class="pub-card pub-feature mb-4">
                <div class="pub-feature__body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold small">
                            <i class="fas fa-bolt me-1"></i> Berita Utama
                        </span>
                        @if($featuredNews->category)
                            <span class="pub-badge">{{ $featuredNews->category->name }}</span>
                        @endif
                    </div>
                    <h2>{{ $featuredNews->title }}</h2>
                    <p class="pub-card__excerpt pub-line-clamp-3">
                        {{ $featuredNews->excerpt ? strip_tags($featuredNews->excerpt) : Str::limit(strip_tags($featuredNews->content), 220) }}
                    </p>
                    <div class="pub-meta mt-3">
                        <span><i class="fas fa-calendar-day text-primary me-1"></i>{{ $featuredNews->published_at?->translatedFormat('d M Y') }}</span>
                        <span><i class="fas fa-clock text-info me-1"></i>{{ $this->readingTime($featuredNews->content) }}</span>
                        <span class="pub-button pub-button--accent ms-lg-auto py-2 px-4 shadow-sm">
                            <span>Baca Selengkapnya</span>
                            <i class="fas fa-arrow-right ms-1"></i>
                        </span>
                    </div>
                </div>
                <div class="pub-feature__media">
                    @if($featuredNews->featured_image)
                        <img src="{{ Storage::url($featuredNews->featured_image) }}" alt="{{ $featuredNews->title }}" loading="lazy">
                    @else
                        <div class="pub-feature__placeholder">
                            <i class="fas fa-newspaper fa-3x opacity-50"></i>
                        </div>
                    @endif
                </div>
            </a>
        @endif

        {{-- Main Layout Grid --}}
        <div class="pub-layout">
            <main class="pub-main">
                @if($news->isNotEmpty())
                    <div class="pub-grid">
                        @foreach($news as $item)
                            <a href="{{ route('root.publication.news-show', ['slug' => $item->slug]) }}" class="pub-card">
                                <div class="pub-thumb">
                                    @if($item->featured_image)
                                        <img src="{{ Storage::url($item->featured_image) }}" alt="{{ $item->title }}" loading="lazy">
                                    @else
                                        <div class="pub-thumb__placeholder">
                                            <i class="fas fa-newspaper fa-2x opacity-40"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="pub-card__body">
                                    @if($item->category)
                                        <span class="pub-badge">{{ $item->category->name }}</span>
                                    @endif
                                    <h3 class="pub-card__title pub-line-clamp-2">{{ $item->title }}</h3>
                                    <p class="pub-card__excerpt pub-line-clamp-3">
                                        {{ $item->excerpt ? strip_tags($item->excerpt) : Str::limit(strip_tags($item->content), 130) }}
                                    </p>
                                    <div class="pub-card__footer pub-meta">
                                        <span><i class="far fa-calendar text-primary me-1"></i>{{ $item->published_at?->format('d M Y') }}</span>
                                        <span><i class="far fa-clock text-info me-1"></i>{{ $this->readingTime($item->content) }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- Pagination Links --}}
                    <div class="mt-4 d-flex justify-content-center">
                        {{ $news->links() }}
                    </div>
                @else
                    <div class="pub-empty text-center py-5">
                        <div style="width:64px;height:64px;border-radius:20px;background:rgba(59,130,246,.1);margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#3b82f6;">
                            <i class="fas fa-newspaper-slash"></i>
                        </div>
                        <h3 class="h4 mb-2 fw-bolder text-body">Tidak Ditemukan Berita</h3>
                        <p class="pub-muted mb-4" style="max-width: 420px; margin: 0 auto; font-size: .9rem;">
                            Tidak ada artikel berita yang cocok dengan kata kunci pencarian atau filter kategori yang sedang aktif.
                        </p>
                        <button class="pub-button pub-button--accent px-4" wire:click="clearFilters">
                            <i class="fas fa-rotate-right me-1"></i> Reset Filter
                        </button>
                    </div>
                @endif
            </main>

            {{-- Sidebar Filter & Latest News --}}
            <aside class="pub-side">
                {{-- Search & Sort Filter Box --}}
                <section class="pub-filter">
                    <h2 class="pub-section-title"><i class="fas fa-magnifying-glass me-1"></i> Cari & Urutkan</h2>
                    <div class="pub-form-row">
                        <div class="position-relative">
                            <input type="search" class="pub-input ps-5" placeholder="Cari judul atau isi..." wire:model.live.debounce.300ms="search">
                            <i class="fas fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        </div>
                        <select class="pub-select" wire:model.live="sort">
                            <option value="terbaru">Terbaru Dulu</option>
                            <option value="terlama">Terlama Dulu</option>
                        </select>
                        @if($search || $filterCategory || $sort !== 'terbaru')
                            <button class="pub-button w-100" type="button" wire:click="clearFilters">
                                <i class="fas fa-rotate-right me-1 text-danger"></i>
                                <span>Reset Filter</span>
                            </button>
                        @endif
                    </div>
                </section>

                {{-- Category Chips --}}
                @if($categories->isNotEmpty())
                    <section class="pub-sidebox">
                        <h2 class="pub-section-title"><i class="fas fa-folder-open me-1"></i> Kategori Berita</h2>
                        <div class="pub-chip-group">
                            <button type="button" class="pub-chip {{ $filterCategory === '' ? 'pub-chip--active' : '' }}" wire:click="$set('filterCategory', '')">
                                Semua Kategori
                            </button>
                            @foreach($categories as $category)
                                <button type="button" class="pub-chip {{ $filterCategory === $category->slug ? 'pub-chip--active' : '' }}" wire:click="$set('filterCategory', '{{ $category->slug }}')">
                                    {{ $category->name }} ({{ $category->news_count }})
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Latest News List Widget --}}
                <section class="pub-sidebox">
                    <h2 class="pub-section-title"><i class="fas fa-fire me-1"></i> Berita Terkini</h2>
                    <div class="pub-side-list">
                        @forelse($latestNews as $latest)
                            <a href="{{ route('root.publication.news-show', ['slug' => $latest->slug]) }}" class="pub-side-item">
                                <div class="pub-date-tile">
                                    <strong>{{ $latest->published_at?->format('d') }}</strong>
                                    <span>{{ $latest->published_at?->translatedFormat('M') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-bold text-body pub-line-clamp-2" style="font-size: .88rem; line-height: 1.35;">{{ $latest->title }}</div>
                                    <div class="pub-muted small mt-1"><i class="far fa-calendar me-1"></i>{{ $latest->published_at?->format('d M Y') }}</div>
                                </div>
                            </a>
                        @empty
                            <p class="pub-muted mb-0 small text-center py-2">Belum ada berita terbaru.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
