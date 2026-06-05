<?php

use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public int $offeringId;
    public array $classInfo = [];
    public array $stats = [];
    public array $students = [];
    public array $attendance = [];
    public array $grades = [];

    public function mount($id): void
    {
        $this->offeringId = (int) $id;

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
            ->with([
                'courseOffering.academicYear',
                'courseOffering.studyProgram',
                'courseOffering.course',
                'courseOffering.lecturers.lecturerProfile.user',
            ])
            ->first();

        if (! $assignment || ! $assignment->courseOffering) {
            abort(404);
        }

        $offering = $assignment->courseOffering;

        $this->classInfo = [
            'id' => $offering->id,
            'academic_year' => $offering->academicYear?->name ?? '-',
            'study_program' => $offering->studyProgram?->name ?? '-',
            'course_code' => $offering->course?->code ?? '-',
            'course_name' => $offering->course?->name ?? '-',
            'label' => $offering->label ?? '-',
            'class_code' => $offering->code ?? '-',
            'semester_no' => $offering->semester_no ?? '-',
            'credits' => $offering->credits ?? $offering->course?->credits ?? 0,
            'capacity' => $offering->capacity,
            'delivery_mode' => $offering->delivery_mode ?? '-',
            'status' => $offering->status ?? '-',
            'lecturers' => $offering->lecturers
                ->where('is_active', true)
                ->map(fn ($lecturer) => [
                    'name' => $lecturer->lecturerProfile?->user?->name ?? '-',
                    'role' => $lecturer->role ?? '-',
                ])
                ->values()
                ->all(),
        ];

        $this->loadStudentsData();
        $this->loadAttendanceData();
        $this->loadGradesData();
        $this->buildStats();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Course Offering Detail',
        ]);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Published', 'Open' => 'bg-green-lt text-green',
            'Finalized', 'Opened' => 'bg-blue-lt text-blue',
            'Draft' => 'bg-yellow-lt text-yellow',
            'Closed', 'Cancelled', 'Absent' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    private function loadStudentsData(): void
    {
        $details = StudyPlanDetail::query()
            ->with([
                'studyPlan.studentProfile.user',
                'studentGrade',
            ])
            ->where('course_offering_id', $this->offeringId)
            ->get();

        $this->students = $details
            ->map(function (StudyPlanDetail $detail) {
                $studentProfile = $detail->studyPlan?->studentProfile;
                $grade = $detail->studentGrade;

                return [
                    'student_profile_id' => $studentProfile?->id,
                    'nim' => $studentProfile?->nim ?? '-',
                    'name' => $studentProfile?->user?->name ?? '-',
                    'study_plan_status' => $detail->studyPlan?->status ?? '-',
                    'grade_letter' => $grade?->letter_grade ?? '-',
                    'grade_status' => $grade?->grade_status ?? '-',
                ];
            })
            ->filter(fn (array $student) => $student['student_profile_id'])
            ->unique('student_profile_id')
            ->values()
            ->all();
    }

    private function loadAttendanceData(): void
    {
        $sessions = AttendanceSession::query()
            ->with(['lecturerProfile.user', 'records'])
            ->where('course_offering_id', $this->offeringId)
            ->orderBy('meeting_no')
            ->orderBy('meeting_date')
            ->get();

        $this->attendance = $sessions
            ->map(function (AttendanceSession $session) {
                $records = $session->records;
                $presentCount = $records->whereIn('status', ['Present', 'Late', 'Excused', 'Sick'])->count();
                $absentCount = $records->where('status', 'Absent')->count();

                return [
                    'id' => $session->id,
                    'meeting_no' => $session->meeting_no,
                    'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                    'start_time' => $this->formatTime($session->start_time),
                    'end_time' => $this->formatTime($session->end_time),
                    'lecturer_name' => $session->lecturerProfile?->user?->name ?? '-',
                    'topic' => $session->topic ?? '-',
                    'status' => $session->status,
                    'total_records' => $records->count(),
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                ];
            })
            ->values()
            ->all();
    }

    private function loadGradesData(): void
    {
        $gradeRows = StudentGrade::query()
            ->with(['studyPlanDetail.studyPlan.studentProfile.user'])
            ->whereHas('studyPlanDetail', function ($query) {
                $query->where('course_offering_id', $this->offeringId);
            })
            ->latest('graded_at')
            ->get();

        $this->grades = $gradeRows
            ->map(function (StudentGrade $grade) {
                $studentProfile = $grade->studyPlanDetail?->studyPlan?->studentProfile;

                return [
                    'nim' => $studentProfile?->nim ?? '-',
                    'name' => $studentProfile?->user?->name ?? '-',
                    'final_score' => $grade->final_score !== null ? number_format((float) $grade->final_score, 2) : '-',
                    'letter_grade' => $grade->letter_grade ?? '-',
                    'grade_point' => $grade->grade_point !== null ? number_format((float) $grade->grade_point, 2) : '-',
                    'result_status' => $grade->result_status ?? '-',
                    'grade_status' => $grade->grade_status ?? '-',
                    'graded_at' => $grade->graded_at?->format('d M Y H:i') ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    private function buildStats(): void
    {
        $publishedGrades = collect($this->grades)->where('grade_status', 'Published');
        $averagePoint = $publishedGrades
            ->filter(fn (array $grade) => $grade['grade_point'] !== '-')
            ->avg(fn (array $grade) => (float) $grade['grade_point']);

        $this->stats = [
            'total_students' => count($this->students),
            'total_sessions' => count($this->attendance),
            'opened_sessions' => collect($this->attendance)->where('status', 'Opened')->count(),
            'graded_students' => count($this->grades),
            'published_students' => $publishedGrades->count(),
            'average_grade_point' => $averagePoint ? number_format((float) $averagePoint, 2) : '-',
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
            border-radius: 16px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
            transform: translateY(-4px);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .hero-action {
            border-radius: 12px;
            padding: 0.85rem 1.1rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .quick-action-card {
            display: block;
            height: 100%;
            border-radius: 18px;
            background: white;
            border: 1px solid #e2e8f0;
            padding: 1.35rem;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
            transition: all 0.2s ease;
        }

        .quick-action-card:hover {
            transform: translateY(-3px);
            border-color: #667eea;
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.14);
            color: inherit;
        }

        .quick-action-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .quick-action-title {
            font-weight: 800;
            color: #111827;
            margin-bottom: 0.2rem;
        }

        .quick-action-meta {
            color: #64748b;
            font-size: 0.84rem;
            line-height: 1.35;
        }

        .overview-panel {
            border-radius: 18px;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .overview-panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .preview-row {
            padding: 0.95rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .preview-row:last-child {
            border-bottom: none;
        }

        .soft-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            background: #f1f5f9;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 700;
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
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Detail Kelas</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">{{ $classInfo['course_code'] }} - {{ $classInfo['course_name'] }}</h2>
                            <div style="font-size: 1.1rem; opacity: 0.95; margin-top: 4px;">Kelas {{ $classInfo['label'] }}</div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="info-badge">
                            <i class="fas fa-hashtag"></i>
                            {{ $classInfo['class_code'] }}
                        </span>
                        <span class="info-badge">
                            <i class="fas fa-calendar-alt"></i>
                            Semester {{ $classInfo['semester_no'] }}
                        </span>
                        <span class="info-badge">
                            <i class="fas fa-book"></i>
                            {{ $classInfo['credits'] }} SKS
                        </span>
                        <span class="info-badge">
                            <i class="fas fa-circle-check"></i>
                            {{ $classInfo['status'] }}
                        </span>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="d-flex flex-column gap-2 align-items-lg-end">
                        <a href="{{ route('lecturer.course-offerings.attendance', ['offeringId' => $offeringId]) }}" class="hero-action bg-white text-primary">
                            <i class="fas fa-qrcode"></i>Buka Absensi
                        </a>
                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                            <a href="{{ route('lecturer.course-offerings.grades', ['offeringId' => $offeringId]) }}" class="hero-action" style="background: rgba(255,255,255,0.18); color: white;">
                                <i class="fas fa-chart-line"></i>Nilai
                            </a>
                            <a href="{{ route('lecturer.course-materials.index', ['offeringId' => $offeringId]) }}" class="hero-action" style="background: rgba(255,255,255,0.18); color: white;">
                                <i class="fas fa-book"></i>Materi
                            </a>
                            <a href="{{ route('lecturer.course-offerings.students', ['offeringId' => $offeringId]) }}" class="hero-action" style="background: rgba(255,255,255,0.18); color: white;">
                                <i class="fas fa-users"></i>Mahasiswa
                            </a>
                        </div>
                        <a href="{{ route('lecturer.course-offerings.index') }}" class="text-white text-decoration-none mt-2" style="font-weight: 700; opacity: 0.9;">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke daftar kelas
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,0.2);">
                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 8px;">
                    <i class="fas fa-user-tie me-2"></i><strong>Dosen Pengampu:</strong>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($classInfo['lecturers'] as $lecturer)
                        <span style="padding: 8px 14px; border-radius: 10px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); font-size: 0.9rem; font-weight: 500;">
                            {{ $lecturer['name'] }} ({{ $lecturer['role'] }})
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row row-cards mb-4">
        <div class="col-md-3">
            <div class="card modern-card stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Mahasiswa</div>
                        <div class="h2 mb-0 mt-1" style="font-weight: 800; color: #1e293b;">{{ number_format($stats['total_students']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card modern-card stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Sesi Absensi</div>
                        <div class="h2 mb-0 mt-1" style="font-weight: 800; color: #1e293b;">{{ number_format($stats['total_sessions']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card modern-card stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Nilai Masuk</div>
                        <div class="h2 mb-0 mt-1" style="font-weight: 800; color: #1e293b;">{{ number_format($stats['graded_students']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card modern-card stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fas fa-star"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Rata-rata Point</div>
                        <div class="h2 mb-0 mt-1" style="font-weight: 800; color: #1e293b;">{{ $stats['average_grade_point'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $openedSession = collect($attendance)->firstWhere('status', 'Opened');
        $latestSession = collect($attendance)->reverse()->first();
        $recentStudents = collect($students)->take(4);
        $recentGrades = collect($grades)->take(4);
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <a href="{{ route('lecturer.course-offerings.attendance', ['offeringId' => $offeringId]) }}" class="quick-action-card">
                <div class="d-flex align-items-start gap-3">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div>
                        <div class="quick-action-title">Absensi</div>
                        <div class="quick-action-meta">{{ $stats['opened_sessions'] }} sesi sedang dibuka, {{ $stats['total_sessions'] }} total sesi</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('lecturer.course-offerings.students', ['offeringId' => $offeringId]) }}" class="quick-action-card">
                <div class="d-flex align-items-start gap-3">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="quick-action-title">Mahasiswa</div>
                        <div class="quick-action-meta">{{ $stats['total_students'] }} mahasiswa terdaftar di kelas ini</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('lecturer.course-offerings.grades', ['offeringId' => $offeringId]) }}" class="quick-action-card">
                <div class="d-flex align-items-start gap-3">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="quick-action-title">Nilai</div>
                        <div class="quick-action-meta">{{ $stats['graded_students'] }} nilai masuk, {{ $stats['published_students'] }} sudah terbit</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('lecturer.course-materials.index', ['offeringId' => $offeringId]) }}" class="quick-action-card">
                <div class="d-flex align-items-start gap-3">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <div class="quick-action-title">Materi</div>
                        <div class="quick-action-meta">Kelola bahan ajar dan referensi kelas</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="overview-panel">
                <div class="overview-panel-header">
                    <div>
                        <div style="font-weight: 800; color: #111827;">Sesi Absensi</div>
                        <div style="font-size: 0.82rem; color: #64748b;">Akses cepat ke sesi yang paling relevan</div>
                    </div>
                    <a href="{{ route('lecturer.course-offerings.attendance', ['offeringId' => $offeringId]) }}" class="btn btn-primary">
                        <i class="fas fa-qrcode me-2"></i>Buka Absensi
                    </a>
                </div>

                @if($openedSession)
                    <div class="preview-row" style="background: #ecfdf5;">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-green-lt text-green">Sedang Dibuka</span>
                                <strong style="color: #111827;">Pertemuan {{ $openedSession['meeting_no'] }}</strong>
                            </div>
                            <div style="color: #64748b;">{{ $openedSession['topic'] }} · {{ $openedSession['meeting_date'] }} · {{ $openedSession['start_time'] }} - {{ $openedSession['end_time'] }}</div>
                        </div>
                        <span class="soft-chip"><i class="fas fa-check-circle"></i>H {{ $openedSession['present_count'] }}</span>
                    </div>
                @elseif($latestSession)
                    <div class="preview-row">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge {{ $this->statusBadgeClass($latestSession['status']) }}">{{ $latestSession['status'] }}</span>
                                <strong style="color: #111827;">Pertemuan {{ $latestSession['meeting_no'] }}</strong>
                            </div>
                            <div style="color: #64748b;">{{ $latestSession['topic'] }} · {{ $latestSession['meeting_date'] }} · {{ $latestSession['start_time'] }} - {{ $latestSession['end_time'] }}</div>
                        </div>
                        <span class="soft-chip"><i class="fas fa-users"></i>{{ $latestSession['total_records'] }} record</span>
                    </div>
                @else
                    <div class="preview-row">
                        <div style="color: #64748b;">Belum ada sesi absensi untuk kelas ini.</div>
                    </div>
                @endif

                @foreach(collect($attendance)->reverse()->take(3) as $session)
                    <div class="preview-row">
                        <div>
                            <strong style="color: #111827;">Pertemuan {{ $session['meeting_no'] }}</strong>
                            <div style="font-size: 0.82rem; color: #64748b;">{{ $session['meeting_date'] }} · {{ $session['topic'] }}</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="soft-chip">H {{ $session['present_count'] }}</span>
                            <span class="soft-chip">A {{ $session['absent_count'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-lg-5">
            <div class="overview-panel mb-4">
                <div class="overview-panel-header">
                    <div>
                        <div style="font-weight: 800; color: #111827;">Mahasiswa</div>
                        <div style="font-size: 0.82rem; color: #64748b;">Ringkasan peserta kelas</div>
                    </div>
                    <a href="{{ route('lecturer.course-offerings.students', ['offeringId' => $offeringId]) }}" class="btn btn-outline-primary btn-sm">Lihat semua</a>
                </div>
                @forelse($recentStudents as $student)
                    <div class="preview-row">
                        <div>
                            <strong style="color: #111827;">{{ $student['name'] }}</strong>
                            <div style="font-size: 0.82rem; color: #64748b;">{{ $student['nim'] }}</div>
                        </div>
                        <span class="badge {{ $this->statusBadgeClass($student['grade_status']) }}">{{ $student['grade_letter'] }}</span>
                    </div>
                @empty
                    <div class="preview-row"><span style="color: #64748b;">Belum ada mahasiswa terdaftar.</span></div>
                @endforelse
            </div>

            <div class="overview-panel">
                <div class="overview-panel-header">
                    <div>
                        <div style="font-weight: 800; color: #111827;">Nilai</div>
                        <div style="font-size: 0.82rem; color: #64748b;">Status input nilai kelas</div>
                    </div>
                    <a href="{{ route('lecturer.course-offerings.grades', ['offeringId' => $offeringId]) }}" class="btn btn-outline-primary btn-sm">Kelola</a>
                </div>
                @forelse($recentGrades as $grade)
                    <div class="preview-row">
                        <div>
                            <strong style="color: #111827;">{{ $grade['name'] }}</strong>
                            <div style="font-size: 0.82rem; color: #64748b;">Nilai akhir {{ $grade['final_score'] }}</div>
                        </div>
                        <span class="badge {{ $this->statusBadgeClass($grade['grade_status']) }}">{{ $grade['letter_grade'] }}</span>
                    </div>
                @empty
                    <div class="preview-row"><span style="color: #64748b;">Belum ada nilai yang diinput.</span></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
