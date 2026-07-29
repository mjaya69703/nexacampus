<?php

use Livewire\Component;
use App\Models\Publication\Announcement;
use App\Enums\AnnouncementTargetType;

new class extends Component
{
    public array $pinned = [];
    public array $announcements = [];
    public int $total = 0;

    public function mount(): void
    {
        $query = Announcement::published()
            ->where('target_type', AnnouncementTargetType::GLOBAL)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        $this->total = $query->count();

        $items = $query->limit(30)->get();

        $this->pinned = $items->where('is_pinned', true)
            ->map(fn($a) => [
                'id'          => $a->id,
                'title'       => $a->title,
                'content'     => strip_tags($a->content),
                'priority'    => $a->priority?->value ?? 'normal',
                'published_at'=> $a->published_at?->format('d M Y'),
                'has_attachment' => filled($a->attachment_path),
            ])->values()->toArray();

        $this->announcements = $items->where('is_pinned', false)
            ->map(fn($a) => [
                'id'          => $a->id,
                'title'       => $a->title,
                'content'     => Str::limit(strip_tags($a->content), 160),
                'priority'    => $a->priority?->value ?? 'normal',
                'published_at'=> $a->published_at?->format('d M Y'),
                'has_attachment' => filled($a->attachment_path),
            ])->values()->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => 'Pengumuman',
        ]);
    }
};
?>

@php
use Illuminate\Support\Str;
$priorityMap = [
    'urgent' => ['label' => 'Penting', 'class' => 'bg-red-lt text-danger',    'icon' => 'fa-circle-exclamation'],
    'high'   => ['label' => 'Tinggi',  'class' => 'bg-warning-lt text-warning','icon' => 'fa-arrow-up'],
    'normal' => ['label' => 'Normal',  'class' => 'bg-secondary-lt text-secondary','icon' => 'fa-minus'],
    'low'    => ['label' => 'Rendah',  'class' => 'bg-muted-lt text-muted',   'icon' => 'fa-arrow-down'],
];
@endphp

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
                                <span>Informasi Resmi Kampus</span>
                            </div>
                            <h1 class="admission-title mb-3">Pengumuman<br><span style="opacity:.8">Kampus NexaCampus</span></h1>
                            <p class="admission-subtitle mb-0">
                                Temukan seluruh pengumuman resmi dari civitas akademika NexaCampus — akademik, kemahasiswaan, dan administrasi.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Total Pengumuman</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ $total }} Pengumuman</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Resmi</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ count($pinned) }}</span>
                                            <small class="text-white-50">Disematkan</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ count($announcements) }}</span>
                                            <small class="text-white-50">Terbaru</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($pinned) > 0)
                {{-- Pinned --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="step-badge" style="background:linear-gradient(135deg,#ef4444,#b91c1c);"><i class="fas fa-thumbtack"></i></div>
                        <h3 class="fw-bolder text-body mb-0" style="font-size:1.1rem;">Disematkan</h3>
                    </div>
                    <div class="row g-3">
                        @foreach($pinned as $ann)
                        @php $p = $priorityMap[$ann['priority']] ?? $priorityMap['normal']; @endphp
                        <div class="col-12">
                            <div class="admission-card border-0 rounded-3 shadow-sm p-4 d-flex gap-4 align-items-start" style="border-left:4px solid #ef4444 !important;">
                                <div class="flex-fill">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <span class="badge {{ $p['class'] }} fw-semibold"><i class="fas {{ $p['icon'] }} me-1"></i>{{ $p['label'] }}</span>
                                        <span class="badge bg-danger text-white fw-semibold"><i class="fas fa-thumbtack me-1"></i>Disematkan</span>
                                        @if($ann['has_attachment'])<span class="badge bg-primary-lt text-primary"><i class="fas fa-paperclip me-1"></i>Lampiran</span>@endif
                                    </div>
                                    <h4 class="fw-bolder text-body mb-1" style="font-size:1rem;">{{ $ann['title'] }}</h4>
                                    <p class="text-muted mb-2" style="font-size:.875rem;">{{ $ann['content'] }}</p>
                                    <div class="text-muted" style="font-size:.78rem;"><i class="fas fa-calendar-days me-1"></i>{{ $ann['published_at'] }}</div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Regular --}}
                @if(count($announcements) > 0)
                <div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="step-badge"><i class="fas fa-bullhorn"></i></div>
                        <h3 class="fw-bolder text-body mb-0" style="font-size:1.1rem;">Pengumuman Terbaru</h3>
                    </div>
                    <div class="row g-3">
                        @foreach($announcements as $ann)
                        @php $p = $priorityMap[$ann['priority']] ?? $priorityMap['normal']; @endphp
                        <div class="col-lg-6">
                            <div class="admission-card border-0 rounded-3 shadow-sm p-4 h-100" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge {{ $p['class'] }} fw-semibold" style="font-size:.7rem;"><i class="fas {{ $p['icon'] }} me-1"></i>{{ $p['label'] }}</span>
                                    @if($ann['has_attachment'])<span class="badge bg-primary-lt text-primary" style="font-size:.7rem;"><i class="fas fa-paperclip me-1"></i>Lampiran</span>@endif
                                </div>
                                <h5 class="fw-bolder text-body mb-2" style="font-size:.95rem;">{{ $ann['title'] }}</h5>
                                <p class="text-muted mb-3" style="font-size:.83rem;line-height:1.6;">{{ $ann['content'] }}</p>
                                <div class="text-muted" style="font-size:.75rem;"><i class="fas fa-calendar-days me-1"></i>{{ $ann['published_at'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-bullhorn"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Belum Ada Pengumuman</h4>
                    <p class="text-muted mb-0">Pengumuman kampus akan muncul di sini segera setelah dipublikasikan.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
