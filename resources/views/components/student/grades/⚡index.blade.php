<?php

use App\Models\Academic\GradeAppeal;
use App\Models\Academic\StudentGrade;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;

    public bool $hasGrades = false;

    public array $gradeItems = [];

    public array $summary = [
        'total_courses' => 0,
        'published_courses' => 0,
        'average_grade_point' => null,
        'active_appeals' => 0,
    ];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $studentProfile = $user->studentProfile()->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;

        $grades = StudentGrade::query()
            ->with([
                'studyPlanDetail.studyPlan.academicYear',
                'studyPlanDetail.courseOffering.course',
                'components' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->whereHas('studyPlanDetail.studyPlan', fn ($query) => $query->where('student_profile_id', $studentProfile->id))
            ->where('grade_status', 'Published')
            ->orderByDesc('graded_at')
            ->orderByDesc('id')
            ->get();

        $this->hasGrades = $grades->isNotEmpty();

        $appeals = GradeAppeal::query()
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('student_grade_id', $grades->pluck('id'))
            ->latest('submitted_at')
            ->latest('id')
            ->get()
            ->groupBy('student_grade_id');

        $this->gradeItems = $grades
            ->map(function (StudentGrade $grade) use ($appeals) {
                $detail = $grade->studyPlanDetail;
                $studyPlan = $detail?->studyPlan;
                $offering = $detail?->courseOffering;
                $course = $offering?->course;
                $latestAppeal = $appeals->get($grade->id)?->first();

                return [
                    'course_code' => $course?->code ?? '-',
                    'course_name' => $course?->name ?? ($offering?->label ?? '-'),
                    'class_label' => $offering?->label ?? '-',
                    'academic_year' => $studyPlan?->academicYear?->name ?? '-',
                    'semester_no' => $studyPlan?->semester_no,
                    'final_score' => $grade->final_score,
                    'letter_grade' => $grade->letter_grade,
                    'grade_point' => $grade->grade_point,
                    'result_status' => $grade->result_status,
                    'appeal_status' => $latestAppeal?->status,
                    'has_active_appeal' => in_array($latestAppeal?->status, ['submitted', 'under_review'], true),
                    'components' => $grade->components
                        ->map(fn ($component) => [
                            'name' => $component->name,
                            'weight' => $component->weight_percentage,
                            'score' => $component->score,
                            'notes' => $component->notes,
                        ])
                        ->values()
                        ->all(),
                    'total_weight' => round((float) $grade->components->sum(fn ($component) => (float) ($component->weight_percentage ?? 0)), 2),
                ];
            })
            ->values()
            ->all();

        $this->summary = [
            'total_courses' => $grades->count(),
            'published_courses' => $grades->count(),
            'average_grade_point' => $grades->whereNotNull('grade_point')->avg('grade_point'),
            'active_appeals' => $appeals->flatten()->whereIn('status', ['submitted', 'under_review'])->count(),
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'Passed' => 'Lulus',
            'Failed' => 'Tidak Lulus',
            'Incomplete' => 'Belum Lengkap',
            'Withdrawn' => 'Mengundurkan Diri',
            'Cancelled' => 'Dibatalkan',
            default => $status ?: '-',
        };
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Passed' => 'bg-green-lt text-green',
            'Incomplete' => 'bg-yellow-lt text-yellow',
            'Failed', 'Cancelled', 'Withdrawn' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function appealStatusLabel(?string $status): string
    {
        return match ($status) {
            'submitted' => 'Keberatan Diajukan',
            'under_review' => 'Sedang Direview',
            'approved' => 'Koreksi Disetujui',
            'rejected' => 'Keberatan Ditolak',
            'closed' => 'Ditutup',
            default => 'Belum Ada Keberatan',
        };
    }

    public function appealBadgeClass(?string $status): string
    {
        return match ($status) {
            'submitted' => 'bg-yellow-lt text-yellow',
            'under_review' => 'bg-blue-lt text-blue',
            'approved' => 'bg-green-lt text-green',
            'rejected' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Nilai Saya',
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
            transition: all 0.3s ease;
            background: white;
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

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            line-height: 1;
        }

        .grade-item {
            padding: 1.25rem;
            border-radius: 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .grade-item:hover {
            transform: translateY(-3px);
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
        }

        .grade-letter {
            width: 82px;
            min-width: 82px;
            height: 82px;
            border-radius: 18px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 18px rgba(16, 185, 129, 0.22);
        }

        .grade-letter strong {
            font-size: 2rem;
            line-height: 1;
        }

        .action-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .component-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.78rem;
            text-transform: uppercase;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @else
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Nilai Akademik Mahasiswa</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">Nilai Terpublikasi</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">Pantau hasil studi yang sudah dirilis dan ajukan keberatan bila ada nilai yang perlu diklarifikasi.</div>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-badge"><i class="fas fa-book"></i>{{ $summary['total_courses'] }} mata kuliah</span>
                                    <span class="info-badge"><i class="fas fa-chart-line"></i>Rata-rata GP {{ $summary['average_grade_point'] ? number_format($summary['average_grade_point'], 2) : '-' }}</span>
                                    <span class="info-badge"><i class="fas fa-scale-balanced"></i>{{ $summary['active_appeals'] }} keberatan aktif</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="d-flex flex-column gap-2">
                            <a href="{{ route('student.transcript.index') }}" class="action-btn justify-content-center" style="background: rgba(255,255,255,0.94); color: #4f46e5;">
                                <i class="fas fa-file-lines"></i>Transkrip Akademik
                            </a>
                            <a href="{{ route('student.grade-appeals.index') }}" class="action-btn justify-content-center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                                <i class="fas fa-scale-balanced"></i>Keberatan Nilai
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb;"><i class="fas fa-book"></i></div>
                    <div class="stat-label">Mata Kuliah</div>
                    <div class="stat-value">{{ $summary['total_courses'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #059669;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">Nilai Terbit</div>
                    <div class="stat-value">{{ $summary['published_courses'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706;"><i class="fas fa-star"></i></div>
                    <div class="stat-label">Rata-rata GP</div>
                    <div class="stat-value">{{ $summary['average_grade_point'] ? number_format($summary['average_grade_point'], 2) : '-' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); color: #7c3aed;"><i class="fas fa-scale-balanced"></i></div>
                    <div class="stat-label">Keberatan Aktif</div>
                    <div class="stat-value">{{ $summary['active_appeals'] }}</div>
                </div>
            </div>
        </div>

        <div class="card modern-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-list-check me-2" style="color: #667eea;"></i>Daftar Nilai</h3>
                <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 8px;">{{ count($gradeItems) }} nilai published</span>
            </div>
            <div class="card-body p-4">
                @forelse ($gradeItems as $i => $grade)
                    <div class="grade-item">
                        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between">
                            <div class="d-flex gap-3">
                                <div class="grade-letter">
                                    <strong>{{ $grade['letter_grade'] ?? '-' }}</strong>
                                    <span style="font-size: 0.78rem;">GP {{ $grade['grade_point'] !== null ? number_format((float) $grade['grade_point'], 2) : '-' }}</span>
                                </div>
                                <div>
                                    <div style="font-weight: 700; font-size: 1.05rem; color: #1f2937;">{{ $grade['course_name'] }}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem;">
                                        <i class="fas fa-hashtag me-1" style="color: #667eea;"></i>{{ $grade['course_code'] }}
                                        <span class="mx-2">&bull;</span>
                                        <i class="fas fa-calendar me-1" style="color: #f59e0b;"></i>{{ $grade['academic_year'] }}
                                        <span class="mx-2">&bull;</span>
                                        <i class="fas fa-layer-group me-1" style="color: #10b981;"></i>Semester {{ $grade['semester_no'] ?? '-' }}
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <span class="badge bg-blue-lt text-blue" style="padding: 0.45rem 0.75rem; border-radius: 8px;">Skor {{ $grade['final_score'] !== null ? number_format((float) $grade['final_score'], 2) : '-' }}</span>
                                        <span class="badge {{ $this->statusBadgeClass($grade['result_status']) }}" style="padding: 0.45rem 0.75rem; border-radius: 8px;">{{ $this->statusLabel($grade['result_status']) }}</span>
                                        <span class="badge {{ $this->appealBadgeClass($grade['appeal_status']) }}" style="padding: 0.45rem 0.75rem; border-radius: 8px;">{{ $this->appealStatusLabel($grade['appeal_status']) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-column flex-sm-row flex-lg-column gap-2 align-items-stretch justify-content-center">
                                <button class="action-btn justify-content-center" type="button" data-bs-toggle="collapse" data-bs-target="#grade-{{ $i }}" style="background: white; color: #4f46e5; border: 2px solid #e0e7ff;">
                                    <i class="fas fa-list-ul"></i>Komponen
                                </button>
                                <a href="{{ route('student.grade-appeals.index') }}" class="action-btn justify-content-center" style="{{ $grade['has_active_appeal'] ? 'background: #eef2ff; color: #4f46e5;' : 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;' }}">
                                    <i class="fas {{ $grade['has_active_appeal'] ? 'fa-clock' : 'fa-pen-to-square' }}"></i>{{ $grade['has_active_appeal'] ? 'Pantau Keberatan' : 'Ajukan Keberatan' }}
                                </a>
                            </div>
                        </div>

                        <div id="grade-{{ $i }}" class="collapse mt-3">
                            <div class="table-responsive" style="border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
                                <table class="table component-table mb-0">
                                    <thead><tr><th>Komponen</th><th>Bobot</th><th>Skor</th><th>Catatan</th></tr></thead>
                                    <tbody>
                                        @forelse ($grade['components'] as $component)
                                            <tr>
                                                <td class="fw-semibold">{{ $component['name'] }}</td>
                                                <td>{{ $component['weight'] !== null ? number_format((float) $component['weight'], 2).'%' : '-' }}</td>
                                                <td>{{ $component['score'] !== null ? number_format((float) $component['score'], 2) : '-' }}</td>
                                                <td>{{ $component['notes'] ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada komponen nilai.</td></tr>
                                        @endforelse
                                        <tr>
                                            <td class="fw-bold">Total Bobot</td>
                                            <td class="fw-bold">{{ number_format((float) $grade['total_weight'], 2) }}%</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <i class="fas fa-inbox" style="font-size: 4rem; color: #cbd5e1;"></i>
                        <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">Belum ada nilai yang dipublikasikan.</div>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
