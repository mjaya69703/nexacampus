<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Support\Academic\AcademicAttendanceQrService;
use App\Support\Inertia\ShellProps;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal kuliah, rekap absensi, dan pencatatan QR mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - Jadwal mingguan dibangun dari `course_schedules` milik KRS yang sudah
 *   disetujui. Sebelumnya halaman hanya menampilkan `attendance_sessions`
 *   sehingga jadwal kosong total saat dosen belum membuat sesi.
 * - Sesi absensi minggu ini digabung ke jadwal yang cocok; sesi tanpa jadwal
 *   tetap ditampilkan sebagai baris tambahan agar tidak hilang.
 * - Mahasiswa hanya bisa melihat/mencatat absensi untuk kelas yang masih
 *   aktif pada KRS tahun akademik berjalan.
 */
class StudentScheduleController extends Controller
{
    private const DAY_LABELS = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    private const DAY_ORDER = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
    ];

    private const STATUS_LABELS = [
        'Present' => 'Hadir',
        'Late' => 'Terlambat',
        'Excused' => 'Izin',
        'Sick' => 'Sakit',
        'Absent' => 'Alpha',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);

        $context = $this->context($profile);

        $state = [
            'hasProfile' => $profile !== null,
            'hasAcademicYear' => $context['year'] !== null,
            'registrationStatus' => $context['registration']?->registration_status,
            'academicStatus' => $context['registration']?->academic_status,
            'studyPlanStatus' => $context['plan']?->status,
            'hasApprovedRegistration' => false,
            'isAcademicallyActive' => false,
            'hasStudyPlan' => false,
            'hasApprovedStudyPlan' => false,
            'hasSchedules' => false,
        ];

        $details = collect();
        $schedules = collect();
        $sessions = collect();
        $weekLabel = null;
        $totalCourses = 0;
        $totalCredits = 0;

        if ($profile !== null && $context['year'] !== null && $context['registration'] !== null) {
            $state['hasApprovedRegistration'] = $context['registration']->registration_status === 'Approved';
            $state['isAcademicallyActive'] = $state['hasApprovedRegistration']
                && $context['registration']->academic_status === 'Aktif';
        }

        if ($state['isAcademicallyActive'] && $context['plan'] !== null) {
            $state['hasStudyPlan'] = true;
            $state['hasApprovedStudyPlan'] = $context['plan']->status === 'Approved';
        }

        if ($state['hasApprovedStudyPlan'] && $profile !== null && $context['year'] !== null) {
            $details = StudyPlanDetail::query()
                ->where('study_plan_id', $context['plan']->id)
                ->officiallyEnrolled()
                ->with(['courseOffering.course'])
                ->get();

            $offeringIds = $details->pluck('course_offering_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $totalCourses = $details->count();
            $totalCredits = (int) $details->sum(
                fn (StudyPlanDetail $detail) => (int) ($detail->courseOffering?->course?->credits ?? $detail->credits ?? 0)
            );

            if ($offeringIds->isNotEmpty()) {
                $weekStart = now()->startOfWeek(Carbon::MONDAY);
                $weekEnd = $weekStart->copy()->addDays(5)->endOfDay();
                $weekLabel = $weekStart->format('d M').' - '.$weekEnd->format('d M Y');

                $schedules = CourseSchedule::query()
                    ->with(['room.building', 'courseOffering.course'])
                    ->whereIn('course_offering_id', $offeringIds)
                    ->where('is_active', true)
                    ->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get();

                $sessions = AttendanceSession::query()
                    ->with([
                        'courseOffering.course',
                        'courseSchedule.room.building',
                        'records' => fn ($query) => $query->where('student_profile_id', $profile->id),
                    ])
                    ->whereIn('course_offering_id', $offeringIds)
                    ->whereBetween('meeting_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                    ->orderBy('meeting_date')
                    ->orderBy('start_time')
                    ->get();
            }
        }

        $rows = $this->buildRows($schedules, $sessions);

        $state['hasSchedules'] = $rows->isNotEmpty();

        $grouped = $rows->groupBy('dayKey');

        $days = collect(self::DAY_ORDER)->map(function (int $order, string $dayKey) use ($grouped) {
            return [
                'key' => $dayKey,
                'label' => self::DAY_LABELS[$dayKey],
                'order' => $order,
                'items' => $grouped->get($dayKey, collect())->values()->all(),
            ];
        })->values()->all();

        $attended = $sessions->filter(function (AttendanceSession $session) {
            $status = $session->records->first()?->status;

            return in_array($status, ['Present', 'Late', 'Excused', 'Sick'], true);
        })->count();

        return Inertia::render('Student/Schedule/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Jadwal Kuliah'),
            'state' => $state,
            'student' => [
                'name' => $profile?->user?->name ?? $user->name,
                'nim' => $profile?->nim ?? '-',
                'program' => $profile?->studyProgram?->name ?? '-',
                'semester' => $profile?->current_semester,
                'academicYear' => $context['year']?->name,
            ],
            'weekLabel' => $weekLabel,
            'days' => $days,
            'stats' => [
                'weeklySessions' => $sessions->count(),
                'attended' => $attended,
                'opened' => $sessions->where('status', 'Opened')->count(),
                'courses' => $totalCourses,
                'credits' => $totalCredits,
                'weeklyClasses' => $rows->count(),
            ],
            'urls' => [
                'index' => route('student.schedule.index'),
                'attendance' => route('student.schedule.attendance', ['offeringId' => '__ID__']),
                'record' => route('student.schedule.attendance.record', ['sessionId' => '__ID__']),
                'materials' => route('student.course-materials.index'),
            ],
        ]);
    }

    public function attendance(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);
        $context = $this->context($profile);

        $offering = CourseOffering::query()->with('course')->find($offeringId);

        abort_unless($offering !== null, 404, 'Kelas tidak ditemukan.');

        $access = $this->canAccessOffering($profile, $context, $offeringId);

        $items = collect();
        $summary = [
            'totalMeetings' => 0,
            'opened' => 0,
            'attended' => 0,
            'notInput' => 0,
        ];

        if ($access) {
            $sessions = AttendanceSession::query()
                ->with(['records' => fn ($query) => $query->where('student_profile_id', $profile->id)])
                ->where('course_offering_id', $offeringId)
                ->orderBy('meeting_no')
                ->orderBy('meeting_date')
                ->get();

            $items = $sessions->map(function (AttendanceSession $session) {
                $record = $session->records->first();
                $status = $record?->status;

                return [
                    'sessionId' => $session->id,
                    'meetingNo' => $session->meeting_no,
                    'meetingDate' => $session->meeting_date?->format('d M Y') ?? '-',
                    'timeRange' => $this->formatTime($session->start_time).' - '.$this->formatTime($session->end_time),
                    'topic' => filled($session->topic) ? $session->topic : '-',
                    'sessionStatus' => $session->status,
                    'attendanceStatus' => $status ? (self::STATUS_LABELS[$status] ?? $status) : 'Belum diinput',
                    'attendanceTone' => $this->attendanceTone($status),
                    'canRecord' => $this->isSessionOpen($session),
                ];
            })->values();

            $summary = [
                'totalMeetings' => $items->count(),
                'opened' => $sessions->where('status', 'Opened')->count(),
                'attended' => $items->filter(fn (array $item) => in_array(
                    $item['attendanceStatus'],
                    ['Hadir', 'Terlambat', 'Izin', 'Sakit'],
                    true
                ))->count(),
                'notInput' => $items->where('attendanceStatus', 'Belum diinput')->count(),
            ];
        }

        return Inertia::render('Student/Schedule/Attendance', [
            'shell' => ShellProps::make($user, 'Academic', 'Detail Absensi'),
            'hasProfile' => $profile !== null,
            'hasAccess' => $access,
            'course' => [
                'id' => $offering->id,
                'name' => $offering->course?->name ?? $offering->label ?? '-',
                'code' => $offering->course?->code ?? $offering->code ?? '-',
                'classLabel' => $offering->label,
                'academicYear' => $context['year']?->name,
                'registrationStatus' => $context['registration']?->registration_status,
            ],
            'items' => $items->all(),
            'summary' => $summary,
            'urls' => [
                'schedule' => route('student.schedule.index'),
                'materials' => route('student.course-materials.index', ['offeringId' => $offering->id]),
                'record' => route('student.schedule.attendance.record', ['sessionId' => '__ID__']),
            ],
        ]);
    }

    public function record(Request $request, int $sessionId): Response|RedirectResponse
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);
        $context = $this->context($profile);

        $session = AttendanceSession::query()
            ->with('courseOffering.course')
            ->find($sessionId);

        abort_unless($session !== null, 404, 'Sesi absensi tidak ditemukan.');

        $access = $this->canAccessOffering($profile, $context, (int) $session->course_offering_id);

        // Tautan QR membawa token & slot. Proses langsung lalu bersihkan query
        // agar refresh halaman tidak mengirim ulang token yang sudah berganti.
        if ($access && $request->filled('token') && $request->filled('slot')) {
            try {
                $this->recordScan(
                    $session,
                    $profile,
                    (string) $request->query('token'),
                    (int) $request->query('slot'),
                    $request
                );

                return redirect()
                    ->route('student.schedule.attendance.record', ['sessionId' => $session->id])
                    ->with('success', 'Absensi berhasil. Status Anda tercatat Hadir.');
            } catch (ValidationException $exception) {
                return redirect()
                    ->route('student.schedule.attendance.record', ['sessionId' => $session->id])
                    ->with('error', collect($exception->errors())->flatten()->first() ?: 'Kode absensi tidak valid.');
            }
        }

        $existing = $profile === null ? null : $session->records()
            ->where('student_profile_id', $profile->id)
            ->first();

        return Inertia::render('Student/Schedule/Record', [
            'shell' => ShellProps::make($user, 'Academic', 'Isi Absensi'),
            'hasProfile' => $profile !== null,
            'hasAccess' => $access,
            'session' => [
                'id' => $session->id,
                'offeringId' => $session->course_offering_id,
                'courseName' => $session->courseOffering?->course?->name ?? $session->courseOffering?->label ?? '-',
                'meetingNo' => $session->meeting_no,
                'meetingDate' => $session->meeting_date?->format('d M Y') ?? '-',
                'timeRange' => $this->formatTime($session->start_time).' - '.$this->formatTime($session->end_time),
                'status' => $session->status,
                'isOpen' => $this->isSessionOpen($session),
                'windowMessage' => $this->isSessionOpen($session)
                    ? null
                    : 'Absensi hanya bisa diisi saat dosen membuka sesi QR.',
            ],
            'attendance' => [
                'status' => $existing?->status,
                'statusLabel' => $existing?->status
                    ? (self::STATUS_LABELS[$existing->status] ?? $existing->status)
                    : 'Belum Absen',
                'tone' => $this->attendanceTone($existing?->status),
                'notes' => $existing?->notes,
            ],
            'context' => [
                'academicYear' => $context['year']?->name,
                'registrationStatus' => $context['registration']?->registration_status,
            ],
            'urls' => [
                'back' => route('student.schedule.attendance', ['offeringId' => $session->course_offering_id]),
                'scan' => route('student.schedule.attendance.record.scan', ['sessionId' => $session->id]),
            ],
        ]);
    }

    public function scan(Request $request, int $sessionId): RedirectResponse
    {
        $profile = $this->studentProfile($request);
        $context = $this->context($profile);

        $session = AttendanceSession::query()->find($sessionId);

        abort_unless($session !== null, 404, 'Sesi absensi tidak ditemukan.');

        abort_unless(
            $this->canAccessOffering($profile, $context, (int) $session->course_offering_id),
            403,
            'Anda tidak terdaftar pada kelas ini.'
        );

        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $payload = trim($validated['payload']);
        $parsed = parse_url($payload);
        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $token = (string) ($query['token'] ?? '');
        $slot = isset($query['slot']) ? (int) $query['slot'] : null;

        if ($token === '' || $slot === null) {
            return back()->with('error', 'QR tidak dikenali sebagai kode absensi.');
        }

        try {
            $this->recordScan($session, $profile, $token, $slot, $request, [
                'latitude' => isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                'longitude' => isset($validated['longitude']) ? (float) $validated['longitude'] : null,
                'accuracy' => isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
            ]);
        } catch (ValidationException $exception) {
            return back()->with(
                'error',
                collect($exception->errors())->flatten()->first() ?: 'Kode absensi tidak valid.'
            );
        }

        return redirect()
            ->route('student.schedule.attendance.record', ['sessionId' => $session->id])
            ->with('success', 'Absensi berhasil. Status Anda tercatat Hadir.');
    }

    private function recordScan(
        AttendanceSession $session,
        StudentProfile $profile,
        string $token,
        int $slot,
        Request $request,
        array $metadata = []
    ): void {
        app(AcademicAttendanceQrService::class)->recordScan(
            $session,
            $profile,
            $token,
            $slot,
            $request->user()?->id,
            array_merge($metadata, [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
        );
    }

    private function buildRows(Collection $schedules, Collection $sessions): Collection
    {
        $sessionBySchedule = $sessions
            ->whereNotNull('course_schedule_id')
            ->keyBy('course_schedule_id');

        $usedSessionIds = collect();
        $scheduleIds = $schedules->pluck('id')->all();

        $rows = $schedules->map(function (CourseSchedule $schedule) use ($sessionBySchedule, &$usedSessionIds) {
            $session = $sessionBySchedule->get($schedule->id);

            if ($session !== null) {
                $usedSessionIds->push($session->id);
            }

            return $this->rowFromSchedule($schedule, $session);
        });

        $extras = $sessions
            ->reject(fn (AttendanceSession $session) => $usedSessionIds->contains($session->id))
            ->reject(fn (AttendanceSession $session) => $session->course_schedule_id !== null
                && in_array($session->course_schedule_id, $scheduleIds, true))
            ->map(fn (AttendanceSession $session) => $this->rowFromSession($session));

        return $rows->concat($extras)
            ->filter(fn (array $row) => isset(self::DAY_ORDER[$row['dayKey']]))
            ->sortBy(fn (array $row) => sprintf(
                '%02d-%s-%s',
                self::DAY_ORDER[$row['dayKey']] ?? 99,
                $row['startTime'],
                $row['courseName']
            ))
            ->values();
    }

    private function rowFromSchedule(CourseSchedule $schedule, ?AttendanceSession $session): array
    {
        $record = $session?->records->first();

        return [
            'key' => 'schedule-'.$schedule->id,
            'dayKey' => $schedule->day_of_week,
            'startTime' => $this->formatTime($schedule->start_time),
            'endTime' => $this->formatTime($schedule->end_time),
            'courseName' => $schedule->courseOffering?->course?->name ?? $schedule->courseOffering?->label ?? '-',
            'courseCode' => $schedule->courseOffering?->course?->code ?? '-',
            'classLabel' => $schedule->courseOffering?->label,
            'room' => $schedule->room?->name ?? '-',
            'building' => $schedule->room?->building?->name ?? '-',
            'deliveryMode' => $schedule->delivery_mode ?? '-',
            'sessionType' => $schedule->session_type ?? '-',
            'meetingNo' => $session?->meeting_no,
            'meetingDate' => $session?->meeting_date?->format('d M Y'),
            'sessionId' => $session?->id,
            'sessionStatus' => $session?->status,
            'offeringId' => $schedule->course_offering_id,
            'attendanceStatus' => $record?->status
                ? (self::STATUS_LABELS[$record->status] ?? $record->status)
                : 'Belum Absen',
            'attendanceTone' => $this->attendanceTone($record?->status),
            'canRecord' => $session !== null && $this->isSessionOpen($session),
        ];
    }

    private function rowFromSession(AttendanceSession $session): array
    {
        $record = $session->records->first();
        $schedule = $session->courseSchedule;

        return [
            'key' => 'session-'.$session->id,
            'dayKey' => $session->meeting_date?->englishDayOfWeek ?? '',
            'startTime' => $this->formatTime($session->start_time),
            'endTime' => $this->formatTime($session->end_time),
            'courseName' => $session->courseOffering?->course?->name ?? $session->courseOffering?->label ?? '-',
            'courseCode' => $session->courseOffering?->course?->code ?? '-',
            'classLabel' => $session->courseOffering?->label,
            'room' => $schedule?->room?->name ?? '-',
            'building' => $schedule?->room?->building?->name ?? '-',
            'deliveryMode' => $schedule?->delivery_mode ?? '-',
            'sessionType' => $schedule?->session_type ?? '-',
            'meetingNo' => $session->meeting_no,
            'meetingDate' => $session->meeting_date?->format('d M Y'),
            'sessionId' => $session->id,
            'sessionStatus' => $session->status,
            'offeringId' => $session->course_offering_id,
            'attendanceStatus' => $record?->status
                ? (self::STATUS_LABELS[$record->status] ?? $record->status)
                : 'Belum Absen',
            'attendanceTone' => $this->attendanceTone($record?->status),
            'canRecord' => $this->isSessionOpen($session),
        ];
    }

    /**
     * @return array{year: ?AcademicYear, registration: ?StudentRegistration, plan: ?StudyPlan}
     */
    private function context(?StudentProfile $profile): array
    {
        if ($profile === null) {
            return ['year' => null, 'registration' => null, 'plan' => null];
        }

        $year = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        if ($year === null) {
            return ['year' => null, 'registration' => null, 'plan' => null];
        }

        $registration = StudentRegistration::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year_id', $year->id)
            ->latest('id')
            ->first();

        $plan = StudyPlan::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year_id', $year->id)
            ->latest('id')
            ->first();

        return ['year' => $year, 'registration' => $registration, 'plan' => $plan];
    }

    private function canAccessOffering(?StudentProfile $profile, array $context, int $offeringId): bool
    {
        if ($profile === null || $context['year'] === null || $context['plan'] === null) {
            return false;
        }

        if ($context['plan']->status !== 'Approved') {
            return false;
        }

        return StudyPlanDetail::query()
            ->where('study_plan_id', $context['plan']->id)
            ->where('course_offering_id', $offeringId)
            ->officiallyEnrolled()
            ->exists();
    }

    private function studentProfile(Request $request): ?StudentProfile
    {
        return $request->user()?->studentProfile()->with('studyProgram')->first();
    }

    private function isSessionOpen(AttendanceSession $session): bool
    {
        return $session->status === 'Opened' && $session->closed_at === null;
    }

    private function attendanceTone(?string $status): string
    {
        return match ($status) {
            'Present' => 'green',
            'Late', 'Excused', 'Sick' => 'amber',
            'Absent' => 'red',
            default => 'gray',
        };
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
}
