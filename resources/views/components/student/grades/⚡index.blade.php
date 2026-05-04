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
        .student-summary-card {
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .student-summary-label {
            font-size: 12px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .student-summary-value {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.1;
        }

        .student-grade-card {
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
        }

        .student-grade-meta {
            font-size: 12px;
            color: #6b7280;
        }

        .student-grade-score {
            font-size: 30px;
            font-weight: 700;
            line-height: 1;
        }

        .student-grade-divider {
            border-top: 1px dashed rgba(0, 0, 0, 0.08);
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .student-grade-table th,
        .student-grade-table td {
            padding: 8px 10px;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @elseif (! $hasGrades)
        <div class="alert alert-info">Belum ada nilai yang dipublikasikan.</div>
    @else
        <div class="row mb-4 row-cards">
            <div class="col-md-4">
                <div class="card student-summary-card">
                    <div class="card-body">
                        <div class="student-summary-label">Total Mata Kuliah</div>
                        <div class="student-summary-value">{{ $summary['total_courses'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card student-summary-card">
                    <div class="card-body">
                        <div class="student-summary-label">Nilai Dipublikasikan</div>
                        <div class="student-summary-value">{{ $summary['published_courses'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card student-summary-card">
                    <div class="card-body">
                        <div class="student-summary-label">Rata-rata Grade Point</div>
                        <div class="student-summary-value">
                            {{ $summary['average_grade_point'] ? number_format($summary['average_grade_point'], 2) : '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards">
            @foreach ($gradeItems as $i => $grade)
                <div class="col-lg-6">
                    <div class="card student-grade-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <div class="fw-semibold">{{ $grade['course_code'] }}</div>
                                    <div class="student-grade-meta">{{ $grade['course_name'] }}</div>
                                </div>

                                <div class="text-end">
                                    <div class="student-grade-score text-primary">{{ $grade['letter_grade'] ?? '-' }}</div>
                                    <div class="student-grade-meta">
                                        GP {{ $grade['grade_point'] !== null ? number_format((float) $grade['grade_point'], 2) : '-' }}
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div class="student-grade-meta">
                                    {{ $grade['academic_year'] }} - Semester {{ $grade['semester_no'] ?? '-' }}
                                </div>

                                <div class="d-flex gap-2">
                                    <span class="badge bg-azure-lt">
                                        Score {{ $grade['final_score'] !== null ? number_format((float) $grade['final_score'], 2) : '-' }}
                                    </span>
                                    <span class="badge {{ $this->statusBadgeClass($grade['result_status']) }}">
                                        {{ $grade['result_status'] ?? '-' }}
                                    </span>
                                </div>
                            </div>

                            <button class="btn btn-sm btn-ghost-secondary w-100" data-bs-toggle="collapse" data-bs-target="#grade-{{ $i }}">
                                Lihat Komponen Nilai
                            </button>

                            <div id="grade-{{ $i }}" class="collapse student-grade-divider">
                                <div class="table-responsive">
                                    <table class="table table-sm student-grade-table">
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
                                                    <td>{{ $component['name'] }}</td>
                                                    <td>{{ $component['weight'] !== null ? number_format((float) $component['weight'], 2) . '%' : '-' }}</td>
                                                    <td>{{ $component['score'] !== null ? number_format((float) $component['score'], 2) : '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-secondary">Belum ada komponen nilai.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="student-grade-meta">
                                    Total Bobot: {{ number_format((float) $grade['total_weight'], 2) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
