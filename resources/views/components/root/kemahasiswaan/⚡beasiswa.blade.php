<?php

use Livewire\Component;
use App\Models\Financial\Scholarship;
use Illuminate\Support\Str;

new class extends Component
{
    public array $scholarships = [];

    public function mount(): void
    {
        $this->scholarships = Scholarship::where('is_active', true)
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'type'        => $s->type,
                'description' => Str::limit(strip_tags($s->description), 160),
                'full_desc'   => $s->description,
                'requirements'=> $s->requirements,
                'discount'    => $s->discount_type === 'percentage' 
                                 ? (float)$s->discount_percentage . '%' 
                                 : 'Rp ' . number_format($s->fixed_amount, 0, ',', '.'),
                'duration'    => $s->duration_semesters,
            ])->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Kemahasiswaan',
            'pages' => 'Beasiswa',
        ]);
    }
};
?>

@php
$typeIcon = [
    'internal'   => ['icon'=>'fa-building-columns', 'color'=>'#3b82f6', 'bg'=>'rgba(59,130,246,.1)'],
    'government' => ['icon'=>'fa-landmark',         'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,.1)'],
    'corporate'  => ['icon'=>'fa-handshake',        'color'=>'#10b981', 'bg'=>'rgba(16,185,129,.1)'],
    'foundation' => ['icon'=>'fa-dove',             'color'=>'#8b5cf6', 'bg'=>'rgba(139,92,246,.1)'],
];
@endphp

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
                                <span>Bantuan Pendidikan</span>
                            </div>
                            <h1 class="admission-title mb-3">Program<br><span style="opacity:.8">Beasiswa & Keringanan</span></h1>
                            <p class="admission-subtitle mb-4">
                                Kami berkomitmen memberikan akses pendidikan tinggi terbaik bagi seluruh mahasiswa berprestasi dan yang membutuhkan bantuan finansial.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-light px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane text-primary"></i> Daftar Sekarang
                                </a>
                                <a href="{{ route('root.admission.tuition') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-coins"></i> Cek Biaya UKT
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Program Aktif</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ count($scholarships) }} Beasiswa</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Tersedia</span>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-shield-halved text-white-50"></i>
                                        <small class="text-white-50">Didukung oleh pemerintah dan mitra industri terkemuka.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($scholarships) > 0)
                <div class="row g-4">
                    @foreach($scholarships as $s)
                    @php $ti = $typeIcon[$s['type']] ?? ['icon'=>'fa-award','color'=>'#f59e0b','bg'=>'rgba(245,158,11,.1)']; @endphp
                    <div class="col-lg-6">
                        <div class="admission-card border-0 rounded-4 shadow-sm h-100 overflow-hidden d-flex flex-column" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='none'">
                            <div class="p-4 border-bottom d-flex align-items-start justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width:48px;height:48px;border-radius:12px;background:{{ $ti['bg'] }};color:{{ $ti['color'] }};display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">
                                        <i class="fas {{ $ti['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-bolder text-body mb-1" style="font-size:1.1rem;">{{ $s['name'] }}</h4>
                                        <div class="badge bg-light text-muted fw-semibold text-uppercase" style="font-size:.65rem;letter-spacing:.05em;">{{ str_replace('_', ' ', $s['type']) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 flex-fill d-flex flex-column">
                                <p class="text-muted mb-4 flex-fill" style="font-size:.85rem;line-height:1.6;">{{ $s['description'] }}</p>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <div class="p-3 rounded-3" style="background:var(--tblr-bg-surface-secondary);">
                                            <div class="text-muted mb-1" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Bantuan / Potongan</div>
                                            <div class="fw-bolder text-primary" style="font-size:1rem;">{{ $s['discount'] }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 rounded-3" style="background:var(--tblr-bg-surface-secondary);">
                                            <div class="text-muted mb-1" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Durasi (Semester)</div>
                                            <div class="fw-bolder text-body" style="font-size:1rem;">{{ $s['duration'] }} Smt</div>
                                        </div>
                                    </div>
                                </div>

                                @if($s['requirements'])
                                <div>
                                    <div class="fw-bold text-body mb-2" style="font-size:.85rem;">Persyaratan Umum:</div>
                                    <div class="text-muted" style="font-size:.85rem;line-height:1.6;">
                                        {!! $s['requirements'] !!}
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-hand-holding-dollar"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Belum Ada Program Beasiswa</h4>
                    <p class="text-muted mb-0">Informasi program beasiswa akan segera diperbarui oleh biro kemahasiswaan.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
