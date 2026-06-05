<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public bool $hasApprovedRegistration = false;
    public bool $isAcademicallyActive = false;
    public bool $hasStudyPlan = false;
    public bool $hasApprovedStudyPlan = false;
    public bool $hasSchedules = false;
    public ?int $studentProfileId = null;
    public ?int $activeAcademicYearId = null;
    public ?string $activeAcademicYearName = null;
    public ?string $registrationStatus = null;
    public ?string $studyPlanStatus = null;
    public array $groupedSchedules = [];
    public ?string $studentName = null;
    public ?string $studentNim = null;
    public ?string $studyProgramName = null;
    public ?int $currentSemester = null;
    public ?string $academicStatus = null;
    public int $totalCourses = 0;
    public int $totalCredits = 0;
    public int $weeklySessionCount = 0;
    public int $openedSessionCount = 0;
    public int $attendedSessionCount = 0;
    public ?string $weekLabel = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $studentProfile = $user->studentProfile()->with('studyProgram')->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->studentProfileId = $studentProfile->id;
        $this->studentName = $studentProfile->user?->name ?? '-';
        $this->studentNim = $studentProfile->nim ?? '-';
        $this->studyProgramName = $studentProfile->studyProgram?->name ?? '-';
        $this->currentSemester = $studentProfile->current_semester;

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        if (! $activeAcademicYear) {
            return;
        }

        $this->activeAcademicYearId = $activeAcademicYear->id;
        $this->activeAcademicYearName = $activeAcademicYear->name;

        $registration = StudentRegistration::query()
            ->where('student_profile_id', $this->studentProfileId)
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->latest('id')
            ->first();

        $this->registrationStatus = $registration?->registration_status;
        $this->academicStatus = $registration?->academic_status;

        if (! $registration || $registration->registration_status !== 'Approved') {
            return;
        }

        $this->hasApprovedRegistration = true;

        if ($registration->academic_status !== 'Aktif') {
            return;
        }

        $this->isAcademicallyActive = true;

        $studyPlan = StudyPlan::query()
            ->with(['details.courseOffering.course'])
            ->where('student_profile_id', $this->studentProfileId)
            ->where('academic_year_id', $this->activeAcademicYearId)
            ->latest('id')
            ->first();

        if (! $studyPlan) {
            return;
        }

        $this->hasStudyPlan = true;
        $this->studyPlanStatus = $studyPlan->status;

        if ($studyPlan->status !== 'Approved') {
            return;
        }

        $this->hasApprovedStudyPlan = true;
        $this->totalCourses = $studyPlan->details->count();
        $this->totalCredits = (int) $studyPlan->details->sum(function ($detail) {
            return (int) ($detail->courseOffering?->course?->credits ?? $detail->credits ?? 0);
        });

        $offeringIds = $studyPlan->details
            ->pluck('course_offering_id')
            ->filter()
            ->unique()
            ->values();

        if ($offeringIds->isEmpty()) {
            return;
        }

        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(5)->endOfDay();
        $this->weekLabel = $weekStart->format('d M') . ' - ' . $weekEnd->format('d M Y');

        $dayLabels = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        $dayOrder = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
        ];

        $statusMap = [
            'Present' => 'Hadir',
            'Late' => 'Terlambat',
            'Excused' => 'Izin',
            'Sick' => 'Sakit',
            'Absent' => 'Alpha',
        ];

        $sessions = AttendanceSession::query()
            ->with([
                'courseOffering.course',
                'courseSchedule.room.building',
                'records' => function ($query) {
                    $query->where('student_profile_id', $this->studentProfileId);
                },
            ])
            ->whereIn('course_offering_id', $offeringIds)
            ->whereBetween('meeting_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('meeting_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (AttendanceSession $session) use ($dayOrder, $statusMap) {
                $meetingDate = $session->meeting_date;

                if (! $meetingDate) {
                    return null;
                }

                $dayOfWeek = $meetingDate->englishDayOfWeek;

                if (! array_key_exists($dayOfWeek, $dayOrder)) {
                    return null;
                }

                $offering = $session->courseOffering;
                $courseSchedule = $session->courseSchedule;
                $record = $session->records->first();
                $attendanceStatus = $record?->status;
                $canFillAttendance = $this->isSessionWindowOpen($session);

                return [
                    'day_of_week' => $dayOfWeek,
                    'meeting_date' => $meetingDate->format('d M Y'),
                    'meeting_no' => $session->meeting_no,
                    'start_time' => $this->formatTime($session->start_time),
                    'end_time' => $this->formatTime($session->end_time),
                    'course_name' => $offering?->course?->name ?? $offering?->label ?? '-',
                    'course_code' => $offering?->course?->code ?? '-',
                    'room' => $courseSchedule?->room?->name ?? '-',
                    'building' => $courseSchedule?->room?->building?->name ?? '-',
                    'delivery_mode' => $courseSchedule?->delivery_mode ?? '-',
                    'session_status' => $session->status,
                    'attendance_status' => $attendanceStatus ? ($statusMap[$attendanceStatus] ?? $attendanceStatus) : 'Belum Absen',
                    'attendance_class' => match ($attendanceStatus) {
                        'Present' => 'bg-green-lt text-green',
                        'Late', 'Excused', 'Sick' => 'bg-yellow-lt text-yellow',
                        'Absent' => 'bg-red-lt text-red',
                        default => 'bg-secondary-lt text-secondary',
                    },
                    'can_fill_attendance' => $canFillAttendance,
                    'offering_id' => $offering?->id,
                    'session_id' => $session->id,
                ];
            })
            ->filter()
            ->sortBy(function (array $item) use ($dayOrder) {
                return sprintf('%02d-%s-%s', $dayOrder[$item['day_of_week']] ?? 99, $item['meeting_date'], $item['start_time']);
            })
            ->values();

        $this->hasSchedules = $sessions->isNotEmpty();
        $this->weeklySessionCount = $sessions->count();
        $this->openedSessionCount = $sessions->where('session_status', 'Opened')->count();
        $this->attendedSessionCount = $sessions->whereIn('attendance_status', ['Hadir', 'Terlambat', 'Izin', 'Sakit'])->count();

        $grouped = $sessions->groupBy('day_of_week');

        $this->groupedSchedules = collect($dayOrder)
            ->mapWithKeys(function ($index, $day) use ($dayLabels, $grouped) {
                return [($dayLabels[$day] ?? $day) => $grouped->get($day, collect())->values()->all()];
            })
            ->all();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'My Schedule',
        ]);
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

    private function isSessionWindowOpen(AttendanceSession $session): bool
    {
        return $session->status === 'Opened' && $session->closed_at === null;
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
            padding: 0.4rem 0.8rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            font-size: 0.85rem;
            color: white;
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

        .session-card {
            border-radius: 16px;
            padding: 1.25rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .session-card:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-color: #667eea;
            transform: translateX(4px);
        }

        .day-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .profile-box {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .krs-summary-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 16px;
            padding: 1.5rem;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia. Jadwal belum dapat ditampilkan.</div>
    @elseif (! $hasApprovedRegistration)
        <div class="alert alert-warning">
            Registrasi semester belum disetujui dengan status {{ $registrationStatus ?? '-' }}. Jadwal belum dapat ditampilkan.
        </div>
    @elseif (! $isAcademicallyActive)
        <div class="alert alert-warning">
            Status akademik semester ini adalah {{ $academicStatus ?? '-' }}. Jadwal hanya tersedia untuk mahasiswa aktif.
        </div>
    @elseif (! $hasStudyPlan)
        <div class="alert alert-warning">KRS belum tersedia. Jadwal belum dapat ditampilkan.</div>
    @elseif (! $hasApprovedStudyPlan)
        <div class="alert alert-warning">KRS belum disetujui dengan status {{ $studyPlanStatus ?? '-' }}. Jadwal belum dapat diakses.</div>
    @elseif (! $hasSchedules)
        <div class="alert alert-info">Belum ada sesi perkuliahan minggu ini ({{ $weekLabel }}).</div>
    @else
        {{-- Hero Section with Gradient --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Jadwal Kuliah Mingguan</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $studentName }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-id-card me-2"></i>{{ $studentNim }} • {{ $studyProgramName }}
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-badge">
                                        <i class="fas fa-calendar me-2"></i>Semester {{ $currentSemester ?? '-' }}
                                    </span>
                                    <span class="info-badge">
                                        <i class="fas fa-clock me-2"></i>{{ $weekLabel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Status Registrasi</div>
                                    <span class="badge bg-white text-primary">{{ $registrationStatus }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Status KRS</div>
                                    <span class="badge bg-white text-warning">{{ $studyPlanStatus }}</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Status Akademik</div>
                                    <div style="font-weight: 600;">{{ $academicStatus ?? '-' }}</div>
                                </div>
                            </div>
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
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Total Sesi Minggu Ini</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $weeklySessionCount }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #f59e0b;">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Sesi Sudah Diabsen</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $attendedSessionCount }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #10b981;">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Sesi Masih Dibuka</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #1f2937;">{{ $openedSessionCount }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Schedule Cards --}}
        <div class="row row-cards">
            <div class="col-lg-8">
                @foreach ($groupedSchedules as $day => $items)
                    <div class="modern-card mb-4">
                        <div class="day-header">
                            <h3 class="mb-0" style="font-weight: 600; color: white;">
                                <i class="fas fa-calendar-day me-2"></i>{{ $day }}
                            </h3>
                            <span style="background: rgba(255,255,255,0.2); padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.85rem;">
                                {{ count($items) }} sesi
                            </span>
                        </div>
                        <div class="card-body p-4">
                            @if (count($items) === 0)
                                <div style="text-align: center; padding: 2rem; color: #9ca3af;">
                                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <div>Tidak ada sesi pada hari ini.</div>
                                </div>
                            @else
                                @foreach ($items as $item)
                                    <div class="session-card">
                                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $item['course_name'] }}</div>
                                                <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem;">
                                                    <i class="fas fa-hashtag me-1" style="color: #667eea;"></i>{{ $item['course_code'] }}
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-layer-group me-1" style="color: #10b981;"></i>Pertemuan {{ $item['meeting_no'] ?? '-' }}
                                                </div>
                                                <div style="font-size: 0.8rem; color: #9ca3af;">
                                                    <i class="fas fa-calendar me-1"></i>{{ $item['meeting_date'] }}
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-clock me-1"></i>{{ $item['start_time'] }} - {{ $item['end_time'] }}
                                                </div>
                                                <div style="font-size: 0.8rem; color: #9ca3af; margin-top: 0.25rem;">
                                                    <i class="fas fa-building me-1"></i>{{ $item['building'] }} / {{ $item['room'] }}
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-wifi me-1"></i>{{ $item['delivery_mode'] }}
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ $item['attendance_class'] }} mb-2" style="padding: 0.5rem 0.75rem;">
                                                    <i class="fas fa-check-circle me-1"></i>{{ $item['attendance_status'] }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 mt-3">
                                            <a href="{{ route('student.schedule.attendance', ['offeringId' => $item['offering_id']]) }}"
                                               class="action-btn"
                                               style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; flex: 1; justify-content: center;">
                                                <i class="fas fa-info-circle"></i> Detail Sesi
                                            </a>

                                            <a href="{{ route('student.course-materials.index', ['offeringId' => $item['offering_id']]) }}"
                                               class="action-btn"
                                               style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; flex: 1; justify-content: center;">
                                                <i class="fas fa-book"></i> Materi
                                            </a>

                                            @if ($item['can_fill_attendance'])
                                                <a href="{{ route('student.schedule.attendance.record', ['sessionId' => $item['session_id']]) }}"
                                                   class="action-btn"
                                                   style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; flex: 1; justify-content: center;">
                                                    <i class="fas fa-qrcode"></i> Scan Absensi
                                                </a>
                                            @else
                                                <button type="button" class="action-btn" disabled
                                                        style="background: #e5e7eb; color: #9ca3af; flex: 1; justify-content: center; cursor: not-allowed;">
                                                    <i class="fas fa-lock"></i> Menunggu Dosen
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="col-lg-4">
                {{-- Profile Box --}}
                <div class="profile-box">
                    <div style="font-size: 0.85rem; color: #1e40af; margin-bottom: 0.5rem; font-weight: 600;">
                        <i class="fas fa-user me-2"></i>Profil Singkat
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Mahasiswa</div>
                        <div style="font-weight: 600; color: #1f2937;">{{ $studentName }}</div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Program Studi</div>
                        <div style="font-weight: 600; color: #1f2937;">{{ $studyProgramName }}</div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Semester</div>
                        <div style="font-weight: 600; color: #1f2937;">{{ $currentSemester ?? '-' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: #3b82f6; margin-bottom: 0.25rem;">Tahun Akademik</div>
                        <div style="font-weight: 600; color: #1f2937;">{{ $activeAcademicYearName }}</div>
                    </div>
                </div>

                {{-- KRS Summary Box --}}
                <div class="krs-summary-box">
                    <div style="font-size: 0.85rem; color: #92400e; margin-bottom: 1rem; font-weight: 600;">
                        <i class="fas fa-list-check me-2"></i>Ringkasan KRS
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span style="color: #78350f;">Mata Kuliah</span>
                        <strong style="color: #92400e; font-size: 1.25rem;">{{ $totalCourses }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span style="color: #78350f;">Total SKS</span>
                        <strong style="color: #92400e; font-size: 1.25rem;">{{ $totalCredits }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color: #78350f;">Sesi Dibuka</span>
                        <strong style="color: #92400e; font-size: 1.25rem;">{{ $openedSessionCount }}</strong>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
