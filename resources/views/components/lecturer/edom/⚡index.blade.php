<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomResponse;
use App\Models\Organization\LecturerPerformanceReview;
use App\Support\Organization\EdomService;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;
    public array $lecturerInfo = [];
    public array $periodOptions = [];
    public ?int $selectedPeriodId = null;
    public array $summary = [];
    public array $courseRows = [];
    public array $comments = [];
    public array $review = [];

    public function mount(): void
    {
        $this->initializeDefaults();

        $user = auth()->user();
        $lecturerProfile = $user?->lecturerProfile()
            ->with(['studyProgram', 'faculty'])
            ->first();

        if (! $user || ! $lecturerProfile) {
            return;
        }

        $this->hasProfile = true;
        $this->lecturerInfo = [
            'name' => $user->name,
            'nidn' => $lecturerProfile->nidn ?? '-',
            'study_program' => $lecturerProfile->studyProgram?->name ?? '-',
            'faculty' => $lecturerProfile->faculty?->name ?? '-',
        ];

        $periodIds = EdomResponse::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->pluck('edom_period_id')
            ->merge(LecturerPerformanceReview::query()
                ->where('lecturer_profile_id', $lecturerProfile->id)
                ->whereNotNull('edom_period_id')
                ->pluck('edom_period_id'))
            ->filter()
            ->unique()
            ->values();

        $periods = EdomPeriod::query()
            ->whereIn('id', $periodIds)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        $this->periodOptions = $periods
            ->map(fn (EdomPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'status' => $period->status,
                'range' => trim(($period->starts_at?->format('d M Y') ?? '-').' - '.($period->ends_at?->format('d M Y') ?? '-')),
            ])
            ->values()
            ->all();

        $this->selectedPeriodId = $periods->first()?->id;
        $this->loadResult();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Evaluasi',
            'pages' => 'Hasil EDOM Saya',
        ]);
    }

    public function updatedSelectedPeriodId(): void
    {
        $this->loadResult();
    }

    public function scoreStyle($score): string
    {
        if ($score === null || $score === '-') {
            return 'background:#f1f5f9;color:#64748b;';
        }

        $score = (float) $score;

        if ($score >= 4) {
            return 'background:#dcfce7;color:#15803d;';
        }

        if ($score >= 3) {
            return 'background:#fef3c7;color:#b45309;';
        }

        return 'background:#fee2e2;color:#dc2626;';
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draf',
            'open' => 'Dibuka',
            'closed' => 'Ditutup',
            'calculated' => 'Dihitung',
            'published' => 'Terbit',
            default => $status ?: '-',
        };
    }

    private function loadResult(): void
    {
        $this->summary = [
            'average' => null,
            'responses' => 0,
            'available' => false,
            'minimum' => 0,
            'period_status' => '-',
        ];
        $this->courseRows = [];
        $this->comments = [];
        $this->review = [];

        $lecturerProfile = auth()->user()?->lecturerProfile;

        if (! $lecturerProfile || ! $this->selectedPeriodId) {
            return;
        }

        $period = EdomPeriod::query()->find($this->selectedPeriodId);

        if (! $period) {
            return;
        }

        $aggregate = app(EdomService::class)->lecturerAggregate($lecturerProfile->id, $period);
        $this->summary = [
            'average' => $aggregate['average'],
            'responses' => $aggregate['responses'],
            'available' => (bool) $aggregate['available'],
            'minimum' => (int) $period->minimum_responses,
            'period_status' => $period->status,
        ];

        $review = LecturerPerformanceReview::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('edom_period_id', $period->id)
            ->latest('calculated_at')
            ->first();

        if ($review) {
            $this->review = [
                'final_score' => $review->final_score ? number_format((float) $review->final_score, 2) : '-',
                'teaching' => $review->teaching_compliance_score ? number_format((float) $review->teaching_compliance_score, 2).'%' : '-',
                'attendance' => $review->attendance_compliance_score ? number_format((float) $review->attendance_compliance_score, 2).'%' : '-',
                'workload' => $review->workload_total_sks ? number_format((float) $review->workload_total_sks, 2).' SKS' : '-',
                'status' => $this->statusLabel($review->status),
                'calculated_at' => $review->calculated_at?->format('d M Y H:i') ?? '-',
            ];
        }

        if (! $aggregate['available']) {
            return;
        }

        $responses = EdomResponse::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('edom_period_id', $period->id)
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'answers.question'])
            ->get();

        $this->courseRows = $responses
            ->groupBy('course_offering_id')
            ->map(function ($items) {
                $first = $items->first();
                $scores = $items->flatMap(fn (EdomResponse $response) => $response->answers->pluck('score')->filter());

                return [
                    'course' => trim(($first->courseOffering?->course?->code ? $first->courseOffering->course->code.' - ' : '').($first->courseOffering?->course?->name ?? '-')),
                    'label' => $first->courseOffering?->label ?? '-',
                    'academic_year' => $first->courseOffering?->academicYear?->name ?? '-',
                    'responses' => $items->count(),
                    'average' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null,
                ];
            })
            ->sortByDesc('average')
            ->values()
            ->all();

        $this->comments = $aggregate['comments']
            ->take(12)
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
        ];

        $this->summary = [
            'average' => null,
            'responses' => 0,
            'available' => false,
            'minimum' => 0,
            'period_status' => '-',
        ];
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

