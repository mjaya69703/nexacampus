<?php

use App\Models\Academic\StudentGrade;
use Livewire\Component;

new class extends Component {
    public bool $hasProfile = false;
    public bool $hasGrades = false;
    public array $gradeItems = [];
    public array $summary = [
        'total_courses' => 0,
        'published_courses' => 0,
        'average_grade_point' => null,
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
                'components' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('id');
                },
            ])
            ->whereHas('studyPlanDetail.studyPlan', function ($query) use ($studentProfile) {
                $query->where('student_profile_id', $studentProfile->id);
            })
            ->where('grade_status', 'Published')
            ->orderByDesc('graded_at')
            ->orderByDesc('id')
            ->get();

        $this->hasGrades = $grades->isNotEmpty();

        $this->gradeItems = $grades
            ->map(function (StudentGrade $grade) {
                $detail = $grade->studyPlanDetail;
                $studyPlan = $detail?->studyPlan;
                $offering = $detail?->courseOffering;
                $course = $offering?->course;

                $components = $grade->components
                    ->map(function ($component) {
                        return [
                            'name' => $component->name,
                            'weight' => $component->weight_percentage,
                            'score' => $component->score,
                            'notes' => $component->notes,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'course_code' => $course?->code ?? '-',
                    'course_name' => $course?->name ?? ($offering?->label ?? '-'),
                    'academic_year' => $studyPlan?->academicYear?->name ?? '-',
                    'semester_no' => $studyPlan?->semester_no,
                    'final_score' => $grade->final_score,
                    'letter_grade' => $grade->letter_grade,
                    'grade_point' => $grade->grade_point,
                    'result_status' => $grade->result_status,
                    'grade_status' => $grade->grade_status,
                    'components' => $components,
                    'total_weight' => round(
                        (float) $grade->components->sum(function ($component) {
                            return (float) ($component->weight_percentage ?? 0);
                        }),
                        2,
                    ),
                ];
            })
            ->values()
            ->all();

        $this->summary = [
            'total_courses' => $grades->count(),
            'published_courses' => $grades->count(),
            'average_grade_point' => $grades->whereNotNull('grade_point')->avg('grade_point'),
        ];
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Published', 'Passed' => 'bg-green-lt',
            'Incomplete' => 'bg-yellow-lt',
            'Failed', 'Cancelled', 'Withdrawn' => 'bg-red-lt',
            default => 'bg-secondary-lt',
        };
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'My Grades',
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

        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .grade-card {
            border-radius: 16px;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .grade-card:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-color: #667eea;
            transform: translateY(-4px);
        }

        .grade-letter {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .component-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .component-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1rem;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .component-table th:first-child {
            border-radius: 10px 0 0 0;
        }

        .component-table th:last-child {
            border-radius: 0 10px 0 0;
        }

        .component-table td {
            padding: 0.75rem 1rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }

        .component-table tr:last-child td:first-child {
            border-radius: 0 0 0 10px;
        }

        .component-table tr:last-child td:last-child {
            border-radius: 0 0 10px 0;
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
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @elseif (! $hasGrades)
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Belum ada nilai yang dipublikasikan.
        </div>
    @else
        {{-- Hero Section with Gradient --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Nilai Akademik Mahasiswa</div>
                        <h1 class="h2 mb-2" style="font-weight: 700;">Transkrip Nilai</h1>
                        <div style="opacity: 0.9;">
                            <i class="fas fa-graduation-cap me-2"></i>Lihat semua nilai mata kuliah yang telah dipublikasikan
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row row-cards mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #3b82f6;">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Total Mata Kuliah</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['total_courses'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #f59e0b;">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Nilai Dipublikasikan</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $summary['published_courses'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #10b981;">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Rata-rata Grade Point</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">
                                {{ $summary['average_grade_point'] ? number_format($summary['average_grade_point'], 2) : '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Grade Cards --}}
        <div class="row row-cards">
            @foreach ($gradeItems as $i => $grade)
                <div class="col-lg-6">
                    <div class="grade-card h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem; font-size: 1.1rem;">
                                    <i class="fas fa-hashtag me-2" style="color: #667eea;"></i>{{ $grade['course_code'] }}
                                </div>
                                <div style="font-size: 0.9rem; color: #6b7280;">{{ $grade['course_name'] }}</div>
                                <div style="font-size: 0.8rem; color: #9ca3af; margin-top: 0.5rem;">
                                    <i class="fas fa-calendar me-1"></i>{{ $grade['academic_year'] }} - Semester {{ $grade['semester_no'] ?? '-' }}
                                </div>
                            </div>

                            <div class="text-end">
                                <div class="grade-letter">{{ $grade['letter_grade'] ?? '-' }}</div>
                                <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem;">
                                    GP {{ $grade['grade_point'] !== null ? number_format((float) $grade['grade_point'], 2) : '-' }}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <span class="badge bg-blue-lt text-blue" style="padding: 0.5rem 0.75rem;">
                                <i class="fas fa-chart-bar me-1"></i>Score {{ $grade['final_score'] !== null ? number_format((float) $grade['final_score'], 2) : '-' }}
                            </span>
                            <span class="badge {{ $this->statusBadgeClass($grade['result_status']) }}" style="padding: 0.5rem 0.75rem;">
                                <i class="fas fa-check-circle me-1"></i>{{ $grade['result_status'] ?? '-' }}
                            </span>
                        </div>

                        <button class="action-btn w-100 justify-content-center" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#grade-{{ $i }}"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="fas fa-list-ul"></i> Lihat Komponen Nilai
                        </button>

                        <div id="grade-{{ $i }}" class="collapse mt-3">
                            <div class="table-responsive">
                                <table class="component-table">
                                    <thead>
                                        <tr>
                                            <th>Komponen</th>
                                            <th>Bobot</th>
                                            <th>Skor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($grade['components'] as $component)
                                            <tr>
                                                <td style="font-weight: 500;">{{ $component['name'] }}</td>
                                                <td>{{ $component['weight'] !== null ? number_format((float) $component['weight'], 2) . '%' : '-' }}</td>
                                                <td style="font-weight: 600; color: #667eea;">{{ $component['score'] !== null ? number_format((float) $component['score'], 2) : '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" style="text-align: center; color: #9ca3af; padding: 2rem;">
                                                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                                                    <div>Belum ada komponen nilai.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top: 1rem; padding: 0.75rem; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 10px; text-align: center;">
                                <div style="font-size: 0.85rem; color: #1e40af; font-weight: 600;">
                                    <i class="fas fa-calculator me-2"></i>Total Bobot: {{ number_format((float) $grade['total_weight'], 2) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
