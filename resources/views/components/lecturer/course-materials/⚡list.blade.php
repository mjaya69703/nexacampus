<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public bool $hasOfferings = false;
    public array $offerings = [];
    public array $filteredOfferings = [];
    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            return;
        }

        $assignments = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'courseOffering.studyProgram',
                'courseOffering.courseSchedules.room.building',
            ])
            ->latest('id')
            ->get();

        $this->offerings = $assignments
            ->map(function ($assignment) {
                $offering = $assignment->courseOffering;

                if (! $offering) {
                    return null;
                }

                $enrolledCount = StudyPlanDetail::query()
                    ->where('course_offering_id', $offering->id)
                    ->count();

                $activeSchedule = $offering->courseSchedules->where('is_active', true)->first();

                return [
                    'id' => $offering->id,
                    'course_code' => $offering->course?->code ?? '-',
                    'course_name' => $offering->course?->name ?? '-',
                    'label' => $offering->label ?? '-',
                    'academic_year' => $offering->academicYear?->name ?? '-',
                    'study_program' => $offering->studyProgram?->name ?? '-',
                    'delivery_mode' => $activeSchedule?->delivery_mode ?? $offering->delivery_mode ?? '-',
                    'room' => $activeSchedule?->room?->name ?? '-',
                    'building' => $activeSchedule?->room?->building?->name ?? '-',
                    'enrolled_count' => $enrolledCount,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $this->hasOfferings = count($this->offerings) > 0;
        $this->applyFilters();
    }

    public function updatedSearch(): void
    {
        $this->applyFilters();
    }

    private function applyFilters(): void
    {
        $search = mb_strtolower(trim($this->search));

        $this->filteredOfferings = collect($this->offerings)
            ->filter(function (array $offering) use ($search) {
                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    $offering['course_code'] ?? '',
                    $offering['course_name'] ?? '',
                    $offering['label'] ?? '',
                    $offering['academic_year'] ?? '',
                    $offering['study_program'] ?? '',
                ]));

                return str_contains($haystack, $search);
            })
            ->values()
            ->all();
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Course Materials',
        ]);
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
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
            color: white;
        }

        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 16s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.85; }
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
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
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

    <div class="card modern-card hero-gradient mb-4" style="color: white;">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Materi Perkuliahan</div>
                            <h2 class="h2 mb-0" style="font-weight: 700;">Pilih Kelas</h2>
                            <div style="font-size: 1.05rem; opacity: 0.95; margin-top: 4px;">Kelola materi setiap kelas dari sini.</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge-modern" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                            📚 Total Kelas: {{ count($offerings) }}
                        </span>
                    </div>
                </div>
                <div class="col-lg-4">
                    <label class="form-label text-white" style="font-weight: 600;">Cari Kelas</label>
                    <input
                        type="text"
                        class="form-control filter-input"
                        wire:model.live.debounce.300ms="search"
                        placeholder="🔍 Cari kode/nama kelas..."
                    >
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @forelse($filteredOfferings as $offering)
            <div class="col-md-6 col-xl-4">
                <div class="course-card h-100">
                    <div class="course-header">
                        <span class="course-badge">{{ $offering['academic_year'] }}</span>
                        <div style="margin-bottom: 0.5rem;">
                            <span style="font-size: 0.85rem; color: #6b7280; font-weight: 600;">{{ $offering['course_code'] }}</span>
                        </div>
                        <h4 style="font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; font-size: 1.1rem;">
                            {{ $offering['course_name'] }}
                        </h4>
                        <div style="font-size: 0.85rem; color: #6b7280;">
                            {{ $offering['label'] }}
                        </div>
                    </div>

                    <div class="p-3">
                        <div class="mb-3" style="font-size: 0.85rem; color: #6b7280;">
                            <div class="d-flex justify-content-between align-items-center" style="padding: 0.5rem; background: #f9fafb; border-radius: 8px; margin-bottom: 0.5rem;">
                                <span>Program Studi</span>
                                <span style="font-weight: 600; color: #1f2937; text-align: right; max-width: 60%;">
                                    {{ $offering['study_program'] }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center" style="padding: 0.5rem; background: #f9fafb; border-radius: 8px; margin-bottom: 0.5rem;">
                                <span>Mahasiswa</span>
                                <span style="font-weight: 600; color: #1f2937;">{{ $offering['enrolled_count'] }} orang</span>
                            </div>
                            @if($offering['building'] !== '-' || $offering['room'] !== '-')
                                <div class="d-flex justify-content-between align-items-center" style="padding: 0.5rem; background: #f9fafb; border-radius: 8px; margin-bottom: 0.5rem;">
                                    <span>Ruangan</span>
                                    <span style="font-weight: 600; color: #1f2937;">
                                        <i class="fas fa-building me-1" style="color: #667eea;"></i>{{ $offering['building'] }} / {{ $offering['room'] }}
                                    </span>
                                </div>
                            @endif
                            @if($offering['delivery_mode'] !== '-')
                                <div class="d-flex justify-content-between align-items-center" style="padding: 0.5rem; background: #f9fafb; border-radius: 8px;">
                                    <span>Mode</span>
                                    <span style="font-weight: 600; color: #1f2937;">{{ $offering['delivery_mode'] }}</span>
                                </div>
                            @endif
                        </div>

                        <a href="{{ route('lecturer.course-materials.index', ['offeringId' => $offering['id']]) }}" class="action-btn action-btn-primary w-100 d-block text-center text-decoration-none">
                            <i class="fas fa-folder-open me-2"></i>Buka Materi
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card modern-card p-5 text-center">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <div class="mt-3" style="font-size: 1.1rem; color: #64748b; font-weight: 600;">
                        @if($search)
                            Tidak ada kelas yang sesuai pencarian.
                        @else
                            Belum ada kelas untuk dikelola.
                        @endif
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
