<?php

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\GradeAppeal;
use App\Support\StudentGradeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public bool $hasProfile = false;

    public string $statusFilter = 'active';

    public array $appealRows = [];

    public array $summary = [
        'submitted' => 0,
        'under_review' => 0,
        'resolved' => 0,
    ];

    public ?int $selectedAppealId = null;

    public string $decision = 'approved';

    public string $lecturerResponse = '';

    public ?string $resolvedScore = null;

    public function mount(): void
    {
        $this->loadData();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Keberatan Nilai',
        ]);
    }

    public function updatedStatusFilter(): void
    {
        $this->loadData();
    }

    public function startReview(int $appealId): void
    {
        $appeal = $this->authorizedAppeal($appealId);

        if ($appeal->status !== 'submitted') {
            return;
        }

        $appeal->update([
            'status' => 'under_review',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        $this->loadData();
        $appeal->refresh()->load('studentGrade');
        $this->primeResolutionForm($appeal, 'approved');
        session()->flash('success', 'Pengajuan dipindahkan ke status review. Isi skor koreksi dan respons untuk menyelesaikan review.');
    }

    public function openResolution(int $appealId, string $decision): void
    {
        $appeal = $this->authorizedAppeal($appealId);

        if (! in_array($appeal->status, ['submitted', 'under_review'], true)) {
            return;
        }

        $this->primeResolutionForm($appeal, $decision);
    }

    public function cancelResolution(): void
    {
        $this->resetResolutionForm();
    }

    public function resolveAppeal(): void
    {
        $this->validate([
            'selectedAppealId' => ['required', 'integer'],
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'lecturerResponse' => ['required', 'string', 'min:10', 'max:4000'],
            'resolvedScore' => [$this->decision === 'approved' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'lecturerResponse.min' => 'Berikan respons minimal 10 karakter.',
            'resolvedScore.required' => 'Isi skor koreksi saat menyetujui pengajuan.',
        ]);

        $appeal = $this->authorizedAppeal((int) $this->selectedAppealId);

        if (! in_array($appeal->status, ['submitted', 'under_review'], true)) {
            $this->resetResolutionForm();
            $this->loadData();

            return;
        }

        DB::transaction(function () use ($appeal) {
            $resolvedScore = $this->decision === 'approved' ? (float) $this->resolvedScore : null;

            if ($this->decision === 'approved') {
                $calculator = app(StudentGradeCalculator::class);
                $letterGrade = $calculator->resolveLetterGrade($resolvedScore);

                $appeal->studentGrade->update([
                    'final_score' => $resolvedScore,
                    'letter_grade' => $letterGrade,
                    'grade_point' => $calculator->resolveGradePoint($letterGrade),
                    'result_status' => $calculator->resolveResultStatus($letterGrade),
                    'graded_by' => auth()->id(),
                    'graded_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
            }

            $appeal->update([
                'status' => $this->decision,
                'lecturer_response' => $this->lecturerResponse,
                'resolved_score' => $resolvedScore,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => $appeal->reviewed_at ?? now(),
                'resolved_at' => now(),
                'updated_by' => auth()->id(),
            ]);
        });

        $this->resetResolutionForm();
        $this->loadData();

        session()->flash('success', 'Keputusan keberatan nilai berhasil disimpan.');
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'submitted' => 'Masuk',
            'under_review' => 'Direview',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'closed' => 'Ditutup',
            default => $status ?: '-',
        };
    }

    public function statusStyle(?string $status): string
    {
        return match ($status) {
            'approved' => 'background:#dcfce7;color:#15803d;',
            'rejected' => 'background:#fee2e2;color:#dc2626;',
            'under_review' => 'background:#dbeafe;color:#1d4ed8;',
            'closed' => 'background:#f1f5f9;color:#64748b;',
            default => 'background:#fef3c7;color:#b45309;',
        };
    }

    public function categoryLabel(?string $category): string
    {
        return match ($category) {
            'calculation' => 'Perhitungan nilai',
            'component' => 'Komponen penilaian',
            'input_error' => 'Kesalahan input',
            'feedback' => 'Butuh klarifikasi',
            'other' => 'Lainnya',
            default => $category ?: '-',
        };
    }

    private function loadData(): void
    {
        $lecturerProfile = auth()->user()?->lecturerProfile()->first();

        if (! $lecturerProfile) {
            $this->hasProfile = false;
            $this->appealRows = [];

            return;
        }

        $this->hasProfile = true;

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->pluck('course_offering_id');

        $baseQuery = GradeAppeal::query()
            ->where(function ($query) use ($lecturerProfile, $offeringIds) {
                $query->where('lecturer_profile_id', $lecturerProfile->id)
                    ->orWhereIn('course_offering_id', $offeringIds);
            });

        $summaryRows = (clone $baseQuery)->get(['status']);
        $this->summary = [
            'submitted' => $summaryRows->where('status', 'submitted')->count(),
            'under_review' => $summaryRows->where('status', 'under_review')->count(),
            'resolved' => $summaryRows->whereIn('status', ['approved', 'rejected', 'closed'])->count(),
        ];

        $query = (clone $baseQuery)
            ->with([
                'studentProfile.user',
                'studentGrade.components',
                'courseOffering.course',
                'courseOffering.academicYear',
                'attachments',
            ]);

        if ($this->statusFilter === 'active') {
            $query->whereIn('status', ['submitted', 'under_review']);
        } elseif ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $this->appealRows = $query
            ->latest('submitted_at')
            ->latest('id')
            ->get()
            ->map(function (GradeAppeal $appeal) {
                $course = $appeal->courseOffering?->course;
                $grade = $appeal->studentGrade;

                return [
                    'id' => $appeal->id,
                    'student' => $appeal->studentProfile?->user?->name ?? '-',
                    'nim' => $appeal->studentProfile?->nim ?? '-',
                    'course' => trim(($course?->code ? $course->code.' - ' : '').($course?->name ?? '-')),
                    'academic_year' => $appeal->courseOffering?->academicYear?->name ?? '-',
                    'status' => $appeal->status,
                    'category' => $appeal->reason_category,
                    'reason' => $appeal->reason,
                    'expected_outcome' => $appeal->expected_outcome,
                    'response' => $appeal->lecturer_response,
                    'study_plan_detail_id' => $grade?->study_plan_detail_id,
                    'original_score' => $appeal->original_score,
                    'current_score' => $grade?->final_score,
                    'current_letter' => $grade?->letter_grade ?? '-',
                    'requested_score' => $appeal->requested_score,
                    'resolved_score' => $appeal->resolved_score,
                    'submitted_at' => $appeal->submitted_at?->format('d M Y H:i') ?? '-',
                    'resolved_at' => $appeal->resolved_at?->format('d M Y H:i'),
                    'attachments' => $appeal->attachments
                        ->map(fn ($attachment) => [
                            'id' => $attachment->id,
                            'name' => $attachment->file_name,
                            'size' => $attachment->file_size,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function authorizedAppeal(int $appealId): GradeAppeal
    {
        $lecturerProfile = auth()->user()?->lecturerProfile()->first();

        if (! $lecturerProfile) {
            abort(403);
        }

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->where('is_active', true)
            ->pluck('course_offering_id');

        return GradeAppeal::query()
            ->with(['studentGrade'])
            ->where('id', $appealId)
            ->where(function ($query) use ($lecturerProfile, $offeringIds) {
                $query->where('lecturer_profile_id', $lecturerProfile->id)
                    ->orWhereIn('course_offering_id', $offeringIds);
            })
            ->firstOrFail();
    }

    private function resetResolutionForm(): void
    {
        $this->selectedAppealId = null;
        $this->decision = 'approved';
        $this->lecturerResponse = '';
        $this->resolvedScore = null;
    }

    private function primeResolutionForm(GradeAppeal $appeal, string $decision): void
    {
        $this->selectedAppealId = $appeal->id;
        $this->decision = $decision === 'rejected' ? 'rejected' : 'approved';
        $this->lecturerResponse = $appeal->lecturer_response ?? '';
        $this->resolvedScore = $this->decision === 'approved'
            ? (string) ($appeal->requested_score ?? $appeal->studentGrade?->final_score ?? '')
            : null;
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
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.75rem;"><i class="fas fa-scale-balanced"></i></span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Evaluasi Akademik</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Keberatan Nilai</h1>
                        <div style="opacity:.9;">Tinjau klarifikasi mahasiswa, beri keputusan, dan koreksi skor jika pengajuan valid.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-inbox"></i>{{ $summary['submitted'] }} masuk</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-hourglass-half"></i>{{ $summary['under_review'] }} review</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ $summary['resolved'] }} selesai</span>
                        </div>
                    </div>
                </div>
                <div class="assignment-panel" style="min-width:min(100%, 260px);background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.28);color:white;">
                    <label class="form-label" style="color:white;">Filter</label>
                    <select class="form-select" wire:model.live="statusFilter">
                        <option value="active">Aktif</option>
                        <option value="submitted">Masuk</option>
                        <option value="under_review">Direview</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if (! $hasProfile)
        <div class="assignment-panel text-center py-5 text-secondary">Profil dosen belum tersedia.</div>
    @else
        <div class="card assignment-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Daftar Pengajuan</h3>
                <span class="assignment-pill">{{ count($appealRows) }} pengajuan</span>
            </div>
            <div class="card-body p-4">
                <div class="assignment-shell">
                    @forelse ($appealRows as $appeal)
                        <div class="assignment-list-item">
                            <div class="row g-3 align-items-start">
                                <div class="col-xl-7">
                                    <div class="d-flex gap-3">
                                        <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user-graduate"></i></span>
                                        <div>
                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                <span class="assignment-pill" style="{{ $this->statusStyle($appeal['status']) }}"><i class="fas fa-circle-info"></i>{{ $this->statusLabel($appeal['status']) }}</span>
                                                <span class="assignment-pill"><i class="fas fa-tag"></i>{{ $this->categoryLabel($appeal['category']) }}</span>
                                            </div>
                                            <div class="fw-bold">{{ $appeal['student'] }} <span class="text-secondary">({{ $appeal['nim'] }})</span></div>
                                            <div class="text-secondary small">{{ $appeal['course'] }} &middot; {{ $appeal['academic_year'] }}</div>
                                            <div class="assignment-panel mt-3">
                                                <div class="text-secondary small mb-1">Alasan mahasiswa</div>
                                                <div>{{ $appeal['reason'] }}</div>
                                                @if ($appeal['expected_outcome'])
                                                    <div class="text-secondary small mt-2">Harapan: {{ $appeal['expected_outcome'] }}</div>
                                                @endif
                                            </div>
                                            @if ($appeal['response'])
                                                <div class="assignment-panel mt-2" style="background:#f8fafc;">
                                                    <div class="text-secondary small mb-1">Respons tersimpan</div>
                                                    <div>{{ $appeal['response'] }}</div>
                                                </div>
                                            @endif
                                            @if ($appeal['attachments'])
                                                <div class="d-flex flex-wrap gap-2 mt-3">
                                                    @foreach ($appeal['attachments'] as $attachment)
                                                        <a class="assignment-attachment" href="{{ route('lecturer.grade-appeal-attachments.preview', $attachment['id']) }}" target="_blank">
                                                            <i class="fas fa-paperclip"></i>{{ $attachment['name'] }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-5">
                                    <div class="row g-2">
                                        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary small">Awal</div><div class="fw-bold">{{ $appeal['original_score'] !== null ? number_format((float) $appeal['original_score'], 2) : '-' }}</div></div></div>
                                        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary small">Saat Ini</div><div class="fw-bold">{{ $appeal['current_score'] !== null ? number_format((float) $appeal['current_score'], 2) : '-' }}</div></div></div>
                                        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary small">Huruf</div><div class="fw-bold">{{ $appeal['current_letter'] }}</div></div></div>
                                        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary small">Diminta</div><div class="fw-bold">{{ $appeal['requested_score'] !== null ? number_format((float) $appeal['requested_score'], 2) : '-' }}</div></div></div>
                                        <div class="col-12 text-secondary small"><i class="fas fa-clock me-1"></i>Dikirim {{ $appeal['submitted_at'] }} @if($appeal['resolved_at']) &middot; selesai {{ $appeal['resolved_at'] }} @endif</div>
                                        @if (in_array($appeal['status'], ['submitted', 'under_review'], true))
                                            <div class="col-12 d-flex flex-wrap gap-2 justify-content-xl-end mt-2">
                                                @if ($appeal['study_plan_detail_id'])
                                                    <a class="btn btn-outline-secondary" href="{{ route('lecturer.student-grades.edit', ['id' => $appeal['study_plan_detail_id']]) }}">
                                                        <i class="fas fa-table-list me-1"></i>Lihat Detail Nilai
                                                    </a>
                                                @endif
                                                @if ($appeal['status'] === 'submitted')
                                                    <button type="button" class="btn btn-primary" wire:click="startReview({{ $appeal['id'] }})"><i class="fas fa-pen-to-square me-1"></i>Mulai Review & Koreksi</button>
                                                @else
                                                    <button type="button" class="btn btn-success" wire:click="openResolution({{ $appeal['id'] }}, 'approved')"><i class="fas fa-pen me-1"></i>Koreksi Nilai</button>
                                                @endif
                                                <button type="button" class="btn btn-outline-danger" wire:click="openResolution({{ $appeal['id'] }}, 'rejected')"><i class="fas fa-xmark me-1"></i>Tolak Pengajuan</button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($selectedAppealId === $appeal['id'])
                                <div class="assignment-panel mt-3">
                                    <div class="d-flex gap-2 align-items-start mb-3 text-secondary small">
                                        <i class="fas fa-circle-info text-primary mt-1"></i>
                                        <div>Gunakan panel ini untuk menyelesaikan review. Jika disetujui, skor koreksi akan memperbarui nilai published tanpa membuka edit manual komponen.</div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-lg-3">
                                            <label class="form-label">Keputusan</label>
                                            <select class="form-select" wire:model.live="decision">
                                                <option value="approved">Setujui & Koreksi</option>
                                                <option value="rejected">Tolak Pengajuan</option>
                                            </select>
                                            @error('decision') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-lg-3">
                                            <label class="form-label">Skor Koreksi</label>
                                            <input type="number" min="0" max="100" step="0.01" class="form-control" wire:model="resolvedScore" @disabled($decision === 'rejected')>
                                            @error('resolvedScore') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-lg-6">
                                            <label class="form-label">Respons</label>
                                            <textarea class="form-control" rows="3" wire:model="lecturerResponse"></textarea>
                                            @error('lecturerResponse') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                        <button type="button" class="btn btn-outline-secondary" wire:click="cancelResolution">Batal</button>
                                        <button type="button" class="btn btn-primary" wire:click="resolveAppeal" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="resolveAppeal"><i class="fas fa-save me-1"></i>Simpan Keputusan</span>
                                            <span wire:loading wire:target="resolveAppeal">Menyimpan...</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-secondary py-5">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <div>Belum ada pengajuan keberatan nilai.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
