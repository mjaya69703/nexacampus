<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlanDetail;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public bool $hasApprovedRegistration = false;
    public bool $hasAccess = false;
    public ?int $studentProfileId = null;
    public ?int $activeAcademicYearId = null;
    public ?string $activeAcademicYearName = null;
    public ?string $registrationStatus = null;
    public ?int $sessionId = null;
    public ?int $offeringId = null;
    public ?string $courseName = null;
    public ?int $meetingNo = null;
    public ?string $meetingDate = null;
    public ?string $timeRange = null;
    public ?string $sessionStatus = null;
    public bool $canSubmitAttendance = false;
    public ?string $attendanceWindowMessage = null;
    public string $status = 'Present';
    public ?string $notes = null;

    public function mount(int $sessionId): void
    {
        $this->sessionId = $sessionId;

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

        $session = AttendanceSession::query()
            ->with('courseOffering.course')
            ->whereKey($sessionId)
            ->first();

        if (! $session) {
            return;
        }

        $this->offeringId = $session->course_offering_id;

        $hasStudyPlanAccess = StudyPlanDetail::query()
            ->where('course_offering_id', $session->course_offering_id)
            ->whereHas('studyPlan', function ($query) {
                $query->where('student_profile_id', $this->studentProfileId)
                    ->where('academic_year_id', $this->activeAcademicYearId)
                    ->where('status', 'Approved');
            })
            ->exists();

        if (! $hasStudyPlanAccess) {
            return;
        }

        $this->hasAccess = true;
        $this->courseName = $session->courseOffering?->course?->name ?? $session->courseOffering?->label ?? '-';
        $this->meetingNo = $session->meeting_no;
        $this->meetingDate = $session->meeting_date?->format('d M Y') ?? '-';
        $this->timeRange = $this->formatTime($session->start_time) . ' - ' . $this->formatTime($session->end_time);
        $this->sessionStatus = $session->status;
        $this->canSubmitAttendance = $this->isSessionWindowOpen($session);
        $this->attendanceWindowMessage = $this->canSubmitAttendance
            ? null
            : 'Absensi hanya bisa diisi saat status sesi Opened dan waktu saat ini berada dalam rentang jadwal sesi.';

        $existingRecord = AttendanceRecord::query()
            ->where('attendance_session_id', $this->sessionId)
            ->where('student_profile_id', $this->studentProfileId)
            ->first();

        if ($existingRecord) {
            $this->status = $existingRecord->status;
            $this->notes = $existingRecord->notes;
        }
    }

    public function saveRecord(): void
    {
        if (! $this->hasAccess || ! $this->studentProfileId || ! $this->sessionId) {
            session()->flash('error', 'Akses tidak valid untuk mengisi absensi.');

            return;
        }

        $session = AttendanceSession::query()
            ->whereKey($this->sessionId)
            ->where('course_offering_id', $this->offeringId)
            ->first();

        if (! $session) {
            session()->flash('error', 'Sesi absensi tidak ditemukan.');

            return;
        }

        if (! $this->isSessionWindowOpen($session)) {
            session()->flash('error', 'Absensi tidak dapat diisi. Sesi harus Opened dan berada dalam rentang waktu jadwal.');
            $this->canSubmitAttendance = false;
            $this->attendanceWindowMessage = 'Absensi hanya bisa diisi saat status sesi Opened dan waktu saat ini berada dalam rentang jadwal sesi.';

            return;
        }

        $this->canSubmitAttendance = true;
        $this->attendanceWindowMessage = null;

        $this->validate([
            'status' => ['required', Rule::in(['Present', 'Absent', 'Excused', 'Sick', 'Late'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = AttendanceRecord::query()->firstOrNew([
            'attendance_session_id' => $this->sessionId,
            'student_profile_id' => $this->studentProfileId,
        ]);

        if (! $record->exists) {
            $record->created_by = auth()->id();
        }

        $record->status = $this->status;
        $record->notes = $this->notes;
        $record->recorded_at = now();
        $record->recorded_by = auth()->id();
        $record->updated_by = auth()->id();
        $record->save();

        session()->flash('success', 'Absensi berhasil disimpan.');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Isi Absensi',
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
        .student-attendance-record-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .student-attendance-record-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .student-attendance-record-meta {
            border-radius: 16px;
            background: #f8fafc;
            padding: 14px 16px;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil mahasiswa belum tersedia.</div>
    @elseif (! $hasApprovedRegistration)
        <div class="alert alert-warning">Registrasi semester belum disetujui, data absensi belum bisa diakses.</div>
    @elseif (! $hasAccess)
        <div class="alert alert-danger">Sesi absensi tidak ditemukan atau Anda tidak memiliki akses.</div>
    @else
        @if (! $canSubmitAttendance)
            <div class="alert alert-warning">{{ $attendanceWindowMessage }}</div>
        @endif

        <div class="row row-cards">
            <div class="col-lg-8">
                <div class="card student-attendance-record-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Form Isi Absensi</h3>
                        <a href="{{ route('student.schedule.attendance', ['offeringId' => $offeringId]) }}" class="btn btn-outline-secondary">
                            Kembali ke Detail Sesi
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <div class="student-attendance-record-meta">
                                    <div class="student-attendance-record-label">Mata Kuliah</div>
                                    <div class="fw-semibold mt-2">{{ $courseName }}</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="student-attendance-record-meta">
                                    <div class="student-attendance-record-label">Pertemuan</div>
                                    <div class="fw-semibold mt-2">{{ $meetingNo ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="student-attendance-record-meta">
                                    <div class="student-attendance-record-label">Tanggal</div>
                                    <div class="fw-semibold mt-2">{{ $meetingDate }}</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="student-attendance-record-meta">
                                    <div class="student-attendance-record-label">Jam</div>
                                    <div class="fw-semibold mt-2">{{ $timeRange }}</div>
                                </div>
                            </div>
                        </div>

                        <form wire:submit="saveRecord">
                            <div class="mb-3">
                                <label class="form-label">Status Kehadiran</label>
                                <select class="form-select" wire:model="status">
                                    <option value="Present">Hadir</option>
                                    <option value="Late">Terlambat</option>
                                    <option value="Excused">Izin</option>
                                    <option value="Sick">Sakit</option>
                                    <option value="Absent">Alpha</option>
                                </select>
                                @error('status')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Catatan</label>
                                <textarea
                                    class="form-control"
                                    rows="4"
                                    wire:model="notes"
                                    placeholder="Tambahkan catatan absensi jika diperlukan"
                                ></textarea>
                                @error('notes')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary" @disabled(! $canSubmitAttendance)>
                                    Simpan Absensi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card student-attendance-record-card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Informasi Sesi</h3>
                    </div>
                    <div class="card-body">
                        <div class="student-attendance-record-label">Tahun Akademik</div>
                        <div class="mb-3 mt-2">
                            <span class="badge bg-azure-lt text-azure">{{ $activeAcademicYearName }}</span>
                        </div>

                        <div class="student-attendance-record-label">Status Registrasi</div>
                        <div class="mb-3 mt-2">
                            <span class="badge bg-green-lt text-green">{{ $registrationStatus }}</span>
                        </div>

                        <div class="student-attendance-record-label">Status Sesi</div>
                        <div class="mt-2">
                            <span class="badge bg-azure-lt text-azure">{{ $sessionStatus ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
