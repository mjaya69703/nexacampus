<?php

namespace App\Http\Controllers\Home;

use App\Enums\AnnouncementTargetType;
use App\Enums\FaqType;
use App\Models\Publication\Agenda;
use App\Models\Publication\Announcement;
use App\Models\Publication\Faq;
use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use App\Models\Campus\CampusLocation;
use App\Models\Settings\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicationPageController extends \App\Http\Controllers\Controller
{
    public function announcements(Request $request): Response
    {
        $query = Announcement::published()
            ->where('target_type', AnnouncementTargetType::GLOBAL)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        $total = $query->count();

        $items = $query->limit(30)->get();

        $pinned = $items->where('is_pinned', true)
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => strip_tags((string) $a->content),
                'priority' => $a->priority?->value ?? 'normal',
                'publishedAt' => $a->published_at?->format('d M Y'),
                'hasAttachment' => filled($a->attachment_path),
            ])->values()->all();

        $regular = $items->where('is_pinned', false)
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => Str::limit(strip_tags((string) $a->content), 160),
                'priority' => $a->priority?->value ?? 'normal',
                'publishedAt' => $a->published_at?->format('d M Y'),
                'hasAttachment' => filled($a->attachment_path),
            ])->values()->all();

        return $this->render($request, 'Home/Publikasi/Pengumuman', [
            'total' => $total,
            'pinned' => $pinned,
            'regulars' => $regular,
        ]);
    }

    public function faq(Request $request): Response
    {
        $faqs = Faq::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
                'category' => $f->category,
                'type' => $f->type instanceof FaqType ? $f->type->value : (string) $f->type,
            ])->all();

        $typeCounts = ['all' => Faq::where('is_active', true)->count()];
        foreach (FaqType::cases() as $case) {
            $typeCounts[$case->value] = Faq::where('is_active', true)->where('type', $case->value)->count();
        }

        $types = collect(FaqType::cases())
            ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
            ->all();

        return $this->render($request, 'Home/Publikasi/Faq', [
            'faqs' => $faqs,
            'types' => $types,
            'typeCounts' => $typeCounts,
        ]);
    }

    public function news(Request $request): Response
    {
        $featured = News::published()
            ->with('category')
            ->orderByDesc('published_at')
            ->first();

        $query = News::published()->with('category');
        if ($featured) {
            $query->where('id', '!=', $featured->id);
        }

        $items = $query->orderByDesc('published_at')->get()->map(fn ($n) => $this->mapNewsCard($n))->all();

        $categories = PublicationCategory::withCount(['news' => fn ($q) => $q->published()])
            ->whereHas('news', fn ($q) => $q->published())
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'count' => $c->news_count])
            ->all();

        $latest = News::published()
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['title', 'slug', 'published_at'])
            ->map(fn ($n) => [
                'title' => $n->title,
                'slug' => $n->slug,
                'day' => $n->published_at?->format('d'),
                'month' => $n->published_at?->translatedFormat('M'),
                'date' => $n->published_at?->format('d M Y'),
            ])->all();

        return $this->render($request, 'Home/Publikasi/Berita', [
            'featured' => $featured ? $this->mapFeaturedNews($featured) : null,
            'articles' => $items,
            'categories' => $categories,
            'latest' => $latest,
        ]);
    }

    public function newsShow(Request $request, string $slug): Response
    {
        $article = News::published()
            ->with(['creator', 'category'])
            ->where('slug', $slug)
            ->firstOrFail();

        $related = collect();
        if ($article->category_id) {
            $related = News::published()
                ->where('category_id', $article->category_id)
                ->where('id', '!=', $article->id)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn ($item) => [
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'image' => $item->featured_image ? Storage::url($item->featured_image) : null,
                    'excerpt' => $item->excerpt ? strip_tags((string) $item->excerpt) : Str::limit(strip_tags((string) $item->content), 120),
                ])->all();
        }

        $latest = News::published()
            ->where('id', '!=', $article->id)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['title', 'slug', 'published_at'])
            ->map(fn ($n) => [
                'title' => $n->title,
                'slug' => $n->slug,
                'day' => $n->published_at?->format('d'),
                'month' => $n->published_at?->translatedFormat('M'),
                'date' => $n->published_at?->format('d M Y'),
            ])->all();

        return $this->render($request, 'Home/Publikasi/BeritaShow', [
            'article' => [
                'title' => $article->title,
                'content' => $article->content,
                'categoryName' => $article->category?->name,
                'author' => $article->creator->name ?? 'Tim Humas NexaCampus',
                'publishedAt' => $article->published_at?->translatedFormat('d M Y'),
                'readingTime' => max(1, (int) ceil(mb_strlen(strip_tags((string) $article->content)) / 1000)),
                'image' => $article->featured_image ? Storage::url($article->featured_image) : null,
                'shareUrl' => $request->url(),
            ],
            'related' => $related,
            'latest' => $latest,
            'categories' => $this->newsCategories(),
        ]);
    }

    public function agenda(Request $request): Response
    {
        $events = Agenda::published()
            ->with('category')
            ->orderBy('event_date')
            ->get()
            ->map(fn ($e) => $this->mapAgendaCard($e))
            ->values()
            ->all();

        $categories = PublicationCategory::whereHas('agendas', fn ($q) => $q->published())
            ->withCount(['agendas' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'count' => $c->agendas_count])
            ->all();

        $months = Agenda::published()
            ->whereNotNull('event_date')
            ->selectRaw('YEAR(event_date) as year, MONTH(event_date) as month')
            ->distinct()
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($m) => sprintf('%04d-%02d', $m->year, $m->month))
            ->all();

        $featuredEvent = Agenda::published()->upcoming()->with('category')->orderBy('event_date')->first();
        $latestEvents = Agenda::published()->upcoming()->with('category')->orderBy('event_date')->limit(4)->get()
            ->map(fn ($e) => $this->mapAgendaCard($e))->all();

        return $this->render($request, 'Home/Publikasi/Agenda', [
            'events' => $events,
            'upcomingCount' => Agenda::published()->upcoming()->count(),
            'pastCount' => Agenda::published()->past()->count(),
            'totalEvents' => Agenda::published()->count(),
            'months' => $months,
            'categories' => $categories,
            'featuredEvent' => $featuredEvent ? $this->mapAgendaCard($featuredEvent) : null,
            'latestEvents' => $latestEvents,
        ]);
    }

    public function agendaShow(Request $request, string $slug): Response
    {
        $event = Agenda::published()
            ->with(['creator', 'category'])
            ->where('slug', $slug)
            ->firstOrFail();

        $categories = PublicationCategory::whereHas('agendas', fn ($q) => $q->published())
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])
            ->all();

        $latestEvents = Agenda::published()->upcoming()
            ->where('id', '!=', $event->id)
            ->with('category')
            ->orderBy('event_date')
            ->limit(4)
            ->get()
            ->map(fn ($e) => $this->mapAgendaCard($e))
            ->all();

        return $this->render($request, 'Home/Publikasi/AgendaShow', [
            'event' => array_merge($this->mapAgendaCard($event), [
                'description' => $event->description,
            ]),
            'isUpcoming' => (bool) ($event->event_date && $event->event_date >= now()->startOfDay()),
            'categories' => $categories,
            'latestEvents' => $latestEvents,
        ]);
    }

    public function galeri(Request $request): Response
    {
        $albums = GalleryAlbum::published()
            ->with(['images', 'category'])
            ->latest()
            ->get()
            ->map(fn ($album) => [
                'id' => $album->id,
                'title' => $album->title,
                'description' => $album->description,
                'cover' => $album->cover_image_path
                    ? Storage::disk('public')->url($album->cover_image_path)
                    : ($album->images->first()
                        ? Storage::disk('public')->url($album->images->first()->image_path)
                        : null),
                'imageCount' => $album->images->count(),
                'categoryName' => $album->category?->name ?? 'Umum',
                'images' => $album->images->map(fn ($image) => [
                    'url' => Storage::disk('public')->url($image->image_path),
                    'caption' => $image->caption,
                ])->all(),
            ])->all();

        return $this->render($request, 'Home/Publikasi/Galeri', ['albums' => $albums]);
    }

    public function kontak(Request $request): Response
    {
        $campus = Campus::first();
        $locations = CampusLocation::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get()
            ->map(fn (CampusLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'address' => $location->address,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'isMain' => (bool) $location->is_main,
            ])
            ->values();

        if ($locations->isEmpty() && $campus?->latitude !== null && $campus?->longitude !== null) {
            $locations = collect([[
                'id' => 'settings',
                'name' => $campus->name,
                'code' => 'MAIN',
                'address' => collect([$campus->address, $campus->city, $campus->province, $campus->postal_code])
                    ->filter()
                    ->implode(', '),
                'latitude' => (float) $campus->latitude,
                'longitude' => (float) $campus->longitude,
                'isMain' => true,
            ]]);
        }

        return $this->render($request, 'Home/Publikasi/Kontak', [
            'contact' => $campus ? [
                'phone' => $campus->phone,
                'faximile' => $campus->faximile,
                'whatsapp' => $campus->whatsapp,
                'emailInfo' => $campus->email_info,
                'emailHumas' => $campus->email_humas,
                'address' => $campus->address,
                'city' => $campus->city,
                'province' => $campus->province,
                'postalCode' => $campus->postal_code,
                'latitude' => $campus->latitude,
                'longitude' => $campus->longitude,
                'locations' => $locations->all(),
                'instagram' => $campus->instagram,
                'facebook' => $campus->facebook,
                'xtwitter' => $campus->xtwitter,
                'linkedin' => $campus->linkedin,
                'tiktok' => $campus->tiktok,
            ] : null,
        ]);
    }

    private function mapNewsCard(News $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->title,
            'slug' => $n->slug,
            'excerpt' => $n->excerpt ? strip_tags((string) $n->excerpt) : Str::limit(strip_tags((string) $n->content), 130),
            'readingTime' => max(1, (int) ceil(mb_strlen(strip_tags((string) $n->content)) / 1000)),
            'publishedAt' => $n->published_at?->format('d M Y'),
            'day' => $n->published_at?->format('d'),
            'month' => $n->published_at?->translatedFormat('M'),
            'image' => $n->featured_image ? Storage::url($n->featured_image) : null,
            'categorySlug' => $n->category?->slug,
            'categoryName' => $n->category?->name,
        ];
    }

    private function mapFeaturedNews(News $n): array
    {
        return array_merge($this->mapNewsCard($n), [
            'lede' => $n->excerpt ? strip_tags((string) $n->excerpt) : Str::limit(strip_tags((string) $n->content), 220),
        ]);
    }

    private function mapAgendaCard(Agenda $e): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'slug' => $e->slug,
            'excerpt' => Str::limit(strip_tags((string) ($e->description ?? '')), 120),
            'eventDate' => $e->event_date?->format('Y-m-d'),
            'day' => $e->event_date?->format('d'),
            'month' => $e->event_date?->translatedFormat('M'),
            'fullDate' => $e->event_date?->translatedFormat('l, d F Y'),
            'time' => filled($e->event_time) ? substr((string) $e->event_time, 0, 5) : null,
            'location' => $e->location,
            'isToday' => (bool) $e->event_date?->isToday(),
            'isUpcoming' => (bool) ($e->event_date && $e->event_date >= now()->startOfDay()),
            'categoryName' => $e->category?->name,
            'categorySlug' => $e->category?->slug,
        ];
    }

    private function newsCategories(): array
    {
        return PublicationCategory::withCount(['news' => fn ($q) => $q->published()])
            ->whereHas('news', fn ($q) => $q->published())
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'count' => $c->news_count])
            ->all();
    }

    private function render(Request $request, string $component, array $props = []): Response
    {
        $user = $request->user();
        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute) ? route($dashboardRoute) : route('auth.select-role');

        return Inertia::render($component, array_merge([
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
                'description' => \App\Models\Settings\System::value('app_description') ?? 'Sistem informasi akademik perguruan tinggi terpadu.',
            ],
            'user' => \App\Support\Inertia\PublicUser::make($user, $dashboardUrl),
            'links' => [
                'login' => route('auth.signin-index'),
                'admission' => route('root.admission.apply'),
                'admissionStatus' => route('root.admission.status'),
                'tuition' => route('root.admission.tuition'),
                'requirements' => route('root.admission.requirements'),
                'faq' => route('root.faq'),
                'contact' => route('root.kontak'),
                'announcements' => route('root.publication.announcements'),
            ],
        ], $props));
    }
}
