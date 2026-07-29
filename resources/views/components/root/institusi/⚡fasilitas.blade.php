<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Institusi',
            'pages' => 'Fasilitas Kampus',
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
                                <span>Infrastruktur Terbaik</span>
                            </div>
                            <h1 class="admission-title mb-3">Fasilitas<br><span style="opacity:.8">Kampus</span></h1>
                            <p class="admission-subtitle mb-0">
                                Jelajahi berbagai fasilitas modern dan berstandar internasional yang disiapkan untuk mendukung pengalaman belajar dan riset Anda.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-5">
                    @foreach([
                        ['title'=>'Perpustakaan Pusat','icon'=>'fa-book-open-reader','color'=>'#3b82f6','desc'=>'Koleksi literatur lengkap, ruang baca nyaman, dan akses jurnal internasional terkemuka.'],
                        ['title'=>'Laboratorium Modern','icon'=>'fa-flask','color'=>'#10b981','desc'=>'Dilengkapi dengan perangkat mutakhir untuk riset sains, teknologi, dan komputasi.'],
                        ['title'=>'Pusat Olahraga','icon'=>'fa-dumbbell','color'=>'#f59e0b','desc'=>'Stadion mini, lapangan basket indoor, lapangan tenis, dan fasilitas kebugaran.'],
                        ['title'=>'Student Center','icon'=>'fa-building','color'=>'#8b5cf6','desc'=>'Pusat kegiatan UKM, ruang diskusi terbuka, dan food court mahasiswa.'],
                    ] as $org)
                    <div class="col-lg-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100 d-flex align-items-center gap-4">
                            <div style="width:64px;height:64px;border-radius:18px;background:{{ $org['color'] }}15;color:{{ $org['color'] }};display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;">
                                <i class="fas {{ $org['icon'] }}"></i>
                            </div>
                            <div>
                                <h4 class="fw-bolder text-body mb-2" style="font-size:1.1rem;">{{ $org['title'] }}</h4>
                                <p class="text-muted mb-0" style="font-size:.9rem;line-height:1.6;">{{ $org['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</div>
