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
                'courseSchedule',
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
                    'room' => $courseSchedule?->room ?: '-',
                    'building' => $courseSchedule?->building ?: '-',
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
        if ($session->status !== 'Opened' || ! $session->meeting_date || ! $session->start_time || ! $session->end_time) {
            return false;
        }

        $now = now();
        $startAt = Carbon::parse($session->meeting_date->format('Y-m-d') . ' ' . $this->formatTime($session->start_time));
        $endAt = Carbon::parse($session->meeting_date->format('Y-m-d') . ' ' . $this->formatTime($session->end_time));

        return $now->betweenIncluded($startAt, $endAt);
    }
};
?>

@push('styles')
    <style>
        .student-schedule-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .student-schedule-hero {
            background:
                radial-gradient(circle at top left, rgba(32, 107, 196, 0.12), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .student-schedule-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .student-schedule-value {
            font-size: 28px;
            line-height: 1;
            font-weight: 700;
        }

        .student-schedule-meta {
            border-radius: 16px;
            background: #f8fafc;
            padding: 14px 16px;
        }

        .student-session-card {
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            height: 100%;
        }

        .student-session-detail {
            color: #6b7280;
            font-size: 13px;
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
    @elseif (! $hasStudyPlan)
        <div class="alert alert-warning">KRS belum tersedia. Jadwal belum dapat ditampilkan.</div>
    @elseif (! $hasApprovedStudyPlan)
        <div class="alert alert-warning">KRS belum disetujui dengan status {{ $studyPlanStatus ?? '-' }}. Jadwal belum dapat diakses.</div>
    @elseif (! $hasSchedules)
        <div class="alert alert-info">Belum ada sesi perkuliahan minggu ini ({{ $weekLabel }}).</div>
    @else
        <div class="card student-schedule-card student-schedule-hero mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <div class="student-schedule-label mb-2">Jadwal Mingguan Mahasiswa</div>
                        <h2 class="mb-2">{{ $studentName }}</h2>
                        <div class="text-secondary mb-3">
                            {{ $studyProgramName }} • NIM {{ $studentNim }}
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-blue-lt text-blue">Semester {{ $currentSemester ?? '-' }}</span>
                            <span class="badge bg-green-lt text-green">{{ $activeAcademicYearName }}</span>
                            <span class="badge bg-azure-lt text-azure">{{ $weekLabel }}</span>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="student-schedule-meta">
                                    <div class="student-schedule-label">Status Registrasi</div>
                                    <div class="mt-2">
                                        <span class="badge bg-green-lt text-green">{{ $registrationStatus }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="student-schedule-meta">
                                    <div class="student-schedule-label">Status KRS</div>
                                    <div class="mt-2">
                                        <span class="badge bg-yellow-lt text-yellow">{{ $studyPlanStatus }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="student-schedule-meta">
                                    <div class="student-schedule-label">Status Akademik</div>
                                    <div class="mt-2 fw-semibold">{{ $academicStatus ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-4">
            <div class="col-md-4">
                <div class="card student-schedule-card">
                    <div class="card-body">
                        <div class="student-schedule-label">Total Sesi Minggu Ini</div>
                        <div class="student-schedule-value">{{ $weeklySessionCount }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card student-schedule-card">
                    <div class="card-body">
                        <div class="student-schedule-label">Sesi Sudah Diabsen</div>
                        <div class="student-schedule-value">{{ $attendedSessionCount }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card student-schedule-card">
                    <div class="card-body">
                        <div class="student-schedule-label">Sesi Masih Dibuka</div>
                        <div class="student-schedule-value">{{ $openedSessionCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards">
            <div class="col-lg-8">
                @foreach ($groupedSchedules as $day => $items)
                    <div class="card student-schedule-card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">{{ $day }}</h3>
                            <span class="badge bg-azure-lt text-azure">{{ count($items) }} sesi</span>
                        </div>
                        <div class="card-body">
                            @if (count($items) === 0)
                                <div class="text-secondary small">Tidak ada sesi pada hari ini.</div>
                            @else
                                <div class="row row-cards">
                                    @foreach ($items as $item)
                                        <div class="col-md-6">
                                            <div class="card student-session-card">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                                        <span class="badge bg-blue-lt text-blue">{{ $item['start_time'] }} - {{ $item['end_time'] }}</span>
                                                        <span class="badge {{ $item['attendance_class'] }}">{{ $item['attendance_status'] }}</span>
                                                    </div>

                                                    <div class="fw-semibold mb-1">{{ $item['course_name'] }}</div>
                                                    <div class="student-session-detail mb-3">
                                                        {{ $item['course_code'] }} • Pertemuan {{ $item['meeting_no'] ?? '-' }}
                                                    </div>

                                                    <div class="student-session-detail mb-1">{{ $item['meeting_date'] }}</div>
                                                    <div class="student-session-detail mb-1">{{ $item['building'] }} / {{ $item['room'] }}</div>
                                                    <div class="student-session-detail mb-3">
                                                        Mode {{ $item['delivery_mode'] }} • Status sesi {{ $item['session_status'] }}
                                                    </div>

                                                    <div class="d-grid gap-2 mt-auto">
                                                        <a
                                                            href="{{ route('student.schedule.attendance', ['offeringId' => $item['offering_id']]) }}"
                                                            class="btn btn-outline-primary"
                                                        >
                                                            Detail Sesi
                                                        </a>

                                                        @if ($item['can_fill_attendance'])
                                                            <a
                                                                href="{{ route('student.schedule.attendance.record', ['sessionId' => $item['session_id']]) }}"
                                                                class="btn btn-primary"
                                                            >
                                                                Isi Absen
                                                            </a>
                                                        @else
                                                            <button type="button" class="btn btn-secondary" disabled>
                                                                Belum Bisa Absen
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="col-lg-4">
                <div class="card student-schedule-card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Profil Singkat</h3>
                    </div>
                    <div class="card-body">
                        <div class="student-schedule-label">Mahasiswa</div>
                        <div class="fw-semibold mb-3">{{ $studentName }}</div>

                        <div class="student-schedule-label">Program Studi</div>
                        <div class="fw-semibold mb-3">{{ $studyProgramName }}</div>

                        <div class="student-schedule-label">Semester</div>
                        <div class="fw-semibold mb-3">{{ $currentSemester ?? '-' }}</div>

                        <div class="student-schedule-label">Tahun Akademik</div>
                        <div class="fw-semibold">{{ $activeAcademicYearName }}</div>
                    </div>
                </div>

                <div class="card student-schedule-card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Ringkasan KRS</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-secondary">Mata Kuliah</span>
                            <strong>{{ $totalCourses }}</strong>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-secondary">Total SKS</span>
                            <strong>{{ $totalCredits }}</strong>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-secondary">Sesi Dibuka</span>
                            <strong>{{ $openedSessionCount }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
