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
                ->with(['courseOffering.course', 'courseOffering.courseSchedules'])
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
                                'room' => $schedule->room ?: '-',
                                'building' => $schedule->building ?: '-',
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
        .student-shell-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
        }

        .student-dashboard-hero {
            overflow: hidden;
            position: relative;
            background:
                radial-gradient(circle at top right, rgba(192, 132, 252, 0.28), transparent 34%),
                linear-gradient(135deg, var(--app-primary-deep, #4c1d95) 0%, var(--app-primary-bright, #a855f7) 100%);
            color: #fff;
        }

        .student-dashboard-hero::after {
            content: '';
            position: absolute;
            inset: auto -80px -120px auto;
            width: 260px;
            height: 260px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
        }

        .student-dashboard-hero .text-muted,
        .student-dashboard-hero .text-secondary {
            color: rgba(255, 255, 255, 0.72) !important;
        }

        .student-dashboard-avatar {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.16);
            font-size: 24px;
            font-weight: 700;
            backdrop-filter: blur(8px);
        }

        .student-stat-card {
            height: 100%;
        }

        .student-stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }

        .student-stat-value {
            font-size: 30px;
            line-height: 1;
            font-weight: 700;
            margin-top: 8px;
        }

        .student-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 18px;
        }

        .student-quick-link {
            height: 100%;
            text-decoration: none;
            color: inherit;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .student-quick-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
        }

        .student-quick-link-subtitle {
            color: #6b7280;
            font-size: 13px;
        }

        .student-metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .student-metric-item {
            border-radius: 16px;
            background: #f8fafc;
            padding: 14px 16px;
        }

        .student-metric-title {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .student-metric-value {
            font-size: 18px;
            font-weight: 700;
        }

        .student-list-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .student-list-item:last-child {
            border-bottom: 0;
        }

        .student-schedule-pill {
            font-size: 12px;
            color: #6b7280;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum terhubung.</div>
    @else
        <div class="card student-shell-card student-dashboard-hero mb-4">
            <div class="card-body p-4 p-lg-5 position-relative">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-start gap-3">
                            <div class="student-dashboard-avatar">
                                {{ strtoupper(substr($studentInfo['name'] ?? 'M', 0, 1)) }}
                            </div>

                            <div>
                                <div class="text-uppercase small fw-semibold mb-2">Ruang Akademik Mahasiswa</div>
                                <h1 class="h2 mb-2">{{ $studentInfo['name'] }}</h1>
                                <div class="text-secondary mb-3">
                                    {{ $studentInfo['study_program'] }} • {{ $studentInfo['faculty'] }}
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-white text-primary">NIM {{ $studentInfo['nim'] }}</span>
                                    <span class="badge bg-white text-primary">Semester {{ $studentInfo['current_semester'] ?? '-' }}</span>
                                    <span class="badge bg-white text-primary">{{ $activeAcademicYear ?? 'Tahun akademik belum aktif' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="student-metric-grid">
                            <div class="student-metric-item">
                                <div class="student-metric-title">Status Akademik</div>
                                <span class="badge {{ $this->statusBadgeClass($studentInfo['academic_status']) }}">
                                    {{ $studentInfo['academic_status'] ?? '-' }}
                                </span>
                            </div>

                            <div class="student-metric-item">
                                <div class="student-metric-title">Status Registrasi</div>
                                <span class="badge {{ $this->statusBadgeClass($registrationStatus) }}">
                                    {{ $registrationStatus }}
                                </span>
                            </div>

                            <div class="student-metric-item">
                                <div class="student-metric-title">Status KRS</div>
                                <span class="badge {{ $this->statusBadgeClass($currentStudyPlanStatus) }}">
                                    {{ $currentStudyPlanStatus }}
                                </span>
                            </div>

                            <div class="student-metric-item">
                                <div class="student-metric-title">Login Terakhir</div>
                                <div class="student-metric-value">{{ $lastLoginAt ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-4">
            <div class="col-sm-6 col-xl-2">
                <a href="{{ route('student.registration.index') }}" class="card student-shell-card student-quick-link">
                    <div class="card-body">
                        <div class="student-stat-icon bg-blue-lt text-blue mb-3">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="fw-semibold mb-1">Registrasi</div>
                        <div class="student-quick-link-subtitle">Kelola registrasi semester aktif</div>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-xl-2">
                <a href="{{ route('student.study-plan.index') }}" class="card student-shell-card student-quick-link">
                    <div class="card-body">
                        <div class="student-stat-icon bg-purple-lt text-purple mb-3">
                            <i class="fas fa-list-check"></i>
                        </div>
                        <div class="fw-semibold mb-1">KRS</div>
                        <div class="student-quick-link-subtitle">Ambil dan cek mata kuliah semester ini</div>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-xl-2">
                <a href="{{ route('student.grades.index') }}" class="card student-shell-card student-quick-link">
                    <div class="card-body">
                        <div class="student-stat-icon bg-green-lt text-green mb-3">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="fw-semibold mb-1">Nilai</div>
                        <div class="student-quick-link-subtitle">Lihat hasil yang sudah dipublikasikan</div>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-xl-2">
                <a href="{{ route('student.schedule.index') }}" class="card student-shell-card student-quick-link">
                    <div class="card-body">
                        <div class="student-stat-icon bg-orange-lt text-orange mb-3">
                            <i class="fas fa-calendar-days"></i>
                        </div>
                        <div class="fw-semibold mb-1">Jadwal</div>
                        <div class="student-quick-link-subtitle">Pantau sesi kuliah minggu berjalan</div>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-xl-2">
                <a href="{{ route('student.transcript.index') }}" class="card student-shell-card student-quick-link">
                    <div class="card-body">
                        <div class="student-stat-icon bg-red-lt text-red mb-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="fw-semibold mb-1">Transkrip</div>
                        <div class="student-quick-link-subtitle">Ringkasan akademik permanen</div>
                    </div>
                </a>
            </div>
        </div>
    @endif

    <div class="row row-cards mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card student-shell-card student-stat-card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="student-stat-label">Mata Kuliah Aktif</div>
                        <div class="student-stat-value">{{ $stats['current_courses'] }}</div>
                    </div>
                    <div class="student-stat-icon bg-primary-lt text-primary">
                        <i class="fas fa-book-open"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card student-shell-card student-stat-card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="student-stat-label">Total SKS</div>
                        <div class="student-stat-value">{{ $stats['current_credits'] }}</div>
                    </div>
                    <div class="student-stat-icon bg-azure-lt text-azure">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card student-shell-card student-stat-card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="student-stat-label">Nilai Dipublikasikan</div>
                        <div class="student-stat-value">{{ $stats['published_grades'] }}</div>
                    </div>
                    <div class="student-stat-icon bg-green-lt text-green">
                        <i class="fas fa-award"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card student-shell-card student-stat-card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="student-stat-label">Entri Transkrip</div>
                        <div class="student-stat-value">{{ $stats['transcript_entries'] }}</div>
                    </div>
                    <div class="student-stat-icon bg-red-lt text-red">
                        <i class="fas fa-scroll"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-lg-8">
            <div class="card student-shell-card h-100">
                <div class="card-header">
                    <h3 class="card-title">Ringkasan Akademik</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="student-metric-item h-100">
                                <div class="student-metric-title">IPS Semester</div>
                                <div class="student-metric-value">{{ $stats['semester_gpa'] }}</div>
                                <div class="text-secondary small mt-2">Diambil dari hasil studi terakhir yang tersedia.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="student-metric-item h-100">
                                <div class="student-metric-title">IPK Kumulatif</div>
                                <div class="student-metric-value">{{ $stats['cumulative_gpa'] }}</div>
                                <div class="text-secondary small mt-2">Menjadi fallback dari rata-rata nilai publikasi jika snapshot belum ada.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="student-metric-item h-100">
                                <div class="student-metric-title">Program Studi</div>
                                <div class="student-metric-value">{{ $studentInfo['study_program'] }}</div>
                                <div class="text-secondary small mt-2">{{ $studentInfo['faculty'] }}</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="student-metric-item h-100">
                                <div class="student-metric-title">Tahun Akademik Aktif</div>
                                <div class="student-metric-value">{{ $activeAcademicYear ?? '-' }}</div>
                                <div class="text-secondary small mt-2">Pastikan registrasi dan KRS aktif di periode ini.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card student-shell-card h-100">
                <div class="card-header">
                    <h3 class="card-title">Kehadiran</h3>
                </div>
                <div class="card-body">
                    <div class="student-stat-value mb-2">{{ $attendance['rate'] ?? '-' }}%</div>
                    <div class="text-secondary small mb-3">
                        {{ $attendance['attended'] }} kehadiran tercatat dari {{ $attendance['total'] }} sesi.
                    </div>

                    @if ($attendance['rate'] !== null)
                        <div class="progress progress-sm mb-4">
                            <div
                                class="progress-bar {{ $attendance['rate'] < 75 ? 'bg-red' : 'bg-green' }}"
                                style="width: {{ $attendance['rate'] }}%"
                            ></div>
                        </div>
                    @endif

                    <div class="student-metric-grid">
                        <div class="student-metric-item">
                            <div class="student-metric-title">Hadir</div>
                            <div class="student-metric-value">{{ $attendance['attended'] }}</div>
                        </div>
                        <div class="student-metric-item">
                            <div class="student-metric-title">Tidak Hadir</div>
                            <div class="student-metric-value">{{ $attendance['absent'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card student-shell-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Nilai Terbaru</h3>
                    <a href="{{ route('student.grades.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($recentGrades as $grade)
                        <div class="student-list-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $grade->studyPlanDetail?->courseOffering?->course?->name ?? '-' }}</div>
                                    <div class="text-secondary small">
                                        Nilai akhir {{ $grade->final_score !== null ? number_format((float) $grade->final_score, 2) : '-' }}
                                        • Huruf {{ $grade->letter_grade ?? '-' }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-blue-lt text-blue mb-2">
                                        GP {{ $grade->grade_point !== null ? number_format((float) $grade->grade_point, 2) : '-' }}
                                    </div>
                                    <div class="text-secondary small">{{ $grade->graded_at?->format('d M Y') }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-secondary">Belum ada nilai yang dipublikasikan.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card student-shell-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Jadwal Ringkas</h3>
                    <a href="{{ route('student.schedule.index') }}" class="btn btn-sm btn-outline-primary">Buka Jadwal</a>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($upcomingSchedules as $schedule)
                        <div class="student-list-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $schedule['course_name'] }}</div>
                                    <div class="student-schedule-pill">
                                        {{ $schedule['day'] }} • {{ $schedule['start_time'] }} - {{ $schedule['end_time'] }}
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="badge {{ $schedule['mode'] === 'Online' ? 'bg-blue-lt text-blue' : 'bg-green-lt text-green' }} mb-2">
                                        {{ $schedule['mode'] ?? '-' }}
                                    </div>
                                    <div class="text-secondary small">{{ $schedule['building'] }} / {{ $schedule['room'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-secondary">Belum ada jadwal aktif.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
