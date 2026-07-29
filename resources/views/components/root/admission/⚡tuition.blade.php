<?php

use Livewire\Component;
use App\Models\Financial\TuitionFee;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\Faculty;

new class extends Component
{
    public array $tuitions = [];
    public int $totalPrograms = 0;

    public function mount(): void
    {
        $this->totalPrograms = StudyProgram::where('is_active', true)->count();

        // Convert to plain array to avoid Livewire serialization error
        $this->tuitions = TuitionFee::with(['studyProgram.faculty'])
            ->where('is_active', true)
            ->orderBy('study_program_id')
            ->get()
            ->groupBy('study_program_id')
            ->map(fn ($fees) => [
                'program_name'  => $fees->first()->studyProgram?->name ?? 'Program Studi Tidak Diketahui',
                'program_code'  => $fees->first()->studyProgram?->code ?? '-',
                'faculty_name'  => $fees->first()->studyProgram?->faculty?->name ?? '-',
                'base_fee'      => (float) $fees->first()->base_fee,
                'lab_fee'       => (float) $fees->first()->lab_fee,
                'library_fee'   => (float) $fees->first()->library_fee,
                'activity_fee'  => (float) $fees->first()->activity_fee,
                'total'         => (float) $fees->first()->totalAmount(),
                'deadline'      => $fees->first()->payment_deadline?->format('d M Y') ?? '-',
            ])
            ->values()
            ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'Biaya Pendidikan (UKT)',
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
                                <span>Transparansi Pembiayaan</span>
                            </div>
                            <h1 class="admission-title mb-3">Biaya Pendidikan<br><span style="opacity:.8">yang Terjangkau & Transparan</span></h1>
                            <p class="admission-subtitle mb-4">
                                Kami berkomitmen pada transparansi penuh. Berikut adalah rincian biaya pendidikan (UKT) lengkap per semester untuk seluruh program studi aktif.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-light px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane text-primary"></i> Daftar Sekarang
                                </a>
                                <a href="{{ route('root.admission.requirements') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-list-check"></i> Lihat Persyaratan
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Ringkasan Biaya</div>
                                        <div class="h3 text-white mb-0 fw-bolder">UKT 2024/2025</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold shadow-sm">Resmi</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ count($tuitions) }}</span>
                                            <small class="text-white-50">Program Studi</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white" style="font-size:1.4rem">
                                                {{ count($tuitions) > 0 ? 'Rp '.number_format(collect($tuitions)->min('total') / 1000000, 1).'Jt' : '-' }}
                                            </span>
                                            <small class="text-white-50">Mulai dari</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-top border-light border-opacity-10">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-shield-halved text-white-50"></i>
                                        <small class="text-white-50">Biaya resmi & terjamin sesuai regulasi Dikti</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($tuitions) > 0)
                    {{-- Tuition Table --}}
                    <div class="admission-card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                        <div class="admission-card-header p-4 border-bottom d-flex align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="step-badge" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fas fa-coins"></i></div>
                                <div>
                                    <h4 class="mb-0 fw-bolder fs-5">Rincian Biaya per Program Studi</h4>
                                    <div class="text-muted" style="font-size: 0.82rem;">Biaya per semester — klik baris untuk detail</div>
                                </div>
                            </div>
                            <span class="badge bg-success-lt fw-bold">{{ count($tuitions) }} Prodi</span>
                        </div>
                        <div class="admission-card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-vcenter table-hover mb-0">
                                    <thead>
                                        <tr style="background: var(--tblr-bg-surface-secondary);">
                                            <th class="ps-4 border-0 text-muted fw-bold" style="font-size:0.72rem;letter-spacing:.06em;text-transform:uppercase;">#</th>
                                            <th class="border-0 text-muted fw-bold" style="font-size:0.72rem;letter-spacing:.06em;text-transform:uppercase;">Program Studi</th>
                                            <th class="border-0 text-muted fw-bold text-end" style="font-size:0.72rem;letter-spacing:.06em;text-transform:uppercase;">Biaya Dasar</th>
                                            <th class="border-0 text-muted fw-bold text-end" style="font-size:0.72rem;letter-spacing:.06em;text-transform:uppercase;">Lab</th>
                                            <th class="border-0 text-muted fw-bold text-end" style="font-size:0.72rem;letter-spacing:.06em;text-transform:uppercase;">Pustaka</th>
                                            <th class="border-0 text-muted fw-bold text-end" style="font-size:0.72rem;letter-spacing:.06em;text-transform:uppercase;">Kegiatan</th>
                                            <th class="pe-4 border-0 text-primary fw-bolder text-end" style="font-size:0.8rem;letter-spacing:.04em;text-transform:uppercase;">Total / Semester</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tuitions as $i => $t)
                                            <tr>
                                                <td class="ps-4 text-muted">{{ $i + 1 }}</td>
                                                <td>
                                                    <div class="fw-bold text-body">{{ $t['program_name'] }}</div>
                                                    <div class="d-flex align-items-center gap-2 mt-1">
                                                        <span class="badge bg-primary-lt text-primary fw-semibold" style="font-size:.7rem;">{{ $t['program_code'] }}</span>
                                                        <span class="text-muted" style="font-size:.75rem;">{{ $t['faculty_name'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-end text-body">Rp {{ number_format($t['base_fee'], 0, ',', '.') }}</td>
                                                <td class="text-end text-body">Rp {{ number_format($t['lab_fee'], 0, ',', '.') }}</td>
                                                <td class="text-end text-body">Rp {{ number_format($t['library_fee'], 0, ',', '.') }}</td>
                                                <td class="text-end text-body">Rp {{ number_format($t['activity_fee'], 0, ',', '.') }}</td>
                                                <td class="pe-4 text-end fw-bolder" style="color:#3b82f6;font-size:0.95rem;">
                                                    Rp {{ number_format($t['total'], 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Info cards --}}
                    <div class="row g-3 mb-4">
                        <div class="col-lg-4">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100 d-flex align-items-start gap-3">
                                <div class="stat-icon-wrap bg-success bg-opacity-10 text-success flex-shrink-0">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <div class="fw-bolder text-body mb-1">KIP Kuliah</div>
                                    <div class="text-muted" style="font-size:.85rem;">Penerima KIP Kuliah mendapatkan pembebasan biaya pendidikan penuh sesuai ketentuan pemerintah.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100 d-flex align-items-start gap-3">
                                <div class="stat-icon-wrap bg-primary bg-opacity-10 text-primary flex-shrink-0">
                                    <i class="fas fa-hand-holding-dollar"></i>
                                </div>
                                <div>
                                    <div class="fw-bolder text-body mb-1">Cicilan & Keringanan</div>
                                    <div class="text-muted" style="font-size:.85rem;">Mahasiswa yang mengalami kesulitan ekonomi dapat mengajukan cicilan pembayaran atau keringanan biaya.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100 d-flex align-items-start gap-3">
                                <div class="stat-icon-wrap bg-warning bg-opacity-10 text-warning flex-shrink-0">
                                    <i class="fas fa-medal"></i>
                                </div>
                                <div>
                                    <div class="fw-bolder text-body mb-1">Beasiswa Prestasi</div>
                                    <div class="text-muted" style="font-size:.85rem;">Tersedia beasiswa prestasi akademik dan non-akademik yang dapat mengurangi beban biaya pendidikan.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                @else
                    {{-- Empty state --}}
                    <div class="admission-card rounded-4 border-0 shadow-sm p-5 text-center">
                        <div style="width:80px;height:80px;border-radius:24px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;">
                            <i class="fas fa-coins"></i>
                        </div>
                        <h4 class="fw-bolder text-body mb-2">Data Biaya Belum Tersedia</h4>
                        <p class="text-muted mb-4">Rincian biaya pendidikan akan segera dipublikasikan oleh tim keuangan. Hubungi bagian keuangan untuk informasi lebih lanjut.</p>
                        <a href="{{ route('root.admission.faq') }}" class="btn btn-primary px-4">Lihat FAQ</a>
                    </div>
                @endif

                {{-- Note --}}
                <div class="alert d-flex gap-3 border-0 shadow-sm rounded-3 mt-3" style="background:linear-gradient(135deg,rgba(59,130,246,.08),rgba(29,78,216,.04));border-left:4px solid #3b82f6 !important;">
                    <div class="text-primary flex-shrink-0 mt-1"><i class="fas fa-circle-info fs-4"></i></div>
                    <div>
                        <div class="fw-bold text-body">Catatan Penting</div>
                        <div class="text-muted" style="font-size:.88rem;">Biaya di atas adalah total estimasi tagihan per semester. Denda keterlambatan tidak termasuk dalam tabel. Nominal dapat berubah sesuai keputusan lembaga.</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>