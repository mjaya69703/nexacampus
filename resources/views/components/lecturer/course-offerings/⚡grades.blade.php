<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public int $offeringId;
    public array $classInfo = [];
    public array $gradeRows = [];

    public function mount(int $offeringId): void
    {
        $this->offeringId = $offeringId;

        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            abort(403);
        }

        $assignment = CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->offeringId)
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram'])
            ->first();

        if (! $assignment || ! $assignment->courseOffering) {
            abort(404);
        }

        $offering = $assignment->courseOffering;

        $this->classInfo = [
            'course' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-'),
            'class' => $offering->label ?? '-',
            'class_code' => $offering->code ?? '-',
            'academic_year' => $offering->academicYear?->name ?? '-',
            'study_program' => $offering->studyProgram?->name ?? '-',
        ];

        $this->gradeRows = StudyPlanDetail::query()
            ->with([
                'studyPlan.studentProfile.user',
                'studentGrade.components',
            ])
            ->where('course_offering_id', $this->offeringId)
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                $student = $detail->studyPlan?->studentProfile;
                $grade = $detail->studentGrade;

                return [
                    'study_plan_detail_id' => $detail->id,
                    'nim' => $student?->nim ?? '-',
                    'name' => $student?->user?->name ?? '-',
                    'final_score' => $grade?->final_score,
                    'letter_grade' => $grade?->letter_grade ?? '-',
                    'grade_point' => $grade?->grade_point,
                    'grade_status' => $grade?->grade_status ?? 'Draft',
                    'component_count' => $grade?->components?->count() ?? 0,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Course Grades',
        ]);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Published' => 'bg-green-lt text-green',
            'Finalized' => 'bg-blue-lt text-blue',
            'Draft' => 'bg-yellow-lt text-yellow',
            default => 'bg-secondary-lt text-secondary',
        };
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

        .grade-card {
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            background: #f8fafc;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .grade-card:hover {
            background: white;
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .avatar-circle {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.4rem;
        }

        .score-display {
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            background: white;
        }

        .score-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1e293b;
        }

        .grade-letter {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.4rem;
            color: white;
        }

        .grade-a { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .grade-b { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .grade-c { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .grade-d { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .grade-e { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); }

        .status-badge-published {
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-badge-finalized {
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-badge-draft {
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }
    </style>
@endpush

<div>
    <x-alert />

    {{-- Hero Section --}}
    <div class="card modern-card hero-gradient mb-4" style="color: white;">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Nilai Kelas</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">{{ $classInfo['course'] }}</h2>
                            <div style="font-size: 1.1rem; opacity: 0.95; margin-top: 4px;">Kelas {{ $classInfo['class'] }}</div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="info-badge" style="color: #1e293b; background: rgba(255,255,255,0.9);">
                            <i class="fas fa-hashtag"></i>
                            {{ $classInfo['class_code'] }}
                        </span>
                        <span class="info-badge" style="color: #1e293b; background: rgba(255,255,255,0.9);">
                            <i class="fas fa-calendar-alt"></i>
                            {{ $classInfo['academic_year'] }}
                        </span>
                        <span class="info-badge" style="color: #1e293b; background: rgba(255,255,255,0.9);">
                            <i class="fas fa-graduation-cap"></i>
                            {{ $classInfo['study_program'] }}
                        </span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('lecturer.course-offerings.show', ['id' => $offeringId]) }}" class="btn btn-light btn-lg" style="border-radius: 12px; font-weight: 600;">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Detail Kelas
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Grades List --}}
    <div class="card modern-card">
        <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-users" style="font-size: 1.3rem; color: #667eea;"></i>
                <h3 class="card-title mb-0" style="font-weight: 700;">Daftar Mahasiswa dan Nilai</h3>
            </div>
            <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem;">
                <i class="fas fa-user-graduate me-1"></i>Total {{ number_format(count($gradeRows)) }} mahasiswa
            </span>
        </div>

        <div class="card-body p-4">
            @forelse ($gradeRows as $row)
                <div class="grade-card">
                    <div class="row align-items-center g-3">
                        <div class="col-md-1">
                            <div class="avatar-circle">
                                {{ strtoupper(substr($row['name'], 0, 1)) }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div style="font-weight: 700; font-size: 1.05rem; color: #1e293b;">{{ $row['name'] }}</div>
                            <div style="font-size: 0.9rem; color: #64748b; margin-top: 4px;">
                                <i class="fas fa-id-card me-1"></i>{{ $row['nim'] }}
                            </div>
                            <div style="margin-top: 6px;">
                                @if($row['grade_status'] === 'Published')
                                    <span class="status-badge-published">
                                        <i class="fas fa-check-circle me-1"></i>{{ $row['grade_status'] }}
                                    </span>
                                @elseif($row['grade_status'] === 'Finalized')
                                    <span class="status-badge-finalized">
                                        <i class="fas fa-lock me-1"></i>{{ $row['grade_status'] }}
                                    </span>
                                @else
                                    <span class="status-badge-draft">
                                        <i class="fas fa-edit me-1"></i>{{ $row['grade_status'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="score-display">
                                        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">Nilai Akhir</div>
                                        <div class="score-number">{{ $row['final_score'] !== null ? number_format((float) $row['final_score'], 1) : '-' }}</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="score-display">
                                        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">Huruf</div>
                                        @php
                                            $gradeClass = match(strtoupper($row['letter_grade'])) {
                                                'A' => 'grade-a',
                                                'B' => 'grade-b',
                                                'C' => 'grade-c',
                                                'D' => 'grade-d',
                                                default => 'grade-e'
                                            };
                                        @endphp
                                        <div class="grade-letter {{ $gradeClass }} mx-auto">
                                            {{ $row['letter_grade'] }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="score-display">
                                        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">Point</div>
                                        <div class="score-number">{{ $row['grade_point'] !== null ? number_format((float) $row['grade_point'], 2) : '-' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 8px; font-size: 0.85rem; color: #64748b;">
                                <i class="fas fa-tasks me-1"></i>{{ $row['component_count'] }} komponen nilai
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="{{ route('lecturer.student-grades.edit', ['id' => $row['study_plan_detail_id']]) }}" class="btn btn-primary btn-lg" style="border-radius: 12px; font-weight: 600; padding: 10px 20px;">
                                <i class="fas fa-edit me-2"></i>Kelola Nilai
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="fas fa-user-slash" style="font-size: 4rem; color: #cbd5e1;"></i>
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">Belum ada mahasiswa terdaftar pada kelas ini.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
