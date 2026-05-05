<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public int $offeringId;
    public array $classInfo = [];
    public array $students = [];

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
            'id' => $offering->id,
            'course' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-'),
            'class' => $offering->label ?? '-',
            'class_code' => $offering->code ?? '-',
            'academic_year' => $offering->academicYear?->name ?? '-',
            'study_program' => $offering->studyProgram?->name ?? '-',
        ];

        $this->students = StudyPlanDetail::query()
            ->with(['studyPlan.studentProfile.user', 'studyPlan'])
            ->where('course_offering_id', $this->offeringId)
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                $studentProfile = $detail->studyPlan?->studentProfile;

                return [
                    'student_profile_id' => $studentProfile?->id,
                    'nim' => $studentProfile?->nim ?? '-',
                    'name' => $studentProfile?->user?->name ?? '-',
                    'study_plan_status' => $detail->studyPlan?->status ?? '-',
                    'is_repeat' => $detail->is_repeat ? 'Ya' : 'Tidak',
                ];
            })
            ->filter(fn (array $student) => $student['student_profile_id'])
            ->unique('student_profile_id')
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Students List',
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

        .student-card {
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            background: #f8fafc;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .student-card:hover {
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

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            font-weight: 500;
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
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Daftar Mahasiswa Kelas</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">{{ $classInfo['course'] }}</h2>
                            <div style="font-size: 1.1rem; opacity: 0.95; margin-top: 4px;">Kelas {{ $classInfo['class'] }}</div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="info-badge" style="color: #1e293b;">
                            <i class="fas fa-hashtag"></i>
                            {{ $classInfo['class_code'] }}
                        </span>
                        <span class="info-badge" style="color: #1e293b;">
                            <i class="fas fa-calendar-alt"></i>
                            {{ $classInfo['academic_year'] }}
                        </span>
                        <span class="info-badge" style="color: #1e293b;">
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

    {{-- Students List --}}
    <div class="card modern-card">
        <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-users" style="font-size: 1.3rem; color: #667eea;"></i>
                <h3 class="card-title mb-0" style="font-weight: 700;">Mahasiswa Terdaftar</h3>
            </div>
            <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem;">
                <i class="fas fa-user-graduate me-1"></i>Total {{ number_format(count($students)) }} mahasiswa
            </span>
        </div>

        <div class="card-body p-4">
            @forelse ($students as $student)
                <div class="student-card">
                    <div class="row align-items-center g-3">
                        <div class="col-md-1">
                            <div class="avatar-circle">
                                {{ strtoupper(substr($student['name'], 0, 1)) }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="font-weight: 700; font-size: 1.05rem; color: #1e293b;">{{ $student['name'] }}</div>
                            <div style="font-size: 0.9rem; color: #64748b; margin-top: 4px;">
                                <i class="fas fa-id-card me-1"></i>{{ $student['nim'] }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 6px;">Status KRS</div>
                            <span class="info-badge" style="color: #1e293b;">
                                <i class="fas fa-info-circle"></i>
                                {{ $student['study_plan_status'] }}
                            </span>
                        </div>
                        <div class="col-md-2">
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 6px;">Mengulang</div>
                            @if($student['is_repeat'] === 'Ya')
                                <span style="padding: 6px 12px; border-radius: 8px; background: #fee2e2; color: #991b1b; font-weight: 600; font-size: 0.85rem;">
                                    <i class="fas fa-redo me-1"></i>{{ $student['is_repeat'] }}
                                </span>
                            @else
                                <span style="padding: 6px 12px; border-radius: 8px; background: #d1fae5; color: #065f46; font-weight: 600; font-size: 0.85rem;">
                                    <i class="fas fa-check-circle me-1"></i>Tidak
                                </span>
                            @endif
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                                <i class="fas fa-eye me-1"></i>Detail
                            </button>
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
