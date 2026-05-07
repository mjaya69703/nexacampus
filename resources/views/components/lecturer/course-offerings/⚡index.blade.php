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
                'courseOffering.courseSchedules.room.building',
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
                    'room' => $offering->courseSchedules->where('is_active', true)->first()?->room?->name ?? '-',
                    'building' => $offering->courseSchedules->where('is_active', true)->first()?->room?->building?->name ?? '-',
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
        .modern-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
        }

        .modern-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
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

        .course-card {
            border-radius: 16px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            background: white;
            overflow: hidden;
        }

        .course-card:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.2);
        }

        .course-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            position: relative;
        }

        .course-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-open {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .badge-closed {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .badge-draft {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 12px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }

        .progress-bar-modern {
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
        }

        .action-btn {
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .action-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .action-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .filter-input {
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
        {{-- Hero Section --}}
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                📚
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Ruang Mengajar</div>
                                <h2 class="h2 mb-0" style="font-weight: 700;">{{ $lecturerInfo['name'] }}</h2>
                            </div>
                        </div>
                        <div style="opacity: 0.9; margin-bottom: 1rem;">
                            NIDN {{ $lecturerInfo['nidn'] }} • {{ $lecturerInfo['employment_status'] }}
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge-modern" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                🎓 {{ $lecturerInfo['study_program'] }}
                            </span>
                            <span class="badge-modern" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                🏛️ {{ $lecturerInfo['faculty'] }}
                            </span>
                            <span class="badge-modern" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                📅 {{ $activeAcademicYearName ?? '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em;">Kelas</div>
                                    <div class="h2 mt-2 mb-0" style="font-weight: 700;">{{ number_format($stats['total_offerings']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em;">Mahasiswa</div>
                                    <div class="h2 mt-2 mb-0" style="font-weight: 700;">{{ number_format($stats['total_students']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em;">Jadwal</div>
                                    <div class="h2 mt-2 mb-0" style="font-weight: 700;">{{ number_format($stats['total_schedules']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; padding: 1rem; text-align: center;">
                                    <div style="font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em;">Sesi</div>
                                    <div class="h2 mt-2 mb-0" style="font-weight: 700;">{{ number_format($stats['total_sessions']) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter Section --}}
        <div class="modern-card mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control filter-input" placeholder="🔍 Cari kode, mata kuliah, atau kelas..." wire:model.live.debounce.300ms="search">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select filter-input" wire:model.live="yearFilter">
                            <option value="all">📅 Semua Tahun Akademik</option>
                            @foreach ($yearOptions as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select filter-input" wire:model.live="statusFilter">
                            <option value="all">🏷️ Semua Status</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select filter-input" wire:model.live="modeFilter">
                            <option value="all">💻 Semua Mode</option>
                            @foreach ($modeOptions as $mode)
                                <option value="{{ $mode }}">{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-3 text-secondary small">
                    Menampilkan <strong>{{ count($filteredOfferings) }}</strong> dari <strong>{{ count($offerings) }}</strong> kelas
                </div>
            </div>
        </div>

        {{-- Course Cards Grid --}}
        <div class="row g-4">
            @forelse ($filteredOfferings as $offering)
                <div class="col-lg-6 col-xl-4">
                    <div class="course-card h-100">
                        {{-- Card Header --}}
                        <div class="course-header">
                            <span class="course-badge {{ 
                                $offering['status'] === 'Open' ? 'badge-open' : 
                                ($offering['status'] === 'Closed' ? 'badge-closed' : 'badge-draft') 
                            }}">
                                {{ $offering['status'] }}
                            </span>
                            <div style="margin-bottom: 0.5rem;">
                                <span style="font-size: 0.85rem; color: #6b7280; font-weight: 600;">{{ $offering['course_code'] }}</span>
                            </div>
                            <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: 1.1rem;">
                                {{ $offering['course_name'] }}
                            </h4>
                            <div style="font-size: 0.85rem; color: #6b7280;">
                                {{ $offering['label'] }} • {{ $offering['class_code'] }}
                            </div>
                        </div>

                        {{-- Card Body --}}
                        <div class="p-3">
                            {{-- Info Grid --}}
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="stat-item">
                                        <div class="stat-value">{{ $offering['credits'] }}</div>
                                        <div class="stat-label">SKS</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item">
                                        <div class="stat-value">{{ $offering['semester_no'] }}</div>
                                        <div class="stat-label">Semester</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Stats --}}
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span style="font-size: 0.85rem; color: #6b7280;">👥 Mahasiswa</span>
                                    <span style="font-weight: 600; color: #1f2937;">
                                        {{ $offering['enrolled_count'] }} / {{ $offering['capacity'] ?? '∞' }}
                                    </span>
                                </div>
                                @if ($offering['capacity'])
                                    <div class="progress-bar-modern">
                                        <div class="progress-bar-fill" style="width: {{ min(100, ($offering['enrolled_count'] / $offering['capacity']) * 100) }}%;"></div>
                                    </div>
                                @endif
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div style="padding: 0.5rem; background: #f9fafb; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">{{ $offering['schedule_count'] }}</div>
                                        <div style="font-size: 0.7rem; color: #6b7280;">JADWAL</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div style="padding: 0.5rem; background: #f9fafb; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">{{ $offering['session_count'] }}</div>
                                        <div style="font-size: 0.7rem; color: #6b7280;">SESI</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Additional Info --}}
                            <div class="mb-3">
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f9fafb; border-radius: 8px; margin-bottom: 0.5rem;">
                                    <span style="font-size: 0.8rem; color: #6b7280;">Periode</span>
                                    <span style="font-size: 0.8rem; font-weight: 600; color: #1f2937;">{{ $offering['academic_year'] }}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f9fafb; border-radius: 8px; margin-bottom: 0.5rem;">
                                    <span style="font-size: 0.8rem; color: #6b7280;">Ruangan</span>
                                    <span style="font-size: 0.8rem; font-weight: 600; color: #1f2937;">
                                        <i class="fas fa-building me-1" style="color: #667eea;"></i>{{ $offering['building'] }} / {{ $offering['room'] }}
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f9fafb; border-radius: 8px; margin-bottom: 0.5rem;">
                                    <span style="font-size: 0.8rem; color: #6b7280;">Program Studi</span>
                                    <span style="font-size: 0.8rem; font-weight: 600; color: #1f2937; text-align: right; max-width: 60%;">{{ $offering['study_program'] }}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f9fafb; border-radius: 8px;">
                                    <span style="font-size: 0.8rem; color: #6b7280;">Mode</span>
                                    <span style="font-size: 0.8rem; font-weight: 600; color: #1f2937;">{{ $offering['delivery_mode'] }}</span>
                                </div>
                            </div>

                            {{-- Action Button --}}
                            <a href="{{ route('lecturer.course-offerings.show', ['id' => $offering['id']]) }}" class="action-btn action-btn-primary w-100 d-block text-center text-decoration-none">
                                <i class="fas fa-rocket me-2"></i>Buka Kelas
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="modern-card" style="text-align: center; padding: 3rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">📭</div>
                        <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Tidak Ada Kelas Ditemukan</h4>
                        <p style="color: #6b7280;">Data kelas tidak ditemukan untuk filter saat ini.</p>
                    </div>
                </div>
            @endforelse
        </div>
    @endif
</div>
