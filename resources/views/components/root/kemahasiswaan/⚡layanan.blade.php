<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Kemahasiswaan',
            'pages' => 'Layanan Mahasiswa',
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
                                <span>Pusat Bantuan Terpadu</span>
                            </div>
                            <h1 class="admission-title mb-3">Layanan<br><span style="opacity:.8">Mahasiswa</span></h1>
                            <p class="admission-subtitle mb-0">
                                Kami menyediakan berbagai fasilitas layanan kesehatan, konseling psikologi, dan layanan administrasi terpadu untuk kelancaran studi Anda.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-5">
                    @foreach([
                        ['title'=>'Klinik Kesehatan','icon'=>'fa-stethoscope','color'=>'#ef4444','desc'=>'Layanan medis dasar dan pertolongan pertama gratis bagi sivitas akademika.'],
                        ['title'=>'Konseling Psikologi','icon'=>'fa-brain','color'=>'#8b5cf6','desc'=>'Pendampingan profesional untuk kesehatan mental dan problem akademik.'],
                        ['title'=>'Student Service Center','icon'=>'fa-users-gear','color'=>'#3b82f6','desc'=>'Pusat pelayanan administrasi satu pintu untuk efisiensi birokrasi mahasiswa.'],
                    ] as $org)
                    <div class="col-lg-4 col-sm-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100">
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
