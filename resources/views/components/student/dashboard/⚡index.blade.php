<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public array $studentInfo = [];
    public array $stats = [];
    public array $attendance = [];
    public array $upcomingSchedules = [];
    public ?string $activeAcademicYear = null;
    public ?string $registrationStatus = null;
    public ?string $currentStudyPlanStatus = null;
    public ?string $lastLoginAt = null;
    public $recentGrades = [];

    public function mount(): void
    {
        $this->initializeDefaults();

        $user = auth()->user();
        $this->lastLoginAt = $user?->last_login_at?->format('d M Y H:i');

        if (! $user) {
            return;
        }

        $studentProfile = $user->studentProfile()
            ->with(['studyProgram.faculty', 'entryAcademicYear'])
            ->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        $this->activeAcademicYear = $activeAcademicYear?->name;

        $latestRegistration = StudentRegistration::query()
            ->where('student_profile_id', $studentProfile->id)
            ->with('academicYear')
            ->latest('id')
            ->first();

        $currentRegistration = $activeAcademicYear
            ? StudentRegistration::query()
                ->where('student_profile_id', $studentProfile->id)
                ->where('academic_year_id', $activeAcademicYear->id)
                ->latest('id')
                ->first()
            : $latestRegistration;

        $latestStudyPlan = StudyPlan::query()
            ->where('student_profile_id', $studentProfile->id)
            ->with('academicYear')
            ->latest('id')
            ->first();

        $currentStudyPlan = $activeAcademicYear
            ? StudyPlan::query()
                ->where('student_profile_id', $studentProfile->id)
                ->where('academic_year_id', $activeAcademicYear->id)
                ->latest('id')
                ->first()
            : $latestStudyPlan;

        $latestStudyResult = StudyResult::query()
            ->where('student_profile_id', $studentProfile->id)
            ->latest('id')
            ->first();

        $this->registrationStatus = $currentRegistration?->registration_status ?? '-';
        $this->currentStudyPlanStatus = $currentStudyPlan?->status ?? '-';

        $studyPlanDetailQuery = StudyPlanDetail::query();

        if ($currentStudyPlan) {
            $studyPlanDetailQuery->where('study_plan_id', $currentStudyPlan->id);
        } else {
            $studyPlanDetailQuery->whereRaw('1 = 0');
        }

        $currentCourses = (clone $studyPlanDetailQuery)->count();
        $currentCredits = (clone $studyPlanDetailQuery)->sum('credits');

        $publishedGradesQuery = StudentGrade::query()
            ->whereHas('studyPlanDetail.studyPlan', function ($query) use ($studentProfile) {
                $query->where('student_profile_id', $studentProfile->id);
            })
            ->where('grade_status', 'Published');

        $publishedGradesCount = (clone $publishedGradesQuery)->count();
        $publishedGradeAverage = (clone $publishedGradesQuery)
            ->whereNotNull('grade_point')
            ->avg('grade_point');

        $transcriptEntriesCount = TranscriptEntry::query()
            ->where('student_profile_id', $studentProfile->id)
            ->count();

        $attendanceQuery = AttendanceRecord::query()
            ->where('student_profile_id', $studentProfile->id);

        $totalAttendance = (clone $attendanceQuery)->count();
        $attendedCount = (clone $attendanceQuery)
            ->whereIn('status', ['Present', 'Late', 'Excused', 'Sick'])
            ->count();
        $absentCount = (clone $attendanceQuery)
            ->where('status', 'Absent')
            ->count();

        $attendanceRate = $totalAttendance > 0
            ? round(($attendedCount / $totalAttendance) * 100, 1)
            : null;

        $this->studentInfo = [
            'name' => $user->name,
            'nim' => $studentProfile->nim,
            'study_program' => $studentProfile->studyProgram?->name ?? '-',
            'faculty' => $studentProfile->studyProgram?->faculty?->name ?? '-',
            'academic_status' => $studentProfile->academic_status,
            'current_semester' => $studentProfile->current_semester,
        ];

        $this->stats = [
            'current_courses' => (int) $currentCourses,
            'current_credits' => (int) $currentCredits,
            'published_grades' => (int) $publishedGradesCount,
            'transcript_entries' => (int) $transcriptEntriesCount,
            'semester_gpa' => $latestStudyResult?->semester_gpa
                ? number_format((float) $latestStudyResult->semester_gpa, 2)
                : '-',
            'cumulative_gpa' => $latestStudyResult?->cumulative_gpa
                ? number_format((float) $latestStudyResult->cumulative_gpa, 2)
                : ($publishedGradeAverage
                    ? number_format((float) $publishedGradeAverage, 2)
                    : '-'),
        ];

        $this->attendance = [
            'total' => (int) $totalAttendance,
            'attended' => (int) $attendedCount,
            'absent' => (int) $absentCount,
            'rate' => $attendanceRate,
        ];

        $this->recentGrades = StudentGrade::query()
            ->with(['studyPlanDetail.courseOffering.course'])
            ->whereHas('studyPlanDetail.studyPlan', function ($query) use ($studentProfile) {
                $query->where('student_profile_id', $studentProfile->id);
            })
            ->where('grade_status', 'Published')
            ->latest('graded_at')
            ->limit(8)
            ->get();

        if ($currentStudyPlan) {
            $dayOrder = [
                'Monday' => 1,
                'Tuesday' => 2,
                'Wednesday' => 3,
                'Thursday' => 4,
                'Friday' => 5,
                'Saturday' => 6,
                'Sunday' => 7,
            ];

            $this->upcomingSchedules = StudyPlanDetail::query()
                ->with(['courseOffering.course', 'courseOffering.courseSchedules.room.building'])
                ->where('study_plan_id', $currentStudyPlan->id)
                ->get()
                ->flatMap(function ($detail) {
                    $offering = $detail->courseOffering;

                    if (! $offering) {
                        return [];
                    }

                    $courseName = $offering->course?->name
                        ?? $offering->course?->title
                        ?? ($offering->label ?: '-');

                    return $offering->courseSchedules
                        ->where('is_active', true)
                        ->map(function ($schedule) use ($courseName) {
                            return [
                                'course_name' => $courseName,
                                'day' => $schedule->day_of_week,
                                'start_time' => $this->formatTime($schedule->start_time),
                                'end_time' => $this->formatTime($schedule->end_time),
                                'room' => $schedule->room?->name ?? '-',
                                'building' => $schedule->room?->building?->name ?? '-',
                                'mode' => $schedule->delivery_mode,
                            ];
                        });
                })
                ->sortBy(function (array $schedule) use ($dayOrder) {
                    return $dayOrder[$schedule['day']] ?? 99;
                })
                ->values()
                ->take(8)
                ->all();
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Dashboard',
            'pages' => 'Student Dashboard',
        ]);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Approved', 'Published', 'Passed', 'Aktif' => 'bg-green-lt text-green',
            'Submitted', 'Taken' => 'bg-blue-lt text-blue',
            'Draft', 'Incomplete' => 'bg-yellow-lt text-yellow',
            'Rejected', 'Cancelled', 'Failed', 'Drop Out', 'Keluar' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    private function initializeDefaults(): void
    {
        $this->studentInfo = [
            'name' => '-',
            'nim' => '-',
            'study_program' => '-',
            'faculty' => '-',
            'academic_status' => '-',
            'current_semester' => null,
        ];

        $this->stats = [
            'current_courses' => 0,
            'current_credits' => 0,
            'published_grades' => 0,
            'transcript_entries' => 0,
            'semester_gpa' => '-',
            'cumulative_gpa' => '-',
        ];

        $this->attendance = [
            'total' => 0,
            'attended' => 0,
            'absent' => 0,
            'rate' => null,
        ];
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && strlen($value) >= 5) {
            return substr($value, 0, 5);
        }

        return '-';
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

        .modern-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
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

        .quick-action-btn {
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .quick-action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
        }

        .schedule-item {
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .schedule-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }

        .grade-item {
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .grade-item:hover {
            background: #f1f5f9;
        }

        .attendance-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
        }

        .attendance-circle-inner {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .info-badge {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.85rem;
            font-weight: 500;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum terhubung.</div>
    @else
        {{-- Hero Section --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                {{ strtoupper(substr($studentInfo['name'] ?? 'M', 0, 1)) }}
                            </div>

                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Ruang Akademik Mahasiswa</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $studentInfo['name'] }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-graduation-cap me-2"></i>{{ $studentInfo['study_program'] }} • {{ $studentInfo['faculty'] }}
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-badge">
                                        <i class="fas fa-id-card me-2"></i>NIM {{ $studentInfo['nim'] }}
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-calendar me-2"></i>Semester {{ $studentInfo['current_semester'] ?? '-' }}
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-clock me-2"></i>{{ $activeAcademicYear ?? 'Tahun akademik belum aktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Status Akademik</div>
                                    <span class="badge bg-whitebg-white text-primary" style="font-size: 0.85rem;">
                                        {{ $studentInfo['academic_status'] ?? '-' }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Registrasi</div>
                                    <span class="badge {{ $this->statusBadgeClass($registrationStatus) }}" style="font-size: 0.85rem;">
                                        {{ $registrationStatus }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">KRS</div>
                                    <span class="badge {{ $this->statusBadgeClass($currentStudyPlanStatus) }}" style="font-size: 0.85rem;">
                                        {{ $currentStudyPlanStatus }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Login Terakhir</div>
                                    <div style="font-weight: 600; font-size: 0.85rem;">{{ $lastLoginAt ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="{{ route('student.registration.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-clipboard-list" style="color: #3b82f6;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Registrasi Akademik</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Kelola registrasi semester aktif</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('student.study-plan.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-list-check" style="color: #8b5cf6;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">KRS (Kartu Rencana Studi)</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Ambil dan cek mata kuliah semester ini</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('student.grades.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-graduation-cap" style="color: #10b981;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Nilai Akademik</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Lihat hasil yang sudah dipublikasikan</div>
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="{{ route('student.schedule.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-calendar-days" style="color: #f59e0b;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Jadwal Kuliah</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Pantau sesi kuliah minggu berjalan</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('student.transcript.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-book" style="color: #ef4444;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Transkrip Akademik</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Ringkasan akademik permanen</div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="{{ route('student.schedule.index') }}" class="quick-action-btn">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-check-circle" style="color: #06b6d4;"></i></div>
                    <div style="font-weight: 600; color: #1f2937;">Absensi</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Cek kehadiran per mata kuliah</div>
                </a>
            </div>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-label">Mata Kuliah Aktif</div>
                <div class="stat-value">{{ $stats['current_courses'] }}</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white;">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-label">Total SKS</div>
                <div class="stat-value">{{ $stats['current_credits'] }}</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                    <i class="fas fa-award"></i>
                </div>
                <div class="stat-label">Nilai Dipublikasikan</div>
                <div class="stat-value">{{ $stats['published_grades'] }}</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">
                    <i class="fas fa-scroll"></i>
                </div>
                <div class="stat-label">Entri Transkrip</div>
                <div class="stat-value">{{ $stats['transcript_entries'] }}</div>
            </div>
        </div>
    </div>

    {{-- Academic Summary & Attendance --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card modern-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-chart-line me-2" style="color: #667eea;"></i>Ringkasan Akademik</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div style="padding: 1.25rem; border-radius: 12px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); height: 100%;">
                                <div style="font-size: 0.85rem; color: #92400e; margin-bottom: 0.5rem; font-weight: 500;">IPS Semester</div>
                                <div style="font-size: 2rem; font-weight: 700; color: #92400e;">{{ $stats['semester_gpa'] }}</div>
                                <div style="font-size: 0.8rem; color: #a16207; margin-top: 0.5rem;">Diambil dari hasil studi terakhir yang tersedia.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div style="padding: 1.25rem; border-radius: 12px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); height: 100%;">
                                <div style="font-size: 0.85rem; color: #1e40af; margin-bottom: 0.5rem; font-weight: 500;">IPK Kumulatif</div>
                                <div style="font-size: 2rem; font-weight: 700; color: #1e40af;">{{ $stats['cumulative_gpa'] }}</div>
                                <div style="font-size: 0.8rem; color: #3b82f6; margin-top: 0.5rem;">Menjadi fallback dari rata-rata nilai publikasi jika snapshot belum ada.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div style="padding: 1.25rem; border-radius: 12px; background: #f8fafc; height: 100%;">
                                <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; font-weight: 500;">Program Studi</div>
                                <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">{{ $studentInfo['study_program'] }}</div>
                                <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.5rem;">{{ $studentInfo['faculty'] }}</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div style="padding: 1.25rem; border-radius: 12px; background: #f8fafc; height: 100%;">
                                <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem; font-weight: 500;">Tahun Akademik Aktif</div>
                                <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">{{ $activeAcademicYear ?? '-' }}</div>
                                <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.5rem;">Pastikan registrasi dan KRS aktif di periode ini.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card modern-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-check-circle me-2" style="color: #10b981;"></i>Kehadiran</h3>
                </div>
                <div class="card-body p-4 text-center">
                    @if ($attendance['rate'] !== null)
                        <div class="attendance-circle" style="background: conic-gradient(#10b981 {{ $attendance['rate'] }}%, #e5e7eb 0%);">
                            <div class="attendance-circle-inner">
                                <div style="font-size: 1.75rem; font-weight: 700; color: #10b981;">{{ $attendance['rate'] }}%</div>
                                <div style="font-size: 0.75rem; color: #6b7280;">Rate</div>
                            </div>
                        </div>
                    @else
                        <div class="attendance-circle" style="background: #e5e7eb;">
                            <div class="attendance-circle-inner">
                                <div style="font-size: 1.75rem; font-weight: 700; color: #9ca3af;">-</div>
                                <div style="font-size: 0.75rem; color: #6b7280;">No Data</div>
                            </div>
                        </div>
                    @endif

                    <div class="text-secondary small mb-3">
                        {{ $attendance['attended'] }} kehadiran tercatat dari {{ $attendance['total'] }} sesi.
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div style="padding: 1rem; border-radius: 10px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);">
                                <div style="font-size: 0.8rem; color: #065f46; margin-bottom: 0.25rem;">Hadir</div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: #065f46;">{{ $attendance['attended'] }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 1rem; border-radius: 10px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);">
                                <div style="font-size: 0.8rem; color: #991b1b; margin-bottom: 0.25rem;">Tidak Hadir</div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: #991b1b;">{{ $attendance['absent'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Grades & Upcoming Schedules --}}
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card modern-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-graduation-cap me-2" style="color: #10b981;"></i>Nilai Terbaru</h3>
                    <a href="{{ route('student.grades.index') }}" class="btn btn-outline-primary" style="border-radius: 8px;">Lihat Semua</a>
                </div>

                <div class="card-body p-4">
                    @forelse ($recentGrades as $grade)
                        <div class="grade-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $grade->studyPlanDetail?->courseOffering?->course?->name ?? '-' }}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">
                                        <i class="fas fa-star me-1" style="color: #f59e0b;"></i>
                                        Nilai akhir {{ $grade->final_score !== null ? number_format((float) $grade->final_score, 2) : '-' }}
                                        • Huruf {{ $grade->letter_grade ?? '-' }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-blue-lt text-blue mb-2" style="font-size: 0.85rem; padding: 0.5rem 0.75rem;">
                                        <i class="fas fa-chart-line me-1"></i>GP {{ $grade->grade_point !== null ? number_format((float) $grade->grade_point, 2) : '-' }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">{{ $grade->graded_at?->format('d M Y') }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-secondary">
                            <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                            Belum ada nilai yang dipublikasikan.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card modern-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-calendar-days me-2" style="color: #f59e0b;"></i>Jadwal Ringkas</h3>
                    <a href="{{ route('student.schedule.index') }}" class="btn btn-outline-primary" style="border-radius: 8px;">Buka Jadwal</a>
                </div>

                <div class="card-body p-4">
                    @forelse ($upcomingSchedules as $schedule)
                        <div class="schedule-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $schedule['course_name'] }}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">
                                        <i class="fas fa-calendar me-1" style="color: #3b82f6;"></i>{{ $schedule['day'] }}
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-clock me-1" style="color: #f59e0b;"></i>{{ $schedule['start_time'] }} - {{ $schedule['end_time'] }}
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="badge {{ $schedule['mode'] === 'Online' ? 'bg-blue-lt text-blue' : 'bg-green-lt text-green' }} mb-2" style="font-size: 0.85rem; padding: 0.5rem 0.75rem;">
                                        <i class="fas fa-wifi me-1"></i>{{ $schedule['mode'] ?? '-' }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">
                                        <i class="fas fa-map-marker-alt me-1"></i>{{ $schedule['building'] }} / {{ $schedule['room'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-secondary">
                            <i class="fas fa-calendar-times" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                            Belum ada jadwal aktif.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
