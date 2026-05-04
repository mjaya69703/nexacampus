<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlanDetail;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public bool $hasApprovedRegistration = false;
    public bool $hasStudyPlanAccess = false;
    public ?int $studentProfileId = null;
    public ?int $activeAcademicYearId = null;
    public ?string $activeAcademicYearName = null;
    public ?string $registrationStatus = null;
    public ?string $courseName = null;
    public ?int $offeringId = null;
    public array $attendanceItems = [];
    public array $summary = [];

    public function mount(int $offeringId): void
    {
        $this->offeringId = $offeringId;
        $this->summary = [
            'total_meetings' => 0,
            'opened_count' => 0,
            'attended_count' => 0,
            'not_input_count' => 0,
        ];

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $studentProfile = $user->studentProfile()->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->studentProfileId = $studentProfile->id;

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

        if (! $registration || $registration->registration_status !== 'Approved') {
            return;
        }

        $this->hasApprovedRegistration = true;

        $studyPlanDetail = StudyPlanDetail::query()
            ->with('courseOffering.course')
            ->where('course_offering_id', $offeringId)
            ->whereHas('studyPlan', function ($query) {
                $query->where('student_profile_id', $this->studentProfileId)
                    ->where('academic_year_id', $this->activeAcademicYearId)
                    ->where('status', 'Approved');
            })
            ->first();

        if (! $studyPlanDetail) {
            return;
        }

        $this->hasStudyPlanAccess = true;
        $this->courseName = $studyPlanDetail->courseOffering?->course?->name ?? $studyPlanDetail->courseOffering?->label ?? '-';

        $sessions = AttendanceSession::query()
            ->with([
                'records' => function ($query) {
                    $query->where('student_profile_id', $this->studentProfileId);
                },
            ])
            ->where('course_offering_id', $offeringId)
            ->orderBy('meeting_no')
            ->orderBy('meeting_date')
            ->get();

        $statusMap = [
            'Present' => 'Hadir',
            'Late' => 'Terlambat',
            'Excused' => 'Izin',
            'Sick' => 'Sakit',
            'Absent' => 'Alpha',
        ];

        $this->attendanceItems = $sessions
            ->map(function ($session) use ($statusMap) {
                $record = $session->records->first();
                $status = $record?->status;
                $canFillAttendance = $this->isSessionWindowOpen($session);

                return [
                    'session_id' => $session->id,
                    'meeting_no' => $session->meeting_no,
                    'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                    'time_range' => $this->formatTime($session->start_time) . ' - ' . $this->formatTime($session->end_time),
                    'topic' => $session->topic ?: '-',
                    'session_status' => $session->status,
                    'status' => $status ? ($statusMap[$status] ?? $status) : 'Belum diinput',
                    'status_class' => match ($status) {
                        'Present' => 'bg-green-lt text-green',
                        'Late', 'Excused', 'Sick' => 'bg-yellow-lt text-yellow',
                        'Absent' => 'bg-red-lt text-red',
                        default => 'bg-secondary-lt text-secondary',
                    },
                    'can_fill_attendance' => $canFillAttendance,
                ];
            })
            ->values()
            ->all();

        $collection = collect($this->attendanceItems);
        $this->summary = [
            'total_meetings' => $collection->count(),
            'opened_count' => $collection->where('session_status', 'Opened')->count(),
            'attended_count' => $collection->whereIn('status', ['Hadir', 'Terlambat', 'Izin', 'Sakit'])->count(),
            'not_input_count' => $collection->where('status', 'Belum diinput')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Course Attendance',
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
        .student-attendance-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .student-attendance-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .student-attendance-value {
            font-size: 28px;
            line-height: 1;
            font-weight: 700;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @elseif (! $hasApprovedRegistration)
        <div class="alert alert-warning">Registrasi semester belum disetujui, data kehadiran belum bisa diakses.</div>
    @elseif (! $hasStudyPlanAccess)
        <div class="alert alert-danger">Data kelas tidak ditemukan atau Anda tidak memiliki akses.</div>
    @else
        <div class="card student-attendance-card mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="student-attendance-label mb-2">Kehadiran Mata Kuliah</div>
                        <h2 class="mb-2">{{ $courseName }}</h2>
                        <div class="text-secondary">
                            Tahun akademik {{ $activeAcademicYearName }} • Status registrasi {{ $registrationStatus }}
                        </div>
                    </div>

                    <div class="col-lg-4 text-lg-end">
                        <a href="{{ route('student.schedule.index') }}" class="btn btn-outline-secondary">
                            Kembali ke Jadwal
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-4">
            <div class="col-md-3">
                <div class="card student-attendance-card">
                    <div class="card-body">
                        <div class="student-attendance-label">Total Sesi</div>
                        <div class="student-attendance-value">{{ $summary['total_meetings'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card student-attendance-card">
                    <div class="card-body">
                        <div class="student-attendance-label">Sesi Dibuka</div>
                        <div class="student-attendance-value">{{ $summary['opened_count'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card student-attendance-card">
                    <div class="card-body">
                        <div class="student-attendance-label">Sudah Diabsen</div>
                        <div class="student-attendance-value">{{ $summary['attended_count'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card student-attendance-card">
                    <div class="card-body">
                        <div class="student-attendance-label">Belum Diinput</div>
                        <div class="student-attendance-value">{{ $summary['not_input_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card student-attendance-card">
            <div class="card-header">
                <h3 class="card-title mb-0">Daftar Pertemuan</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Pertemuan</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Topik</th>
                                <th>Status Sesi</th>
                                <th>Status Absensi</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($attendanceItems as $item)
                                <tr>
                                    <td>{{ $item['meeting_no'] ?? '-' }}</td>
                                    <td>{{ $item['meeting_date'] }}</td>
                                    <td>{{ $item['time_range'] }}</td>
                                    <td>{{ $item['topic'] }}</td>
                                    <td><span class="badge bg-azure-lt text-azure">{{ $item['session_status'] }}</span></td>
                                    <td><span class="badge {{ $item['status_class'] }}">{{ $item['status'] }}</span></td>
                                    <td class="text-end">
                                        @if ($item['can_fill_attendance'])
                                            <a href="{{ route('student.schedule.attendance.record', ['sessionId' => $item['session_id']]) }}" class="btn btn-primary">
                                                Isi / Update Absen
                                            </a>
                                        @else
                                            <button type="button" class="btn btn-secondary" disabled>
                                                Belum Bisa
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-secondary">Belum ada sesi kehadiran pada kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
