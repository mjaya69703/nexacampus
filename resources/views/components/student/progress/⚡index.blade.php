<?php

use App\Support\StudentProgressAnalyticsService;
use Livewire\Component;

new class extends Component {
    public bool $hasProfile = false;
    public array $progress = [];

    public function mount(StudentProgressAnalyticsService $analytics): void
    {
        $studentProfile = auth()->user()?->studentProfile()
            ->with(['user', 'studyProgram.faculty'])
            ->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->progress = $analytics->summarize($studentProfile);
    }

    public function money(float|int|null $amount): string
    {
        return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    public function levelClass(?string $level): string
    {
        return match ($level) {
            'success' => 'risk-success',
            'warning' => 'risk-warning',
            'danger' => 'risk-danger',
            default => 'risk-neutral',
        };
    }

    public function levelLabel(?string $level): string
    {
        return match ($level) {
            'success' => 'Aman',
            'warning' => 'Pantau',
            'danger' => 'Perlu Atensi',
            default => 'Belum Cukup Data',
        };
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Academic Progress',
        ]);
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            background: var(--tblr-bg-surface);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .hero-icon {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
        }

        .hero-mini-card {
            border-radius: 16px;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(10px);
            min-height: 96px;
        }

        .hero-mini-label {
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .hero-mini-value {
            color: #ffffff;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.1;
            margin-top: 0.5rem;
        }

        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            min-height: 142px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .stat-value {
            color: #0f172a;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-note {
            color: #64748b;
            font-size: 0.86rem;
            margin-top: 0.65rem;
        }

        .progress-track {
            height: 12px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }

        .section-subtitle {
            color: #64748b;
            margin-bottom: 1.25rem;
        }

        .advisor-panel {
            border-radius: 16px;
            padding: 1.25rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid transparent;
        }

        .risk-card {
            border-radius: 16px;
            padding: 1.15rem;
            border: 1px solid #e2e8f0;
            min-height: 154px;
            background: #ffffff;
        }

        .risk-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.78rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .risk-success {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }

        .risk-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        .risk-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .risk-neutral {
            background: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }

        .attention-item {
            border-radius: 14px;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid transparent;
        }

        .recommendation-item {
            display: flex;
            gap: 0.8rem;
            padding: 0.95rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .recommendation-item:last-child {
            border-bottom: none;
        }

        .recommendation-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            background: #eef2ff;
            color: #4338ca;
        }

        .advisor-note-item {
            border-radius: 14px;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid transparent;
        }

        .chart-box {
            min-height: 300px;
        }

        @media (max-width: 575.98px) {
            .stat-value {
                font-size: 1.65rem;
            }

            .hero-meta {
                flex-direction: column;
                align-items: flex-start !important;
            }
        }

        [data-bs-theme=dark] .modern-card,
        body[data-bs-theme=dark] .modern-card,
        [data-bs-theme=dark] .stat-card,
        body[data-bs-theme=dark] .stat-card,
        [data-bs-theme=dark] .risk-card,
        body[data-bs-theme=dark] .risk-card {
            background: #211632 !important;
            border-color: rgba(167, 139, 255, 0.18) !important;
            color: #f3edff !important;
        }

        [data-bs-theme=dark] .advisor-panel,
        body[data-bs-theme=dark] .advisor-panel,
        [data-bs-theme=dark] .attention-item,
        body[data-bs-theme=dark] .attention-item,
        [data-bs-theme=dark] .advisor-note-item,
        body[data-bs-theme=dark] .advisor-note-item {
            background: rgba(43, 28, 67, 0.85) !important;
            border-color: rgba(167, 139, 255, 0.22) !important;
            color: #f3edff !important;
        }

        [data-bs-theme=dark] .stat-value,
        body[data-bs-theme=dark] .stat-value,
        [data-bs-theme=dark] .text-dark,
        body[data-bs-theme=dark] .text-dark,
        [data-bs-theme=dark] [style*="color: #0f172a"],
        body[data-bs-theme=dark] [style*="color: #0f172a"],
        [data-bs-theme=dark] .section-title,
        body[data-bs-theme=dark] .section-title {
            color: #f3edff !important;
        }
    </style>
@endpush

<div>
    @if (! $hasProfile)
        <div class="alert alert-warning">
            Profil mahasiswa belum tersedia. Hubungi admin akademik agar halaman progress bisa ditampilkan.
        </div>
    @else
        <div class="modern-card hero-gradient text-white mb-4">
            <div class="card-body p-4 p-lg-5" style="position: relative; z-index: 2;">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center gap-3 mb-3 hero-meta">
                            <span class="hero-icon">
                                <i class="fas fa-chart-line"></i>
                            </span>
                            <div>
                                <div class="text-white-50 fw-bold mb-1">{{ $progress['student']['active_year'] }}</div>
                                <h1 class="mb-1" style="font-weight: 800;">Progress Akademik</h1>
                                <div class="d-flex flex-wrap gap-2 text-white-50">
                                    <span>{{ $progress['student']['name'] }}</span>
                                    <span>{{ $progress['student']['nim'] }}</span>
                                    <span>Semester {{ $progress['student']['semester'] ?? '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge bg-white text-primary px-3 py-2">{{ $progress['student']['status'] }}</span>
                            <span class="badge bg-white text-primary px-3 py-2">{{ $progress['student']['study_program'] }}</span>
                            <span class="badge bg-white text-primary px-3 py-2">{{ $progress['student']['faculty'] }}</span>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="hero-mini-card">
                                    <div class="hero-mini-label">IPK</div>
                                    <div class="hero-mini-value">{{ $progress['gpa']['cumulative'] !== null ? number_format((float) $progress['gpa']['cumulative'], 2) : '-' }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="hero-mini-card">
                                    <div class="hero-mini-label">SKS</div>
                                    <div class="hero-mini-value">{{ $progress['credits']['passed'] }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="hero-mini-card">
                                    <div class="hero-mini-label">Progress</div>
                                    <div class="hero-mini-value">{{ $progress['credits']['progress'] }}%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-label">IPK Kumulatif</div>
                    <div class="stat-value mt-3">{{ $progress['gpa']['cumulative'] !== null ? number_format((float) $progress['gpa']['cumulative'], 2) : '-' }}</div>
                    <div class="stat-note">IPS terakhir {{ $progress['gpa']['semester'] !== null ? number_format((float) $progress['gpa']['semester'], 2) : '-' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-label">SKS Lulus</div>
                    <div class="stat-value mt-3">{{ $progress['credits']['passed'] }}</div>
                    <div class="stat-note">Dari target {{ $progress['credits']['target'] }} SKS</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-label">Sisa Kebutuhan</div>
                    <div class="stat-value mt-3">{{ $progress['credits']['remaining'] }}</div>
                    <div class="stat-note">KRS aktif {{ $progress['credits']['current'] }} SKS, status {{ $progress['credits']['current_status'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-label">Kehadiran</div>
                    <div class="stat-value mt-3">{{ $progress['attendance']['rate'] !== null ? $progress['attendance']['rate'].'%' : '-' }}</div>
                    <div class="stat-note">{{ $progress['attendance']['total'] }} pertemuan terbaca</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="modern-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                            <div>
                                <div class="section-title">Tren IPS dan IPK</div>
                                <div class="section-subtitle mb-0">Perubahan performa semester demi semester.</div>
                            </div>
                            <span class="risk-pill risk-neutral">
                                <i class="fas fa-layer-group"></i>
                                {{ $progress['credits']['progress'] }}% SKS
                            </span>
                        </div>

                        <div class="progress-track mb-4">
                            <div class="progress-fill" style="width: {{ $progress['credits']['progress'] }}%;"></div>
                        </div>

                        @if (count($progress['trend']) > 0)
                            <div id="student-progress-chart" class="chart-box"></div>
                        @else
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-chart-line mb-3" style="font-size: 2.25rem;"></i>
                                <div class="fw-bold text-dark">Belum ada riwayat semester</div>
                                <div>Grafik akan muncul setelah nilai semester dipublikasikan.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="modern-card h-100">
                    <div class="card-body p-4">
                        <div class="section-title">Dosen PA</div>
                        <div class="section-subtitle">Kontak utama untuk rencana akademik.</div>

                        <div class="advisor-panel mb-4">
                            @if ($progress['advisor']['name'])
                                <div class="fw-bold text-dark mb-1">{{ $progress['advisor']['name'] }}</div>
                                <div class="text-muted mb-2">{{ $progress['advisor']['identity'] ?? '-' }}</div>
                                <div class="small text-muted">{{ $progress['advisor']['academic_year'] }} - {{ $progress['advisor']['period'] }}</div>
                            @else
                                <div class="fw-bold text-dark mb-1">Belum ada Dosen PA aktif</div>
                                <div class="text-muted">Hubungi admin akademik kalau data ini belum sesuai.</div>
                            @endif
                        </div>

                        <div class="section-title">Keuangan</div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="text-muted">Outstanding</span>
                            <span class="fw-bold">{{ $this->money($progress['financial']['outstanding']) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="text-muted">Jatuh tempo</span>
                            <span class="fw-bold">{{ $progress['financial']['overdue_count'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="text-muted">Hold aktif</span>
                            <span class="fw-bold">{{ $progress['financial']['active_holds'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            @foreach ($progress['risks'] as $risk)
                <div class="col-md-6 col-xl-3">
                    <div class="risk-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div class="fw-bold text-dark">{{ $risk['label'] }}</div>
                            <span class="risk-pill {{ $this->levelClass($risk['level']) }}">{{ $this->levelLabel($risk['level']) }}</span>
                        </div>
                        <div class="text-muted">{{ $risk['reason'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="modern-card h-100">
                    <div class="card-body p-4">
                        <div class="section-title">Mata Kuliah Perlu Atensi</div>
                        <div class="section-subtitle">Prioritas diskusi saat menyusun rencana semester berikutnya.</div>

                        @forelse ($progress['failed_courses'] as $course)
                            <div class="attention-item mb-3">
                                <div class="d-flex justify-content-between gap-3 flex-wrap">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $course['code'] }} - {{ $course['name'] }}</div>
                                        <div class="text-muted small">Semester {{ $course['semester'] ?? '-' }} - {{ $course['credits'] }} SKS</div>
                                    </div>
                                    <span class="risk-pill risk-danger">Nilai {{ $course['grade'] }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle mb-3" style="font-size: 2rem; color: #16a34a;"></i>
                                <div class="fw-bold text-dark">Tidak ada mata kuliah gagal yang terbaca</div>
                                <div>Tetap cek KRS dan target SKS sebelum periode berikutnya.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="modern-card h-100">
                    <div class="card-body p-4">
                        <div class="section-title">Rekomendasi Berikutnya</div>
                        <div class="section-subtitle">Langkah yang paling masuk akal dari data saat ini.</div>

                        @foreach ($progress['recommendations'] as $recommendation)
                            <div class="recommendation-item">
                                <span class="recommendation-icon"><i class="fas fa-arrow-right"></i></span>
                                <div class="fw-semibold text-dark">{{ $recommendation }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if (count($progress['advisor_notes']) > 0)
            <div class="modern-card mt-4">
                <div class="card-body p-4">
                    <div class="section-title">Catatan Dosen PA</div>
                    <div class="section-subtitle">Ringkasan follow-up yang dibagikan oleh Dosen PA.</div>

                    <div class="row g-3">
                        @foreach ($progress['advisor_notes'] as $note)
                            <div class="col-lg-4">
                                <div class="advisor-note-item h-100">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <div class="fw-bold text-dark">{{ $note['topic'] }}</div>
                                        <span class="risk-pill {{ $note['status'] === 'Done' ? 'risk-success' : 'risk-warning' }}">{{ $note['status'] }}</span>
                                    </div>
                                    <div class="text-muted small mb-2">{{ $note['notes'] }}</div>
                                    @if ($note['recommendation'])
                                        <div class="text-muted small mb-2"><strong>Rekomendasi:</strong> {{ $note['recommendation'] }}</div>
                                    @endif
                                    <div class="text-muted small">
                                        {{ $note['lecturer'] }}
                                        @if ($note['follow_up_at'])
                                            - Follow-up {{ $note['follow_up_at'] }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>

@push('scripts')
    <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const element = document.querySelector('#student-progress-chart');
            const rows = @js($progress['trend'] ?? []);

            if (!element || typeof ApexCharts === 'undefined' || rows.length === 0) {
                return;
            }

            new ApexCharts(element, {
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: { show: false },
                    fontFamily: 'inherit'
                },
                colors: ['#667eea', '#764ba2'],
                dataLabels: { enabled: false },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.28,
                        opacityTo: 0.04,
                        stops: [0, 95, 100]
                    }
                },
                series: [
                    {
                        name: 'IPS',
                        data: rows.map((row) => row.semester_gpa)
                    },
                    {
                        name: 'IPK',
                        data: rows.map((row) => row.cumulative_gpa)
                    }
                ],
                xaxis: {
                    categories: rows.map((row) => row.semester),
                    labels: { style: { colors: '#64748b' } }
                },
                yaxis: {
                    min: 0,
                    max: 4,
                    tickAmount: 4,
                    labels: {
                        formatter: (value) => Number(value).toFixed(2),
                        style: { colors: '#64748b' }
                    }
                },
                grid: {
                    borderColor: '#e2e8f0',
                    strokeDashArray: 4
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right'
                },
                tooltip: {
                    y: {
                        formatter: (value) => Number(value).toFixed(2)
                    }
                }
            }).render();
        });
    </script>
@endpush
