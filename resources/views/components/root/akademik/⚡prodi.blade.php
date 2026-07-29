<?php

use Livewire\Component;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;

new class extends Component
{
    public array $faculties = [];
    public int $totalPrograms = 0;

    public function mount(): void
    {
        $this->totalPrograms = StudyProgram::where('is_active', true)->count();

        $this->faculties = Faculty::with(['studyPrograms' => fn($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->get()
            ->map(fn($f) => [
                'id'         => $f->id,
                'name'       => $f->name,
                'short_name' => $f->short_name,
                'desc'       => $f->desc,
                'programs'   => $f->studyPrograms->map(fn($p) => [
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'code'          => $p->code,
                    'degree'        => $p->degree,
                    'prefix_degree' => $p->prefix_degree,
                    'suffix_degree' => $p->suffix_degree,
                    'desc'          => $p->desc,
                    // Additional rich info for UI
                    'duration'      => $p->degree === 'S1' || $p->degree === 'D4' ? 8 : ($p->degree === 'D3' ? 6 : 4),
                    'credits'       => $p->degree === 'S1' || $p->degree === 'D4' ? 144 : ($p->degree === 'D3' ? 110 : 72),
                ])->toArray(),
            ])->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Akademik',
            'pages' => 'Program Studi',
        ]);
    }
};
?>

@php
$facultyThemes = [
    ['color'=>'#3b82f6', 'icon'=>'fa-microchip', 'grad'=>'linear-gradient(135deg, #3b82f6, #1d4ed8)'],
    ['color'=>'#10b981', 'icon'=>'fa-leaf',      'grad'=>'linear-gradient(135deg, #10b981, #047857)'],
    ['color'=>'#f59e0b', 'icon'=>'fa-chart-line','grad'=>'linear-gradient(135deg, #f59e0b, #b45309)'],
    ['color'=>'#ef4444', 'icon'=>'fa-heart-pulse','grad'=>'linear-gradient(135deg, #ef4444, #b91c1c)'],
    ['color'=>'#8b5cf6', 'icon'=>'fa-scale-balanced','grad'=>'linear-gradient(135deg, #8b5cf6, #6d28d9)'],
    ['color'=>'#ec4899', 'icon'=>'fa-camera-retro','grad'=>'linear-gradient(135deg, #ec4899, #be185d)'],
    ['color'=>'#06b6d4', 'icon'=>'fa-earth-americas','grad'=>'linear-gradient(135deg, #06b6d4, #0369a1)'],
    ['color'=>'#64748b', 'icon'=>'fa-building','grad'=>'linear-gradient(135deg, #64748b, #334155)'],
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
                                <span>Pilihan Akademik Beragam</span>
                            </div>
                            <h1 class="admission-title mb-3">Temukan Minat &<br><span style="opacity:.8">Bakat Anda di Sini</span></h1>
                            <p class="admission-subtitle mb-4">
                                NexaCampus menawarkan berbagai program studi inovatif dan relevan dengan kebutuhan industri. Pilih program studi yang sesuai dengan visi masa depan Anda.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-light px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane text-primary"></i> Daftar Sekarang
                                </a>
                                <a href="{{ route('root.admission.tuition') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-coins"></i> Biaya Kuliah
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow position-relative overflow-hidden">
                                <div class="position-absolute top-0 end-0 p-4 opacity-10 text-white" style="font-size:8rem;margin-top:-2rem;margin-right:-2rem;">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="position-relative z-index-1">
                                    <div class="d-flex justify-content-between align-items-start mb-4 pb-3 border-bottom border-light border-opacity-10">
                                        <div>
                                            <div class="text-white-50 small fw-bold text-uppercase">Total Program</div>
                                            <div class="h1 text-white mb-0 fw-black" style="font-size:3rem;line-height:1;">{{ $totalPrograms }}</div>
                                        </div>
                                        <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold shadow-sm d-flex align-items-center gap-2">
                                            <i class="fas fa-award text-warning"></i> Akreditasi A
                                        </span>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center gap-3">
                                                <div style="width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;color:#fff;">
                                                    <i class="fas fa-building-columns fs-3"></i>
                                                </div>
                                                <div>
                                                    <div class="text-white fw-bold">{{ count($faculties) }} Fakultas Terkemuka</div>
                                                    <div class="text-white-50 small">Siap mencetak lulusan berdaya saing global.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($faculties) > 0)
                <div class="row g-5">
                    @foreach($faculties as $fi => $fac)
                    @php $theme = $facultyThemes[$fi % count($facultyThemes)]; @endphp
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div style="width:64px;height:64px;border-radius:18px;background:{{ $theme['grad'] }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.8rem;flex-shrink:0;box-shadow:0 8px 24px {{ $theme['color'] }}50;">
                                <i class="fas {{ $theme['icon'] }}"></i>
                            </div>
                            <div>
                                <h2 class="fw-bolder text-body mb-1" style="font-size:1.6rem;letter-spacing:-.02em;">{{ $fac['name'] }}</h2>
                                <p class="text-muted mb-0" style="font-size:.95rem;">
                                    @if($fac['desc']){{ $fac['desc'] }}@else Jelajahi berbagai program studi unggulan di {{ $fac['name'] }} yang mendidik Anda dengan fasilitas dan tenaga pengajar profesional. @endif
                                </p>
                            </div>
                        </div>

                        <div class="row g-4">
                            @foreach($fac['programs'] as $prodi)
                            <div class="col-xl-4 col-md-6">
                                <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100 d-flex flex-column" style="transition:all .3s ease;border-top:4px solid {{ $theme['color'] }} !important;" onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 20px 40px rgba(0,0,0,.08)'" onmouseout="this.style.transform='none';this.style.boxShadow=''">
                                    
                                    {{-- Card Header --}}
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <div>
                                            <h4 class="fw-bolder text-body mb-1" style="font-size:1.25rem;line-height:1.3;letter-spacing:-.01em;">{{ $prodi['name'] }}</h4>
                                            <div class="d-flex align-items-center gap-2 mt-2">
                                                <span class="badge" style="background:{{ $theme['color'] }}15;color:{{ $theme['color'] }};font-size:.75rem;letter-spacing:.05em;">
                                                    <i class="fas fa-barcode me-1"></i>{{ $prodi['code'] }}
                                                </span>
                                                <span class="badge bg-secondary-lt text-secondary" style="font-size:.75rem;">{{ $prodi['degree'] }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Mini Stats --}}
                                    <div class="row g-2 mb-3 mt-2">
                                        <div class="col-6">
                                            <div class="p-2 rounded-3 text-center" style="background:var(--tblr-bg-surface-secondary);">
                                                <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;">Lama Studi</div>
                                                <div class="fw-bold text-body" style="font-size:.9rem;"><i class="fas fa-clock text-muted me-1"></i>{{ $prodi['duration'] }} Smt</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 rounded-3 text-center" style="background:var(--tblr-bg-surface-secondary);">
                                                <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;">Beban SKS</div>
                                                <div class="fw-bold text-body" style="font-size:.9rem;"><i class="fas fa-book-open text-muted me-1"></i>{{ $prodi['credits'] }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Gelar --}}
                                    @if($prodi['suffix_degree'] || $prodi['prefix_degree'])
                                    <div class="d-flex align-items-center gap-2 p-2 rounded-3 mb-3 border border-dashed">
                                        <div style="width:32px;height:32px;border-radius:50%;background:{{ $theme['color'] }}15;color:{{ $theme['color'] }};display:flex;align-items:center;justify-content:center;font-size:.8rem;">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Gelar Kelulusan</div>
                                            <div class="fw-bolder text-body" style="font-size:.9rem;">
                                                {{ $prodi['prefix_degree'] ? $prodi['prefix_degree'].' ' : '' }}{{ $prodi['suffix_degree'] }}
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    @if($prodi['desc'])
                                    <p class="text-muted mb-4 flex-fill" style="font-size:.85rem;line-height:1.6;">{{ \Illuminate\Support\Str::limit(strip_tags($prodi['desc']), 120) }}</p>
                                    @else
                                    <div class="flex-fill">
                                        <ul class="text-muted list-unstyled mb-4" style="font-size:.85rem;line-height:1.8;">
                                            <li><i class="fas fa-check text-success me-2"></i>Kurikulum adaptif industri</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Tenaga pengajar tersertifikasi</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Fasilitas praktikum lengkap</li>
                                        </ul>
                                    </div>
                                    @endif

                                    <div class="mt-auto pt-3 border-top d-flex gap-2">
                                        <a href="{{ route('root.admission.apply') }}" class="btn flex-fill fw-bold rounded-pill" style="background:{{ $theme['color'] }}15;color:{{ $theme['color'] }};border:none;">
                                            Daftar Sekarang
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-building-columns"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Data Program Studi Kosong</h4>
                    <p class="text-muted mb-0">Program studi akan segera ditambahkan oleh administrator.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
