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
    public bool $isAcademicallyActive = false;
    public bool $hasAccess = false;
    public ?int $studentProfileId = null;
    public ?int $activeAcademicYearId = null;
    public ?string $activeAcademicYearName = null;
    public ?string $registrationStatus = null;
    public ?string $academicStatus = null;
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
        $this->academicStatus = $registration?->academic_status;

        if (! $registration || $registration->registration_status !== 'Approved') {
            return;
        }

        $this->hasApprovedRegistration = true;

        if ($registration->academic_status !== 'Aktif') {
            return;
        }

        $this->isAcademicallyActive = true;

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

        .meta-card {
            padding: 1rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transition: all 0.3s ease;
        }

        .meta-card:hover {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }

        .meta-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .meta-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .form-control-custom {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .status-option {
            padding: 1rem;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .status-option:hover {
            border-color: #667eea;
            background: #f8fafc;
        }

        .status-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        }

        .submit-btn {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .info-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
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
        <div class="alert alert-warning">Registrasi semester belum disetujui, data absensi belum bisa diakses.</div>
    @elseif (! $isAcademicallyActive)
        <div class="alert alert-warning">Status akademik semester ini adalah {{ $academicStatus ?? '-' }}, data absensi belum bisa diakses.</div>
    @elseif (! $hasAccess)
        <div class="alert alert-danger">Sesi absensi tidak ditemukan atau Anda tidak memiliki akses.</div>
    @else
        {{-- Hero Section with Gradient --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; backdrop-filter: blur(10px);">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Form Isi Absensi</div>
                        <h1 class="h2 mb-2" style="font-weight: 700;">{{ $courseName }}</h1>
                        <div style="opacity: 0.9; margin-bottom: 1rem;">
                            <i class="fas fa-calendar me-2"></i>Pertemuan {{ $meetingNo ?? '-' }} • 
                            <i class="fas fa-clock ms-2 me-2"></i>{{ $timeRange }}
                        </div>
                        <a href="{{ route('student.schedule.attendance', ['offeringId' => $offeringId]) }}" class="btn btn-light" style="border-radius: 10px; padding: 0.6rem 1.2rem; font-weight: 600;">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke Detail Sesi
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if (! $canSubmitAttendance)
            <div class="alert alert-warning mb-4" style="border-radius: 12px; padding: 1rem 1.5rem;">
                <i class="fas fa-exclamation-triangle me-2"></i>{{ $attendanceWindowMessage }}
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Form Card --}}
                <div class="modern-card">
                    <div class="card-header" style="padding: 1.5rem; border-bottom: 2px solid #f1f5f9;">
                        <h3 class="mb-0" style="font-weight: 600; color: #1f2937;">
                            <i class="fas fa-clipboard-list me-2" style="color: #667eea;"></i>Form Kehadiran
                        </h3>
                    </div>
                    <div class="card-body" style="padding: 2rem;">
                        {{-- Session Info Cards --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <div class="meta-card">
                                    <div class="meta-label">
                                        <i class="fas fa-book me-2" style="color: #667eea;"></i>Mata Kuliah
                                    </div>
                                    <div class="meta-value">{{ $courseName }}</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="meta-card">
                                    <div class="meta-label">
                                        <i class="fas fa-hashtag me-2" style="color: #10b981;"></i>Pertemuan
                                    </div>
                                    <div class="meta-value">{{ $meetingNo ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="meta-card">
                                    <div class="meta-label">
                                        <i class="fas fa-calendar-day me-2" style="color: #f59e0b;"></i>Tanggal
                                    </div>
                                    <div class="meta-value">{{ $meetingDate }}</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="meta-card">
                                    <div class="meta-label">
                                        <i class="fas fa-clock me-2" style="color: #ef4444;"></i>Jam
                                    </div>
                                    <div class="meta-value">{{ $timeRange }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Attendance Form --}}
                        <form wire:submit="saveRecord">
                            <div class="mb-4">
                                <label class="form-label" style="font-weight: 600; color: #1f2937; margin-bottom: 1rem;">
                                    <i class="fas fa-user-check me-2" style="color: #667eea;"></i>Status Kehadiran
                                </label>
                                <select class="form-select form-control-custom" wire:model="status">
                                    <option value="Present">✓ Hadir</option>
                                    <option value="Late">⏰ Terlambat</option>
                                    <option value="Excused">📝 Izin</option>
                                    <option value="Sick">🤒 Sakit</option>
                                    <option value="Absent">❌ Alpha</option>
                                </select>
                                @error('status')
                                    <div class="text-danger small mt-2">
                                        <i class="fas fa-exclamation-circle me-1"></i>{{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label" style="font-weight: 600; color: #1f2937; margin-bottom: 1rem;">
                                    <i class="fas fa-sticky-note me-2" style="color: #667eea;"></i>Catatan
                                </label>
                                <textarea
                                    class="form-control form-control-custom"
                                    rows="5"
                                    wire:model="notes"
                                    placeholder="Tambahkan catatan absensi jika diperlukan..."
                                ></textarea>
                                @error('notes')
                                    <div class="text-danger small mt-2">
                                        <i class="fas fa-exclamation-circle me-1"></i>{{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="submit-btn" @disabled(! $canSubmitAttendance) 
                                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <i class="fas fa-save"></i> Simpan Absensi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Info Sidebar --}}
                <div class="modern-card">
                    <div class="card-header" style="padding: 1.5rem; border-bottom: 2px solid #f1f5f9;">
                        <h3 class="mb-0" style="font-weight: 600; color: #1f2937;">
                            <i class="fas fa-info-circle me-2" style="color: #667eea;"></i>Informasi Sesi
                        </h3>
                    </div>
                    <div class="card-body" style="padding: 1.5rem;">
                        <div class="mb-4">
                            <div class="meta-label mb-2">
                                <i class="fas fa-calendar-alt me-2" style="color: #667eea;"></i>Tahun Akademik
                            </div>
                            <span class="info-badge" style="background: #dbeafe; color: #1e40af; width: 100%; text-align: center;">
                                {{ $activeAcademicYearName }}
                            </span>
                        </div>

                        <div class="mb-4">
                            <div class="meta-label mb-2">
                                <i class="fas fa-check-circle me-2" style="color: #10b981;"></i>Status Registrasi
                            </div>
                            <span class="info-badge" style="background: #d1fae5; color: #065f46; width: 100%; text-align: center;">
                                {{ $registrationStatus }}
                            </span>
                        </div>

                        <div>
                            <div class="meta-label mb-2">
                                <i class="fas fa-toggle-on me-2" style="color: #f59e0b;"></i>Status Sesi
                            </div>
                            <span class="info-badge" style="background: {{ match($sessionStatus) {
                                'Opened' => '#d1fae5; color: #065f46;',
                                'Closed' => '#fee2e2; color: #991b1b;',
                                default => '#f1f5f9; color: #64748b;',
                            } }}; width: 100%; text-align: center;">
                                {{ $sessionStatus ?? '-' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
