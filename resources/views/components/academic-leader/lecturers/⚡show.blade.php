<?php

use App\Models\Organization\LecturerPerformanceReview;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Organization\AcademicLeaderContext;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public int $lecturerProfileId;

    public function mount(int $lecturerProfileId): void
    {
        abort_unless(app(AcademicLeaderContext::class)->canAccessLecturerProfile($lecturerProfileId), 403);
        $this->lecturerProfileId = $lecturerProfileId;
    }

    public function scoreStyle($score): string
    {
        $score = (float) $score;

        if ($score >= 80) return 'background:#dcfce7;color:#15803d;';
        if ($score >= 60) return 'background:#fef3c7;color:#b45309;';

        return 'background:#fee2e2;color:#dc2626;';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => 'Draf',
        };
    }

    public function render(): View
    {
        $profile = app(AcademicLeaderContext::class)
            ->scopedLecturerProfileQuery()
            ->with(['user', 'studyProgram.faculty'])
            ->findOrFail($this->lecturerProfileId);

        $workloads = LecturerWorkloadSubmission::query()
            ->with(['period', 'items'])
            ->where('lecturer_profile_id', $profile->id)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $reviews = LecturerPerformanceReview::query()
            ->with(['edomPeriod', 'workloadPeriod'])
            ->where('lecturer_profile_id', $profile->id)
            ->latest('calculated_at')
            ->limit(8)
            ->get();

        $programBenchmark = LecturerPerformanceReview::query()
            ->whereHas('lecturerProfile', fn ($query) => $query->where('study_program_id', $profile->study_program_id))
            ->avg('final_score') ?: 0;
        $facultyBenchmark = LecturerPerformanceReview::query()
            ->whereHas('lecturerProfile', fn ($query) => $query->where('faculty_id', $profile->faculty_id))
            ->avg('final_score') ?: 0;

        return $this->view([
            'profile' => $profile,
            'workloads' => $workloads,
            'reviews' => $reviews,
            'stats' => [
                'latest_sks' => $workloads->first()?->total_sks ?: 0,
                'approved_bkd' => $workloads->where('status', 'approved')->count(),
                'latest_score' => $reviews->first()?->final_score ?: 0,
                'avg_score' => $reviews->avg('final_score') ?: 0,
                'program_benchmark' => $programBenchmark,
                'faculty_benchmark' => $facultyBenchmark,
            ],
        ])->layout('layouts.app', ['menus' => 'Pemantauan Akademik', 'pages' => 'Detail Dosen']);
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-user-tie"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Detail Dosen</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">{{ $profile->user?->name ?? '-' }}</h1>
                        <div style="opacity:.9;">{{ $profile->studyProgram?->name ?? '-' }} / {{ $profile->studyProgram?->faculty?->name ?? '-' }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-scale-balanced"></i>{{ $stats['latest_sks'] }} SKS terakhir</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-star-half-stroke"></i>{{ number_format($stats['latest_score'], 2) }} skor terakhir</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-chart-line"></i>{{ number_format($stats['avg_score'], 2) }} rata-rata</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('academic-leader.dashboard.index') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;"><i class="fas fa-arrow-left"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">BKD Approved</div><div class="h2 fw-bold mb-0">{{ $stats['approved_bkd'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Skor Terakhir</div><div class="h2 fw-bold text-primary mb-0">{{ number_format($stats['latest_score'], 2) }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Benchmark Prodi</div><div class="h2 fw-bold text-success mb-0">{{ number_format($stats['program_benchmark'], 2) }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Benchmark Fakultas</div><div class="h2 fw-bold text-warning mb-0">{{ number_format($stats['faculty_benchmark'], 2) }}</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card assignment-card h-100">
                <div class="card-header py-3"><h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-scale-balanced me-2 text-primary"></i>Riwayat BKD</h3></div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($workloads as $submission)
                            <div class="assignment-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <span class="assignment-pill mb-2">{{ $this->statusLabel($submission->status) }}</span>
                                        <div class="fw-bold">{{ $submission->period?->name ?? '-' }}</div>
                                        <div class="text-secondary small">Mengajar {{ $submission->teaching_sks }} / Jabatan {{ $submission->structural_sks }} / Tridharma {{ $submission->tridharma_sks }}</div>
                                    </div>
                                    <div class="h3 mb-0">{{ $submission->total_sks }} SKS</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5"><i class="fas fa-inbox fa-3x mb-3"></i><div>Belum ada BKD.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card assignment-card h-100">
                <div class="card-header py-3"><h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-chart-line me-2 text-primary"></i>Tren Performa</h3></div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($reviews as $review)
                            <div class="assignment-list-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <span class="assignment-pill mb-2" style="{{ $this->scoreStyle($review->final_score) }}">Akhir {{ $review->final_score ?: '-' }}</span>
                                        <div class="fw-bold">{{ $review->edomPeriod?->name ?? '-' }}</div>
                                        <div class="text-secondary small">EDOM {{ $review->edom_score ?: '-' }} / Respon {{ $review->edom_response_count }} / BKD {{ $review->workload_total_sks ?: '-' }}</div>
                                    </div>
                                    <div class="text-end text-secondary small">{{ $review->calculated_at?->format('d M Y') ?? '-' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5"><i class="fas fa-inbox fa-3x mb-3"></i><div>Belum ada review performa.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
