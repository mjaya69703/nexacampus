<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Support\GradeBookExportService;
use Livewire\Component;

new class extends Component
{
    public array $rows = [];
    public array $statistics = [];
    public array $distribution = [];
    public array $courseOptions = [];
    public array $yearOptions = [];
    public array $semesterOptions = [];

    public string $search = '';
    public string $courseOfferingId = '';
    public string $academicYearId = '';
    public string $semesterNo = '';

    public function mount(): void
    {
        $this->loadOptions();
        $this->loadRows();
    }

    public function updatedSearch(): void
    {
        $this->loadRows();
    }

    public function updatedCourseOfferingId(): void
    {
        $this->loadRows();
    }

    public function updatedAcademicYearId(): void
    {
        $this->loadRows();
    }

    public function updatedSemesterNo(): void
    {
        $this->loadRows();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Grade Book',
        ]);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'courseOfferingId', 'academicYearId', 'semesterNo']);
        $this->loadRows();
    }

    public function exportUrl(string $type): string
    {
        return route('lecturer.student-grades.grade-book.export.'.$type, array_filter([
            'course_offering_id' => $this->courseOfferingId,
            'academic_year_id' => $this->academicYearId,
            'semester_no' => $this->semesterNo,
            'search' => $this->search,
        ], fn ($value) => filled($value)));
    }

    public function gradeBadgeClass(string $letter): string
    {
        return match (true) {
            str_starts_with($letter, 'A') => 'bg-green-lt text-green',
            str_starts_with($letter, 'B') => 'bg-blue-lt text-blue',
            str_starts_with($letter, 'C') => 'bg-yellow-lt text-yellow',
            in_array($letter, ['D', 'E'], true) => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function lifecycleBadgeClass(string $status): string
    {
        return match ($status) {
            'Published' => 'bg-green-lt text-green',
            'Finalized' => 'bg-blue-lt text-blue',
            default => 'bg-yellow-lt text-yellow',
        };
    }

    private function loadOptions(): void
    {
        $user = auth()->user();
        $lecturerProfile = $user?->lecturerProfile()->first();

        if (! $lecturerProfile) {
            return;
        }

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->pluck('course_offering_id');

        $offerings = CourseOffering::query()
            ->with(['course', 'academicYear'])
            ->whereIn('id', $offeringIds)
            ->orderByDesc('created_at')
            ->get();

        $this->courseOptions = $offerings
            ->map(fn (CourseOffering $offering) => [
                'id' => (string) $offering->id,
                'label' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-').' / '.$offering->label,
            ])
            ->values()
            ->all();

        $this->yearOptions = AcademicYear::query()
            ->whereIn('id', $offerings->pluck('academic_year_id')->filter()->unique())
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => ['id' => (string) $year->id, 'label' => $year->name])
            ->values()
            ->all();

        $this->semesterOptions = $offerings
            ->pluck('semester_no')
            ->filter()
            ->unique()
            ->sort()
            ->map(fn ($semester) => ['id' => (string) $semester, 'label' => 'Semester '.$semester])
            ->values()
            ->all();
    }

    private function loadRows(): void
    {
        $service = app(GradeBookExportService::class);
        $rows = $service->lecturerRows(auth()->user(), [
            'course_offering_id' => $this->courseOfferingId,
            'academic_year_id' => $this->academicYearId,
            'semester_no' => $this->semesterNo,
            'search' => $this->search,
        ]);

        $this->rows = $rows->all();
        $this->statistics = $service->statistics($rows);
        $this->distribution = $service->distribution($rows)->all();
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

        .grade-book-hero {
            overflow: visible;
        }

        .grade-book-hero::before {
            display: none;
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

        .grade-stat-card {
            border-radius: 16px;
            padding: 18px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid transparent;
        }

        .grade-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .filter-input {
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
        }

        .filter-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient grade-book-hero mb-4" style="color: white;">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-3">
                <div class="col-lg">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                            <i class="fas fa-table"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Rekap & Export Nilai</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">Grade Book</h2>
                            <div style="opacity: 0.9; margin-top: 0.5rem;">Rekap nilai dari seluruh kelas yang Anda ampu.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-auto">
                    <div class="btn-list">
                        <a class="btn btn-light" href="{{ route('lecturer.student-grades.index') }}">
                            <i class="fa fa-arrow-left me-2"></i>Nilai
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fa fa-download me-2"></i>Export
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="{{ $this->exportUrl('csv') }}">CSV</a>
                                <a class="dropdown-item" href="{{ $this->exportUrl('xlsx') }}">Excel XLSX</a>
                                <a class="dropdown-item" href="{{ $this->exportUrl('pdf') }}">PDF</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-4">
        @foreach ([
            ['label' => 'Total Mahasiswa', 'value' => number_format($statistics['total_students'] ?? 0), 'icon' => 'fa-users', 'color' => 'primary'],
            ['label' => 'Sudah Dinilai', 'value' => number_format($statistics['graded_count'] ?? 0), 'icon' => 'fa-check', 'color' => 'green'],
            ['label' => 'Final/Publish', 'value' => number_format($statistics['finalized_count'] ?? 0), 'icon' => 'fa-lock', 'color' => 'blue'],
            ['label' => 'Rata-rata', 'value' => $statistics['average_score'] ?? '-', 'icon' => 'fa-chart-line', 'color' => 'purple'],
            ['label' => 'Median', 'value' => $statistics['median_score'] ?? '-', 'icon' => 'fa-grip-lines', 'color' => 'yellow'],
            ['label' => 'Pass Rate', 'value' => ($statistics['pass_rate'] ?? 0).'%', 'icon' => 'fa-percent', 'color' => 'teal'],
        ] as $stat)
            <div class="col-sm-6 col-lg-4 col-xl-2">
                <div class="card modern-card grade-stat-card h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="grade-stat-icon bg-{{ $stat['color'] }}">
                            <i class="fa {{ $stat['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="text-secondary small">{{ $stat['label'] }}</div>
                            <div class="h3 mb-0" style="font-weight: 800; color: #1e293b;">{{ $stat['value'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="modern-card mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label">Cari</label>
                    <input type="text" class="form-control filter-input" placeholder="Nama, NIM, mata kuliah, kelas" wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Mata Kuliah</label>
                    <select class="form-select filter-input" wire:model.live="courseOfferingId">
                        <option value="">Semua mata kuliah</option>
                        @foreach ($courseOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label">Tahun Akademik</label>
                    <select class="form-select filter-input" wire:model.live="academicYearId">
                        <option value="">Semua tahun</option>
                        @foreach ($yearOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label">Semester</label>
                    <select class="form-select filter-input" wire:model.live="semesterNo">
                        <option value="">Semua semester</option>
                        @foreach ($semesterOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100" wire:click="resetFilters">
                        <i class="fa fa-rotate-left"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card modern-card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Mahasiswa</th>
                                <th>Program Studi</th>
                                <th>Mata Kuliah</th>
                                <th>Kelas</th>
                                <th class="text-end">Skor</th>
                                <th>Nilai</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row['student_name'] }}</div>
                                        <div class="text-secondary small">{{ $row['nim'] }}</div>
                                    </td>
                                    <td>{{ $row['study_program'] }}</td>
                                    <td>
                                        <div>{{ $row['course'] }}</div>
                                        <div class="text-secondary small">{{ $row['academic_year'] }} / Semester {{ $row['semester_no'] }}</div>
                                    </td>
                                    <td>{{ $row['class'] }}</td>
                                    <td class="text-end">{{ $row['final_score'] !== null ? number_format($row['final_score'], 2) : '-' }}</td>
                                    <td>
                                        <span class="badge {{ $this->gradeBadgeClass($row['letter_grade']) }}">{{ $row['letter_grade'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $this->lifecycleBadgeClass($row['grade_status']) }}">{{ $row['grade_status'] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('lecturer.student-grades.edit', ['id' => $row['study_plan_detail_id']]) }}">
                                            <i class="fa fa-edit me-1"></i>Kelola
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-secondary py-5">Tidak ada data grade book untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card modern-card">
                <div class="card-header">
                    <h3 class="card-title">Distribusi Nilai</h3>
                </div>
                <div class="card-body">
                    @foreach ($distribution as $item)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold">{{ $item['letter'] }}</span>
                                <span class="text-secondary">{{ $item['count'] }} ({{ $item['percentage'] }}%)</span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar" style="width: {{ $item['percentage'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
