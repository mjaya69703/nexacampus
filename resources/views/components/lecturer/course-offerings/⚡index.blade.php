<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public bool $hasOfferings = false;
    public ?string $activeAcademicYearName = null;
    public array $lecturerInfo = [];
    public array $stats = [];
    public array $offerings = [];
    public array $filteredOfferings = [];
    public array $yearOptions = [];
    public array $statusOptions = [];
    public array $modeOptions = [];
    public string $search = '';
    public string $yearFilter = 'all';
    public string $statusFilter = 'all';
    public string $modeFilter = 'all';

    public function mount(): void
    {
        $this->initializeDefaults();

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $lecturerProfile = $user->lecturerProfile()
            ->with(['studyProgram', 'faculty'])
            ->first();

        if (! $lecturerProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->lecturerInfo = [
            'name' => $user->name,
            'nidn' => $lecturerProfile->nidn ?? '-',
            'study_program' => $lecturerProfile->studyProgram?->name ?? '-',
            'faculty' => $lecturerProfile->faculty?->name ?? '-',
            'employment_status' => $lecturerProfile->employment_status ?? '-',
        ];

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        $this->activeAcademicYearName = $activeAcademicYear?->name;

        $assignedOfferings = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->with([
                'courseOffering.academicYear',
                'courseOffering.studyProgram',
                'courseOffering.course',
                'courseOffering.courseSchedules',
                'courseOffering.attendanceSessions',
            ])
            ->latest('id')
            ->get();

        $this->offerings = $assignedOfferings
            ->map(function ($assignment) {
                $offering = $assignment->courseOffering;

                if (! $offering) {
                    return null;
                }

                $enrolledCount = StudyPlanDetail::query()
                    ->where('course_offering_id', $offering->id)
                    ->count();

                return [
                    'id' => $offering->id,
                    'academic_year' => $offering->academicYear?->name ?? '-',
                    'study_program' => $offering->studyProgram?->name ?? '-',
                    'course_code' => $offering->course?->code ?? '-',
                    'course_name' => $offering->course?->name ?? '-',
                    'label' => $offering->label ?? '-',
                    'class_code' => $offering->code ?? '-',
                    'semester_no' => $offering->semester_no,
                    'credits' => $offering->credits ?? $offering->course?->credits ?? 0,
                    'delivery_mode' => $offering->delivery_mode ?? '-',
                    'status' => $offering->status ?? '-',
                    'lecturer_role' => $assignment->role ?? '-',
                    'schedule_count' => $offering->courseSchedules->where('is_active', true)->count(),
                    'session_count' => $offering->attendanceSessions->count(),
                    'capacity' => $offering->capacity,
                    'enrolled_count' => $enrolledCount,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $offering) => $offering['academic_year'])
            ->values()
            ->all();

        $this->hasOfferings = count($this->offerings) > 0;
        $totalSchedules = collect($this->offerings)->sum('schedule_count');
        $totalSessions = collect($this->offerings)->sum('session_count');
        $totalStudents = collect($this->offerings)->sum('enrolled_count');

        $this->yearOptions = collect($this->offerings)->pluck('academic_year')->filter()->unique()->values()->all();
        $this->statusOptions = collect($this->offerings)->pluck('status')->filter()->unique()->values()->all();
        $this->modeOptions = collect($this->offerings)->pluck('delivery_mode')->filter()->unique()->values()->all();

        $this->applyFilters();

        $this->stats = [
            'total_offerings' => count($this->offerings),
            'total_schedules' => (int) $totalSchedules,
            'total_sessions' => (int) $totalSessions,
            'total_students' => (int) $totalStudents,
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'My Course Offerings',
        ]);
    }

    public function updatedSearch(): void
    {
        $this->applyFilters();
    }

    public function updatedYearFilter(): void
    {
        $this->applyFilters();
    }

    public function updatedStatusFilter(): void
    {
        $this->applyFilters();
    }

    public function updatedModeFilter(): void
    {
        $this->applyFilters();
    }

    public function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'Open' => 'bg-green-lt text-green',
            'Closed' => 'bg-red-lt text-red',
            'Draft' => 'bg-yellow-lt text-yellow',
            'Cancelled' => 'bg-dark-lt text-dark',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    private function applyFilters(): void
    {
        $search = mb_strtolower(trim($this->search));

        $this->filteredOfferings = collect($this->offerings)
            ->filter(function (array $offering) use ($search) {
                if ($this->yearFilter !== 'all' && $offering['academic_year'] !== $this->yearFilter) {
                    return false;
                }

                if ($this->statusFilter !== 'all' && $offering['status'] !== $this->statusFilter) {
                    return false;
                }

                if ($this->modeFilter !== 'all' && $offering['delivery_mode'] !== $this->modeFilter) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    $offering['course_code'] ?? '',
                    $offering['course_name'] ?? '',
                    $offering['label'] ?? '',
                    $offering['class_code'] ?? '',
                    $offering['study_program'] ?? '',
                ]));

                return str_contains($haystack, $search);
            })
            ->values()
            ->all();
    }

    private function initializeDefaults(): void
    {
        $this->lecturerInfo = [
            'name' => '-',
            'nidn' => '-',
            'study_program' => '-',
            'faculty' => '-',
            'employment_status' => '-',
        ];

        $this->stats = [
            'total_offerings' => 0,
            'total_schedules' => 0,
            'total_sessions' => 0,
            'total_students' => 0,
        ];

        $this->yearOptions = [];
        $this->statusOptions = [];
        $this->modeOptions = [];
        $this->filteredOfferings = [];
    }
};
?>

