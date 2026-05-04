<?php

use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $sessionId;
    public array $sessionInfo = [];
    public array $students = [];
    public array $statuses = [];

    public function mount(int $sessionId): void
    {
        $this->sessionId = $sessionId;

        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            abort(403);
        }

        $session = AttendanceSession::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear'])
            ->whereKey($this->sessionId)
            ->first();

        if (! $session) {
            abort(404);
        }

        $allowed = CourseOfferingLecturer::query()
            ->where('course_offering_id', $session->course_offering_id)
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->exists();

        if (! $allowed) {
            abort(403);
        }

        $this->sessionInfo = [
            'course_offering_id' => $session->course_offering_id,
            'course' => ($session->courseOffering?->course?->code ?? '-') . ' - ' . ($session->courseOffering?->course?->name ?? '-'),
            'class' => $session->courseOffering?->label ?? '-',
            'academic_year' => $session->courseOffering?->academicYear?->name ?? '-',
            'meeting_no' => $session->meeting_no,
            'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
            'time' => $this->formatTime($session->start_time) . ' - ' . $this->formatTime($session->end_time),
            'topic' => $session->topic ?? '-',
            'status' => $session->status,
        ];

        $existingRecords = AttendanceRecord::query()
            ->where('attendance_session_id', $this->sessionId)
            ->get()
            ->keyBy('student_profile_id');

        $this->students = StudyPlanDetail::query()
            ->with(['studyPlan.studentProfile.user'])
            ->where('course_offering_id', $session->course_offering_id)
            ->get()
            ->map(function (StudyPlanDetail $detail) use ($existingRecords) {
                $studentProfile = $detail->studyPlan?->studentProfile;
                $studentProfileId = $studentProfile?->id;
                $record = $studentProfileId ? $existingRecords->get($studentProfileId) : null;

                if (! $studentProfileId) {
                    return null;
                }

                $this->statuses[$studentProfileId] = $record?->status ?? 'Absent';

                return [
                    'student_profile_id' => $studentProfileId,
                    'nim' => $studentProfile->nim ?? '-',
                    'name' => $studentProfile->user?->name ?? '-',
                    'current_status' => $record?->status ?? '-',
                    'notes' => $record?->notes,
                ];
            })
            ->filter()
            ->unique('student_profile_id')
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function saveAttendance(): void
    {
        $this->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in(['Present', 'Late', 'Excused', 'Sick', 'Absent'])],
        ]);

        DB::transaction(function () {
            foreach ($this->students as $student) {
                $studentProfileId = $student['student_profile_id'];
                $status = $this->statuses[$studentProfileId] ?? 'Absent';

                $record = AttendanceRecord::query()->firstOrNew([
                    'attendance_session_id' => $this->sessionId,
                    'student_profile_id' => $studentProfileId,
                ]);

                if (! $record->exists) {
                    $record->created_by = auth()->id();
                }

                $record->status = $status;
                $record->recorded_at = now();
                $record->recorded_by = auth()->id();
                $record->updated_by = auth()->id();
                $record->save();
            }
        });

        session()->flash('success', 'Absensi mahasiswa berhasil disimpan.');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Edit Attendance',
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
};
?>

@push('styles')
    <style>
        .lecturer-attendance-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .lecturer-attendance-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .lecturer-attendance-meta {
            border-radius: 16px;
            background: #f8fafc;
            padding: 14px 16px;
            height: 100%;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card lecturer-attendance-card mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="lecturer-attendance-label mb-2">Kelola Kehadiran Sesi</div>
                    <h2 class="mb-2">{{ $sessionInfo['course'] }} - {{ $sessionInfo['class'] }}</h2>
                    <div class="text-secondary mb-3">
                        {{ $sessionInfo['academic_year'] }} • Pertemuan {{ $sessionInfo['meeting_no'] }} • {{ $sessionInfo['meeting_date'] }}
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-blue-lt text-blue">{{ $sessionInfo['time'] }}</span>
                        <span class="badge bg-azure-lt text-azure">{{ $sessionInfo['status'] }}</span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('lecturer.course-offerings.attendance', ['offeringId' => $sessionInfo['course_offering_id']]) }}" class="btn btn-outline-secondary">
                        Kembali ke Daftar Sesi
                    </a>
                </div>
            </div>

            <div class="text-secondary small mt-3">Topik: {{ $sessionInfo['topic'] }}</div>
        </div>
    </div>

    <div class="card lecturer-attendance-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Isi Kehadiran Mahasiswa</h3>
            <button type="button" wire:click="saveAttendance" class="btn btn-primary btn-sm">Simpan Absensi</button>
        </div>

        <div class="card-body border-bottom">
            <div class="alert alert-info mb-0">
                Status <strong>Terlambat</strong> sekarang tersedia di form dosen agar selaras dengan domain absensi dan rekap kehadiran.
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Status Saat Ini</th>
                        <th>Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>{{ $student['nim'] }}</td>
                            <td>{{ $student['name'] }}</td>
                            <td>
                                <span class="badge bg-secondary-lt text-secondary">{{ $student['current_status'] }}</span>
                            </td>
                            <td>
                                <select class="form-select" wire:model="statuses.{{ $student['student_profile_id'] }}">
                                    <option value="Present">Hadir</option>
                                    <option value="Late">Terlambat</option>
                                    <option value="Excused">Izin</option>
                                    <option value="Sick">Sakit</option>
                                    <option value="Absent">Alpha</option>
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-4">Tidak ada mahasiswa terdaftar untuk sesi ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('statuses.*')
            <div class="card-footer text-danger small">{{ $message }}</div>
        @enderror
    </div>
</div>
