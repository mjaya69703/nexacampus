<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public array $lecturerInfo = [];
    public array $stats = [];
    public array $recentClasses = [];
    public array $upcomingSessions = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $lecturerProfile = $user->lecturerProfile()
            ->with(['studyProgram', 'faculty'])
            ->first();

        if (! $lecturerProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->lecturerInfo = [
            'name' => $user->name,
            'email' => $user->email,
            'nidn' => $lecturerProfile->nidn ?? '-',
            'study_program' => $lecturerProfile->studyProgram?->name ?? '-',
            'faculty' => $lecturerProfile->faculty?->name ?? '-',
            'employment_status' => $lecturerProfile->employment_status ?? '-',
        ];

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        $assignments = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->with(['courseOffering.course', 'courseOffering.academicYear'])
            ->get();

        $offeringIds = $assignments->pluck('course_offering_id')->filter();

        $totalStudents = $offeringIds->isEmpty()
            ? 0
            : StudyPlanDetail::query()->whereIn('course_offering_id', $offeringIds)->count();

        $totalSessions = $offeringIds->isEmpty()
            ? 0
            : AttendanceSession::query()->whereIn('course_offering_id', $offeringIds)->count();

        $gradedRows = $offeringIds->isEmpty()
            ? collect()
            : StudentGrade::query()
                ->whereHas('studyPlanDetail', fn ($query) => $query->whereIn('course_offering_id', $offeringIds))
                ->get();

        $this->stats = [
            'active_year' => $activeAcademicYear?->name ?? '-',
            'total_classes' => $assignments->count(),
            'total_students' => $totalStudents,
            'total_sessions' => $totalSessions,
            'finalized_grades' => $gradedRows->where('grade_status', 'Finalized')->count(),
            'published_grades' => $gradedRows->where('grade_status', 'Published')->count(),
        ];

        $this->recentClasses = $assignments
            ->map(function ($assignment) {
                $offering = $assignment->courseOffering;

                return [
                    'id' => $offering?->id,
                    'course' => ($offering?->course?->code ?? '-') . ' - ' . ($offering?->course?->name ?? '-'),
                    'class' => $offering?->label ?? '-',
                    'academic_year' => $offering?->academicYear?->name ?? '-',
                    'role' => $assignment->role ?? '-',
                ];
            })
            ->take(6)
            ->values()
            ->all();

        // Upcoming sessions sorted by nearest date/time first
        $this->upcomingSessions = $offeringIds->isEmpty()
            ? []
            : AttendanceSession::query()
                ->with(['courseOffering.course'])
                ->whereIn('course_offering_id', $offeringIds)
                ->whereDate('meeting_date', '>=', now())
                ->orderBy('meeting_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->limit(6)
                ->get()
                ->map(function (AttendanceSession $session) {
                    return [
                        'course' => ($session->courseOffering?->course?->code ?? '-') . ' - ' . ($session->courseOffering?->course?->name ?? '-'),
                        'meeting_no' => $session->meeting_no,
                        'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                        'start_time' => $this->formatTime($session->start_time),
                        'end_time' => $this->formatTime($session->end_time),
                        'status' => $session->status ?? '-',
                    ];
                })
                ->all();
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

    public function render()
    {
        $data = [
            'menus' => 'Dashboard',
            'pages' => 'Lecturer Dashboard',
        ];

        return $this->view()->layout('layouts.app', $data);
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .quick-action-btn {
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
            border: 2px solid #e5e7eb;
            cursor: pointer;
        }

        .quick-action-btn:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            transform: translateY(-2px);
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1.5rem;
            border-left: 2px solid #e5e7eb;
        }

        .timeline-item:last-child {
            border-left: 2px solid transparent;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #667eea;
            border: 2px solid white;
        }

        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .progress-ring {
            transform: rotate(-90deg);
        }

        .progress-ring-circle {
            transition: stroke-dashoffset 0.5s ease;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil dosen belum tersedia.</div>
    @else
        {{-- Hero Section --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Selamat Datang,</div>
                                <h1 class="h2 mb-0" style="font-weight: 700;">{{ $lecturerInfo['name'] }}</h1>
                            </div>
                        </div>
                        <div style="opacity: 0.9; margin-bottom: 1rem;">
                            {{ $lecturerInfo['study_program'] }} • {{ $lecturerInfo['faculty'] }}
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge-modern" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                🎓 NIDN {{ $lecturerInfo['nidn'] }}
                            </span>
                            <span class="badge-modern" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                📅 {{ $stats['active_year'] }}
                            </span>
                            <span class="badge-modern" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                ✉️ {{ $lecturerInfo['email'] }}
                            </span>
                        </div>
                    </div>

                    <div class="col-lg-4 text-lg-end">
                        <div class="d-flex flex-column gap-2">
                            <a href="{{ route('lecturer.course-offerings.index') }}" class="btn btn-light fw-semibold" style="border-radius: 12px; padding: 0.75rem 1.5rem;">
                                📚 Lihat Kelas
                            </a>
                            <a href="{{ route('lecturer.student-grades.index') }}" class="btn btn-outline-light fw-semibold" style="border-radius: 12px; padding: 0.75rem 1.5rem;">
                                <i class="fas fa-chart-line me-2"></i>Input Nilai
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards with Icons --}}
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl">
                <div class="modern-card stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        📚
                    </div>
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Total Kelas</div>
                    <div class="h2 mt-2 mb-0" style="font-weight: 700; color: #1f2937;">{{ $stats['total_classes'] }}</div>
                    <div style="font-size: 0.8rem; color: #10b981; margin-top: 0.5rem;">↑ Aktif mengajar</div>
                </div>
            </div>
            <div class="col-md-6 col-xl">
                <div class="modern-card stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                        👥
                    </div>
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Mahasiswa</div>
                    <div class="h2 mt-2 mb-0" style="font-weight: 700; color: #1f2937;">{{ $stats['total_students'] }}</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.5rem;">Terdaftar di kelas</div>
                </div>
            </div>
            <div class="col-md-6 col-xl">
                <div class="modern-card stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                        📅
                    </div>
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Sesi Absensi</div>
                    <div class="h2 mt-2 mb-0" style="font-weight: 700; color: #1f2937;">{{ $stats['total_sessions'] }}</div>
                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.5rem;">Total sesi dibuat</div>
                </div>
            </div>
            <div class="col-md-6 col-xl">
                <div class="modern-card stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                        ✓
                    </div>
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Nilai Finalized</div>
                    <div class="h2 mt-2 mb-0" style="font-weight: 700; color: #1f2937;">{{ $stats['finalized_grades'] }}</div>
                    <div style="font-size: 0.8rem; color: #f59e0b; margin-top: 0.5rem;">⏳ Menunggu publish</div>
                </div>
            </div>
            <div class="col-md-6 col-xl">
                <div class="modern-card stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white;">
                        🎯
                    </div>
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Nilai Published</div>
                    <div class="h2 mt-2 mb-0" style="font-weight: 700; color: #1f2937;">{{ $stats['published_grades'] }}</div>
                    <div style="font-size: 0.8rem; color: #10b981; margin-top: 0.5rem;">✓ Sudah dipublish</div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="modern-card mb-4">
            <div class="card-body p-4">
                <h3 class="h5 mb-3" style="font-weight: 700; color: #1f2937;">⚡ Aksi Cepat</h3>
                <div class="row g-3">
                    <div class="col-md-4">
                        <a href="{{ route('lecturer.course-offerings.index') }}" class="quick-action-btn d-block text-decoration-none">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-calendar-check" style="color: #10b981;"></i></div>
                            <div style="font-weight: 600; color: #1f2937;">Kelola Absensi</div>
                            <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Pilih kelas untuk absensi</div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('lecturer.student-grades.index') }}" class="quick-action-btn d-block text-decoration-none">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-chart-line" style="color: #3b82f6;"></i></div>
                            <div style="font-weight: 600; color: #1f2937;">Input Nilai</div>
                            <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Masukkan nilai mahasiswa</div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('lecturer.course-offerings.index') }}" class="quick-action-btn d-block text-decoration-none">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"><i class="fas fa-book-open" style="color: #f59e0b;"></i></div>
                            <div style="font-weight: 600; color: #1f2937;">Kelola Kelas</div>
                            <div style="font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem;">Lihat daftar kelas</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Content Grid --}}
        <div class="row g-4">
            {{-- Recent Classes --}}
            <div class="col-lg-6">
                <div class="modern-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h5 mb-0" style="font-weight: 700; color: #1f2937;">📚 Kelas Terbaru</h3>
                            <a href="{{ route('lecturer.course-offerings.index') }}" class="btn btn-outline-primary" style="border-radius: 8px;">Lihat Semua</a>
                        </div>
                        <div class="timeline">
                            @forelse ($recentClasses as $class)
                                <div class="timeline-item">
                                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $class['course'] }}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">
                                        <span>{{ $class['class'] }}</span> • 
                                        <span>{{ $class['academic_year'] }}</span> • 
                                        <span class="badge bg-primary-lt text-primary">{{ $class['role'] }}</span>
                                    </div>
                                </div>
                            @empty
                                <div style="text-align: center; padding: 2rem; color: #6b7280;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                                    <div>Belum ada kelas aktif</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Upcoming Teaching Schedule --}}
            <div class="col-lg-6">
                <div class="modern-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h5 mb-0" style="font-weight: 700; color: #1f2937;"><i class="fas fa-clock me-2" style="color: #f59e0b;"></i>Jadwal Mengajar Terdekat</h3>
                            <a href="{{ route('lecturer.course-offerings.index') }}" class="btn btn-outline-primary" style="border-radius: 8px;">Lihat Semua Kelas</a>
                        </div>
                        <div class="timeline">
                            @forelse ($upcomingSessions as $session)
                                <div class="timeline-item">
                                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $session['course'] }}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">
                                        <span><i class="fas fa-hashtag me-1"></i>Pertemuan #{{ $session['meeting_no'] }}</span> • 
                                        <span><i class="fas fa-calendar-day me-1"></i>{{ $session['meeting_date'] }}</span> • 
                                        <span><i class="fas fa-clock me-1"></i>{{ $session['start_time'] }} - {{ $session['end_time'] }}</span>
                                    </div>
                                    <div style="margin-top: 0.5rem;">
                                        <span class="badge {{ $session['status'] === 'Opened' ? 'bg-success-lt text-success' : ($session['status'] === 'Closed' ? 'bg-danger-lt text-danger' : 'bg-secondary-lt text-secondary') }}">
                                            <i class="fas {{ $session['status'] === 'Opened' ? 'fa-circle-check' : ($session['status'] === 'Closed' ? 'fa-circle-xmark' : 'fa-file-alt') }} me-1"></i>{{ $session['status'] }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div style="text-align: center; padding: 2rem; color: #6b7280;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;"><i class="fas fa-calendar-times"></i></div>
                                    <div>Tidak ada jadwal terdekat</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
