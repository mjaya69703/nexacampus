<?php

use Livewire\Component;
use App\Models\Settings\Campus;

new class extends Component
{
    public ?Campus $campus = null;

    public function mount(): void
    {
        $this->campus = Campus::first();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Institusi',
            'pages' => 'Profil Kampus',
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
                                <span>Mengenal Lebih Dekat</span>
                            </div>
                            <h1 class="admission-title mb-3">Profil<br><span style="opacity:.8">{{ $campus?->name ?? 'NexaCampus' }}</span></h1>
                            <p class="admission-subtitle mb-4">
                                Berkomitmen untuk mencetak generasi unggul yang siap bersaing secara global melalui pendidikan berkualitas dan berkarakter.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.institusi.visi-misi') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-bullseye"></i> Visi & Misi
                                </a>
                                <a href="{{ route('root.kontak') }}" class="btn btn-outline-light border-0 px-3 fw-bold d-flex align-items-center gap-2" style="background:rgba(255,255,255,.1);">
                                    <i class="fas fa-address-book"></i> Hubungi Kami
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            @if($campus && $campus->logo_vertikal)
                            <div class="d-flex justify-content-center p-4">
                                <img src="{{ $campus->logo_vertikal }}" alt="Logo {{ $campus->name }}" style="max-height:220px;filter:drop-shadow(0 10px 20px rgba(0,0,0,.2));">
                            </div>
                            @else
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Tahun Berdiri</div>
                                        <div class="h3 text-white mb-0 fw-bolder">Sejak 1990</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Akreditasi A</span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-5">
                    <div class="col-lg-8">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-5 h-100">
                            <h3 class="fw-bolder text-body mb-4" style="font-size:1.4rem;">Tentang {{ $campus?->name ?? 'NexaCampus' }}</h3>
                            <div class="text-muted" style="line-height:1.8;font-size:.95rem;">
                                <p>Selamat datang di <strong>{{ $campus?->name ?? 'NexaCampus' }}</strong>, institusi pendidikan tinggi terdepan yang berdedikasi untuk mencetak lulusan kompeten, inovatif, dan berintegritas. Sejak didirikan, kami terus berinovasi dalam mengintegrasikan teknologi ke dalam proses pembelajaran dan tridharma perguruan tinggi.</p>
                                <p>Dengan fasilitas kampus yang modern, tenaga pengajar profesional berstandar industri, dan jejaring kerja sama yang luas, kami membekali setiap mahasiswa tidak hanya dengan teori akademik, tetapi juga keterampilan praktis yang dibutuhkan di dunia kerja global.</p>
                                <p class="mb-0">Lingkungan belajar yang inklusif dan dinamis di {{ $campus?->name ?? 'NexaCampus' }} mendorong eksplorasi potensi diri secara maksimal, baik dalam bidang akademik maupun non-akademik.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100 d-flex flex-column gap-4" style="background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold" style="font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;">
                                    <i class="fas fa-location-dot"></i> Lokasi Kampus
                                </div>
                                <div class="fw-bolder text-body mb-1" style="font-size:1.1rem;">Kampus Utama</div>
                                <div class="text-muted" style="font-size:.9rem;line-height:1.5;">
                                    {{ $campus?->address ?? 'Jl. Pendidikan No. 1, Kota Akademik' }}<br>
                                    {{ $campus?->city ?? 'Kota' }}, {{ $campus?->province ?? 'Provinsi' }} {{ $campus?->postal_code ?? '' }}
                                </div>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold" style="font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;">
                                    <i class="fas fa-address-card"></i> Informasi Kontak
                                </div>
                                <div class="d-flex flex-column gap-2 text-muted" style="font-size:.9rem;">
                                    @if($campus?->phone)<div><i class="fas fa-phone me-2 text-secondary"></i>{{ $campus->phone }}</div>@endif
                                    @if($campus?->whatsapp)<div><i class="fab fa-whatsapp me-2 text-success" style="font-size:1.1rem;"></i>{{ $campus->whatsapp }}</div>@endif
                                    @if($campus?->email_info)<div><i class="fas fa-envelope me-2 text-secondary"></i>{{ $campus->email_info }}</div>@endif
                                </div>
                            </div>
                            <div class="mt-auto pt-4 border-top">
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-primary w-100 fw-bold">Bergabung Bersama Kami</a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Fast Facts --}}
                <div class="row g-4 mb-5">
                    @foreach([
                        ['icon'=>'fa-user-graduate','color'=>'#3b82f6','val'=>'15K+','label'=>'Alumni Sukses'],
                        ['icon'=>'fa-chalkboard-user','color'=>'#8b5cf6','val'=>'500+','label'=>'Dosen Pakar'],
                        ['icon'=>'fa-building-columns','color'=>'#10b981','val'=>'30+','label'=>'Program Studi'],
                        ['icon'=>'fa-handshake','color'=>'#f59e0b','val'=>'100+','label'=>'Mitra Industri'],
                    ] as $fact)
                    <div class="col-lg-3 col-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 text-center">
                            <div style="width:56px;height:56px;border-radius:16px;background:{{ $fact['color'] }}15;color:{{ $fact['color'] }};margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                                <i class="fas {{ $fact['icon'] }}"></i>
                            </div>
                            <div class="fw-black text-body" style="font-size:1.8rem;line-height:1;">{{ $fact['val'] }}</div>
                            <div class="text-muted fw-semibold mt-1" style="font-size:.85rem;">{{ $fact['label'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</div>
