<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Institusi',
            'pages' => 'Visi & Misi',
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
                            <h1 class="admission-title mb-4">Visi & Misi<br><span style="opacity:.8">NexaCampus</span></h1>
                            <p class="admission-subtitle mb-0 px-lg-5">
                                Arah strategis dan komitmen kami dalam membangun pendidikan masa depan yang berdaya saing global dan berlandaskan nilai luhur.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center mb-5">
                    <div class="col-lg-10">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-4" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;">
                            <h3 class="fw-black text-uppercase mb-4" style="letter-spacing:0.1em;font-size:1.1rem;color:rgba(255,255,255,.7);">Visi Kami</h3>
                            <p class="fw-semibold fst-italic mb-0" style="font-size:1.4rem;line-height:1.6;">
                                "Menjadi perguruan tinggi unggul berkelas dunia yang inovatif, adaptif, dan berwawasan lingkungan dalam pengembangan IPTEK untuk kesejahteraan masyarakat pada tahun 2030."
                            </p>
                        </div>
                        
                        <div class="admission-card border-0 rounded-4 shadow-sm p-5">
                            <h3 class="fw-black text-uppercase mb-4 text-center text-primary" style="letter-spacing:0.1em;font-size:1.1rem;">Misi Kami</h3>
                            <ol class="text-muted" style="line-height:2;font-size:1.05rem;padding-left:1.5rem;">
                                <li class="mb-3">Menyelenggarakan pendidikan berkualitas yang berstandar internasional untuk menghasilkan lulusan yang kompeten, berkarakter, dan berjiwa wirausaha.</li>
                                <li class="mb-3">Melaksanakan penelitian dasar dan terapan yang inovatif untuk memecahkan permasalahan bangsa dan berkontribusi pada perkembangan ilmu pengetahuan.</li>
                                <li class="mb-3">Mendedikasikan hasil pendidikan dan penelitian melalui kegiatan pengabdian yang memberdayakan masyarakat.</li>
                                <li>Membangun tata kelola universitas yang mandiri, transparan, dan akuntabel (Good University Governance).</li>
                            </ol>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
