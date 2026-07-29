<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Institusi',
            'pages' => 'Struktur Pimpinan',
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
                                <span>Manajemen Universitas</span>
                            </div>
                            <h1 class="admission-title mb-3">Struktur<br><span style="opacity:.8">Pimpinan</span></h1>
                            <p class="admission-subtitle mb-0">
                                Berkenalan dengan jajaran pimpinan yang mendedikasikan diri untuk kemajuan dan pengembangan institusi pendidikan NexaCampus.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-5">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#64748b,#475569);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-sitemap"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Halaman Sedang Dalam Pengembangan</h4>
                    <p class="text-muted mb-0">Bagan dan profil pimpinan institusi akan segera ditampilkan.</p>
                </div>

            </div>
        </div>
    </div>
</div>
