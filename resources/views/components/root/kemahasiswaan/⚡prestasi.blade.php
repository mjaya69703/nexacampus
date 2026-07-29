<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Kemahasiswaan',
            'pages' => 'Prestasi Mahasiswa',
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
                                <span>Kebanggaan Kampus</span>
                            </div>
                            <h1 class="admission-title mb-3">Prestasi &<br><span style="opacity:.8">Penghargaan</span></h1>
                            <p class="admission-subtitle mb-0">
                                Mahasiswa NexaCampus terus mengukir prestasi gemilang di tingkat nasional maupun internasional. Jadilah bagian dari sejarah kami.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Akumulasi Prestasi</div>
                                    <div class="h3 text-white mb-0 fw-bolder">300+ Medali</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-medal text-white-50"></i>
                                    <small class="text-white-50">Dalam 5 tahun terakhir.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-5">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#f59e0b,#d97706);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-trophy"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Halaman Sedang Dalam Pengembangan</h4>
                    <p class="text-muted mb-0">Galeri prestasi mahasiswa akan segera diluncurkan.</p>
                </div>

            </div>
        </div>
    </div>
</div>
