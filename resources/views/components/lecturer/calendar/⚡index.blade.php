<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseSchedule;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public array $lecturerInfo = [];
    public array $stats = [];
    public array $schedules = [];
    public array $filteredSchedules = [];
    public array $weeklySchedule = [];
    public array $yearOptions = [];
    public array $modeOptions = [];
    public string $yearFilter = 'all';
    public string $modeFilter = 'all';
    public string $dayFilter = 'all';
    public string $search = '';

    private array $dayOrder = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
        'Sunday' => 7,
    ];

    private array $dayLabels = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu',
    ];

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
        ];

        $activeAcademicYear = AcademicYear::query()
            ->where('is_active', true)
            ->latest('start_date')
            ->first();

        $this->yearFilter = $activeAcademicYear?->name ?? 'all';

        $this->schedules = CourseSchedule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($lecturerProfile) {
                $query->where('lecturer_profile_id', $lecturerProfile->id)
                    ->orWhereHas('courseOffering.lecturers', function ($lecturerQuery) use ($lecturerProfile) {
                        $lecturerQuery
                            ->where('lecturer_profile_id', $lecturerProfile->id)
                            ->where('is_active', true);
                    });
            })
            ->with([
                'courseOffering.academicYear',
                'courseOffering.studyProgram',
                'courseOffering.course',
                'room.building',
            ])
            ->get()
            ->map(fn (CourseSchedule $schedule) => $this->scheduleRow($schedule))
            ->sortBy(fn (array $schedule) => sprintf('%02d-%s', $schedule['day_order'], $schedule['start_time']))
            ->values()
            ->all();

        $this->yearOptions = collect($this->schedules)->pluck('academic_year')->filter()->unique()->values()->all();
        $this->modeOptions = collect($this->schedules)->pluck('delivery_mode')->filter()->unique()->values()->all();

        if ($this->yearFilter !== 'all' && ! in_array($this->yearFilter, $this->yearOptions, true)) {
            $this->yearFilter = 'all';
        }

        $this->applyFilters();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Kalender Mengajar',
        ]);
    }

    public function updatedYearFilter(): void
    {
        $this->applyFilters();
    }

    public function updatedModeFilter(): void
    {
        $this->applyFilters();
    }

    public function updatedDayFilter(): void
    {
        $this->applyFilters();
    }

    public function updatedSearch(): void
    {
        $this->applyFilters();
    }

    public function dayLabel(string $day): string
    {
        return $this->dayLabels[$day] ?? $day;
    }

    public function dayKeys(): array
    {
        return array_keys($this->dayOrder);
    }

    public function modeBadgeClass(?string $mode): string
    {
        return match ($mode) {
            'Online' => 'bg-blue-lt text-blue',
            'Hybrid' => 'bg-yellow-lt text-yellow',
            'Offline' => 'bg-green-lt text-green',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function currentDay(): string
    {
        return now()->format('l');
    }

    private function applyFilters(): void
    {
        $search = mb_strtolower(trim($this->search));

        $filtered = collect($this->schedules)
            ->filter(function (array $schedule) use ($search) {
                if ($this->yearFilter !== 'all' && $schedule['academic_year'] !== $this->yearFilter) {
                    return false;
                }

                if ($this->modeFilter !== 'all' && $schedule['delivery_mode'] !== $this->modeFilter) {
                    return false;
                }

                if ($this->dayFilter !== 'all' && $schedule['day'] !== $this->dayFilter) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    $schedule['course_code'],
                    $schedule['course_name'],
                    $schedule['class_label'],
                    $schedule['class_code'],
                    $schedule['room'],
                    $schedule['building'],
                    $schedule['study_program'],
                ]));

                return str_contains($haystack, $search);
            })
            ->sortBy(fn (array $schedule) => sprintf('%02d-%s', $schedule['day_order'], $schedule['start_time']))
            ->values();

        $this->filteredSchedules = $filtered->all();
        $this->weeklySchedule = collect(array_keys($this->dayOrder))
            ->mapWithKeys(fn (string $day) => [$day => $filtered->where('day', $day)->values()->all()])
            ->all();

        $this->stats = [
            'total_schedules' => $filtered->count(),
            'total_courses' => $filtered->pluck('course_offering_id')->unique()->count(),
            'online_count' => $filtered->whereIn('delivery_mode', ['Online', 'Hybrid'])->count(),
            'today_count' => $filtered->where('day', $this->currentDay())->count(),
        ];
    }

    private function scheduleRow(CourseSchedule $schedule): array
    {
        $offering = $schedule->courseOffering;

        return [
            'id' => $schedule->id,
            'course_offering_id' => $offering?->id,
            'academic_year' => $offering?->academicYear?->name ?? '-',
            'study_program' => $offering?->studyProgram?->name ?? '-',
            'course_code' => $offering?->course?->code ?? '-',
            'course_name' => $offering?->course?->name ?? '-',
            'class_label' => $offering?->label ?? '-',
            'class_code' => $offering?->code ?? '-',
            'day' => $schedule->day_of_week,
            'day_order' => $this->dayOrder[$schedule->day_of_week] ?? 99,
            'start_time' => $this->formatTime($schedule->start_time),
            'end_time' => $this->formatTime($schedule->end_time),
            'session_type' => $schedule->session_type ?? '-',
            'delivery_mode' => $schedule->delivery_mode ?? '-',
            'room' => $schedule->room?->name ?? '-',
            'building' => $schedule->room?->building?->name ?? '-',
            'meeting_link' => $schedule->meeting_link,
            'notes' => $schedule->notes,
        ];
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && strlen($value) >= 5) {
            return substr($value, 0, 5);
        }

        return '-';
    }

    private function initializeDefaults(): void
    {
        $this->lecturerInfo = [
            'name' => '-',
            'nidn' => '-',
            'study_program' => '-',
            'faculty' => '-',
        ];

        $this->stats = [
            'total_schedules' => 0,
            'total_courses' => 0,
            'online_count' => 0,
            'today_count' => 0,
        ];

        $this->weeklySchedule = collect(array_keys($this->dayOrder))
            ->mapWithKeys(fn (string $day) => [$day => []])
            ->all();
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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

        .hero-icon {
            width: 72px;
            height: 72px;
            background: rgba(255,255,255,0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            backdrop-filter: blur(10px);
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: 16px;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            min-height: 132px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .stat-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .stat-value {
            color: #0f172a;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-top: 0.6rem;
        }

        .calendar-day {
            border-radius: 16px;
            border: 2px solid #e5e7eb;
            background: #f8fafc;
            min-height: 180px;
            padding: 1rem;
        }

        .calendar-day.is-today {
            border-color: #667eea;
            background: #eef2ff;
        }

        .schedule-item {
            border-radius: 12px;
            background: var(--tblr-bg-surface);
            border: 1px solid #e2e8f0;
            padding: 0.85rem;
            margin-top: 0.75rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .schedule-time {
            font-size: 0.78rem;
            font-weight: 800;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        [data-bs-theme=dark] .modern-card,
        body[data-bs-theme=dark] .modern-card,
        [data-bs-theme=dark] .stat-card,
        body[data-bs-theme=dark] .stat-card,
        [data-bs-theme=dark] .calendar-day,
        body[data-bs-theme=dark] .calendar-day,
        [data-bs-theme=dark] .calendar-day.is-today,
        body[data-bs-theme=dark] .calendar-day.is-today,
        [data-bs-theme=dark] .schedule-item,
        body[data-bs-theme=dark] .schedule-item {
            background: rgba(43, 28, 67, 0.85) !important;
            border-color: rgba(167, 139, 255, 0.22) !important;
            color: #f3edff !important;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-danger">Profil dosen belum tersedia. Hubungi admin akademik.</div>
    @else
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div class="hero-icon">
                                <i class="fas fa-calendar-days"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Kalender Mengajar</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $lecturerInfo['name'] }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-id-card me-2"></i>{{ $lecturerInfo['nidn'] }} &bull; {{ $lecturerInfo['study_program'] }}
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-white text-primary" style="border-radius: 999px; padding: 0.55rem 0.8rem;">
                                        <i class="fas fa-university me-2"></i>{{ $lecturerInfo['faculty'] }}
                                    </span>
                                    <span class="badge bg-white text-primary" style="border-radius: 999px; padding: 0.55rem 0.8rem;">
                                        <i class="fas fa-calendar-check me-2"></i>{{ $this->dayLabel($this->currentDay()) }} ini
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Jadwal</div>
                                    <div style="font-size: 2rem; font-weight: 700;">{{ $stats['total_schedules'] }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 0.5rem;">Hari Ini</div>
                                    <div style="font-size: 2rem; font-weight: 700;">{{ $stats['today_count'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Total Jadwal</div>
                    <div class="stat-value">{{ $stats['total_schedules'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Kelas Aktif</div>
                    <div class="stat-value">{{ $stats['total_courses'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Online/Hybrid</div>
                    <div class="stat-value text-blue">{{ $stats['online_count'] }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Hari Ini</div>
                    <div class="stat-value text-primary">{{ $stats['today_count'] }}</div>
                </div>
            </div>
        </div>

        <div class="card modern-card mb-4">
            <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;">
                    <i class="fas fa-filter me-2" style="color: #667eea;"></i>Filter Jadwal
                </h3>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label">Cari kelas atau ruangan</label>
                        <input type="search" class="form-control" wire:model.live.debounce.350ms="search" placeholder="Nama mata kuliah, kode, ruangan">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label">Tahun akademik</label>
                        <select class="form-select" wire:model.live="yearFilter">
                            <option value="all">Semua tahun</option>
                            @foreach ($yearOptions as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label">Hari</label>
                        <select class="form-select" wire:model.live="dayFilter">
                            <option value="all">Semua hari</option>
                            @foreach ($this->dayKeys() as $day)
                                <option value="{{ $day }}">{{ $this->dayLabel($day) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <label class="form-label">Mode</label>
                        <select class="form-select" wire:model.live="modeFilter">
                            <option value="all">Semua mode</option>
                            @foreach ($modeOptions as $mode)
                                <option value="{{ $mode }}">{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card modern-card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;">
                    <i class="fas fa-calendar-week me-2" style="color: #10b981;"></i>Kalender Mingguan
                </h3>
                <span class="badge bg-primary-lt text-primary">{{ count($filteredSchedules) }} jadwal tampil</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    @foreach ($weeklySchedule as $day => $items)
                        <div class="col-md-6 col-xl-4">
                            <div class="calendar-day h-100 {{ $day === $this->currentDay() ? 'is-today' : '' }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="font-weight: 800; color: #1f2937;">{{ $this->dayLabel($day) }}</div>
                                    <span class="badge bg-white text-secondary">{{ count($items) }}</span>
                                </div>

                                @forelse ($items as $schedule)
                                    <div class="schedule-item">
                                        <div class="schedule-time">{{ $schedule['start_time'] }} - {{ $schedule['end_time'] }}</div>
                                        <div class="fw-bold mt-1" style="color: #1f2937;">{{ $schedule['course_name'] }}</div>
                                        <div class="text-secondary small">{{ $schedule['course_code'] }} &bull; {{ $schedule['class_label'] }}</div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <span class="badge {{ $this->modeBadgeClass($schedule['delivery_mode']) }}">{{ $schedule['delivery_mode'] }}</span>
                                            <span class="badge bg-secondary-lt text-secondary">{{ $schedule['room'] }}</span>
                                        </div>
                                        <div class="mt-3">
                                            <a href="{{ route('lecturer.course-offerings.show', $schedule['course_offering_id']) }}" class="btn btn-outline-primary w-100" style="border-radius: 8px;">
                                                <i class="fas fa-arrow-right me-2"></i>Buka Kelas
                                            </a>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-secondary py-4">
                                        <i class="fas fa-calendar-xmark" style="font-size: 1.6rem; opacity: 0.35; display: block; margin-bottom: 0.5rem;"></i>
                                        Tidak ada jadwal.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card modern-card">
            <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;">
                    <i class="fas fa-list-check me-2" style="color: #f59e0b;"></i>Daftar Jadwal
                </h3>
            </div>
            <div class="card-body p-4">
                @forelse ($filteredSchedules as $schedule)
                    <div class="schedule-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div style="flex: 1; min-width: 220px;">
                                <div class="schedule-time">{{ $this->dayLabel($schedule['day']) }}, {{ $schedule['start_time'] }} - {{ $schedule['end_time'] }}</div>
                                <div class="fw-bold mt-1" style="color: #1f2937;">{{ $schedule['course_name'] }}</div>
                                <div class="text-secondary small">{{ $schedule['course_code'] }} &bull; {{ $schedule['class_code'] }} &bull; {{ $schedule['academic_year'] }}</div>
                                <div class="text-secondary small mt-1">
                                    <i class="fas fa-location-dot me-1"></i>{{ $schedule['room'] }}{{ $schedule['building'] !== '-' ? ' - '.$schedule['building'] : '' }}
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge {{ $this->modeBadgeClass($schedule['delivery_mode']) }}">{{ $schedule['delivery_mode'] }}</span>
                                <span class="badge bg-purple-lt text-purple">{{ $schedule['session_type'] }}</span>
                                @if ($schedule['meeting_link'])
                                    <a href="{{ $schedule['meeting_link'] }}" target="_blank" class="btn btn-outline-primary" style="border-radius: 8px;">
                                        <i class="fas fa-video me-2"></i>Link
                                    </a>
                                @endif
                                <a href="{{ route('lecturer.course-offerings.show', $schedule['course_offering_id']) }}" class="btn btn-primary" style="border-radius: 8px;">
                                    <i class="fas fa-arrow-right me-2"></i>Kelas
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                        Tidak ada jadwal yang cocok dengan filter.
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
