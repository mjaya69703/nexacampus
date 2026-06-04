<?php

use App\Models\Organization\LecturerPerformanceReview;
use App\Support\Organization\AcademicLeaderContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
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

    public function render()
    {
        $context = app(AcademicLeaderContext::class);
        $facultyIds = $context->facultyIds();
        $programIds = $context->studyProgramIds();

        $query = LecturerPerformanceReview::query()
            ->with(['owner', 'edomPeriod', 'lecturerProfile.studyProgram'])
            ->whereHas('lecturerProfile', function (Builder $query) use ($facultyIds, $programIds) {
                $query->where(function (Builder $nested) use ($facultyIds, $programIds) {
                    if ($programIds) $nested->orWhereIn('study_program_id', $programIds);
                    if ($facultyIds) $nested->orWhereIn('faculty_id', $facultyIds);
                    if (! $programIds && ! $facultyIds) $nested->whereRaw('1 = 0');
                });
            })
            ->latest('calculated_at');

        $statsQuery = clone $query;
        $rows = $query->paginate(12);

        return $this->view([
            'rows' => $rows,
            'hasScope' => $context->hasScope(),
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'avg_edom' => (clone $statsQuery)->avg('edom_score') ?: 0,
                'avg_final' => (clone $statsQuery)->avg('final_score') ?: 0,
                'responses' => (clone $statsQuery)->sum('edom_response_count'),
            ],
        ])->layout('layouts.app', ['menus' => 'Pemantauan Akademik', 'pages' => 'EDOM & Performa']);
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-star-half-stroke"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Pemantauan Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">EDOM & Performa Dosen</h1>
                        <div style="opacity:.9;">Pantau agregat evaluasi mahasiswa dan sinyal kepatuhan mengajar sesuai scope jabatan aktif.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-user-tie"></i>{{ $stats['total'] }} dosen</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-comments"></i>{{ $stats['responses'] }} respon</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-chart-line"></i>{{ number_format($stats['avg_final'], 2) }} rata-rata</span>
                        </div>
                    </div>
                </div>
                <div class="assignment-panel" style="min-width:min(100%, 260px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                    <div style="opacity:.86;font-weight:700;">Rata-rata EDOM</div>
                    <div class="h2 mb-0" style="font-weight:900;">{{ number_format($stats['avg_edom'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    @unless ($hasScope)
        <div class="assignment-panel mb-4" style="background:#fffbeb;border-color:#fde68a;">
            <div class="fw-bold text-warning"><i class="fas fa-triangle-exclamation me-2"></i>Scope belum tersedia</div>
            <div class="text-secondary mt-1">Akun ini belum memiliki scope fakultas atau program studi aktif.</div>
        </div>
    @endunless

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Review</div><div class="h2 fw-bold mb-0">{{ $stats['total'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Respon EDOM</div><div class="h2 fw-bold text-primary mb-0">{{ $stats['responses'] }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Rata-rata EDOM</div><div class="h2 fw-bold text-success mb-0">{{ number_format($stats['avg_edom'], 2) }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Rata-rata Akhir</div><div class="h2 fw-bold text-warning mb-0">{{ number_format($stats['avg_final'], 2) }}</div></div></div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Daftar Performa</h3>
            <span class="assignment-pill">{{ $rows->total() }} data</span>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($rows as $row)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-5">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-tie"></i></span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill" style="{{ $this->scoreStyle($row->final_score) }}">Akhir {{ $row->final_score ?: '-' }}</span>
                                            <span class="assignment-pill"><i class="fas fa-calendar"></i>{{ $row->edomPeriod?->name ?? '-' }}</span>
                                        </div>
                                        <div class="fw-bold">{{ $row->owner?->name ?? '-' }}</div>
                                        <div class="text-secondary small">{{ $row->lecturerProfile?->studyProgram?->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="assignment-panel">
                                    <div class="d-flex justify-content-between border-bottom py-1"><span>EDOM</span><strong>{{ $row->edom_score ?: '-' }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span>Mengajar</span><strong>{{ $row->teaching_compliance_score ? $row->teaching_compliance_score.'%' : '-' }}</strong></div>
                                    <div class="d-flex justify-content-between pt-1"><span>Absensi</span><strong>{{ $row->attendance_compliance_score ? $row->attendance_compliance_score.'%' : '-' }}</strong></div>
                                </div>
                            </div>
                            <div class="col-xl-3 text-xl-end">
                                <div class="d-flex justify-content-xl-end gap-2 flex-wrap">
                                    <span class="assignment-pill">{{ $row->edom_response_count }} respon</span>
                                    <span class="assignment-pill">BKD {{ $row->workload_total_sks ?: '-' }}</span>
                                </div>
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
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
</div>
