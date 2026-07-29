<?php

use Livewire\Component;
use App\Models\Academic\Curriculum;
use App\Models\Academic\StudyProgram;

new class extends Component
{
    public array $curriculums = [];

    public function mount(): void
    {
        $this->curriculums = Curriculum::with('studyProgram')
            ->where('is_active', true)
            ->get()
            ->groupBy(fn($c) => $c->studyProgram?->name ?? 'Umum')
            ->map(fn($group) => $group->map(fn($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'year'          => $c->year,
                'total_credits' => $c->total_credits ?? 144, // fallback if empty
                'desc'          => $c->desc,
            ])->toArray())
            ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Akademik',
            'pages' => 'Silabus & Kurikulum',
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
                                <span>Panduan Belajar Terpadu</span>
                            </div>
                            <h1 class="admission-title mb-3">Silabus &<br><span style="opacity:.8">Kurikulum</span></h1>
                            <p class="admission-subtitle mb-0">
                                Kurikulum di NexaCampus dirancang secara dinamis, adaptif terhadap perkembangan industri, dan berstandar internasional untuk membekali mahasiswa dengan kompetensi unggul.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Kurikulum Aktif</div>
                                    <div class="h3 text-white mb-0 fw-bolder">{{ collect($curriculums)->flatten(1)->count() }} Dokumen</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-book-open text-white-50"></i>
                                    <small class="text-white-50">Silabus pembelajaran dan distribusi mata kuliah.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($curriculums) > 0)
                <div class="row g-5">
                    @foreach($curriculums as $program => $items)
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#10b981,#047857);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex-shrink:0;">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <h3 class="fw-bolder text-body mb-0" style="font-size:1.3rem;">{{ $program }}</h3>
                            <div class="flex-fill border-bottom border-2 ms-2 opacity-25"></div>
                        </div>

                        <div class="row g-4">
                            @foreach($items as $c)
                            <div class="col-lg-4 col-sm-6">
                                <div class="admission-card border-0 rounded-4 shadow-sm p-4 h-100 d-flex flex-column" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='none'">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <div class="badge bg-success-lt text-success px-2 py-1"><i class="fas fa-check-circle me-1"></i>Aktif</div>
                                        <div class="badge bg-secondary-lt text-secondary fw-bold">Tahun {{ $c['year'] }}</div>
                                    </div>
                                    
                                    <h4 class="fw-bolder text-body mb-1" style="font-size:1.15rem;">{{ $c['name'] }}</h4>
                                    
                                    <div class="p-2 rounded-3 text-center my-3" style="background:var(--tblr-bg-surface-secondary);border:1px dashed var(--tblr-border-color);">
                                        <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;">Beban SKS Kelulusan</div>
                                        <div class="fw-bold text-primary" style="font-size:1.1rem;"><i class="fas fa-book-open me-2 text-muted"></i>{{ $c['total_credits'] }} SKS</div>
                                    </div>

                                    @if($c['desc'])
                                    <p class="text-muted mb-4 flex-fill" style="font-size:.85rem;line-height:1.6;">{{ \Illuminate\Support\Str::limit(strip_tags($c['desc']), 120) }}</p>
                                    @else
                                    <div class="flex-fill mb-4"></div>
                                    @endif

                                    <div class="mt-auto border-top pt-3 text-center">
                                        <button class="btn btn-outline-primary rounded-pill px-4 fw-bold w-100" onclick="alert('Fitur download silabus akan segera hadir.')">
                                            <i class="fas fa-download me-2"></i>Unduh Silabus
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-5">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#10b981,#047857);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-book-open"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Kurikulum Belum Tersedia</h4>
                    <p class="text-muted mb-0">Dokumen kurikulum aktif belum dipublikasikan oleh bagian akademik.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
