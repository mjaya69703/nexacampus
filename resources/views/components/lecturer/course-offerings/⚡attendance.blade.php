<?php

use App\Models\Academic\AttendanceSession;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use App\Support\Academic\AcademicAttendanceQrService;
use Livewire\Component;

new class extends Component
{
    public int $offeringId;
    public array $classInfo = [];
    public array $sessions = [];
    
    // Edit modal properties
    public ?int $editingSessionId = null;
    public string $editTopic = '';
    public string $editStatus = '';
    public bool $showEditModal = false;
    public bool $showQrModal = false;
    public ?int $qrSessionId = null;
    public ?array $qrPayload = null;
    public ?array $qrSessionInfo = null;

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
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'courseOffering.courseSchedules.room.building',
            ])
            ->first();

        if (! $assignment || ! $assignment->courseOffering) {
            abort(404);
        }

        $offering = $assignment->courseOffering;
        $activeSchedule = $offering->courseSchedules->where('is_active', true)->first();

        $this->classInfo = [
            'course' => ($offering->course?->code ?? '-') . ' - ' . ($offering->course?->name ?? '-'),
            'class' => $offering->label ?? '-',
            'class_code' => $offering->code ?? '-',
            'academic_year' => $offering->academicYear?->name ?? '-',
            'room' => $activeSchedule?->room?->name ?? '-',
            'building' => $activeSchedule?->room?->building?->name ?? '-',
            'delivery_mode' => $activeSchedule?->delivery_mode ?? '-',
            'total_students' => $this->enrolledStudentCount(),
        ];

        $this->refreshSessions();
    }

    public function refreshSessions(): void
    {
        $totalStudents = (int) ($this->classInfo['total_students'] ?? $this->enrolledStudentCount());

        $this->sessions = AttendanceSession::query()
            ->with(['lecturerProfile.user', 'records'])
            ->where('course_offering_id', $this->offeringId)
            ->orderBy('meeting_no')
            ->orderBy('meeting_date')
            ->get()
            ->map(function (AttendanceSession $session) use ($totalStudents) {
                $presentCount = $session->records->whereIn('status', ['Present', 'Late'])->count();
                $excusedCount = $session->records->where('status', 'Excused')->count();
                $sickCount = $session->records->where('status', 'Sick')->count();
                $absentCount = $session->records->where('status', 'Absent')->count();
                $recordedCount = $session->records->count();
                $missingCount = max(0, $totalStudents - $recordedCount);
                $validCount = $presentCount + $excusedCount + $sickCount;
                $percent = fn (int $count): int => $totalStudents > 0 ? (int) round(($count / $totalStudents) * 100) : 0;

                return [
                    'id' => $session->id,
                    'meeting_no' => $session->meeting_no,
                    'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                    'start_time' => $this->formatTime($session->start_time),
                    'end_time' => $this->formatTime($session->end_time),
                    'topic' => $session->topic ?? '-',
                    'lecturer' => $session->lecturerProfile?->user?->name ?? '-',
                    'status' => $session->status,
                    'total_students' => $totalStudents,
                    'total_records' => $recordedCount,
                    'present_count' => $presentCount,
                    'excused_count' => $excusedCount,
                    'sick_count' => $sickCount,
                    'absent_count' => $absentCount,
                    'missing_count' => $missingCount,
                    'valid_count' => $validCount,
                    'valid_percent' => $percent($validCount),
                    'summary_rows' => [
                        ['label' => 'H', 'name' => 'Hadir', 'count' => $presentCount, 'percent' => $percent($presentCount), 'icon' => 'fa-check-circle', 'style' => 'background: #d1fae5; color: #065f46;'],
                        ['label' => 'I', 'name' => 'Izin', 'count' => $excusedCount, 'percent' => $percent($excusedCount), 'icon' => 'fa-file-alt', 'style' => 'background: #fef3c7; color: #92400e;'],
                        ['label' => 'S', 'name' => 'Sakit', 'count' => $sickCount, 'percent' => $percent($sickCount), 'icon' => 'fa-notes-medical', 'style' => 'background: #ede9fe; color: #5b21b6;'],
                        ['label' => 'A', 'name' => 'Alpha', 'count' => $absentCount, 'percent' => $percent($absentCount), 'icon' => 'fa-times-circle', 'style' => 'background: #fee2e2; color: #991b1b;'],
                        ['label' => 'B', 'name' => 'Belum', 'count' => $missingCount, 'percent' => $percent($missingCount), 'icon' => 'fa-hourglass-half', 'style' => 'background: #e2e8f0; color: #475569;'],
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function enrolledStudentCount(): int
    {
        return StudyPlanDetail::query()
            ->where('course_offering_id', $this->offeringId)
            ->whereHas('studyPlan', function ($query) {
                $query->where('status', 'Approved');
            })
            ->with('studyPlan.studentProfile')
            ->get()
            ->pluck('studyPlan.studentProfile.id')
            ->filter()
            ->unique()
            ->count();
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

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'Draft' => 'Dijadwalkan',
            'Opened' => 'Berjalan',
            'Closed' => 'Ditutup',
            'Cancelled' => 'Dibatalkan',
            default => $status ?: '-',
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
    
    public function openEditModal(int $sessionId): void
    {
        $session = AttendanceSession::find($sessionId);
        
        if (!$session) {
            session()->flash('error', 'Sesi tidak ditemukan.');
            return;
        }
        
        $this->editingSessionId = $sessionId;
        $this->editTopic = $session->topic ?? '';
        $this->editStatus = $session->status;
        $this->showEditModal = true;
    }
    
    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingSessionId = null;
        $this->editTopic = '';
        $this->editStatus = '';
    }
    
    public function updateSession(): void
    {
        if (!$this->editingSessionId) {
            return;
        }
        
        $session = AttendanceSession::find($this->editingSessionId);
        
        if (!$session) {
            session()->flash('error', 'Sesi tidak ditemukan.');
            $this->closeEditModal();
            return;
        }
        
        $session->update([
            'topic' => $this->editTopic,
            'status' => $this->editStatus,
        ]);
        
        session()->flash('success', 'Sesi berhasil diperbarui!');
        $this->closeEditModal();
        $this->mount($this->offeringId); // Refresh data
    }

    public function openQrAttendance(int $sessionId): void
    {
        $session = $this->findLecturerSession($sessionId);

        app(AcademicAttendanceQrService::class)->openSession($session, auth()->id());

        $this->qrSessionId = $sessionId;
        $this->showQrModal = true;
        $this->mount($this->offeringId);
        $this->refreshQr();

        session()->flash('success', 'Absensi QR dibuka. Mahasiswa sudah bisa scan.');
    }

    public function refreshQr(): void
    {
        if (! $this->qrSessionId || ! $this->showQrModal) {
            return;
        }

        $session = $this->findLecturerSession($this->qrSessionId);

        $this->qrSessionInfo = [
            'meeting_no' => $session->meeting_no,
            'topic' => $session->topic ?: '-',
            'status' => $session->status,
            'opened_at' => $session->opened_at?->format('H:i:s') ?? '-',
        ];

        $this->qrPayload = $session->status === 'Opened'
            ? app(AcademicAttendanceQrService::class)->qrPayload($session)
            : null;
    }

    public function closeQrAttendance(): void
    {
        if (! $this->qrSessionId) {
            return;
        }

        $session = $this->findLecturerSession($this->qrSessionId);
        app(AcademicAttendanceQrService::class)->closeSession($session, auth()->id());

        $this->showQrModal = false;
        $this->qrSessionId = null;
        $this->qrPayload = null;
        $this->qrSessionInfo = null;
        $this->mount($this->offeringId);

        session()->flash('success', 'Absensi QR ditutup. Mahasiswa tidak bisa scan lagi.');
    }

    public function closeQrModalOnly(): void
    {
        $this->showQrModal = false;
        $this->qrSessionId = null;
        $this->qrPayload = null;
        $this->qrSessionInfo = null;
    }

    private function findLecturerSession(int $sessionId): AttendanceSession
    {
        $user = auth()->user();
        $lecturerProfile = $user?->lecturerProfile()->first();

        if (! $lecturerProfile) {
            abort(403);
        }

        $session = AttendanceSession::query()
            ->whereKey($sessionId)
            ->where('course_offering_id', $this->offeringId)
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

        return $session;
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

        .session-card {
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            background: #f8fafc;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .session-card:hover {
            background: white;
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .meeting-number {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.4rem;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-badge-opened {
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            animation: pulse-status 2s infinite;
            transition: all 0.3s ease;
        }
        
        .status-badge-opened:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .status-badge-closed {
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .status-badge-closed:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        @keyframes pulse-status {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        }

        .rekap-stat {
            padding: 6px 9px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.78rem;
            min-width: 58px;
        }

        .attendance-summary-panel {
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
        }

        .attendance-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 6px;
        }

        .attendance-progress {
            height: 7px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin-top: 8px;
        }

        .attendance-progress > div {
            height: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .btn-outline-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content-custom {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 90%;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Sesi Absensi Kelas</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">{{ $classInfo['course'] }}</h2>
                            <div style="font-size: 1.1rem; opacity: 0.95; margin-top: 4px;">Kelas {{ $classInfo['class'] }}</div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="info-badge" style="color: #1e293b;">
                            <i class="fas fa-hashtag"></i>
                            {{ $classInfo['class_code'] }}
                        </span>
                        <span class="info-badge" style="color: #1e293b;">
                            <i class="fas fa-calendar-alt"></i>
                            {{ $classInfo['academic_year'] }}
                        </span>
                        <span class="info-badge" style="color: #1e293b;">
                            <i class="fas fa-building"></i>
                            {{ $classInfo['building'] }} / {{ $classInfo['room'] }}
                        </span>
                        <span class="info-badge" style="color: #1e293b;">
                            <i class="fas fa-wifi"></i>
                            {{ $classInfo['delivery_mode'] }}
                        </span>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('lecturer.course-offerings.show', ['id' => $offeringId]) }}" class="btn btn-light btn-lg" style="border-radius: 12px; font-weight: 600;">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Detail Kelas
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Sessions List --}}
    <div class="card modern-card" wire:poll.2s="refreshSessions">
        <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-list-check" style="font-size: 1.3rem; color: #667eea;"></i>
                <h3 class="card-title mb-0" style="font-weight: 700;">Daftar Sesi</h3>
            </div>
            <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem;">
                <i class="fas fa-layer-group me-1"></i>Total {{ number_format(count($sessions)) }} sesi
            </span>
        </div>

        <div class="card-body p-4">
            @forelse ($sessions as $session)
                <div class="session-card">
                    <div class="row align-items-center g-3">
                        <div class="col-md-1">
                            <div class="meeting-number">#{{ $session['meeting_no'] }}</div>
                        </div>
                        <div class="col-md-4">
                            <div style="font-weight: 700; font-size: 1.05rem; color: #1e293b; margin-bottom: 6px;">
                                <i class="fas fa-bookmark me-2" style="color: #667eea;"></i>{{ $session['topic'] }}
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="info-badge" style="color: #64748b;">
                                    <i class="fas fa-calendar-day"></i>
                                    {{ $session['meeting_date'] }}
                                </span>
                                <span class="info-badge" style="color: #64748b;">
                                    <i class="fas fa-clock"></i>
                                    {{ $session['start_time'] }} - {{ $session['end_time'] }}
                                </span>
                            </div>
                            <div style="margin-top: 6px; font-size: 0.85rem; color: #64748b;">
                                <i class="fas fa-user-tie me-1"></i>{{ $session['lecturer'] }}
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 6px;">Status</div>
                            @if($session['status'] === 'Opened')
                                <span class="status-badge-opened">
                                            <i class="fas fa-circle-check me-1"></i>{{ $this->statusLabel($session['status']) }}
                                </span>
                            @elseif($session['status'] === 'Closed')
                                <span class="status-badge-closed">
                                            <i class="fas fa-circle-xmark me-1"></i>{{ $this->statusLabel($session['status']) }}
                                </span>
                            @else
                                <span style="padding: 6px 14px; border-radius: 8px; background: #e2e8f0; color: #475569; font-weight: 600; font-size: 0.85rem;">
                                            {{ $this->statusLabel($session['status']) }}
                                </span>
                            @endif
                        </div>
                        <div class="col-md-3">
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 6px;">Rekap Kehadiran</div>
                            <div class="attendance-summary-panel">
                                <div class="attendance-summary-grid">
                                    @foreach($session['summary_rows'] as $row)
                                        <div class="rekap-stat text-center" style="{{ $row['style'] }}" title="{{ $row['name'] }} {{ $row['percent'] }}%">
                                            <div><i class="fas {{ $row['icon'] }} me-1"></i>{{ $row['label'] }}</div>
                                            <div style="font-weight: 800;">{{ $row['count'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="attendance-progress">
                                    <div style="width: {{ $session['valid_percent'] }}%;"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.72rem; color: #64748b;">
                                    <span>H+I+S {{ $session['valid_count'] }}/{{ $session['total_students'] }}</span>
                                    <strong style="color: #334155;">{{ $session['valid_percent'] }}%</strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                @if($session['status'] === 'Opened')
                                    <button
                                        type="button"
                                        class="btn btn-success btn-lg"
                                        wire:click="openQrAttendance({{ $session['id'] }})"
                                        title="Tampilkan QR absensi"
                                        style="border-radius: 12px; font-weight: 600; padding: 10px 16px;"
                                    >
                                        <i class="fas fa-qrcode"></i>
                                    </button>
                                @elseif($session['status'] !== 'Closed')
                                    <button
                                        type="button"
                                        class="btn btn-success btn-lg"
                                        wire:click="openQrAttendance({{ $session['id'] }})"
                                        title="Buka absensi QR"
                                        style="border-radius: 12px; font-weight: 600; padding: 10px 16px;"
                                    >
                                        <i class="fas fa-play"></i>
                                    </button>
                                @endif
                                <button 
                                    type="button" 
                                    class="btn btn-outline-primary btn-lg"
                                    wire:click="openEditModal({{ $session['id'] }})"
                                    title="Edit Topic & Status"
                                    style="border-radius: 12px; font-weight: 600; padding: 10px 16px;"
                                >
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="{{ route('lecturer.attendance-sessions.edit', ['sessionId' => $session['id']]) }}" class="btn btn-primary btn-lg" style="border-radius: 12px; font-weight: 600; padding: 10px 20px;">
                                    <i class="fas fa-edit me-2"></i>Kelola Absensi
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times" style="font-size: 4rem; color: #cbd5e1;"></i>
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 500;">Belum ada sesi absensi pada kelas ini.</div>
                </div>
            @endforelse
        </div>
    </div>
    
    {{-- Edit Modal --}}
    @if($showEditModal)
        <div class="modal-backdrop" wire:click.self="closeEditModal">
            <div class="modal-content-custom" onclick="event.stopPropagation()">
                <div class="p-4 p-lg-5">
                    {{-- Modal Header --}}
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem;">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div>
                                <h4 class="mb-0" style="font-weight: 700;">Edit Sesi</h4>
                                <div style="font-size: 0.85rem; color: #64748b;">Perbarui topic dan status</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
                    </div>
                    
                    {{-- Modal Body --}}
                    <div class="mb-4">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">
                            <i class="fas fa-bookmark me-2" style="color: #667eea;"></i>Topic / Materi
                        </label>
                        <input 
                            type="text" 
                            class="form-control form-control-lg"
                            wire:model="editTopic"
                            placeholder="Masukkan topic atau materi pertemuan..."
                            style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                        >
                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 6px;">
                            <i class="fas fa-info-circle me-1"></i>Contoh: "Pengenalan Laravel Livewire"
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" style="font-weight: 600; color: #1e293b;">
                            <i class="fas fa-toggle-on me-2" style="color: #667eea;"></i>Status Sesi
                        </label>
                            <select class="form-select form-select-lg" wire:model="editStatus" style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;">
                                <option value="Draft">Dijadwalkan</option>
                                <option value="Opened">Berjalan - mahasiswa bisa absen</option>
                                <option value="Closed">Ditutup - absensi selesai</option>
                            </select>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 6px;">
                                <i class="fas fa-lightbulb me-1"></i>Sesi berjalan hanya aktif saat dosen membuka QR. Setelah ditutup, mahasiswa tidak bisa scan lagi.
                            </div>
                    </div>
                    
                    {{-- Modal Footer --}}
                    <div class="d-flex gap-2">
                        <button 
                            type="button" 
                            class="btn btn-outline-secondary btn-lg flex-fill"
                            wire:click="closeEditModal"
                            style="border-radius: 12px; font-weight: 600;"
                        >
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-primary btn-lg flex-fill"
                            wire:click="updateSession"
                            style="border-radius: 12px; font-weight: 600; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;"
                        >
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showQrModal)
        <div class="modal-backdrop" wire:poll.2s="refreshQr" wire:click.self="closeQrModalOnly">
            <div class="modal-content-custom" onclick="event.stopPropagation()" style="max-width: 560px;">
                <div class="p-4 p-lg-5">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 52px; height: 52px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.45rem;">
                                <i class="fas fa-qrcode"></i>
                            </div>
                            <div>
                                <h4 class="mb-0" style="font-weight: 800;">Absensi QR</h4>
                                <div style="font-size: 0.85rem; color: #64748b;">Pertemuan {{ $qrSessionInfo['meeting_no'] ?? '-' }} - {{ $qrSessionInfo['topic'] ?? '-' }}</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeQrModalOnly"></button>
                    </div>

                    @if($qrPayload)
                        <div class="text-center">
                            <div class="mb-3" style="font-size: 0.85rem; color: #64748b;">
                                QR berubah otomatis setiap {{ $qrPayload['interval'] }} detik selama sesi dibuka.
                            </div>
                            <div class="d-inline-flex align-items-center justify-content-center p-3 mb-3" style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 18px; min-width: 300px; min-height: 300px;">
                                <div
                                    id="attendanceQrCanvas"
                                    wire:key="attendance-qr-{{ $qrPayload['token'] }}"
                                    data-qr-url="{{ $qrPayload['url'] }}"
                                    data-qr-token="{{ $qrPayload['token'] }}"
                                ></div>
                            </div>
                            <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">
                                <span class="badge bg-green-lt text-green" style="padding: 8px 12px;">Status: Dibuka</span>
                                <span class="badge bg-blue-lt text-blue" style="padding: 8px 12px;">Kode: {{ $qrPayload['token'] }}</span>
                            </div>
                        </div>

                        <div class="alert alert-info" style="border-radius: 12px;">
                            <i class="fas fa-circle-info me-2"></i>
                            Mahasiswa cukup scan QR. Saat tombol ditutup, kode lama langsung tidak bisa digunakan.
                        </div>
                    @else
                        <div class="alert alert-warning" style="border-radius: 12px;">
                            <i class="fas fa-lock me-2"></i>
                            Sesi tidak sedang dibuka untuk absensi QR.
                        </div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-lg flex-fill" wire:click="closeQrModalOnly" style="border-radius: 12px; font-weight: 700;">
                            <i class="fas fa-eye-slash me-2"></i>Sembunyikan
                        </button>
                        <button type="button" class="btn btn-danger btn-lg flex-fill" wire:click="closeQrAttendance" style="border-radius: 12px; font-weight: 700;">
                            <i class="fas fa-stop me-2"></i>Tutup Absensi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        function renderAttendanceQr() {
            const holder = document.getElementById('attendanceQrCanvas');

            if (!holder || !window.QRCode) {
                return;
            }

            const qrUrl = holder.getAttribute('data-qr-url');
            const hasRenderedNode = holder.querySelector('canvas, img, table');

            if (!qrUrl) {
                return;
            }

            if (holder.dataset.renderedUrl === qrUrl && hasRenderedNode) {
                return;
            }

            holder.innerHTML = '';
            holder.dataset.renderedUrl = qrUrl;
            new QRCode(holder, {
                text: qrUrl,
                width: 260,
                height: 260,
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        let attendanceQrRenderTimer = null;
        function scheduleAttendanceQrRender() {
            setTimeout(renderAttendanceQr, 0);
            setTimeout(renderAttendanceQr, 80);

            if (!attendanceQrRenderTimer) {
                attendanceQrRenderTimer = setInterval(() => {
                    if (document.getElementById('attendanceQrCanvas')) {
                        renderAttendanceQr();
                    }
                }, 500);
            }
        }

        document.addEventListener('livewire:navigated', renderAttendanceQr);
        document.addEventListener('livewire:init', () => {
            scheduleAttendanceQrRender();

            if (window.Livewire?.hook) {
                try {
                    Livewire.hook('morph.updated', scheduleAttendanceQrRender);
                    Livewire.hook('commit', ({ succeed }) => succeed(() => scheduleAttendanceQrRender()));
                } catch (error) {
                    scheduleAttendanceQrRender();
                }
            }
        });
        document.addEventListener('DOMContentLoaded', scheduleAttendanceQrRender);
    </script>
@endpush
