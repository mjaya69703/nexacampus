<?php

use Livewire\Component;
use App\Models\Publication\Announcement;
use App\Enums\AnnouncementTargetType;
use Illuminate\Support\Str;

new class extends Component
{
    public array $news = [];
    public int $total = 0;

    public function mount(): void
    {
        // Berita = global announcements with high/urgent priority
        $items = Announcement::published()
            ->where('target_type', AnnouncementTargetType::GLOBAL)
            ->whereIn('priority', ['high', 'urgent'])
            ->orderByDesc('published_at')
            ->limit(24)
            ->get();

        $this->total = $items->count();

        $this->news = $items->map(fn($a) => [
            'id'          => $a->id,
            'title'       => $a->title,
            'excerpt'     => Str::limit(strip_tags($a->content), 200),
            'content'     => $a->content,
            'priority'    => $a->priority?->value ?? 'normal',
            'published_at'=> $a->published_at?->format('d M Y'),
            'is_pinned'   => $a->is_pinned,
            'has_attachment' => filled($a->attachment_path),
        ])->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => 'Berita Kampus',
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
                                <span>Liputan Terkini</span>
                            </div>
                            <h1 class="admission-title mb-3">Berita &<br><span style="opacity:.8">Kabar Terbaru Kampus</span></h1>
                            <p class="admission-subtitle mb-0">
                                Ikuti perkembangan terkini dari NexaCampus — kegiatan akademik, prestasi, dan informasi penting lainnya.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Total Berita</div>
                                    <div class="h3 text-white mb-0 fw-bolder">{{ $total }} Artikel</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-newspaper text-white-50"></i>
                                    <small class="text-white-50">Diperbarui setiap hari kerja oleh tim humas kampus</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($news) > 0)
                {{-- Featured (first item) --}}
                @php $featured = $news[0]; $rest = array_slice($news, 1); @endphp
                <div class="admission-card border-0 rounded-4 shadow-sm overflow-hidden mb-4">
                    <div class="row g-0">
                        <div class="col-lg-5 d-flex align-items-center justify-content-center p-5" style="background:linear-gradient(135deg,#1e293b,#0f172a);min-height:240px;">
                            <div class="text-center">
                                <div style="width:72px;height:72px;border-radius:24px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;box-shadow:0 12px 32px rgba(59,130,246,.4);">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <span class="badge bg-primary text-white fw-bold px-3 py-2">Berita Utama</span>
                            </div>
                        </div>
                        <div class="col-lg-7 p-5">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-warning-lt text-warning fw-semibold">Prioritas Tinggi</span>
                                @if($featured['is_pinned'])<span class="badge bg-danger text-white fw-semibold"><i class="fas fa-thumbtack me-1"></i>Disematkan</span>@endif
                            </div>
                            <h2 class="fw-bolder text-body mb-3" style="font-size:1.3rem;line-height:1.4;">{{ $featured['title'] }}</h2>
                            <p class="text-muted mb-4" style="font-size:.9rem;line-height:1.7;">{{ $featured['excerpt'] }}</p>
                            <div class="text-muted" style="font-size:.8rem;"><i class="fas fa-calendar-days me-1"></i>{{ $featured['published_at'] }}</div>
                        </div>
                    </div>
                </div>

                @if(count($rest) > 0)
                <div class="row g-3">
                    @foreach($rest as $item)
                    <div class="col-lg-4 col-sm-6">
                        <div class="admission-card border-0 rounded-3 shadow-sm p-4 h-100" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="badge bg-primary-lt text-primary fw-semibold" style="font-size:.7rem;">Berita</span>
                                @if($item['has_attachment'])<span class="badge bg-secondary-lt text-secondary" style="font-size:.7rem;"><i class="fas fa-paperclip me-1"></i>Lampiran</span>@endif
                            </div>
                            <h5 class="fw-bolder text-body mb-2" style="font-size:.9rem;line-height:1.4;">{{ $item['title'] }}</h5>
                            <p class="text-muted mb-3" style="font-size:.8rem;line-height:1.6;">{{ Str::limit($item['excerpt'], 120) }}</p>
                            <div class="text-muted" style="font-size:.73rem;"><i class="fas fa-calendar-days me-1"></i>{{ $item['published_at'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-newspaper"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Belum Ada Berita</h4>
                    <p class="text-muted mb-0">Artikel berita akan muncul di sini segera setelah dipublikasikan oleh tim humas.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
