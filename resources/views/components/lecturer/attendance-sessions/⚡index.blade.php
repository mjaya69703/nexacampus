<?php

use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use Livewire\Component;

new class extends Component
{
    public array $sessions = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            return;
        }

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->pluck('course_offering_id');

        if ($offeringIds->isEmpty()) {
            return;
        }

        $this->sessions = AttendanceSession::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'records'])
            ->whereIn('course_offering_id', $offeringIds)
            ->orderByDesc('meeting_date')
            ->orderByDesc('meeting_no')
            ->get()
            ->map(function (AttendanceSession $session) {
                return [
                    'id' => $session->id,
                    'course' => ($session->courseOffering?->course?->code ?? '-') . ' - ' . ($session->courseOffering?->course?->name ?? '-'),
                    'class' => $session->courseOffering?->label ?? '-',
                    'academic_year' => $session->courseOffering?->academicYear?->name ?? '-',
                    'meeting_no' => $session->meeting_no,
                    'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                    'time' => $this->formatTime($session->start_time) . ' - ' . $this->formatTime($session->end_time),
                    'status' => $session->status,
                    'records_count' => $session->records->count(),
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
        .lecturer-attendance-list-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card lecturer-attendance-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-1">Daftar Sesi Absensi</h3>
                <div class="text-secondary small">Semua sesi yang terkait dengan kelas yang Anda ampu.</div>
            </div>
            <span class="text-secondary small">Total {{ number_format(count($sessions)) }} sesi</span>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Mata Kuliah</th>
                        <th>Kelas</th>
                        <th>Pertemuan</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Status</th>
                        <th>Rekaman</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $session['course'] }}</div>
                                <div class="text-secondary small">{{ $session['academic_year'] }}</div>
                            </td>
                            <td>{{ $session['class'] }}</td>
                            <td>#{{ $session['meeting_no'] }}</td>
                            <td>{{ $session['meeting_date'] }}</td>
                            <td>{{ $session['time'] }}</td>
                            <td><span class="badge {{ $this->statusBadgeClass($session['status']) }}">{{ $session['status'] }}</span></td>
                            <td>{{ $session['records_count'] }}</td>
                            <td class="text-end">
                                <a href="{{ route('lecturer.attendance-sessions.edit', ['sessionId' => $session['id']]) }}" class="btn btn-primary btn-sm">
                                    Kelola Absensi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">Belum ada sesi absensi untuk dosen ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
