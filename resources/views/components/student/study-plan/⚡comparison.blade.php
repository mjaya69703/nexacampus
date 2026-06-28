<?php

use App\Models\Academic\StudyPlan;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public array $studentInfo = [];
    public array $plans = [];
    public ?int $leftPlanId = null;
    public ?int $rightPlanId = null;
    public array $left = [];
    public array $right = [];
    public array $comparison = [];

    public function mount(): void
    {
        $studentProfile = auth()->user()?->studentProfile()
            ->with(['studyProgram.faculty'])
            ->first();

        if (! $studentProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->studentInfo = [
            'name' => auth()->user()?->name ?? '-',
            'nim' => $studentProfile->nim,
            'study_program' => $studentProfile->studyProgram?->name ?? '-',
            'faculty' => $studentProfile->studyProgram?->faculty?->name ?? '-',
            'semester' => $studentProfile->current_semester ?? '-',
        ];

        $studyPlans = StudyPlan::query()
            ->where('student_profile_id', $studentProfile->id)
            ->with(['academicYear', 'details.courseOffering.course'])
            ->orderByDesc('semester_no')
            ->orderByDesc('id')
            ->get();

        $this->plans = $studyPlans
            ->map(fn (StudyPlan $plan) => $this->planOption($plan))
            ->values()
            ->all();

        $this->leftPlanId = $studyPlans->get(1)?->id ?? $studyPlans->first()?->id;
        $this->rightPlanId = $studyPlans->first()?->id;

        $this->rebuildComparison($studyPlans);
    }

    public function updatedLeftPlanId(): void
    {
        $this->rebuildComparison();
    }

    public function updatedRightPlanId(): void
    {
        $this->rebuildComparison();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Perbandingan KRS',
            'pages' => 'Perbandingan KRS',
        ]);
    }

    private function rebuildComparison(?Collection $plans = null): void
    {
        $plans ??= $this->loadSelectedPlans();

        $leftPlan = $plans->firstWhere('id', (int) $this->leftPlanId);
        $rightPlan = $plans->firstWhere('id', (int) $this->rightPlanId);

        $this->left = $leftPlan ? $this->summarizePlan($leftPlan) : $this->emptyPlan();
        $this->right = $rightPlan ? $this->summarizePlan($rightPlan) : $this->emptyPlan();
        $this->comparison = $this->comparePlans($this->left, $this->right);
    }

    private function loadSelectedPlans(): Collection
    {
        $ids = collect([$this->leftPlanId, $this->rightPlanId])->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return StudyPlan::query()
            ->whereIn('id', $ids)
            ->where('student_profile_id', auth()->user()?->studentProfile?->id)
            ->with(['academicYear', 'details.courseOffering.course'])
            ->get();
    }

    private function planOption(StudyPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'label' => trim(($plan->academicYear?->name ?? 'Tahun akademik').' - Semester '.($plan->semester_no ?? '-')),
            'status' => $plan->status,
        ];
    }

    private function summarizePlan(StudyPlan $plan): array
    {
        $courses = $this->courseRows($plan);

        return [
            'id' => $plan->id,
            'title' => $plan->academicYear?->name ?? 'Tahun akademik',
            'semester' => $plan->semester_no ?? '-',
            'status' => $plan->status,
            'submitted_at' => $plan->submitted_at?->format('d M Y H:i') ?? '-',
            'approved_at' => $plan->approved_at?->format('d M Y H:i') ?? '-',
            'total_courses' => $courses->count(),
            'total_credits' => (int) $courses->sum('credits'),
            'required_count' => $courses->where('is_required', true)->count(),
            'repeat_count' => $courses->where('is_repeat', true)->count(),
            'courses' => $courses->values()->all(),
        ];
    }

    private function courseRows(StudyPlan $plan): Collection
    {
        return $plan->details
            ->filter(fn ($detail) => filled($detail->courseOffering?->course))
            ->map(function ($detail) {
                $offering = $detail->courseOffering;
                $course = $offering?->course;

                return [
                    'course_id' => $course?->id,
                    'code' => $course?->code ?? $offering?->code ?? '-',
                    'name' => $course?->name ?? '-',
                    'credits' => (int) ($detail->credits ?? $offering?->credits ?? $course?->credits ?? 0),
                    'semester' => $offering?->semester_no ?? $course?->semester_recommendation ?? '-',
                    'status' => $detail->status,
                    'is_required' => (bool) ($offering?->is_required ?? true),
                    'is_repeat' => (bool) $detail->is_repeat,
                    'label' => $offering?->label ?? '-',
                ];
            })
            ->sortBy(fn (array $course) => sprintf('%02d-%s', (int) $course['semester'], $course['code']))
            ->values();
    }

    private function comparePlans(array $left, array $right): array
    {
        $leftCourses = collect($left['courses'] ?? [])->keyBy('course_id');
        $rightCourses = collect($right['courses'] ?? [])->keyBy('course_id');

        $same = $leftCourses->keys()->intersect($rightCourses->keys())->values();
        $added = $rightCourses->keys()->diff($leftCourses->keys())->values();
        $removed = $leftCourses->keys()->diff($rightCourses->keys())->values();

        return [
            'same_count' => $same->count(),
            'added_count' => $added->count(),
            'removed_count' => $removed->count(),
            'credit_delta' => (int) (($right['total_credits'] ?? 0) - ($left['total_credits'] ?? 0)),
            'same' => $same->map(fn ($courseId) => $rightCourses->get($courseId))->values()->all(),
            'added' => $added->map(fn ($courseId) => $rightCourses->get($courseId))->values()->all(),
            'removed' => $removed->map(fn ($courseId) => $leftCourses->get($courseId))->values()->all(),
        ];
    }

    private function emptyPlan(): array
    {
        return [
            'id' => null,
            'title' => '-',
            'semester' => '-',
            'status' => '-',
            'submitted_at' => '-',
            'approved_at' => '-',
            'total_courses' => 0,
            'total_credits' => 0,
            'required_count' => 0,
            'repeat_count' => 0,
            'courses' => [],
        ];
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Approved', 'Taken' => 'bg-green-lt text-green',
            'Submitted', 'Draft' => 'bg-yellow-lt text-yellow',
            'Rejected', 'Dropped', 'Cancelled' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'Approved' => 'Disetujui',
            'Submitted' => 'Diajukan',
            'Draft' => 'Draf',
            'Rejected' => 'Ditolak',
            'Taken' => 'Diambil',
            'Dropped' => 'Dibatalkan',
            'Cancelled' => 'Dibatalkan',
            '-' => '-',
            default => $status,
        };
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
            font-weight: 700;
            backdrop-filter: blur(10px);
        }

        .hero-mini-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            min-height: 96px;
        }

        .hero-mini-label {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .hero-mini-value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
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

        .section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }

        .course-card {
            padding: 1rem;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .course-card + .course-card {
            margin-top: 0.75rem;
        }

        .course-card:hover {
            background: #f1f5f9;
            border-color: #667eea;
        }

        .credit-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            min-height: 34px;
            border-radius: 10px;
            font-weight: 700;
            white-space: nowrap;
        }
    </style>
