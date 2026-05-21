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
    public bool $isAcademicallyActive = false;
    public bool $hasStudyPlanAccess = false;
    public ?int $studentProfileId = null;
    public ?int $activeAcademicYearId = null;
    public ?string $activeAcademicYearName = null;
    public ?string $registrationStatus = null;
    public ?string $academicStatus = null;
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
        $this->academicStatus = $registration?->academic_status;

        if (! $registration || $registration->registration_status !== 'Approved') {
            return;
        }

        $this->hasApprovedRegistration = true;

        if ($registration->academic_status !== 'Aktif') {
            return;
        }

        $this->isAcademicallyActive = true;

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

        .stat-card {
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            transition: all 0.3s ease;
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

        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
        }

        .session-row {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .session-row:hover {
            background: #f8fafc;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @elseif (! $hasApprovedRegistration)
        <div class="alert alert-warning">Registrasi semester belum disetujui, data kehadiran belum bisa diakses.</div>
    @elseif (! $isAcademicallyActive)
        <div class="alert alert-warning">Status akademik semester ini adalah {{ $academicStatus ?? '-' }}, data kehadiran belum bisa diakses.</div>
    @elseif (! $hasStudyPlanAccess)
        <div class="alert alert-danger">Data kelas tidak ditemukan atau Anda tidak memiliki akses.</div>
    @else
        {{-- Hero Section with Gradient --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Absensi Mata Kuliah</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $courseName }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-calendar me-2"></i>{{ $activeAcademicYearName }} • 
                                    <span class="badge bg-white text-primary ms-2">{{ $registrationStatus }}</span>
                                </div>
                                <a href="{{ route('student.schedule.index') }}" class="btn btn-light" style="border-radius: 10px; padding: 0.6rem 1.2rem; font-weight: 600;">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Jadwal
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-value" style="color: #1e40af;">{{ $summary['total_meetings'] }}</div>
                    <div class="stat-label">Total Sesi</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46;">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="stat-value" style="color: #065f46;">{{ $summary['opened_count'] }}</div>
                    <div class="stat-label">Sesi Dibuka</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value" style="color: #92400e;">{{ $summary['attended_count'] }}</div>
                    <div class="stat-label">Sudah Diabsen</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value" style="color: #991b1b;">{{ $summary['not_input_count'] }}</div>
                    <div class="stat-label">Belum Diinput</div>
                </div>
            </div>
        </div>

        {{-- Sessions Table --}}
        <div class="modern-card">
            <div class="card-header" style="padding: 1.5rem; border-bottom: 2px solid #f1f5f9;">
                <h3 class="mb-0" style="font-weight: 600; color: #1f2937;">
                    <i class="fas fa-list-alt me-2" style="color: #667eea;"></i>Daftar Pertemuan
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0" style="margin: 0;">
                        <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <tr>
                                <th style="padding: 1rem; font-weight: 600; border-top-left-radius: 12px;">Pertemuan</th>
                                <th style="padding: 1rem; font-weight: 600;">Tanggal</th>
                                <th style="padding: 1rem; font-weight: 600;">Jam</th>
                                <th style="padding: 1rem; font-weight: 600;">Topik</th>
                                <th style="padding: 1rem; font-weight: 600;">Status Sesi</th>
                                <th style="padding: 1rem; font-weight: 600;">Status Absensi</th>
                                <th style="padding: 1rem; font-weight: 600; text-align: right; border-top-right-radius: 12px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($attendanceItems as $item)
                                <tr class="session-row">
                                    <td style="padding: 1rem; font-weight: 600; color: #1f2937;">{{ $item['meeting_no'] ?? '-' }}</td>
                                    <td style="padding: 1rem;">
                                        <i class="fas fa-calendar-day me-2" style="color: #667eea;"></i>{{ $item['meeting_date'] }}
                                    </td>
                                    <td style="padding: 1rem;">
                                        <i class="fas fa-clock me-2" style="color: #10b981;"></i>{{ $item['time_range'] }}
                                    </td>
                                    <td style="padding: 1rem;">{{ $item['topic'] }}</td>
                                    <td style="padding: 1rem;">
                                        <span class="status-badge" style="background: #dbeafe; color: #1e40af;">
                                            {{ $item['session_status'] }}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span class="status-badge" style="{{ match($item['status']) {
                                            'Hadir' => 'background: #d1fae5; color: #065f46;',
                                            'Terlambat', 'Izin', 'Sakit' => 'background: #fef3c7; color: #92400e;',
                                            'Alpha' => 'background: #fee2e2; color: #991b1b;',
                                            default => 'background: #f1f5f9; color: #64748b;',
                                        } }}">
                                            {{ $item['status'] }}
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        @if ($item['can_fill_attendance'])
                                            <a href="{{ route('student.schedule.attendance.record', ['sessionId' => $item['session_id']]) }}" 
                                               class="action-btn" 
                                               style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                                <i class="fas fa-edit"></i> Isi / Update
                                            </a>
                                        @else
                                            <button type="button" class="action-btn" disabled style="background: #e5e7eb; color: #6b7280;">
                                                <i class="fas fa-lock"></i> Belum Bisa
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block;"></i>
                                        <div style="color: #64748b; font-size: 1rem;">Belum ada sesi kehadiran pada kelas ini.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
