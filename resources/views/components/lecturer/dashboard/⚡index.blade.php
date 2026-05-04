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
    public array $recentSessions = [];

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

        $this->recentSessions = $offeringIds->isEmpty()
            ? []
            : AttendanceSession::query()
                ->with(['courseOffering.course'])
                ->whereIn('course_offering_id', $offeringIds)
                ->orderByDesc('meeting_date')
                ->orderByDesc('meeting_no')
                ->limit(6)
                ->get()
                ->map(function (AttendanceSession $session) {
                    return [
                        'course' => ($session->courseOffering?->course?->code ?? '-') . ' - ' . ($session->courseOffering?->course?->name ?? '-'),
                        'meeting_no' => $session->meeting_no,
                        'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                        'status' => $session->status ?? '-',
                    ];
                })
                ->all();
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
        .lecturer-dashboard-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .lecturer-dashboard-hero {
            background:
                radial-gradient(circle at top right, rgba(192, 132, 252, 0.24), transparent 30%),
                linear-gradient(135deg, var(--app-primary-deep, #4c1d95) 0%, var(--app-primary-bright, #a855f7) 100%);
            color: #fff;
        }

        .lecturer-dashboard-hero .text-secondary {
            color: rgba(255, 255, 255, 0.72) !important;
        }

        .lecturer-dashboard-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil dosen belum tersedia.</div>
    @else
        <div class="card lecturer-dashboard-card lecturer-dashboard-hero mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="text-uppercase small fw-semibold mb-2">Dashboard Dosen</div>
                        <h1 class="h2 mb-2">{{ $lecturerInfo['name'] }}</h1>
                        <div class="text-secondary mb-3">
                            {{ $lecturerInfo['study_program'] }} • {{ $lecturerInfo['faculty'] }} • {{ $lecturerInfo['employment_status'] }}
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-white text-primary">NIDN {{ $lecturerInfo['nidn'] }}</span>
                            <span class="badge bg-white text-primary">{{ $stats['active_year'] }}</span>
                            <span class="badge bg-white text-primary">{{ $lecturerInfo['email'] }}</span>
                        </div>
                    </div>

                    <div class="col-lg-4 text-lg-end">
                        <div class="btn-list justify-content-lg-end">
                            <a href="{{ route('lecturer.course-offerings.index') }}" class="btn btn-light">Kelas</a>
                            <a href="{{ route('lecturer.student-grades.index') }}" class="btn btn-outline-light">Nilai</a>
                            <a href="{{ route('lecturer.attendance-sessions.index') }}" class="btn btn-outline-light">Absensi</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards mb-4">
            <div class="col-md-4 col-xl-2">
                <div class="card lecturer-dashboard-card">
                    <div class="card-body">
                        <div class="lecturer-dashboard-label">Kelas</div>
                        <div class="h2 mt-2 mb-0">{{ $stats['total_classes'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-xl-2">
                <div class="card lecturer-dashboard-card">
                    <div class="card-body">
                        <div class="lecturer-dashboard-label">Mahasiswa</div>
                        <div class="h2 mt-2 mb-0">{{ $stats['total_students'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-xl-2">
                <div class="card lecturer-dashboard-card">
                    <div class="card-body">
                        <div class="lecturer-dashboard-label">Sesi</div>
                        <div class="h2 mt-2 mb-0">{{ $stats['total_sessions'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card lecturer-dashboard-card">
                    <div class="card-body">
                        <div class="lecturer-dashboard-label">Nilai Finalized</div>
                        <div class="h2 mt-2 mb-0">{{ $stats['finalized_grades'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card lecturer-dashboard-card">
                    <div class="card-body">
                        <div class="lecturer-dashboard-label">Nilai Published</div>
                        <div class="h2 mt-2 mb-0">{{ $stats['published_grades'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cards">
            <div class="col-lg-6">
                <div class="card lecturer-dashboard-card h-100">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Kelas Terbaru</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($recentClasses as $class)
                            <div class="list-group-item">
                                <div class="fw-semibold">{{ $class['course'] }}</div>
                                <div class="text-secondary small">{{ $class['class'] }} • {{ $class['academic_year'] }} • {{ $class['role'] }}</div>
                            </div>
                        @empty
                            <div class="p-4 text-secondary">Belum ada kelas aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card lecturer-dashboard-card h-100">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Sesi Terbaru</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($recentSessions as $session)
                            <div class="list-group-item">
                                <div class="fw-semibold">{{ $session['course'] }}</div>
                                <div class="text-secondary small">Pertemuan {{ $session['meeting_no'] }} • {{ $session['meeting_date'] }} • {{ $session['status'] }}</div>
                            </div>
                        @empty
                            <div class="p-4 text-secondary">Belum ada sesi absensi.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