@endpush

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-danger">Profil mahasiswa belum tersedia. Hubungi admin akademik.</div>
    @else
        <div class="card modern-card hero-gradient mb-4" style="color: white;">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start gap-3">
                            <div class="hero-icon">
                                <i class="fas fa-code-branch"></i>
                            </div>

                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.25rem;">Perbandingan KRS</div>
                                <h1 class="h2 mb-2" style="font-weight: 700;">{{ $studentInfo['name'] }}</h1>
                                <div style="opacity: 0.9; margin-bottom: 1rem;">
                                    <i class="fas fa-id-card me-2"></i>{{ $studentInfo['nim'] }} &bull; {{ $studentInfo['study_program'] }}
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-white text-primary" style="border-radius: 999px; padding: 0.55rem 0.8rem;">
                                        <i class="fas fa-calendar me-2"></i>Semester {{ $studentInfo['semester'] }}
                                    </span>
                                    <span class="badge bg-white text-primary" style="border-radius: 999px; padding: 0.55rem 0.8rem;">
                                        <i class="fas fa-university me-2"></i>{{ $studentInfo['faculty'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="hero-mini-card">
                                    <div class="hero-mini-label">Selisih SKS</div>
                                    <div class="hero-mini-value">{{ $comparison['credit_delta'] >= 0 ? '+' : '' }}{{ $comparison['credit_delta'] }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="hero-mini-card">
                                    <div class="hero-mini-label">Mata Kuliah Baru</div>
                                    <div class="hero-mini-value">{{ $comparison['added_count'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (count($plans) < 1)
            <div class="card modern-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                    <h3 class="mb-2">Belum Ada Riwayat KRS</h3>
                    <p class="text-secondary mb-0">KRS yang pernah dibuat akan muncul di sini untuk dibandingkan antar semester.</p>
                </div>
            </div>
        @else
            <div class="card modern-card mb-4">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;">
                        <i class="fas fa-sliders me-2" style="color: #667eea;"></i>Pilih KRS
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">KRS pembanding</label>
                            <select class="form-select" wire:model.live="leftPlanId">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan['id'] }}">{{ $plan['label'] }} - {{ $this->statusLabel($plan['status']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">KRS tujuan</label>
                            <select class="form-select" wire:model.live="rightPlanId">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan['id'] }}">{{ $plan['label'] }} - {{ $this->statusLabel($plan['status']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('student.study-plan.index') }}" class="btn btn-outline-primary w-100" style="border-radius: 8px;">
                                <i class="fas fa-table-list me-2"></i>Buka KRS
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                @foreach ([['KRS pembanding', $left], ['KRS tujuan', $right]] as [$label, $plan])
                    <div class="col-lg-6">
                        <div class="card modern-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                                <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;">
                                    <i class="fas fa-list-check me-2" style="color: #10b981;"></i>{{ $label }}
                                </h3>
                                <span class="badge {{ $this->statusClass($plan['status']) }}">{{ $this->statusLabel($plan['status']) }}</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <div class="text-secondary small">Tahun akademik</div>
                                        <div class="section-title">{{ $plan['title'] }}</div>
                                        <div class="text-secondary">Semester {{ $plan['semester'] }}</div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">MK</div><div class="stat-value">{{ $plan['total_courses'] }}</div></div></div>
                                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">SKS</div><div class="stat-value">{{ $plan['total_credits'] }}</div></div></div>
                                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Wajib</div><div class="stat-value">{{ $plan['required_count'] }}</div></div></div>
                                    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Ulang</div><div class="stat-value">{{ $plan['repeat_count'] }}</div></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-label">Tetap Diambil</div><div class="stat-value">{{ $comparison['same_count'] }}</div></div></div>
                <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-label">Ditambahkan</div><div class="stat-value text-green">{{ $comparison['added_count'] }}</div></div></div>
                <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-label">Tidak Ada Lagi</div><div class="stat-value text-red">{{ $comparison['removed_count'] }}</div></div></div>
                <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-label">Selisih SKS</div><div class="stat-value">{{ $comparison['credit_delta'] >= 0 ? '+' : '' }}{{ $comparison['credit_delta'] }}</div></div></div>
            </div>

            <div class="row g-3">
                @foreach ([['Ditambahkan', 'added', 'bg-green-lt text-green', 'fa-plus-circle', '#10b981'], ['Tidak Ada Lagi', 'removed', 'bg-red-lt text-red', 'fa-minus-circle', '#ef4444'], ['Tetap Diambil', 'same', 'bg-blue-lt text-blue', 'fa-check-circle', '#3b82f6']] as [$title, $key, $badge, $icon, $color])
                    <div class="col-lg-4">
                        <div class="card modern-card h-100">
                            <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                                <h3 class="card-title mb-0" style="font-weight: 700; color: #1f2937;">
                                    <i class="fas {{ $icon }} me-2" style="color: {{ $color }};"></i>{{ $title }}
                                </h3>
                            </div>
                            <div class="card-body p-4">
                                @forelse ($comparison[$key] as $course)
                                    <div class="course-card">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; color: #1f2937; margin-bottom: 0.25rem;">{{ $course['name'] }}</div>
                                                <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.5rem;">
                                                    <i class="fas fa-hashtag me-1" style="color: #667eea;"></i>{{ $course['code'] }}
                                                    <span class="mx-2">&bull;</span>
                                                    <i class="fas fa-calendar me-1" style="color: #f59e0b;"></i>Semester {{ $course['semester'] }}
                                                </div>
                                                <div style="font-size: 0.8rem; color: #9ca3af;">
                                                    <i class="fas fa-tag me-1"></i>{{ $course['label'] }}
                                                    @if ($course['is_repeat'])
                                                        <span class="badge bg-yellow-lt text-yellow ms-2">Ulang</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="credit-pill {{ $badge }}">{{ $course['credits'] }} SKS</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-secondary py-4">
                                        <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 0.5rem;"></i>
                                        Tidak ada mata kuliah pada kategori ini.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
