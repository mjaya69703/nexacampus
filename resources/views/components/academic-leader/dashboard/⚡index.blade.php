<?php

use App\Models\Organization\LecturerPerformanceReview;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\Organization\AcademicLeaderContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
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

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'in_approval' => 'background:#fef3c7;color:#b45309;',
            'approved' => 'background:#dcfce7;color:#15803d;',
            'revision' => 'background:#ffedd5;color:#ea580c;',
            'rejected' => 'background:#fee2e2;color:#dc2626;',
            'cancelled' => 'background:#f1f5f9;color:#64748b;',
            default => 'background:#eef2ff;color:#4338ca;',
        };
    }

    public function scoreStyle($score): string
    {
        $score = (float) $score;

        if ($score >= 80) {
            return 'background:#dcfce7;color:#15803d;';
        }

        if ($score >= 60) {
            return 'background:#fef3c7;color:#b45309;';
        }

        return 'background:#fee2e2;color:#dc2626;';
    }

    private function scopedSubmissions()
    {
        $context = app(AcademicLeaderContext::class);
        $facultyIds = $context->facultyIds();
        $programIds = $context->studyProgramIds();

        return LecturerWorkloadSubmission::query()
            ->with(['owner', 'period', 'lecturerProfile.studyProgram'])
            ->whereHas('lecturerProfile', function (Builder $query) use ($facultyIds, $programIds) {
                $query->where(function (Builder $nested) use ($facultyIds, $programIds) {
                    if ($programIds) {
                        $nested->orWhereIn('study_program_id', $programIds);
                    }
                    if ($facultyIds) {
                        $nested->orWhereIn('faculty_id', $facultyIds);
                    }
                    if (! $programIds && ! $facultyIds) {
                        $nested->whereRaw('1 = 0');
                    }
                });
            });
    }

    private function scopedReviews()
    {
        $context = app(AcademicLeaderContext::class);
        $facultyIds = $context->facultyIds();
        $programIds = $context->studyProgramIds();

        return LecturerPerformanceReview::query()
            ->with(['owner', 'edomPeriod', 'lecturerProfile.studyProgram'])
            ->whereHas('lecturerProfile', function (Builder $query) use ($facultyIds, $programIds) {
                $query->where(function (Builder $nested) use ($facultyIds, $programIds) {
                    if ($programIds) {
                        $nested->orWhereIn('study_program_id', $programIds);
                    }
                    if ($facultyIds) {
                        $nested->orWhereIn('faculty_id', $facultyIds);
                    }
                    if (! $programIds && ! $facultyIds) {
                        $nested->whereRaw('1 = 0');
                    }
                });
            });
    }

    public function render()
    {
        $submissions = $this->scopedSubmissions()->latest('updated_at')->limit(8)->get();
        $reviews = $this->scopedReviews()->latest('calculated_at')->limit(8)->get();

        return $this->view([
            'hasScope' => app(AcademicLeaderContext::class)->hasScope(),
            'submissions' => $submissions,
            'reviews' => $reviews,
            'stats' => [
                'bkd' => $this->scopedSubmissions()->count(),
                'bkd_approval' => $this->scopedSubmissions()->where('status', 'in_approval')->count(),
                'avg_sks' => $this->scopedSubmissions()->avg('total_sks') ?: 0,
                'avg_performance' => $this->scopedReviews()->avg('final_score') ?: 0,
            ],
        ])->layout('layouts.app', ['menus' => 'Pemantauan Akademik', 'pages' => 'Dashboard Pimpinan Akademik']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-chart-line"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Pemantauan Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Dashboard Pimpinan Akademik</h1>
                        <div style="opacity:.9;">Pantau BKD, EDOM, dan performa dosen sesuai scope jabatan aktif.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-file-signature"></i>{{ $stats['bkd'] }} BKD</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ $stats['bkd_approval'] }} approval</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-star-half-stroke"></i>{{ number_format($stats['avg_performance'], 2) }} performa</span>
                        </div>
                    </div>
                </div>
                <div class="assignment-panel" style="min-width:min(100%, 290px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                    <div style="opacity:.86;font-weight:700;">Scope Aktif</div>
                    <div class="h4 mb-1" style="font-weight:900;">{{ $hasScope ? 'Tersedia' : 'Belum Diatur' }}</div>
                    <div style="opacity:.86;">Data mengikuti jabatan fakultas atau program studi yang aktif.</div>
                </div>
            </div>
        </div>
    </div>

    @unless ($hasScope)
        <div class="assignment-panel mb-4" style="background:#fffbeb;border-color:#fde68a;">
            <div class="fw-bold text-warning"><i class="fas fa-triangle-exclamation me-2"></i>Scope belum tersedia</div>
            <div class="text-secondary mt-1">Akun ini belum memiliki scope fakultas atau program studi aktif, jadi data pemantauan belum bisa ditampilkan.</div>
        </div>
    @endunless

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">BKD Scoped</div><div class="h2 fw-bold mb-0">{{ $stats['bkd'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Menunggu Approval</div><div class="h2 fw-bold text-warning mb-0">{{ $stats['bkd_approval'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Rata-rata SKS</div><div class="h2 fw-bold text-primary mb-0">{{ number_format($stats['avg_sks'], 2) }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Rata-rata Performa</div><div class="h2 fw-bold text-success mb-0">{{ number_format($stats['avg_performance'], 2) }}</div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card assignment-card h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-scale-balanced me-2 text-primary"></i>BKD Terbaru</h3>
                    <a href="{{ route('academic-leader.workloads.index') }}" class="assignment-action" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;"><i class="fas fa-arrow-right"></i>Lihat Semua</a>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($submissions as $submission)
                            <div class="assignment-list-item">
                                <div class="d-flex gap-3 align-items-start">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-tie"></i></span>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill" style="{{ $this->statusStyle($submission->status) }}">{{ $this->statusLabel($submission->status) }}</span>
                                            <span class="assignment-pill">{{ $submission->total_sks }} SKS</span>
                                        </div>
                                        <div class="fw-bold">{{ $submission->owner?->name }}</div>
                                        <div class="text-secondary small">{{ $submission->period?->name }} / {{ $submission->lecturerProfile?->studyProgram?->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <div>Belum ada BKD pada scope ini.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card assignment-card h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-star-half-stroke me-2 text-primary"></i>Performa Terbaru</h3>
                    <a href="{{ route('academic-leader.edom.index') }}" class="assignment-action" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;"><i class="fas fa-arrow-right"></i>Lihat Semua</a>
                </div>
                <div class="card-body p-4">
                    <div class="assignment-shell">
                        @forelse ($reviews as $review)
                            <div class="assignment-list-item">
                                <div class="d-flex gap-3 align-items-start">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-chart-simple"></i></span>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill" style="{{ $this->scoreStyle($review->final_score) }}">Akhir {{ $review->final_score ?: '-' }}</span>
                                            <span class="assignment-pill">EDOM {{ $review->edom_score ?: '-' }}</span>
                                            <span class="assignment-pill">{{ $review->edom_response_count }} respon</span>
                                        </div>
                                        <div class="fw-bold">{{ $review->owner?->name }}</div>
                                        <div class="text-secondary small">{{ $review->edomPeriod?->name }} / {{ $review->lecturerProfile?->studyProgram?->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <div>Belum ada review performa pada scope ini.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
