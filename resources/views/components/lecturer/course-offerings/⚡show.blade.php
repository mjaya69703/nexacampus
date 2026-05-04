<?php

use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public int $offeringId;
    public string $activeTab = 'students';
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

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['students', 'attendance', 'grades'], true)) {
            return;
        }

        $this->activeTab = $tab;
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
        .lecturer-class-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .lecturer-class-hero {
            background:
                radial-gradient(circle at top right, rgba(32, 107, 196, 0.12), transparent 30%),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .lecturer-class-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card lecturer-class-card lecturer-class-hero mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="lecturer-class-label mb-2">Detail Kelas</div>
                    <h2 class="mb-2">{{ $classInfo['course_code'] }} - {{ $classInfo['course_name'] }}</h2>
                    <div class="text-secondary mb-3">
                        Kelas {{ $classInfo['label'] }} • {{ $classInfo['study_program'] }} • {{ $classInfo['academic_year'] }}
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-blue-lt text-blue">{{ $classInfo['class_code'] }}</span>
                        <span class="badge bg-azure-lt text-azure">Semester {{ $classInfo['semester_no'] }}</span>
                        <span class="badge bg-green-lt text-green">{{ $classInfo['credits'] }} SKS</span>
                        <span class="badge {{ $this->statusBadgeClass($classInfo['status']) }}">{{ $classInfo['status'] }}</span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('lecturer.course-offerings.index') }}" class="btn btn-outline-secondary">
                        Kembali ke Daftar Kelas
                    </a>
                </div>
            </div>

            <div class="mt-4">
                <div class="lecturer-class-label mb-2">Dosen Pengampu</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($classInfo['lecturers'] as $lecturer)
                        <span class="badge bg-secondary-lt text-secondary">{{ $lecturer['name'] }} ({{ $lecturer['role'] }})</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-md-3">
            <div class="card lecturer-class-card">
                <div class="card-body">
                    <div class="lecturer-class-label">Mahasiswa</div>
                    <div class="h2 mb-0 mt-2">{{ number_format($stats['total_students']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card lecturer-class-card">
                <div class="card-body">
                    <div class="lecturer-class-label">Sesi Absensi</div>
                    <div class="h2 mb-0 mt-2">{{ number_format($stats['total_sessions']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card lecturer-class-card">
                <div class="card-body">
                    <div class="lecturer-class-label">Nilai Masuk</div>
                    <div class="h2 mb-0 mt-2">{{ number_format($stats['graded_students']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card lecturer-class-card">
                <div class="card-body">
                    <div class="lecturer-class-label">Rata-rata Point Publish</div>
                    <div class="h2 mb-0 mt-2">{{ $stats['average_grade_point'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card lecturer-class-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="btn-list">
                <button type="button" class="btn {{ $activeTab === 'students' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTab('students')">
                    Mahasiswa
                </button>
                <button type="button" class="btn {{ $activeTab === 'attendance' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTab('attendance')">
                    Absensi
                </button>
                <button type="button" class="btn {{ $activeTab === 'grades' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTab('grades')">
                    Nilai
                </button>
            </div>

            <div class="btn-list">
                <a href="{{ route('lecturer.course-offerings.students', ['offeringId' => $offeringId]) }}" class="btn btn-outline-primary btn-sm">Halaman Mahasiswa</a>
                <a href="{{ route('lecturer.course-offerings.attendance', ['offeringId' => $offeringId]) }}" class="btn btn-outline-primary btn-sm">Halaman Absensi</a>
                <a href="{{ route('lecturer.course-offerings.grades', ['offeringId' => $offeringId]) }}" class="btn btn-outline-primary btn-sm">Halaman Nilai</a>
            </div>
        </div>

        @if ($activeTab === 'students')
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Status KRS</th>
                            <th>Nilai Huruf</th>
                            <th>Status Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            <tr>
                                <td>{{ $student['nim'] }}</td>
                                <td>{{ $student['name'] }}</td>
                                <td>{{ $student['study_plan_status'] }}</td>
                                <td>{{ $student['grade_letter'] }}</td>
                                <td><span class="badge {{ $this->statusBadgeClass($student['grade_status']) }}">{{ $student['grade_status'] }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">Belum ada data mahasiswa pada kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($activeTab === 'attendance')
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Pertemuan</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Dosen</th>
                            <th>Topik</th>
                            <th>Status</th>
                            <th>Rekap</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendance as $session)
                            <tr>
                                <td>#{{ $session['meeting_no'] }}</td>
                                <td>{{ $session['meeting_date'] }}</td>
                                <td>{{ $session['start_time'] }} - {{ $session['end_time'] }}</td>
                                <td>{{ $session['lecturer_name'] }}</td>
                                <td>{{ $session['topic'] }}</td>
                                <td><span class="badge {{ $this->statusBadgeClass($session['status']) }}">{{ $session['status'] }}</span></td>
                                <td>
                                    <span class="text-success">H: {{ $session['present_count'] }}</span>
                                    <span class="text-danger ms-2">A: {{ $session['absent_count'] }}</span>
                                    <span class="text-secondary ms-2">T: {{ $session['total_records'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">Belum ada sesi absensi untuk kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Nilai Akhir</th>
                            <th>Huruf</th>
                            <th>Point</th>
                            <th>Hasil</th>
                            <th>Status</th>
                            <th>Dinilai Pada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grades as $grade)
                            <tr>
                                <td>{{ $grade['nim'] }}</td>
                                <td>{{ $grade['name'] }}</td>
                                <td>{{ $grade['final_score'] }}</td>
                                <td>{{ $grade['letter_grade'] }}</td>
                                <td>{{ $grade['grade_point'] }}</td>
                                <td>{{ $grade['result_status'] }}</td>
                                <td><span class="badge {{ $this->statusBadgeClass($grade['grade_status']) }}">{{ $grade['grade_status'] }}</span></td>
                                <td>{{ $grade['graded_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">Belum ada data nilai untuk kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
