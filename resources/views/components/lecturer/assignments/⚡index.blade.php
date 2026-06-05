<?php

use App\Models\Academic\Assignment;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public array $offerings = [];
    public array $rows = [];
    public string $search = '';
    public string $statusFilter = 'all';
    public string $offeringFilter = 'all';

    public function mount(): void
    {
        $this->loadOfferings();
        $this->loadRows();
    }

    public function updatedSearch(): void
    {
        $this->loadRows();
    }

    public function updatedStatusFilter(): void
    {
        $this->loadRows();
    }

    public function updatedOfferingFilter(): void
    {
        $this->loadRows();
    }

    private function loadOfferings(): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        $this->offerings = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram'])
            ->latest('id')
            ->get()
            ->map(function (CourseOfferingLecturer $assignment) {
                $offering = $assignment->courseOffering;

                if (! $offering) {
                    return null;
                }

                return [
                    'id' => $offering->id,
                    'course_code' => $offering->course?->code ?? '-',
                    'course_name' => $offering->course?->name ?? '-',
                    'label' => $offering->label ?? '-',
                    'academic_year' => $offering->academicYear?->name ?? '-',
                    'study_program' => $offering->studyProgram?->name ?? '-',
                    'enrolled_count' => StudyPlanDetail::query()->where('course_offering_id', $offering->id)->count(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function loadRows(): void
    {
        $offeringIds = collect($this->offerings)->pluck('id');
        $search = trim($this->search);

        $this->rows = Assignment::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'submissions.grade'])
            ->whereIn('course_offering_id', $offeringIds)
            ->when($this->offeringFilter !== 'all', fn ($query) => $query->where('course_offering_id', $this->offeringFilter))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhereHas('courseOffering.course', fn ($course) => $course->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->orderBy('due_at')
            ->get()
            ->map(function (Assignment $assignment) {
                $enrolled = StudyPlanDetail::query()->where('course_offering_id', $assignment->course_offering_id)->count();
                $submitted = $assignment->submissions->count();
                $graded = $assignment->submissions->filter(fn ($submission) => $submission->grade?->status === 'graded')->count();
                $returned = $assignment->submissions->filter(fn ($submission) => $submission->grade?->status === 'returned')->count();

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'course' => trim(($assignment->courseOffering?->course?->code ? $assignment->courseOffering->course->code.' - ' : '').($assignment->courseOffering?->course?->name ?? '-')),
                    'label' => $assignment->courseOffering?->label ?? '-',
                    'academic_year' => $assignment->courseOffering?->academicYear?->name ?? '-',
                    'due_at' => $assignment->due_at?->format('d M Y H:i') ?? '-',
                    'due_human' => $assignment->due_at?->diffForHumans() ?? '-',
                    'is_published' => $assignment->is_published,
                    'is_past_due' => $assignment->isPastDue(),
                    'enrolled' => $enrolled,
                    'submitted' => $submitted,
                    'late' => $assignment->submissions->where('status', 'late')->count(),
                    'graded' => $graded,
                    'returned' => $returned,
                    'review_queue' => max(0, $submitted - $graded - $returned),
                    'progress' => $enrolled > 0 ? min(100, (int) round(($submitted / $enrolled) * 100)) : 0,
                ];
            })
            ->filter(fn ($row) => match ($this->statusFilter) {
                'review' => $row['review_queue'] > 0,
                'overdue' => $row['is_past_due'],
                'draft' => ! $row['is_published'],
                'published' => $row['is_published'],
                default => true,
            })
            ->values()
            ->all();
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Tugas',
        ]);
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.8rem;">
                        <i class="fas fa-clipboard-check"></i>
                    </span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Ruang Kerja Dosen</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Tugas Perkuliahan</h1>
                        <div style="opacity:.9;">Buat tugas, pantau submission, beri nilai, dan minta revisi dari satu tempat.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-book"></i>{{ count($offerings) }} kelas</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-list-check"></i>{{ count($rows) }} tugas</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-inbox"></i>{{ collect($rows)->sum('review_queue') }} perlu review</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2" style="min-width:min(100%, 620px);">
                    <div class="flex-grow-1" style="min-width:240px;">
                        <label class="form-label text-white fw-bold">Cari</label>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Judul atau mata kuliah...">
                    </div>
                    <div style="min-width:170px;">
                        <label class="form-label text-white fw-bold">Status</label>
                        <select class="form-control" wire:model.live="statusFilter">
                            <option value="all">Semua</option>
                            <option value="review">Perlu Review</option>
                            <option value="overdue">Lewat Deadline</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div style="min-width:210px;">
                        <label class="form-label text-white fw-bold">Kelas</label>
                        <select class="form-control" wire:model.live="offeringFilter">
                            <option value="all">Semua Kelas</option>
                            @foreach ($offerings as $offering)
                                <option value="{{ $offering['id'] }}">{{ $offering['course_code'] }} / {{ $offering['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Tugas Aktif</div><div class="h2 fw-bold mb-0">{{ collect($rows)->where('is_published', true)->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Submission</div><div class="h2 fw-bold mb-0 text-success">{{ collect($rows)->sum('submitted') }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Perlu Review</div><div class="h2 fw-bold mb-0 text-primary">{{ collect($rows)->sum('review_queue') }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Terlambat</div><div class="h2 fw-bold mb-0 text-danger">{{ collect($rows)->sum('late') }}</div></div></div>
    </div>

    <div class="card assignment-card mb-4">
        <div class="card-header py-3">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-plus-circle me-2 text-primary"></i>Buat dari Kelas</h3>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                @forelse ($offerings as $offering)
                    <div class="col-lg-6 col-xxl-4">
                        <div class="assignment-list-item h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <span class="assignment-pill mb-2"><i class="fas fa-calendar"></i>{{ $offering['academic_year'] }}</span>
                                    <div class="fw-bold">{{ $offering['course_code'] }} - {{ $offering['course_name'] }}</div>
                                    <div class="text-secondary small">{{ $offering['label'] }} / {{ $offering['study_program'] }}</div>
                                    <div class="text-secondary small mt-2"><i class="fas fa-users me-1"></i>{{ $offering['enrolled_count'] }} mahasiswa</div>
                                </div>
                            </div>
                            <a href="{{ route('lecturer.assignments.create', ['offeringId' => $offering['id']]) }}" class="assignment-action mt-3" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                <i class="fas fa-plus"></i>Buat Tugas
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center text-secondary py-4">Belum ada kelas aktif.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Daftar Tugas</h3>
            <span class="assignment-pill">{{ count($rows) }} tugas</span>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($rows as $row)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-6">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-clipboard-list"></i></span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill" style="{{ $row['is_published'] ? 'background:#dcfce7;color:#15803d;' : 'background:#f1f5f9;color:#64748b;' }}">{{ $row['is_published'] ? 'Published' : 'Draft' }}</span>
                                            @if ($row['is_past_due'])
                                                <span class="assignment-pill" style="background:#fee2e2;color:#dc2626;">Lewat Deadline</span>
                                            @endif
                                            @if ($row['review_queue'] > 0)
                                                <span class="assignment-pill" style="background:#dbeafe;color:#2563eb;">{{ $row['review_queue'] }} review</span>
                                            @endif
                                        </div>
                                        <div class="fw-bold">{{ $row['title'] }}</div>
                                        <div class="text-secondary small">{{ $row['course'] }} / {{ $row['label'] }}</div>
                                        <div class="text-secondary small mt-1"><i class="fas fa-clock me-1"></i>{{ $row['due_at'] }} ({{ $row['due_human'] }})</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3">
                                <div class="d-flex justify-content-between small mb-1"><span>Submission</span><strong>{{ $row['progress'] }}%</strong></div>
                                <div class="assignment-progress"><span style="width:{{ $row['progress'] }}%;"></span></div>
                                <div class="text-secondary small mt-2">
                                    {{ $row['submitted'] }}/{{ $row['enrolled'] }} submit
                                    @if ($row['graded']) / {{ $row['graded'] }} nilai @endif
                                    @if ($row['returned']) / {{ $row['returned'] }} revisi @endif
                                </div>
                            </div>
                            <div class="col-xl-3">
                                <div class="d-flex justify-content-xl-end gap-2 flex-wrap">
                                    <a href="{{ route('lecturer.assignments.show', ['id' => $row['id']]) }}" class="assignment-action" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;"><i class="fas fa-eye"></i>Review</a>
                                    <a href="{{ route('lecturer.assignments.edit', ['id' => $row['id']]) }}" class="btn btn-light" style="border-radius:12px;font-weight:800;"><i class="fas fa-pen"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div>Belum ada tugas.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
