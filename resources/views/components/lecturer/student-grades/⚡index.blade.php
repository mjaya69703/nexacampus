<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public array $gradeRows = [];
    public string $search = '';

    public function mount(): void
    {
        $this->loadRows();
    }

    public function updatedSearch(): void
    {
        $this->loadRows();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Student Grades',
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

    private function loadRows(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->gradeRows = [];

            return;
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            $this->gradeRows = [];

            return;
        }

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->pluck('course_offering_id');

        if ($offeringIds->isEmpty()) {
            $this->gradeRows = [];

            return;
        }

        $search = mb_strtolower(trim($this->search));

        $rows = StudyPlanDetail::query()
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'courseOffering.studyProgram',
                'studyPlan.studentProfile.user',
                'studentGrade.components',
            ])
            ->whereIn('course_offering_id', $offeringIds)
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                $student = $detail->studyPlan?->studentProfile;
                $offering = $detail->courseOffering;
                $grade = $detail->studentGrade;

                return [
                    'study_plan_detail_id' => $detail->id,
                    'nim' => $student?->nim ?? '-',
                    'student_name' => $student?->user?->name ?? '-',
                    'course' => ($offering?->course?->code ?? '-') . ' - ' . ($offering?->course?->name ?? '-'),
                    'class' => $offering?->label ?? '-',
                    'academic_year' => $offering?->academicYear?->name ?? '-',
                    'study_program' => $offering?->studyProgram?->name ?? '-',
                    'final_score' => $grade?->final_score,
                    'letter_grade' => $grade?->letter_grade ?? '-',
                    'grade_point' => $grade?->grade_point,
                    'grade_status' => $grade?->grade_status ?? 'Draft',
                    'component_count' => $grade?->components?->count() ?? 0,
                ];
            });

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search) {
                $text = mb_strtolower(implode(' ', [
                    $row['nim'],
                    $row['student_name'],
                    $row['course'],
                    $row['class'],
                    $row['study_program'],
                ]));

                return str_contains($text, $search);
            });
        }

        $this->gradeRows = $rows
            ->sortBy(['course', 'student_name'])
            ->values()
            ->all();
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
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            background: white;
            overflow: hidden;
        }

        .grade-card:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.2);
        }

        .grade-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .student-avatar {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-published {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .badge-finalized {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .badge-draft {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .score-display {
            text-align: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 12px;
        }

        .score-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }

        .score-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .grade-letter {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto;
        }

        .grade-a { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .grade-b { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .grade-c { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .grade-d { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .grade-e { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; }

        .info-item {
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 0.5rem;
        }

        .info-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1f2937;
        }

        .action-btn {
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .action-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .action-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .filter-input {
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Input & Kelola Nilai</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">Nilai Mahasiswa</h2>
                        </div>
                    </div>
                    <div style="opacity: 0.9;">
                        Kelola draft dan finalisasi nilai untuk seluruh kelas yang Anda ampu
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <div style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <div style="font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Total Data Nilai</div>
                        <div class="h1 mb-0" style="font-weight: 700;">{{ number_format(count($gradeRows)) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Section --}}
    <div class="modern-card mb-4">
        <div class="card-body p-4">
            <input
                type="text"
                class="form-control filter-input w-100"
                placeholder="🔍 Cari mahasiswa, mata kuliah, kelas, atau program studi..."
                wire:model.live.debounce.300ms="search"
            >
            <div class="mt-3 text-secondary small">
                Menampilkan <strong>{{ count($gradeRows) }}</strong> data nilai mahasiswa
            </div>
        </div>
    </div>

    {{-- Grade Cards Grid --}}
    <div class="row g-4">
        @forelse ($gradeRows as $row)
            <div class="col-lg-6 col-xl-4">
                <div class="grade-card h-100">
                    {{-- Card Header - Student Info --}}
                    <div class="grade-header">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="student-avatar">
                                {{ strtoupper(substr($row['student_name'], 0, 1)) }}
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 700; color: #1f2937; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $row['student_name'] }}
                                </div>
                                <div style="font-size: 0.85rem; color: #6b7280;">
                                    NIM: {{ $row['nim'] }}
                                </div>
                            </div>
                        </div>

                        <span class="status-badge {{ 
                            $row['grade_status'] === 'Published' ? 'badge-published' : 
                            ($row['grade_status'] === 'Finalized' ? 'badge-finalized' : 'badge-draft') 
                        }}">
                            {{ $row['grade_status'] }}
                        </span>
                    </div>

                    {{-- Card Body --}}
                    <div class="p-3">
                        {{-- Course Info --}}
                        <div class="info-item">
                            <div class="info-label">📚 Mata Kuliah</div>
                            <div class="info-value" style="font-size: 0.9rem;">{{ $row['course'] }}</div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="info-item" style="margin-bottom: 0;">
                                    <div class="info-label">🏫 Kelas</div>
                                    <div class="info-value" style="font-size: 0.85rem;">{{ $row['class'] }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item" style="margin-bottom: 0;">
                                    <div class="info-label">📅 Tahun</div>
                                    <div class="info-value" style="font-size: 0.85rem;">{{ $row['academic_year'] }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Score Display --}}
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="score-display">
                                    <div class="score-value" style="font-size: 1.5rem;">
                                        {{ $row['final_score'] !== null ? number_format((float) $row['final_score'], 1) : '-' }}
                                    </div>
                                    <div class="score-label">Skor</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="score-display">
                                    <div class="grade-letter {{ 
                                        stripos($row['letter_grade'], 'A') !== false ? 'grade-a' :
                                        (stripos($row['letter_grade'], 'B') !== false ? 'grade-b' :
                                        (stripos($row['letter_grade'], 'C') !== false ? 'grade-c' :
                                        (stripos($row['letter_grade'], 'D') !== false ? 'grade-d' : 'grade-e')))
                                    }}">
                                        {{ $row['letter_grade'] }}
                                    </div>
                                    <div class="score-label">Huruf</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="score-display">
                                    <div class="score-value" style="font-size: 1.5rem;">
                                        {{ $row['grade_point'] !== null ? number_format((float) $row['grade_point'], 1) : '-' }}
                                    </div>
                                    <div class="score-label">Point</div>
                                </div>
                            </div>
                        </div>

                        {{-- Component Count --}}
                        <div class="mb-3">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem; background: #f0fdf4; border-radius: 10px; color: #10b981; font-weight: 600;">
                                <span>📝</span>
                                <span>{{ $row['component_count'] }} Komponen Nilai</span>
                            </div>
                        </div>

                        {{-- Action Button --}}
                        <a href="{{ route('lecturer.student-grades.edit', ['id' => $row['study_plan_detail_id']]) }}" class="action-btn action-btn-primary w-100 d-block text-center text-decoration-none">
                            <i class="fas fa-edit me-2"></i>Kelola Nilai
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="modern-card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📭</div>
                    <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Belum Ada Data Nilai</h4>
                    <p style="color: #6b7280;">Anda belum memiliki data nilai untuk kelas yang diampu.</p>
                    <a href="{{ route('lecturer.course-offerings.index') }}" class="btn btn-primary mt-3" style="border-radius: 10px; padding: 0.75rem 1.5rem;">
                        📚 Lihat Kelas
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>
