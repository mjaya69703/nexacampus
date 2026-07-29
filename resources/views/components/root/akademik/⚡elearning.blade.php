<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Akademik',
            'pages' => 'E-Learning',
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
                    <div class="row align-items-center justify-content-center text-center">
                        <div class="col-lg-8">
                            <div class="admission-kicker d-inline-flex align-items-center gap-2 mb-3">
                                <span class="badge-pulse"></span>
                                <span>Platform Pembelajaran Digital</span>
                            </div>
                            <h1 class="admission-title mb-4">NexaCampus<br><span style="opacity:.8;color:#f59e0b;">E-Learning Portal</span></h1>
                            <p class="admission-subtitle mb-4 px-lg-5">
                                Akses materi perkuliahan, kumpulkan tugas, dan berdiskusi dengan dosen serta mahasiswa lainnya di mana saja dan kapan saja.
                            </p>
                            <a href="{{ route('auth.signin-index') }}" class="btn px-4 py-2 fw-bold text-white shadow-lg" style="background:linear-gradient(135deg,#f59e0b,#b45309);border:none;border-radius:12px;font-size:1.1rem;">
                                <i class="fas fa-right-to-bracket me-2"></i> Login ke E-Learning
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Fitur Unggulan --}}
                <div class="row g-4 mb-5">
                    @foreach([
                        ['title'=>'Materi Terstruktur','icon'=>'fa-box-archive','color'=>'#3b82f6','desc'=>'Unduh modul, slide presentasi, dan video pembelajaran dengan mudah.'],
                        ['title'=>'Pengumpulan Tugas','icon'=>'fa-cloud-arrow-up','color'=>'#10b981','desc'=>'Submit tugas kuliah secara online dengan sistem tracking dan notifikasi deadline.'],
                        ['title'=>'Forum Diskusi','icon'=>'fa-comments','color'=>'#8b5cf6','desc'=>'Ruang kolaborasi asinkronus antara dosen dan mahasiswa di luar jam kuliah.'],
                        ['title'=>'Ujian Online (CBT)','icon'=>'fa-laptop-file','color'=>'#ef4444','desc'=>'Sistem Computer Based Test yang aman dan reliabel untuk UTS & UAS.'],
                    ] as $feat)
                    <div class="col-lg-3 col-sm-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 text-center h-100" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='none'">
                            <div style="width:64px;height:64px;border-radius:50%;background:{{ $feat['color'] }}15;color:{{ $feat['color'] }};margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                                <i class="fas {{ $feat['icon'] }}"></i>
                            </div>
                            <h4 class="fw-bolder text-body mb-2" style="font-size:1.05rem;">{{ $feat['title'] }}</h4>
                            <p class="text-muted mb-0" style="font-size:.85rem;line-height:1.6;">{{ $feat['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Notice Box --}}
                <div class="admission-card border-0 rounded-4 shadow-sm p-4 d-flex align-items-center gap-4 bg-light">
                    <div style="width:56px;height:56px;border-radius:16px;background:var(--tblr-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;">
                        <i class="fas fa-circle-info"></i>
                    </div>
                    <div>
                        <div class="fw-bolder text-body mb-1" style="font-size:1.1rem;">Bantuan Teknis LMS</div>
                        <div class="text-muted" style="font-size:.9rem;">
                            Jika Anda mengalami kendala saat login atau mengakses mata kuliah di E-Learning, silakan hubungi tim Helpdesk IT di menu Kontak atau WhatsApp Center Akademik.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
