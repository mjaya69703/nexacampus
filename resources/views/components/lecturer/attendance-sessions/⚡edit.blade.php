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

        .student-row {
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            background: #f8fafc;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .student-row:hover {
            background: white;
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateX(4px);
        }

        .status-select {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 10px 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .status-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .avatar-circle {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .current-status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
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
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Kelola Kehadiran Sesi</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">{{ $sessionInfo['course'] }}</h2>
                            <div style="font-size: 1.1rem; opacity: 0.95; margin-top: 4px;">Kelas {{ $sessionInfo['class'] }}</div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="info-badge">
                            <i class="fas fa-calendar-alt"></i>
                            {{ $sessionInfo['academic_year'] }}
                        </span>
                        <span class="info-badge">
                            <i class="fas fa-hashtag"></i>
                            Pertemuan {{ $sessionInfo['meeting_no'] }}
                        </span>
                        <span class="info-badge">
                            <i class="fas fa-clock"></i>
                            {{ $sessionInfo['time'] }}
                        </span>
                        <span class="info-badge">
                            <i class="fas fa-circle-check"></i>
                            {{ $sessionInfo['status'] }}
                        </span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('lecturer.course-offerings.attendance', ['offeringId' => $sessionInfo['course_offering_id']]) }}" class="btn btn-light btn-lg" style="border-radius: 12px; font-weight: 600;">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Sesi
                    </a>
                </div>
            </div>

            <div class="mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,0.2);">
                <div class="d-flex align-items-center gap-2" style="opacity: 0.95;">
                    <i class="fas fa-bookmark"></i>
                    <strong>Topik:</strong> {{ $sessionInfo['topic'] }}
                </div>
                <div class="d-flex align-items-center gap-2 mt-2" style="opacity: 0.95;">
                    <i class="fas fa-calendar-day"></i>
                    <strong>Tanggal:</strong> {{ $sessionInfo['meeting_date'] }}
                </div>
            </div>
        </div>
    </div>

    {{-- Attendance Form Card --}}
    <div class="card modern-card">
        <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-users" style="font-size: 1.3rem; color: #667eea;"></i>
                <h3 class="card-title mb-0" style="font-weight: 700;">Isi Kehadiran Mahasiswa</h3>
            </div>
            <button type="button" wire:click="saveAttendance" class="btn btn-primary btn-lg" style="border-radius: 12px; font-weight: 600; padding: 10px 24px;">
                <i class="fas fa-save me-2"></i>Simpan Absensi
            </button>
        </div>

        <div class="card-body border-bottom">
            <div class="alert alert-info mb-0" style="border-radius: 12px; border-left: 4px solid #3b82f6;">
                <i class="fas fa-info-circle me-2"></i>
                Status <strong>Terlambat</strong> sekarang tersedia di form dosen agar selaras dengan domain absensi dan rekap kehadiran.
            </div>
        </div>

        <div class="card-body p-4">
            @forelse ($students as $student)
                <div class="student-row">
                    <div class="row align-items-center g-3">
                        <div class="col-md-1">
                            <div class="avatar-circle">
                                {{ strtoupper(substr($student['name'], 0, 1)) }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div style="font-weight: 600; font-size: 1rem; color: #1e293b;">{{ $student['name'] }}</div>
                            <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">
                                <i class="fas fa-id-card me-1"></i>{{ $student['nim'] }}
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">Status Saat Ini</div>
                            <span class="current-status-badge" style="background: #e2e8f0; color: #475569;">
                                {{ $student['current_status'] }}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">Pilih Kehadiran</div>
                            <select class="form-select status-select" wire:model="statuses.{{ $student['student_profile_id'] }}">
                                <option value="Present"><i class="fas fa-check-circle"></i> Hadir</option>
                                <option value="Late"><i class="fas fa-clock"></i> Terlambat</option>
                                <option value="Excused"><i class="fas fa-file-alt"></i> Izin</option>
                                <option value="Sick"><i class="fas fa-procedures"></i> Sakit</option>
                                <option value="Absent"><i class="fas fa-times-circle"></i> Alpha</option>
                            </select>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="fas fa-user-slash" style="font-size: 4rem; color: #cbd5e1;"></i>
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">Tidak ada mahasiswa terdaftar untuk sesi ini.</div>
                </div>
            @endforelse
        </div>

        @error('statuses.*')
            <div class="card-footer text-danger small" style="background: #fef2f2; border-top: 2px solid #fecaca;">
                <i class="fas fa-exclamation-triangle me-2"></i>{{ $message }}
            </div>
        @enderror
    </div>
</div>
