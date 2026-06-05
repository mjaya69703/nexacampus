<?php

use App\Models\Academic\Assignment;
use App\Models\Academic\StudyPlan;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public array $assignments = [];
    public string $filter = 'all';
    public string $search = '';

    public function mount(): void
    {
        $this->loadAssignments();
    }

    public function updatedSearch(): void
    {
        $this->loadAssignments();
    }

    public function applyFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->loadAssignments();
    }

    private function loadAssignments(): void
    {
        $studentProfileId = auth()->user()?->studentProfile?->id;

        if (! $studentProfileId) {
            $this->assignments = [];

            return;
        }

        $offeringIds = StudyPlan::query()
            ->where('student_profile_id', $studentProfileId)
            ->with('details')
            ->get()
            ->flatMap(fn (StudyPlan $plan) => $plan->details->pluck('course_offering_id'))
            ->unique()
            ->values();

        $search = trim($this->search);

        $this->assignments = Assignment::query()
            ->published()
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'submissions' => fn ($query) => $query->where('student_profile_id', $studentProfileId)->with(['files', 'grade']),
            ])
            ->whereIn('course_offering_id', $offeringIds)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhereHas('courseOffering.course', fn ($course) => $course->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->orderBy('due_at')
            ->get()
            ->map(function (Assignment $assignment) {
                $submission = $assignment->submissions->first();
                $grade = $submission?->grade;
                $gradeStatus = in_array($grade?->status, ['graded', 'returned'], true) ? $grade->status : null;
                $status = $gradeStatus ?? $submission?->status ?? ($assignment->isPastDue() ? 'missing' : 'not_submitted');

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'course' => trim(($assignment->courseOffering?->course?->code ? $assignment->courseOffering->course->code.' - ' : '').($assignment->courseOffering?->course?->name ?? '-')),
                    'label' => $assignment->courseOffering?->label ?? '-',
                    'academic_year' => $assignment->courseOffering?->academicYear?->name ?? '-',
                    'due_at' => $assignment->due_at?->format('d M Y H:i') ?? '-',
                    'due_human' => $assignment->due_at?->diffForHumans() ?? '-',
                    'is_past_due' => $assignment->isPastDue(),
                    'status' => $status,
                    'score' => $grade?->score,
                    'max_score' => $assignment->max_score,
                    'submitted_at' => $submission?->submitted_at?->format('d M Y H:i'),
                    'files_count' => $submission?->files?->count() ?? 0,
                ];
            })
            ->filter(fn ($row) => match ($this->filter) {
                'open' => $row['status'] === 'not_submitted',
                'submitted' => in_array($row['status'], ['submitted', 'late', 'graded', 'returned'], true),
                'graded' => $row['status'] === 'graded',
                'returned' => $row['status'] === 'returned',
                'late' => in_array($row['status'], ['late', 'missing'], true),
                default => true,
            })
            ->values()
            ->all();
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Submitted',
            'late' => 'Submitted Late',
            'graded' => 'Dinilai',
            'returned' => 'Perlu Revisi',
            'missing' => 'Missing',
            default => 'Belum Submit',
        };
    }

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'submitted' => 'background:#dcfce7;color:#15803d;',
            'late' => 'background:#ffedd5;color:#ea580c;',
            'graded' => 'background:#dbeafe;color:#2563eb;',
            'returned' => 'background:#fef3c7;color:#b45309;',
            'missing' => 'background:#fee2e2;color:#dc2626;',
            default => 'background:#f1f5f9;color:#64748b;',
        };
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-clipboard-check"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Ruang Belajar Mahasiswa</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Tugas Kuliah</h1>
                        <div style="opacity:.9;">Pantau deadline, kirim submission, dan baca feedback dosen tanpa pindah-pindah halaman.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-layer-group"></i>{{ count($assignments) }} tugas</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ collect($assignments)->whereIn('status', ['submitted', 'late', 'graded'])->count() }} submitted</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-rotate-left"></i>{{ collect($assignments)->where('status', 'returned')->count() }} revisi</span>
                        </div>
                    </div>
                </div>
                <div style="min-width:min(100%, 320px);">
                    <label class="form-label text-white fw-bold">Cari Tugas</label>
                    <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Judul atau mata kuliah...">
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Tampil</div><div class="h2 fw-bold mb-0">{{ count($assignments) }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Belum Submit</div><div class="h2 fw-bold mb-0 text-warning">{{ collect($assignments)->where('status', 'not_submitted')->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Dinilai</div><div class="h2 fw-bold mb-0 text-primary">{{ collect($assignments)->where('status', 'graded')->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Missing/Telat</div><div class="h2 fw-bold mb-0 text-danger">{{ collect($assignments)->whereIn('status', ['missing', 'late'])->count() }}</div></div></div>
    </div>

    <div class="card assignment-card mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap gap-2">
                @foreach ([['all', 'Semua'], ['open', 'Belum Submit'], ['submitted', 'Sudah Submit'], ['graded', 'Dinilai'], ['returned', 'Revisi'], ['late', 'Telat/Missing']] as [$key, $label])
                    <button type="button" wire:click="applyFilter('{{ $key }}')" class="btn {{ $filter === $key ? 'btn-primary' : 'btn-light' }}" style="border-radius:999px;font-weight:800;">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Daftar Tugas</h3>
            <span class="assignment-pill">{{ count($assignments) }} tugas</span>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($assignments as $assignment)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-7">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-clipboard-list"></i></span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill" style="{{ $this->statusStyle($assignment['status']) }}">{{ $this->statusLabel($assignment['status']) }}</span>
                                            @if ($assignment['is_past_due'])
                                                <span class="assignment-pill" style="background:#fee2e2;color:#dc2626;">Deadline Lewat</span>
                                            @endif
                                        </div>
                                        <div class="fw-bold">{{ $assignment['title'] }}</div>
                                        <div class="text-secondary small">{{ $assignment['course'] }} / {{ $assignment['label'] }}</div>
                                        <div class="text-secondary small mt-1"><i class="fas fa-clock me-1"></i>{{ $assignment['due_at'] }} ({{ $assignment['due_human'] }})</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3">
                                <div class="assignment-panel">
                                    <div class="text-secondary small">{{ $assignment['status'] === 'graded' ? 'Nilai' : 'Submission' }}</div>
                                    @if ($assignment['status'] === 'graded')
                                        <div class="fw-bold text-primary">{{ number_format((float) $assignment['score'], 2) }} / {{ number_format((float) $assignment['max_score'], 2) }}</div>
                                        <div class="text-secondary small">Feedback tersedia</div>
                                    @else
                                        <div class="fw-bold">{{ $assignment['submitted_at'] ?? 'Belum submit' }}</div>
                                        @if ($assignment['files_count'])
                                            <div class="text-secondary small">{{ $assignment['files_count'] }} file</div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="col-xl-2 text-xl-end">
                                <a href="{{ route('student.assignments.show', ['id' => $assignment['id']]) }}" class="assignment-action" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                    <i class="fas fa-eye"></i>Buka
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div>Belum ada tugas yang tersedia.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
