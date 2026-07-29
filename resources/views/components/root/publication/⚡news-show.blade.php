<?php

use Livewire\Component;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use Illuminate\Support\Str;

new class extends Component
{
    public News $article;
    public array $relatedNews = [];
    public array $latestNews = [];
    public array $categories = [];

    public function mount(string $slug): void
    {
        $this->article = News::published()
            ->with(['creator', 'category'])
            ->where('slug', $slug)
            ->firstOrFail();

        if ($this->article->category_id) {
            $this->relatedNews = News::published()
                ->where('category_id', $this->article->category_id)
                ->where('id', '!=', $this->article->id)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn ($item) => [
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'published_at' => $item->published_at,
                    'featured_image' => $item->featured_image,
                    'excerpt' => $item->excerpt ? strip_tags($item->excerpt) : Str::limit(strip_tags($item->content), 120),
                ])
                ->toArray();
        }

        $this->latestNews = News::published()
            ->where('id', '!=', $this->article->id)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['title', 'slug', 'published_at'])
            ->toArray();

        $this->categories = PublicationCategory::withCount(['news' => fn ($q) => $q->published()])
            ->whereHas('news', fn ($q) => $q->published())
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function readingTime(string $content): int
    {
        return max(1, (int) ceil(mb_strlen(strip_tags($content)) / 1000));
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => $this->article->title,
        ]);
    }
};
?>

@include('components.root.publication.partials.editorial-styles')

