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

<div class="row">
    <div class="col-12">
        <!-- Header Card -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Course Offering Detail</h5>
                <div class="btn-group" role="group">
                    @can('course-offering.update')
                        <a href="{{ route('admin.academic.course-offerings.edit', ['id' => $offering->id]) }}" class="btn btn-warning ">
                            <i class="fas fa-pencil me-1"></i> Edit
                        </a>
                    @endcan
                    <button class="btn btn-secondary " wire:click="goBack">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Tahun Akademik</label>
                            <div class="h6 mb-0">{{ $offering->academicYear?->name ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Program Studi</label>
                            <div class="h6 mb-0">{{ $offering->studyProgram?->name ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Mata Kuliah</label>
                            <div class="h6 mb-0">
                                {{ $offering->course?->code }} - {{ $offering->course?->name ?? '-' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Label (Kelas)</label>
                            <div class="h6 mb-0">{{ $offering->label ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-lg-3">
                        <div class="mb-3">
                            <label class="form-label text-muted">Kode</label>
                            <div class="h6 mb-0">{{ $offering->code ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="mb-3">
                            <label class="form-label text-muted">Semester</label>
                            <div class="h6 mb-0">{{ $offering->semester_no ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="mb-3">
                            <label class="form-label text-muted">Kapasitas</label>
                            <div class="h6 mb-0">{{ $offering->capacity ?? '-' }} Mahasiswa</div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="mb-3">
                            <label class="form-label text-muted">SKS</label>
                            <div class="h6 mb-0">{{ $offering->credits ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="mb-3">
                            <label class="form-label text-muted">Total Pertemuan</label>
                            <div class="h6 mb-0">{{ $offering->total_meetings ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Tanggal Mulai Kelas</label>
                            <div class="h6 mb-0">{{ $offering->class_start_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label text-muted">Tanggal Akhir Kelas</label>
                            <div class="h6 mb-0">{{ $offering->class_end_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-lg-4">
                        <div class="mb-3">
                            <label class="form-label text-muted">Mode Pengiriman</label>
                            <div>
                                @php
                                    $badgeClass = match($offering->delivery_mode) {
                                        'Offline' => 'bg-info',
                                        'Online' => 'bg-primary',
                                        'Hybrid' => 'bg-warning',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $offering->delivery_mode }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
                                @php
                                    $statusBadge = match($offering->status) {
                                        'Draft' => 'bg-secondary',
                                        'Open' => 'bg-success',
                                        'Closed' => 'bg-danger',
                                        'Cancelled' => 'bg-dark',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }}">{{ $offering->status }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="mb-3">
                            <label class="form-label text-muted">Wajib Diambil</label>
                            <div>
                                @if ($offering->is_required)
                                    <span class="badge bg-success">Ya</span>
                                @else
                                    <span class="badge bg-danger">Tidak</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if ($offering->notes)
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label text-muted">Catatan</label>
                            <div class="p-2 rounded">
                                {{ $offering->notes }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Schedules & Generate Sessions</h5>
                @can('course-offering.update')
                    <button class="btn btn-info" wire:click="generateSessions">
                        <i class="fas fa-bolt me-1"></i> Generate Sessions
                    </button>
                @endcan
            </div>
            <div class="card-body">
                @if (!empty($generationMessage))
                    <div class="alert alert-{{ $generationMessage['type'] }} mb-3">
                        {{ $generationMessage['text'] }}
                    </div>
                @endif

                @if (count($schedulesData) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Hari</th>
                                    <th>Mulai</th>
                                    <th>Selesai</th>
                                    <th>Dosen</th>
                                    <th>Lokasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($schedulesData as $schedule)
                                    <tr>
                                        <td>{{ $schedule['day_name'] }}</td>
                                        <td>{{ $schedule['start_time'] }}</td>
                                        <td>{{ $schedule['end_time'] }}</td>
                                        <td>{{ $schedule['lecturer_name'] }}</td>
                                        <td>{{ trim(($schedule['building'] ?? '') . ' ' . ($schedule['room'] ?? '')) ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        Belum ada schedule. Tambahkan dulu agar sistem bisa generate sessions.
                    </div>
                @endif
                <small class="text-muted d-block mt-2">Setelah generate, session tetap bisa diubah manual untuk kebutuhan reschedule/libur/pengganti.</small>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Attendance Sessions</h5>
            </div>
            <div class="card-body">
                @if (count($attendanceSessionsData) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Pertemuan</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Dosen</th>
                                    <th>Topik</th>
                                    <th>Status</th>
                                    <th>Records</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendanceSessionsData as $session)
                                    <tr>
                                        <td>{{ $session['meeting_no'] ?? '-' }}</td>
                                        <td>{{ $session['meeting_date'] }}</td>
                                        <td>{{ $session['start_time'] }} - {{ $session['end_time'] }}</td>
                                        <td>{{ $session['lecturer_name'] }}</td>
                                        <td>{{ $session['topic'] }}</td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $session['status'] }}</span>
                                        </td>
                                        <td>{{ $session['records_count'] }}</td>
                                        <td>
                                            <a
                                                href="{{ route('admin.academic.attendance-sessions.show', ['offeringId' => $offering->id, 'id' => $session['id']]) }}"
                                                class="btn  btn-primary"
                                            >
                                                <i class="fas fa-eye me-1"></i> Detail Session
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        Belum ada attendance session. Gunakan tombol Generate Sessions di atas.
                    </div>
                @endif
            </div>
        </div>

        <!-- Lecturers Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Dosen Pengajar</h5>
            </div>
            <div class="card-body">
                @if ($offering->lecturers && count($offering->lecturers) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-striped">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Dosen</th>
                                    <th>Role</th>
                                    <th>Urut</th>
                                    <th>Status</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($offering->lecturers->sortBy('sort_order') as $index => $lecturer)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $lecturer->lecturerProfile?->user?->name ?? '-' }}</strong>
                                        </td>
                                        <td><span class="badge bg-info">{{ $lecturer->role }}</span></td>
                                        <td>{{ $lecturer->sort_order }}</td>
                                        <td>
                                            @if ($lecturer->is_active)
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($lecturer->notes)
                                                <span class="text-muted small">{{ Str::limit($lecturer->notes, 50) }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> Belum ada dosen pengajar yang ditugaskan.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
