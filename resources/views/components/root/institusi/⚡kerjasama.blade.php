<?php

use Livewire\Component;
use App\Models\Alumni\EmployerPartner;

new class extends Component
{
    public array $partners = [];
    public int $total = 0;

    public function mount(): void
    {
        $query = EmployerPartner::where('is_active', true)
            ->whereNotNull('logo_path')
            ->orderBy('name');
            
        $this->total = $query->count();
        
        $this->partners = $query->limit(24)->get()->map(fn($p) => [
            'name'     => $p->name,
            'industry' => $p->industry,
            'website'  => $p->website,
            'logo'     => $p->logo_path,
        ])->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Institusi',
            'pages' => 'Mitra Kerjasama',
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
                                <span>Jejaring Nasional & Global</span>
                            </div>
                            <h1 class="admission-title mb-3">Mitra<br><span style="opacity:.8">Kerjasama Kampus</span></h1>
                            <p class="admission-subtitle mb-0">
                                Berkolaborasi dengan ratusan institusi akademik, pemerintah, dan perusahaan multinasional untuk memajukan pendidikan dan riset.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Mitra Industri Terdaftar</div>
                                    <div class="h3 text-white mb-0 fw-bolder">{{ $total }}+ Partner</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-handshake text-white-50"></i>
                                    <small class="text-white-50">Penyaluran magang, rekrutmen, dan riset bersama.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($partners) > 0)
                <div class="row g-4 mb-5">
                    @foreach($partners as $p)
                    <div class="col-lg-3 col-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-4 text-center h-100 d-flex flex-column align-items-center justify-content-center" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                            <div class="mb-3" style="height:60px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-building text-muted opacity-50" style="font-size:2rem;"></i>
                                {{-- If real images existed, would use: <img src="{{ $p['logo'] }}" style="max-height:100%;max-width:100%;"> --}}
                            </div>
                            <div class="fw-bolder text-body mb-1" style="font-size:.9rem;line-height:1.2;">{{ $p['name'] }}</div>
                            <div class="text-muted" style="font-size:.75rem;">{{ $p['industry'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-5">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-handshake-angle"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Halaman Sedang Dalam Pengembangan</h4>
                    <p class="text-muted mb-0">Daftar mitra kerjasama akan segera ditampilkan.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