<div class="admission-public pub-shell">
    <div class="pub-wrap">
        {{-- Detail Grid Layout --}}
        <div class="pub-detail">
            <main class="pub-detail-main">
                {{-- Back Navigation --}}
                <a href="{{ route('root.publication.news') }}" class="pub-button pub-back d-inline-flex align-items-center">
                    <i class="fas fa-arrow-left me-1"></i>
                    <span>Kembali ke Berita Kampus</span>
                </a>

                <article class="pub-card p-4 p-md-5">
                    {{-- Article Header --}}
                    <header class="pub-detail-head border-0 pb-0">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            @if($article->category)
                                <span class="pub-badge">{{ $article->category->name }}</span>
                            @endif
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold" style="font-size: .75rem;">
                                <i class="fas fa-newspaper me-1"></i> Warta Resmi
                            </span>
                        </div>

                        <h1 class="pub-detail-title">{{ $article->title }}</h1>

                        <div class="pub-meta mt-3 pt-3 border-top">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); display: flex; align-items: center; justify-content: center; color: #fff; font-size: .9rem;">
                                    <i class="fas fa-user-pen"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-body small" style="line-height: 1.2;">{{ $article->creator->name ?? 'Tim Humas NexaCampus' }}</div>
                                    <div class="pub-muted small" style="font-size: .75rem;">Redaksi Informasi Publik</div>
                                </div>
                            </div>

                            <span class="ms-md-auto"><i class="far fa-calendar-days text-primary me-1"></i>{{ $article->published_at?->translatedFormat('d M Y') }}</span>
                            <span><i class="far fa-clock text-info me-1"></i>{{ $this->readingTime($article->content) }} menit baca</span>
                        </div>
                    </header>

                    {{-- Featured Media Image --}}
                    @if($article->featured_image)
                        <figure class="pub-detail-media my-4">
                            <img src="{{ Storage::url($article->featured_image) }}" alt="{{ $article->title }}" loading="lazy">
                        </figure>
                    @endif

                    {{-- Article Body Content --}}
                    <div class="pub-prose mt-4">
                        {!! $article->content !!}
                    </div>

                    {{-- Social Share Toolbar --}}
                    <section class="mt-5 pt-4 border-top">
                        <h3 class="pub-section-title mb-3"><i class="fas fa-share-nodes me-1"></i> Bagikan Artikel Ini</h3>
                        <div class="pub-chip-group">
                            <button class="pub-button" type="button" onclick="copyPublicationLink(this)" data-url="{{ url()->current() }}">
                                <i class="fas fa-link text-primary"></i>
                                <span>Salin Tautan</span>
                            </button>
                            <a class="pub-button" href="https://api.whatsapp.com/send?text={{ urlencode($article->title . ' - ' . url()->current()) }}" target="_blank" rel="noopener">
                                <i class="fab fa-whatsapp text-success"></i>
                                <span>WhatsApp</span>
                            </a>
                            <a class="pub-button" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener">
                                <i class="fab fa-facebook-f text-info"></i>
                                <span>Facebook</span>
                            </a>
                            <a class="pub-button" href="https://twitter.com/intent/tweet?text={{ urlencode($article->title) }}&url={{ urlencode(url()->current()) }}" target="_blank" rel="noopener">
                                <i class="fab fa-x-twitter text-dark"></i>
                                <span>X (Twitter)</span>
                            </a>
                        </div>
                    </section>
                </article>

                {{-- Related News Grid --}}
                @if(count($relatedNews) > 0)
                    <section class="mt-5 pt-2">
                        <h2 class="pub-section-title mb-3" style="font-size: 1rem;"><i class="fas fa-newspaper me-1"></i> Berita Terkait Lainnya</h2>
                        <div class="pub-grid pub-grid--three">
                            @foreach($relatedNews as $related)
                                <a href="{{ route('root.publication.news-show', ['slug' => $related['slug']]) }}" class="pub-card">
                                    <div class="pub-thumb">
                                        @if($related['featured_image'])
                                            <img src="{{ Storage::url($related['featured_image']) }}" alt="{{ $related['title'] }}" loading="lazy">
                                        @else
                                            <div class="pub-thumb__placeholder"><i class="fas fa-newspaper fa-2x opacity-40"></i></div>
                                        @endif
                                    </div>
                                    <div class="pub-card__body">
                                        <h3 class="pub-card__title pub-line-clamp-2" style="font-size: .95rem;">{{ $related['title'] }}</h3>
                                        <p class="pub-card__excerpt pub-line-clamp-2">{{ $related['excerpt'] }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </main>

            {{-- Sidebar Widgets --}}
            <aside class="pub-side">
                {{-- Latest News Widget --}}
                @if(count($latestNews) > 0)
                    <section class="pub-sidebox">
                        <h2 class="pub-section-title"><i class="fas fa-fire me-1"></i> Berita Terbaru</h2>
                        <div class="pub-side-list">
                            @foreach($latestNews as $latest)
                                <a href="{{ route('root.publication.news-show', ['slug' => $latest['slug']]) }}" class="pub-side-item">
                                    <div class="pub-date-tile">
                                        <strong>{{ \Carbon\Carbon::parse($latest['published_at'])->format('d') }}</strong>
                                        <span>{{ \Carbon\Carbon::parse($latest['published_at'])->translatedFormat('M') }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-body pub-line-clamp-2" style="font-size: .88rem; line-height: 1.35;">{{ $latest['title'] }}</div>
                                        <div class="pub-muted small mt-1"><i class="far fa-calendar me-1"></i>{{ \Carbon\Carbon::parse($latest['published_at'])->format('d M Y') }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Categories Chip List --}}
                @if(count($categories) > 0)
                    <section class="pub-sidebox">
                        <h2 class="pub-section-title"><i class="fas fa-folder-open me-1"></i> Kategori Berita</h2>
                        <div class="pub-chip-group">
                            @foreach($categories as $category)
                                <a href="{{ route('root.publication.news', ['filterCategory' => $category['slug']]) }}" class="pub-chip {{ $category['id'] === $article->category?->id ? 'pub-chip--active' : '' }}">
                                    {{ $category['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function copyPublicationLink(button) {
        const url = button.getAttribute('data-url');
        const originalHTML = button.innerHTML;

        navigator.clipboard.writeText(url).then(() => {
            button.innerHTML = '<i class="fas fa-check text-success"></i><span>Tersalin!</span>';
            button.classList.add('border-success');
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('border-success');
            }, 2000);
        });
    }
</script>
@endpush
