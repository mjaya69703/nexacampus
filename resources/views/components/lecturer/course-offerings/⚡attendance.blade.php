<?php

use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use Livewire\Component;

new class extends Component
{
    public int $offeringId;
    public array $classInfo = [];
    public array $sessions = [];

    public function mount(int $offeringId): void
    {
        $this->offeringId = $offeringId;

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
            ->with(['courseOffering.course', 'courseOffering.academicYear'])
            ->first();

        if (! $assignment || ! $assignment->courseOffering) {
            abort(404);
        }

        $offering = $assignment->courseOffering;

        $this->classInfo = [
            'course' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-'),
            'class' => $offering->label ?? '-',
            'class_code' => $offering->code ?? '-',
            'academic_year' => $offering->academicYear?->name ?? '-',
        ];

        $this->sessions = AttendanceSession::query()
            ->with(['lecturerProfile.user', 'records'])
            ->where('course_offering_id', $this->offeringId)
            ->orderBy('meeting_no')
            ->orderBy('meeting_date')
            ->get()
            ->map(function (AttendanceSession $session) {
                $presentCount = $session->records->whereIn('status', ['Present', 'Late', 'Excused', 'Sick'])->count();
                $absentCount = $session->records->where('status', 'Absent')->count();

                return [
                    'id' => $session->id,
                    'meeting_no' => $session->meeting_no,
                    'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                    'start_time' => $this->formatTime($session->start_time),
                    'end_time' => $this->formatTime($session->end_time),
                    'topic' => $session->topic ?? '-',
                    'lecturer' => $session->lecturerProfile?->user?->name ?? '-',
                    'status' => $session->status,
                    'total_records' => $session->records->count(),
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                ];
            })
            ->values()
            ->all();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Attendance Sessions',
        ]);
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Opened' => 'bg-blue-lt text-blue',
            'Closed' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
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
};
?>

@push('styles')
    <style>
        .lecturer-attendance-page-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card lecturer-attendance-page-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-1">Sesi Absensi Kelas</h3>
                <div class="text-secondary small">{{ $classInfo['course'] }}</div>
            </div>
            <a href="{{ route('lecturer.course-offerings.show', ['id' => $offeringId]) }}" class="btn btn-outline-secondary btn-sm">
                Kembali ke Detail Kelas
            </a>
        </div>
        <div class="card-body">
            <div class="text-secondary small">Kelas {{ $classInfo['class'] }} ({{ $classInfo['class_code'] }}) • {{ $classInfo['academic_year'] }}</div>
        </div>
    </div>

    <div class="card lecturer-attendance-page-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Daftar Sesi</h3>
            <span class="text-secondary small">Total {{ number_format(count($sessions)) }} sesi</span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Pertemuan</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Topik</th>
                        <th>Dosen</th>
                        <th>Status</th>
                        <th>Rekap</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>#{{ $session['meeting_no'] }}</td>
                            <td>{{ $session['meeting_date'] }}</td>
                            <td>{{ $session['start_time'] }} - {{ $session['end_time'] }}</td>
                            <td>{{ $session['topic'] }}</td>
                            <td>{{ $session['lecturer'] }}</td>
                            <td><span class="badge {{ $this->statusBadgeClass($session['status']) }}">{{ $session['status'] }}</span></td>
                            <td>
                                <span class="text-success">H: {{ $session['present_count'] }}</span>
                                <span class="text-danger ms-2">A: {{ $session['absent_count'] }}</span>
                                <span class="text-secondary ms-2">T: {{ $session['total_records'] }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('lecturer.attendance-sessions.edit', ['sessionId' => $session['id']]) }}" class="btn btn-primary btn-sm">
                                    Kelola Absensi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">Belum ada sesi absensi pada kelas ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
