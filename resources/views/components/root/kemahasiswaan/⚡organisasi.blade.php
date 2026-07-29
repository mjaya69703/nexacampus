<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Kemahasiswaan',
            'pages' => 'Organisasi Mahasiswa',
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
                                <span>Kegiatan Ekstrakurikuler</span>
                            </div>
                            <h1 class="admission-title mb-3">Organisasi<br><span style="opacity:.8">Kemahasiswaan</span></h1>
                            <p class="admission-subtitle mb-0">
                                Kembangkan jiwa kepemimpinan dan jejaring Anda melalui berbagai wadah organisasi intra kampus yang aktif dan dinamis.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Total Ormawa</div>
                                    <div class="h3 text-white mb-0 fw-bolder">25+ Organisasi</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-users text-white-50"></i>
                                    <small class="text-white-50">Aktif menyelenggarakan program kerja setiap tahun.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Categories --}}
                <div class="row g-4">
                    @foreach([
                        ['title'=>'BEM & DPM','icon'=>'fa-gavel','color'=>'#ef4444','desc'=>'Badan Eksekutif dan Dewan Perwakilan Mahasiswa tingkat universitas dan fakultas.'],
                        ['title'=>'HIMA','icon'=>'fa-sitemap','color'=>'#3b82f6','desc'=>'Himpunan Mahasiswa Program Studi yang berfokus pada pengembangan keilmuan spesifik.'],
                        ['title'=>'UKM Olahraga','icon'=>'fa-volleyball','color'=>'#10b981','desc'=>'Wadah penyaluran bakat olahraga mulai dari basket, futsal, hingga e-sports.'],
                        ['title'=>'UKM Seni','icon'=>'fa-palette','color'=>'#f59e0b','desc'=>'Kembangkan kreativitas melalui paduan suara, teater, tari tradisional, dan band.'],
                        ['title'=>'UKM Kerohanian','icon'=>'fa-praying-hands','color'=>'#8b5cf6','desc'=>'Organisasi pembinaan mental dan spiritual untuk berbagai agama.'],
                        ['title'=>'UKM Penalaran','icon'=>'fa-lightbulb','color'=>'#06b6d4','desc'=>'Fokus pada riset, jurnalistik, robotika, dan kewirausahaan mahasiswa.'],
                    ] as $org)
                    <div class="col-lg-4 col-sm-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='none'">
                            <div style="width:56px;height:56px;border-radius:16px;background:{{ $org['color'] }}15;color:{{ $org['color'] }};margin-bottom:1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                                <i class="fas {{ $org['icon'] }}"></i>
                            </div>
                            <h4 class="fw-bolder text-body mb-2" style="font-size:1.1rem;">{{ $org['title'] }}</h4>
                            <p class="text-muted mb-0" style="font-size:.9rem;line-height:1.6;">{{ $org['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</div>