<div>
    <x-alert />

    @if (! $hasProfile)
        <div class="alert alert-danger">Profil dosen belum tersedia. Hubungi admin akademik.</div>
    @else
        <div class="card assignment-card assignment-hero mb-4">
            <div class="card-body p-4 p-lg-5" style="position:relative;">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <div class="d-flex gap-3">
                        <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-star-half-stroke"></i></span>
                        <div>
                            <div style="opacity:.86;font-weight:700;">Hasil EDOM Saya</div>
                            <h1 class="h2 mb-2" style="font-weight:900;">{{ $lecturerInfo['name'] }}</h1>
                            <div style="opacity:.9;">{{ $lecturerInfo['nidn'] }} &bull; {{ $lecturerInfo['study_program'] }} &bull; {{ $lecturerInfo['faculty'] }}</div>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <span class="assignment-pill assignment-info-pill"><i class="fas fa-comments"></i>{{ $summary['responses'] }} respon</span>
                                <span class="assignment-pill assignment-info-pill"><i class="fas fa-user-secret"></i>Anonim</span>
                                <span class="assignment-pill assignment-info-pill"><i class="fas fa-calendar-check"></i>{{ $this->statusLabel($summary['period_status']) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="assignment-panel" style="min-width:min(100%, 260px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                        <div style="opacity:.86;font-weight:700;">Rata-rata EDOM</div>
                        <div class="h2 mb-0" style="font-weight:900;">{{ $summary['available'] && $summary['average'] ? number_format((float) $summary['average'], 2) : '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card assignment-card mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-filter me-2 text-primary"></i>Periode Evaluasi</h3>
                <span class="assignment-pill">{{ count($periodOptions) }} periode</span>
            </div>
            <div class="card-body p-4">
                @if (count($periodOptions) > 0)
                    <label class="form-label">Pilih periode EDOM</label>
                    <select class="form-select" wire:model.live="selectedPeriodId">
                        @foreach ($periodOptions as $period)
                            <option value="{{ $period['id'] }}">{{ $period['name'] }} - {{ $this->statusLabel($period['status']) }} - {{ $period['range'] }}</option>
                        @endforeach
                    </select>
                @else
                    <div class="text-secondary">Belum ada hasil EDOM untuk kelas yang Anda ampu.</div>
                @endif
            </div>
        </div>

        @if (! $summary['available'] && $summary['responses'] > 0)
            <div class="assignment-panel mb-4" style="background:#fffbeb;border-color:#fde68a;">
                <div class="fw-bold text-warning"><i class="fas fa-triangle-exclamation me-2"></i>Hasil agregat belum ditampilkan</div>
                <div class="text-secondary mt-1">Hasil EDOM ditampilkan setelah periode ditutup dan minimal {{ $summary['minimum'] }} respon terpenuhi. Saat ini ada {{ $summary['responses'] }} respon.</div>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Respon</div><div class="h2 fw-bold mb-0">{{ $summary['responses'] }}</div></div></div>
            <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Rata-rata EDOM</div><div class="h2 fw-bold text-success mb-0">{{ $summary['available'] && $summary['average'] ? number_format((float) $summary['average'], 2) : '-' }}</div></div></div>
            <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Minimum Respon</div><div class="h2 fw-bold text-primary mb-0">{{ $summary['minimum'] }}</div></div></div>
            <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Skor Akhir</div><div class="h2 fw-bold text-warning mb-0">{{ $review['final_score'] ?? '-' }}</div></div></div>
        </div>

        @if ($review)
            <div class="card assignment-card mb-4">
                <div class="card-header py-3">
                    <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-chart-line me-2 text-primary"></i>Review Performa</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach ([['Status', $review['status']], ['Mengajar', $review['teaching']], ['Absensi', $review['attendance']], ['BKD', $review['workload']], ['Dihitung', $review['calculated_at']]] as [$label, $value])
                            <div class="col-md">
                                <div class="assignment-panel h-100">
                                    <div class="text-secondary">{{ $label }}</div>
                                    <div class="fw-bold mt-1">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-xl-7">
                <div class="card assignment-card h-100">
                    <div class="card-header py-3">
                        <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Ringkasan per Kelas</h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="assignment-shell">
                            @forelse ($courseRows as $row)
                                <div class="assignment-list-item">
                                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                                        <div>
                                            <div class="fw-bold">{{ $row['course'] }}</div>
                                            <div class="text-secondary small">{{ $row['label'] }} &bull; {{ $row['academic_year'] }}</div>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <span class="assignment-pill" style="{{ $this->scoreStyle($row['average']) }}">EDOM {{ $row['average'] ? number_format((float) $row['average'], 2) : '-' }}</span>
                                            <span class="assignment-pill">{{ $row['responses'] }} respon</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-secondary py-5">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <div>Ringkasan kelas belum tersedia untuk periode ini.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card assignment-card h-100">
                    <div class="card-header py-3">
                        <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-comment-dots me-2 text-primary"></i>Komentar Anonim</h3>
                    </div>
                    <div class="card-body p-4">
                        <div class="assignment-shell">
                            @forelse ($comments as $comment)
                                <div class="assignment-panel">
                                    <div class="assignment-pill mb-2">{{ $comment['course'] }}</div>
                                    <div>{{ $comment['comment'] }}</div>
                                </div>
                            @empty
                                <div class="text-center text-secondary py-5">
                                    <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                    <div>Belum ada komentar anonim yang dapat ditampilkan.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
