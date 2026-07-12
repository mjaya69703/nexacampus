<?php

use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseSchedule;
use App\Models\Academic\AttendanceSession;
use App\Support\GenerateAttendanceSessionsService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public CourseOffering $offering;
    public array $schedulesData = [];
    public array $attendanceSessionsData = [];
    public array $generationMessage = [];

    public function mount($id): void
    {
        $this->offering = CourseOffering::with('academicYear', 'studyProgram', 'course', 'lecturers.lecturerProfile.user')->findOrFail($id);
        $this->loadSchedulesData();
        $this->loadAttendanceSessionsData();
    }

    public function loadSchedulesData(): void
    {
        $daysMap = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        $this->schedulesData = CourseSchedule::query()
            ->where('course_offering_id', $this->offering->id)
            ->with(['lecturerProfile.user', 'room.building'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($schedule) => [
                'day_name' => $daysMap[$schedule->day_of_week] ?? $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i') ?? '-',
                'end_time' => $schedule->end_time?->format('H:i') ?? '-',
                'lecturer_name' => $schedule->lecturerProfile?->user?->name ?? '-',
                'room' => $schedule->room?->name,
                'building' => $schedule->room?->building?->name,
            ])
            ->toArray();
    }

    public function loadAttendanceSessionsData(): void
    {
        $this->attendanceSessionsData = AttendanceSession::query()
            ->where('course_offering_id', $this->offering->id)
            ->with(['lecturerProfile.user'])
            ->withCount('records')
            ->orderBy('meeting_no')
            ->orderBy('meeting_date')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'meeting_no' => $session->meeting_no,
                'meeting_date' => $session->meeting_date?->format('d M Y') ?? '-',
                'start_time' => $session->start_time?->format('H:i') ?? '-',
                'end_time' => $session->end_time?->format('H:i') ?? '-',
                'lecturer_name' => $session->lecturerProfile?->user?->name ?? '-',
                'topic' => $session->topic ?? '-',
                'status' => $session->status,
                'records_count' => $session->records_count,
            ])
            ->toArray();
    }

    public function generateSessions(): void
    {
        $this->validate([
            'offering.total_meetings' => 'required|integer|min:1|max:32',
            'offering.class_start_date' => 'required|date',
            'offering.class_end_date' => 'required|date|after_or_equal:offering.class_start_date',
        ]);

        try {
            DB::beginTransaction();

            $offering = CourseOffering::query()->findOrFail($this->offering->id);
            $generatedCount = app(GenerateAttendanceSessionsService::class)->generate($offering);

            DB::commit();

            $this->generationMessage = [
                'type' => 'success',
                'text' => "{$generatedCount} sesi absensi berhasil di-generate. Session tetap editable manual.",
            ];

            $this->offering = CourseOffering::with('academicYear', 'studyProgram', 'course', 'lecturers.lecturerProfile.user')->findOrFail($this->offering->id);
            $this->loadSchedulesData();
            $this->loadAttendanceSessionsData();
            session()->flash('success', $this->generationMessage['text']);
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $this->generationMessage = [
                'type' => 'danger',
                'text' => 'Gagal generate sesi absensi: '.$throwable->getMessage(),
            ];
            session()->flash('error', $this->generationMessage['text']);
        }
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.academic.course-offerings.index');
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Course Offering Detail',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Detail Kelas Penawaran: {{ $offering->code ?? '' }} {{ $offering->label ?? '' }}"
        description="Mata Kuliah: {{ $offering->course?->code }} - {{ $offering->course?->name }} | Program Studi: {{ $offering->studyProgram?->name }} ({{ $offering->academicYear?->name }})"
        icon="layer-group"
    >
        <div class="d-flex align-items-center gap-2">
            @can('course-offering.update')
                <a href="{{ route('admin.academic.course-offerings.edit', ['id' => $offering->id]) }}" class="btn btn-sm btn-primary rounded-pill px-3 py-2 shadow-sm d-flex align-items-center gap-1">
                    <i class="fas fa-edit"></i> Edit Kelas
                </a>
            @endcan
            <button type="button" class="btn btn-sm btn-light text-secondary rounded-pill px-3 py-2 border shadow-sm d-flex align-items-center gap-1" wire:click="goBack">
                <i class="fas fa-arrow-left"></i> Kembali
            </button>
        </div>
    </x-admin.academic.header>

    <!-- Quick Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Kapasitas</span>
                    <h3 class="fw-bold mb-0 text-primary mt-1">{{ $offering->capacity ?? '-' }} <small class="fs-6 text-muted fw-normal">Mhs</small></h3>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="fas fa-users fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Bobot SKS</span>
                    <h3 class="fw-bold mb-0 text-info mt-1">{{ $offering->credits ?? '-' }} <small class="fs-6 text-muted fw-normal">SKS</small></h3>
                </div>
                <div class="bg-info bg-opacity-10 text-info rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="fas fa-book-reader fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Pertemuan</span>
                    <h3 class="fw-bold mb-0 text-success mt-1">{{ count($attendanceSessionsData) }} / {{ $offering->total_meetings ?? '-' }}</h3>
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="fas fa-calendar-check fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 d-flex flex-row align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Status & Mode</span>
                    <div class="d-flex gap-1 mt-1">
                        @php
                            $statusBadge = match($offering->status) {
                                'Draft' => 'bg-secondary',
                                'Open' => 'bg-success',
                                'Closed' => 'bg-danger',
                                'Cancelled' => 'bg-dark',
                                default => 'bg-secondary',
                            };
                            $modeBadge = match($offering->delivery_mode) {
                                'Offline' => 'bg-info text-dark',
                                'Online' => 'bg-primary',
                                'Hybrid' => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }} rounded-pill px-2 py-1">{{ $offering->status }}</span>
                        <span class="badge {{ $modeBadge }} rounded-pill px-2 py-1">{{ $offering->delivery_mode }}</span>
                    </div>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="fas fa-info-circle fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Details & Schedules & Attendance Sessions -->
        <div class="col-lg-8">
            <!-- Course Offering Detail -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0">Informasi Lengkap Penawaran</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Tahun Akademik</label>
                            <div class="fw-bold">{{ $offering->academicYear?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Program Studi</label>
                            <div class="fw-bold">{{ $offering->studyProgram?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Mata Kuliah</label>
                            <div class="fw-bold">{{ $offering->course?->code }} - {{ $offering->course?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Label Kelas / Kode</label>
                            <div class="fw-bold">{{ $offering->label ?? '-' }} <span class="text-muted">({{ $offering->code ?? '-' }})</span></div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Target Semester</label>
                            <div class="fw-bold">Semester {{ $offering->semester_no ?? '-' }}</div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Sifat Mata Kuliah</label>
                            <div>
                                @if ($offering->is_required)
                                    <span class="badge bg-success bg-opacity-10 text-success px-2 py-1"><i class="fas fa-check-circle me-1"></i> Wajib Diambil</span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1">Pilihan</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Tanggal Mulai</label>
                            <div class="fw-semibold">{{ $offering->class_start_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label text-muted small fw-semibold mb-1">Tanggal Akhir</label>
                            <div class="fw-semibold">{{ $offering->class_end_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        @if ($offering->notes)
                            <div class="col-12 border-top pt-3 mt-3">
                                <label class="form-label text-muted small fw-semibold mb-1">Catatan</label>
                                <div class="p-3 bg-light rounded-3 small">{{ $offering->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Schedules & Generate Sessions -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0">Jadwal Perkuliahan Mingguan</h5>
                    @can('course-offering.update')
                        <button type="button" class="btn btn-sm btn-info rounded-pill px-3 shadow-sm text-white" wire:click="generateSessions">
                            <i class="fas fa-bolt me-1"></i> Generate Sesi Absensi
                        </button>
                    @endcan
                </div>
                <div class="card-body p-4">
                    @if (!empty($generationMessage))
                        <div class="alert alert-{{ $generationMessage['type'] }} mb-3 rounded-3 border-0">
                            {{ $generationMessage['text'] }}
                        </div>
                    @endif

                    @if (count($schedulesData) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hari</th>
                                        <th>Jam Perkuliahan</th>
                                        <th>Dosen Pengampu</th>
                                        <th>Ruang & Gedung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($schedulesData as $schedule)
                                        <tr>
                                            <td class="fw-bold">{{ $schedule['day_name'] }}</td>
                                            <td><span class="badge bg-light text-dark border px-2 py-1">{{ $schedule['start_time'] }} - {{ $schedule['end_time'] }}</span></td>
                                            <td>{{ $schedule['lecturer_name'] }}</td>
                                            <td>{{ trim(($schedule['building'] ?? '') . ' ' . ($schedule['room'] ?? '')) ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0 border-0 bg-warning bg-opacity-10 text-warning-emphasis d-flex align-items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Belum ada jadwal perkuliahan yang diatur untuk kelas ini.</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Attendance Sessions -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0">Daftar Sesi Absensi Pertemuan</h5>
                    <span class="badge bg-primary rounded-pill px-3">{{ count($attendanceSessionsData) }} Sesi</span>
                </div>
                <div class="card-body p-4">
                    @if (count($attendanceSessionsData) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pertemuan</th>
                                        <th>Tanggal & Waktu</th>
                                        <th>Dosen & Topik</th>
                                        <th>Status</th>
                                        <th class="text-center">Absensi</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($attendanceSessionsData as $session)
                                        <tr>
                                            <td><span class="badge bg-primary bg-opacity-10 text-primary fw-bold">Ke-{{ $session['meeting_no'] ?? '-' }}</span></td>
                                            <td>
                                                <div class="fw-semibold">{{ $session['meeting_date'] }}</div>
                                                <div class="small text-muted">{{ $session['start_time'] }} - {{ $session['end_time'] }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $session['topic'] }}</div>
                                                <div class="small text-muted">{{ $session['lecturer_name'] }}</div>
                                            </td>
                                            <td>
                                                @php
                                                    $sessBadge = match($session['status']) {
                                                        'Scheduled' => 'bg-secondary',
                                                        'Open' => 'bg-success',
                                                        'Closed' => 'bg-dark',
                                                        'Cancelled' => 'bg-danger',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $sessBadge }} rounded-pill px-2 py-1">{{ $session['status'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info bg-opacity-10 text-info fw-bold">{{ $session['records_count'] }} Mahasiswa</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.academic.attendance-sessions.show', ['offeringId' => $offering->id, 'id' => $session['id']]) }}" class="btn btn-sm btn-light border text-primary rounded-pill px-3 shadow-sm">
                                                    <i class="fas fa-eye me-1"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info mb-0 border-0 bg-info bg-opacity-10 text-info-emphasis d-flex align-items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            <span>Belum ada sesi absensi. Klik tombol <strong>Generate Sesi Absensi</strong> di atas untuk membuat sesi pertemuan otomatis.</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column: Lecturers List -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0">Dosen Pengampu</h5>
                </div>
                <div class="card-body p-4">
                    @if ($offering->lecturers && count($offering->lecturers) > 0)
                        <div class="d-flex flex-column gap-3">
                            @foreach ($offering->lecturers->sortBy('sort_order') as $index => $lecturer)
                                <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="fw-bold text-dark">{{ $lecturer->lecturerProfile?->user?->name ?? '-' }}</div>
                                        @if ($lecturer->is_active)
                                            <span class="badge bg-success bg-opacity-10 text-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Nonaktif</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ $lecturer->role }}</span>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Urutan: {{ $lecturer->sort_order }}</span>
                                    </div>
                                    @if ($lecturer->notes)
                                        <div class="small text-muted mt-2 border-top pt-2">{{ $lecturer->notes }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info-emphasis mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            <span>Belum ada dosen pengajar yang ditugaskan pada kelas ini.</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
