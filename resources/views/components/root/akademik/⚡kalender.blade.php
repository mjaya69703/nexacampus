<?php

use Livewire\Component;
use App\Models\Academic\AcademicYear;

new class extends Component
{
    public ?AcademicYear $activeYear = null;
    public array $periods = [];

    public function mount(): void
    {
        $this->activeYear = AcademicYear::with(['academicPeriods' => fn($q) => $q->orderBy('start_at')])
            ->where('is_active', true)
            ->first();

        if ($this->activeYear) {
            $this->periods = $this->activeYear->academicPeriods->map(fn($p) => [
                'name'       => $p->name,
                'type'       => $p->period_type ?? 'Ganjil/Genap',
                'start_date' => $p->start_at?->format('d M Y'),
                'end_date'   => $p->end_at?->format('d M Y'),
                'is_active'  => $p->is_active,
                'desc'       => $p->desc,
            ])->toArray();
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Akademik',
            'pages' => 'Kalender Akademik',
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
                                <span>Timeline Pembelajaran</span>
                            </div>
                            <h1 class="admission-title mb-3">Kalender<br><span style="opacity:.8">Akademik Kampus</span></h1>
                            <p class="admission-subtitle mb-4">
                                Rencanakan perjalanan studi Anda dengan baik. Berikut adalah jadwal kegiatan akademik resmi untuk tahun ajaran yang sedang berjalan.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Tahun Ajaran Aktif</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ $activeYear?->name ?? 'Belum Diatur' }}</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Berjalan</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $activeYear?->semester ?? '-' }}</span>
                                            <small class="text-white-50">Semester</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ count($periods) }}</span>
                                            <small class="text-white-50">Periode Kegiatan</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($activeYear && count($periods) > 0)
                <div class="admission-card border-0 rounded-4 shadow-sm overflow-hidden mb-5">
                    <div class="p-4 border-bottom d-flex align-items-center justify-content-between gap-3 bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <div class="step-badge" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);"><i class="fas fa-calendar-days"></i></div>
                            <div>
                                <h4 class="mb-0 fw-bolder fs-5">Rincian Periode {{ $activeYear->name }}</h4>
                                <div class="text-muted" style="font-size: 0.82rem;">{{ $activeYear->start_date?->format('d M Y') }} – {{ $activeYear->end_date?->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-0">
                        <div class="table-responsive">
                            <table class="table table-vcenter table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4 border-0 text-muted fw-bold" style="font-size:0.75rem;text-transform:uppercase;">Kegiatan / Periode</th>
                                        <th class="border-0 text-muted fw-bold text-center" style="font-size:0.75rem;text-transform:uppercase;">Tanggal Mulai</th>
                                        <th class="border-0 text-muted fw-bold text-center" style="font-size:0.75rem;text-transform:uppercase;">Tanggal Selesai</th>
                                        <th class="pe-4 border-0 text-muted fw-bold text-end" style="font-size:0.75rem;text-transform:uppercase;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($periods as $p)
                                    <tr class="{{ $p['is_active'] ? 'table-primary-lt' : '' }}">
                                        <td class="ps-4 py-3">
                                            <div class="fw-bolder text-body mb-1" style="font-size:.95rem;">{{ $p['name'] }}</div>
                                            @if($p['desc'])<div class="text-muted" style="font-size:.8rem;">{{ $p['desc'] }}</div>@endif
                                        </td>
                                        <td class="text-center text-body fw-semibold" style="font-size:.9rem;"><i class="fas fa-play text-success me-2" style="font-size:.7rem;"></i>{{ $p['start_date'] ?? '-' }}</td>
                                        <td class="text-center text-body fw-semibold" style="font-size:.9rem;"><i class="fas fa-stop text-danger me-2" style="font-size:.7rem;"></i>{{ $p['end_date'] ?? '-' }}</td>
                                        <td class="pe-4 text-end">
                                            @if($p['is_active'])
                                                <span class="badge bg-primary text-white fw-semibold px-2 py-1">Sedang Berjalan</span>
                                            @else
                                                <span class="badge bg-secondary-lt text-secondary fw-semibold px-2 py-1">Selesai / Belum</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @else
                <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center mb-5">
                    <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;"><i class="fas fa-calendar-xmark"></i></div>
                    <h4 class="fw-bolder text-body mb-2">Tahun Ajaran Belum Diatur</h4>
                    <p class="text-muted mb-0">Kalender akademik untuk tahun ajaran aktif belum tersedia saat ini.</p>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