@push('styles')
    <style>
        .lecturer-shell-card {
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        }

        .lecturer-shell-hero {
            background:
                radial-gradient(circle at top right, rgba(32, 107, 196, 0.12), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .lecturer-shell-label {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-warning">Profil dosen belum tersedia. Hubungi admin akademik.</div>
    @elseif (! $hasOfferings)
        <div class="alert alert-info">Belum ada penugasan kelas untuk dosen ini.</div>
    @else
        <div class="card lecturer-shell-card lecturer-shell-hero mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="lecturer-shell-label mb-2">Ruang Mengajar Dosen</div>
                        <h2 class="mb-2">{{ $lecturerInfo['name'] }}</h2>
                        <div class="text-secondary mb-3">
                            NIDN {{ $lecturerInfo['nidn'] }} • {{ $lecturerInfo['employment_status'] }}
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-blue-lt text-blue">{{ $lecturerInfo['study_program'] }}</span>
                            <span class="badge bg-azure-lt text-azure">{{ $lecturerInfo['faculty'] }}</span>
                            <span class="badge bg-green-lt text-green">{{ $activeAcademicYearName ?? '-' }}</span>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="lecturer-shell-card p-3 h-100">
                                    <div class="lecturer-shell-label">Kelas</div>
                                    <div class="h2 mt-2 mb-0">{{ number_format($stats['total_offerings']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="lecturer-shell-card p-3 h-100">
                                    <div class="lecturer-shell-label">Mahasiswa</div>
                                    <div class="h2 mt-2 mb-0">{{ number_format($stats['total_students']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="lecturer-shell-card p-3 h-100">
                                    <div class="lecturer-shell-label">Jadwal</div>
                                    <div class="h2 mt-2 mb-0">{{ number_format($stats['total_schedules']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="lecturer-shell-card p-3 h-100">
                                    <div class="lecturer-shell-label">Sesi</div>
                                    <div class="h2 mt-2 mb-0">{{ number_format($stats['total_sessions']) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card lecturer-shell-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-1">Daftar Kelas</h3>
                    <div class="text-secondary small">Menampilkan {{ count($filteredOfferings) }} dari {{ count($offerings) }} kelas yang Anda ampu.</div>
                </div>
            </div>

            <div class="card-body border-bottom">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Cari kode, mata kuliah, atau kelas" wire:model.live.debounce.300ms="search">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" wire:model.live="yearFilter">
                            <option value="all">Semua Tahun Akademik</option>
                            @foreach ($yearOptions as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" wire:model.live="statusFilter">
                            <option value="all">Semua Status</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" wire:model.live="modeFilter">
                            <option value="all">Semua Mode</option>
                            @foreach ($modeOptions as $mode)
                                <option value="{{ $mode }}">{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Mata Kuliah</th>
                            <th>Periode</th>
                            <th>Peran</th>
                            <th>Kelas</th>
                            <th>SKS</th>
                            <th>Jadwal</th>
                            <th>Sesi</th>
                            <th>Mahasiswa</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filteredOfferings as $offering)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $offering['course_code'] }} - {{ $offering['course_name'] }}</div>
                                    <div class="text-secondary small">{{ $offering['study_program'] }}</div>
                                </td>
                                <td>
                                    <div>{{ $offering['academic_year'] }}</div>
                                    <div class="text-secondary small">Semester {{ $offering['semester_no'] ?? '-' }}</div>
                                </td>
                                <td>{{ $offering['lecturer_role'] }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $offering['label'] }}</div>
                                    <div class="text-secondary small">{{ $offering['class_code'] }}</div>
                                </td>
                                <td>{{ $offering['credits'] }}</td>
                                <td>{{ $offering['schedule_count'] }}</td>
                                <td>{{ $offering['session_count'] }}</td>
                                <td>
                                    {{ $offering['enrolled_count'] }}
                                    @if ($offering['capacity'])
                                        <span class="text-secondary small">/ {{ $offering['capacity'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $this->statusBadgeClass($offering['status']) }}">{{ $offering['status'] }}</span>
                                    <div class="text-secondary small mt-1">{{ $offering['delivery_mode'] }}</div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('lecturer.course-offerings.show', ['id' => $offering['id']]) }}" class="btn btn-primary btn-sm">Buka Kelas</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-secondary text-center py-4">Data kelas tidak ditemukan untuk filter saat ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
