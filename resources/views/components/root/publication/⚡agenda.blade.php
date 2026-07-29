<?php

use Livewire\Component;
use App\Models\Publication\Announcement;
use App\Enums\AnnouncementTargetType;
use Illuminate\Support\Str;

new class extends Component
{
    public array $upcoming = [];
    public array $past = [];

    public function mount(): void
    {
        $items = Announcement::published()
            ->where('target_type', AnnouncementTargetType::GLOBAL)
            ->orderByDesc('published_at')
            ->limit(30)
            ->get();

        // Treat scheduled_at as event date; fallback to published_at
        $this->upcoming = $items->filter(fn($a) =>
            ($a->scheduled_at ?? $a->published_at)?->isFuture()
        )->map(fn($a) => [
            'id'           => $a->id,
            'title'        => $a->title,
            'excerpt'      => Str::limit(strip_tags($a->content), 150),
            'event_date'   => ($a->scheduled_at ?? $a->published_at)?->format('d M Y'),
            'event_day'    => ($a->scheduled_at ?? $a->published_at)?->format('d'),
            'event_month'  => ($a->scheduled_at ?? $a->published_at)?->format('M'),
            'priority'     => $a->priority?->value ?? 'normal',
            'is_pinned'    => $a->is_pinned,
        ])->values()->toArray();

        $this->past = $items->filter(fn($a) =>
            !($a->scheduled_at ?? $a->published_at)?->isFuture()
        )->take(12)->map(fn($a) => [
            'id'           => $a->id,
            'title'        => $a->title,
            'excerpt'      => Str::limit(strip_tags($a->content), 120),
            'published_at' => $a->published_at?->format('d M Y'),
            'priority'     => $a->priority?->value ?? 'normal',
        ])->values()->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => 'Agenda & Kegiatan',
        ]);
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">

                {{-- Hero --}}
                <div class="admission-hero mb-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Kalender Kegiatan</span>
                            </div>
                            <h1 class="admission-title mb-3">Agenda &<br><span style="opacity:.8">Kegiatan Kampus</span></h1>
                            <p class="admission-subtitle mb-0">
                                Pantau seluruh kegiatan dan event yang diselenggarakan oleh NexaCampus. Jangan sampai ketinggalan!
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Kegiatan Mendatang</div>
                                    <div class="h3 text-white mb-0 fw-bolder">{{ count($upcoming) }} Event</div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-calendar-check text-white-50"></i>
                                    <small class="text-white-50">{{ count($past) }} kegiatan telah selesai dilaksanakan</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($upcoming) > 0)
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <div class="step-badge" style="background:linear-gradient(135deg,#10b981,#059669);"><i class="fas fa-calendar-check"></i></div>
                        <h3 class="fw-bolder text-body mb-0" style="font-size:1.1rem;">Kegiatan Mendatang</h3>
                    </div>
                    <div class="row g-3">
                        @foreach($upcoming as $event)
                        <div class="col-lg-4 col-sm-6">
                            <div class="admission-card border-0 rounded-3 shadow-sm overflow-hidden h-100" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                                <div class="d-flex">
                                    <div class="p-3 d-flex flex-column align-items-center justify-content-center text-white flex-shrink-0" style="background:linear-gradient(135deg,#10b981,#059669);min-width:72px;">
                                        <span class="fw-black lh-1" style="font-size:1.8rem;">{{ $event['event_day'] }}</span>
                                        <span style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">{{ $event['event_month'] }}</span>
                                    </div>
                                    <div class="p-3 flex-fill">
                                        @if($event['is_pinned'])<span class="badge bg-danger text-white mb-1" style="font-size:.65rem;">Prioritas</span>@endif
                                        <h5 class="fw-bolder text-body mb-1" style="font-size:.88rem;line-height:1.3;">{{ $event['title'] }}</h5>
                                        <p class="text-muted mb-0" style="font-size:.78rem;line-height:1.5;">{{ $event['excerpt'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(count($past) > 0)
                <div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="step-badge" style="background:linear-gradient(135deg,#64748b,#475569);"><i class="fas fa-clock-rotate-left"></i></div>
                        <h3 class="fw-bolder text-body mb-0" style="font-size:1.1rem;">Arsip Kegiatan</h3>
                    </div>
                    <div class="admission-card border-0 rounded-3 shadow-sm overflow-hidden">
                        @foreach($past as $i => $item)
                        <div class="d-flex align-items-start gap-3 p-3 {{ $i < count($past)-1 ? 'border-bottom' : '' }}" style="border-color:var(--tblr-border-color)!important;">
                            <div class="text-muted flex-shrink-0" style="font-size:.78rem;min-width:80px;">{{ $item['published_at'] }}</div>
                            <div class="flex-fill">
                                <div class="fw-semibold text-body" style="font-size:.88rem;">{{ $item['title'] }}</div>
                                <div class="text-muted" style="font-size:.78rem;">{{ $item['excerpt'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(count($upcoming) === 0 && count($past) === 0)
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#10b981,#059669);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-calendar-check"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Belum Ada Agenda</h4>
                    <p class="text-muted mb-0">Kegiatan dan event kampus akan muncul di sini segera setelah dipublikasikan.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
