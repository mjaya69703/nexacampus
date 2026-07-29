<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Institusi',
            'pages' => 'Akreditasi Kampus',
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
                                <span>Pengakuan Nasional & Internasional</span>
                            </div>
                            <h1 class="admission-title mb-3">Akreditasi &<br><span style="opacity:.8">Sertifikasi Kampus</span></h1>
                            <p class="admission-subtitle mb-0">
                                Bukti komitmen kami terhadap kualitas pendidikan yang memenuhi standar mutu nasional dari BAN-PT maupun lembaga internasional.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-5">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-award"></i></div>
                    <h2 class="fw-black text-primary mb-3" style="font-size:2rem;">Akreditasi Institusi: UNGGUL</h2>
                    <p class="text-muted mb-4" style="max-width:600px;margin:0 auto;">Berdasarkan Surat Keputusan BAN-PT, NexaCampus telah meraih predikat akreditasi Unggul yang berlaku hingga 2030.</p>
                    <button class="btn btn-primary px-4 rounded-pill fw-bold"><i class="fas fa-download me-2"></i>Unduh Sertifikat Akreditasi</button>
                </div>

            </div>
        </div>
    </div>
</div>
