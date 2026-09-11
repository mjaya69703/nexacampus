<?php

use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\StudentAdvisorNote;
use App\Support\AcademicAdvisorService;
use App\Support\StudentProgressAnalyticsService;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public int $assignmentId;
    public array $assignment = [];
    public array $progress = [];
    public array $notes = [];
    public array $pendingPlans = [];
    public string $approvalNotes = '';
    public array $noteForm = [
        'topic' => '',
        'notes' => '',
        'recommendation' => '',
        'follow_up_at' => '',
        'visible_to_student' => true,
    ];

    public function mount(int $assignmentId): void
    {
        $this->assignmentId = $assignmentId;
        $this->loadPage(app(AcademicAdvisorService::class), app(StudentProgressAnalyticsService::class));
    }

    public function loadPage(AcademicAdvisorService $advisorService, StudentProgressAnalyticsService $analytics): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;
        abort_unless($lecturerProfileId && $advisorService->hasActiveAssignmentsForLecturer($lecturerProfileId), 403);

        $advisorAssignment = AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram.faculty', 'academicYear', 'lecturerProfile.user'])
            ->whereKey($this->assignmentId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->firstOrFail();

        $student = $advisorAssignment->studentProfile;
        abort_unless($student, 404);

        $this->assignment = [
            'student_name' => $student->user?->name ?? '-',
            'nim' => $student->nim ?? '-',
            'study_program' => $student->studyProgram?->name ?? '-',
            'faculty' => $student->studyProgram?->faculty?->name ?? '-',
            'semester' => $student->current_semester ?? '-',
            'academic_status' => $student->academic_status ?? '-',
            'academic_year' => $advisorAssignment->academicYear?->name ?? 'Umum',
            'start_date' => $advisorAssignment->start_date?->format('d M Y') ?? '-',
            'end_date' => $advisorAssignment->end_date?->format('d M Y') ?? 'Aktif',
        ];

        $this->progress = $analytics->summarize($student);
        $this->pendingPlans = $advisorService->pendingPlansForStudent($student->id)
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'year' => $plan->academicYear?->name ?? '-',
                'semester' => $plan->semester_no ?? '-',
                'courses' => (int) $plan->details_count,
                'credits' => (int) $plan->total_credits,
                'submitted_at' => $plan->submitted_at?->format('d M Y H:i') ?? '-',
            ])
            ->values()
            ->all();
        $this->loadNotes($lecturerProfileId);
    }

    public function approvePlan(int $planId): void
    {
        $this->decidePlan($planId, 'Approved');
    }

    public function rejectPlan(int $planId): void
    {
        $this->decidePlan($planId, 'Rejected');
    }

    private function decidePlan(int $planId, string $decision): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;
        abort_unless($lecturerProfileId, 403);

        $plan = \App\Models\Academic\StudyPlan::findOrFail($planId);

        try {
            if ($decision === 'Approved') {
                app(AcademicAdvisorService::class)->approveStudyPlanAsAdvisor(
                    $lecturerProfileId, $plan, $this->approvalNotes ?: null, auth()->id()
                );
                session()->flash('success', 'KRS berhasil disetujui.');
            } else {
                app(AcademicAdvisorService::class)->rejectStudyPlanAsAdvisor(
                    $lecturerProfileId, $plan, $this->approvalNotes ?: null, auth()->id()
                );
                session()->flash('success', 'KRS berhasil ditolak.');
            }
        } catch (\Illuminate\Validation\ValidationException $exception) {
            session()->flash('error', $exception->validator->errors()->first() ?: 'Keputusan gagal diproses.');
        }

        $this->approvalNotes = '';
        $this->loadPage(app(AcademicAdvisorService::class), app(StudentProgressAnalyticsService::class));
    }

    public function saveAdvisorNote(): void
    {
        $validated = $this->validate([
            'noteForm.topic' => ['required', 'string', 'max:120'],
            'noteForm.notes' => ['required', 'string', 'max:2000'],
            'noteForm.recommendation' => ['nullable', 'string', 'max:2000'],
            'noteForm.follow_up_at' => ['nullable', 'date'],
            'noteForm.visible_to_student' => ['boolean'],
        ]);

        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;
        $assignment = AcademicAdvisorAssignment::query()
            ->whereKey($this->assignmentId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->firstOrFail();

        StudentAdvisorNote::query()->create([
            'academic_advisor_assignment_id' => $assignment->id,
            'student_profile_id' => $assignment->student_profile_id,
            'lecturer_profile_id' => $assignment->lecturer_profile_id,
            'topic' => $validated['noteForm']['topic'],
            'notes' => $validated['noteForm']['notes'],
            'recommendation' => $validated['noteForm']['recommendation'] ?: null,
            'follow_up_at' => $validated['noteForm']['follow_up_at'] ?: null,
            'visible_to_student' => (bool) $validated['noteForm']['visible_to_student'],
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->noteForm = [
            'topic' => '',
            'notes' => '',
            'recommendation' => '',
            'follow_up_at' => '',
            'visible_to_student' => true,
        ];

        $this->dispatch('success', message: 'Catatan bimbingan berhasil disimpan.');
        $this->loadNotes((int) $lecturerProfileId);
    }

    public function markNoteDone(int $noteId): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        StudentAdvisorNote::query()
            ->whereKey($noteId)
            ->where('academic_advisor_assignment_id', $this->assignmentId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->update([
                'status' => 'Done',
                'updated_by' => auth()->id(),
            ]);

        $this->dispatch('success', message: 'Follow-up ditandai selesai.');
        $this->loadNotes((int) $lecturerProfileId);
    }

    private function loadNotes(int $lecturerProfileId): void
    {
        $this->notes = StudentAdvisorNote::query()
            ->where('academic_advisor_assignment_id', $this->assignmentId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->latest('created_at')
            ->get()
            ->map(fn (StudentAdvisorNote $note) => [
                'id' => $note->id,
                'topic' => $note->topic,
                'notes' => $note->notes,
                'recommendation' => $note->recommendation,
                'follow_up_at' => $note->follow_up_at?->format('d M Y'),
                'status' => $note->status,
                'visible_to_student' => $note->visible_to_student,
                'created_at' => $note->created_at?->format('d M Y H:i'),
            ])
            ->values()
            ->all();
    }

    public function money(float|int|null $amount): string
    {
        return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    public function riskBadgeClass(?string $level): string
    {
        return match ($level) {
            'danger' => 'bg-red-lt text-red',
            'warning' => 'bg-yellow-lt text-yellow',
            'success' => 'bg-green-lt text-green',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Detail Bimbingan Akademik',
        ]);
    }
};
?>

@push('styles')
    <style>
        .modern-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow: hidden;
            position: relative;
        }

        .hero-gradient::before {
            animation: pulse 15s ease-in-out infinite;
            background: radial-gradient(circle, rgba(255,255,255,.1) 0%, transparent 70%);
            content: '';
            height: 200%;
            position: absolute;
            right: -50%;
            top: -50%;
            width: 200%;
        }

        @keyframes pulse {
            0%, 100% { opacity: .5; transform: scale(1); }
            50% { opacity: .8; transform: scale(1.08); }
        }

        .detail-avatar {
            align-items: center;
            background: rgba(255,255,255,.2);
            border-radius: 18px;
            display: inline-flex;
            font-size: 2rem;
            height: 68px;
            justify-content: center;
            width: 68px;
        }

        .badge-modern {
            border-radius: 999px;
            display: inline-flex;
            font-size: .78rem;
            font-weight: 700;
            gap: .35rem;
            padding: .45rem .75rem;
        }

        .advisor-stat {
            background: rgba(255,255,255,.18);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            padding: 1rem;
            min-height: 96px;
        }

        .metric-card {
            background: #f9fafb;
            border-radius: 14px;
            padding: 1rem;
            min-height: 108px;
        }

        .metric-value {
            color: #1f2937;
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .metric-label {
            color: #6b7280;
            font-size: .72rem;
            font-weight: 700;
            margin-top: .45rem;
            text-transform: uppercase;
        }

        .advisor-action {
            border-radius: 12px;
            font-weight: 700;
            padding: .75rem 1rem;
        }

        .filter-input {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: .75rem 1rem;
            transition: all .3s ease;
        }

        .filter-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, .1);
        }

        .note-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1rem;
        }

        .risk-item {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1rem;
            min-height: 134px;
        }
    </style>
@endpush

<div>
    <x-alert />

    <div class="card modern-card hero-gradient mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;z-index:2;">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="detail-avatar"><i class="fas fa-user-graduate"></i></span>
                        <div>
                            <div style="font-size:.9rem;opacity:.9;margin-bottom:.25rem;">Detail Bimbingan Akademik</div>
                            <h2 class="h2 mb-0" style="font-weight:700;">{{ $assignment['student_name'] }}</h2>
                            <div class="mt-2" style="opacity:.9;">{{ $assignment['nim'] }} - {{ $assignment['study_program'] }}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge-modern" style="background:rgba(255,255,255,.2);color:white;"><i class="fas fa-calendar"></i>{{ $assignment['academic_year'] }}</span>
                        <span class="badge-modern" style="background:rgba(255,255,255,.2);color:white;"><i class="fas fa-layer-group"></i>Semester {{ $assignment['semester'] }}</span>
                        <span class="badge-modern" style="background:rgba(255,255,255,.2);color:white;"><i class="fas fa-user-check"></i>{{ $assignment['academic_status'] }}</span>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="advisor-stat">
                                <div style="font-size:.72rem;opacity:.85;text-transform:uppercase;">IPK</div>
                                <div class="h2 mt-2 mb-0" style="font-weight:700;">{{ $progress['gpa']['cumulative'] !== null ? number_format((float) $progress['gpa']['cumulative'], 2) : '-' }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="advisor-stat">
                                <div style="font-size:.72rem;opacity:.85;text-transform:uppercase;">SKS Lulus</div>
                                <div class="h2 mt-2 mb-0" style="font-weight:700;">{{ $progress['credits']['passed'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="advisor-stat">
                                <div style="font-size:.72rem;opacity:.85;text-transform:uppercase;">Catatan</div>
                                <div class="h2 mt-2 mb-0" style="font-weight:700;">{{ count($notes) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <a href="{{ route('lecturer.academic-advising.index') }}" class="btn btn-outline-secondary advisor-action">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
        </a>
    </div>

    @if (count($pendingPlans) > 0)
        <div class="card modern-card mb-4">
            <div class="card-body p-4">
                <div class="fw-bold mb-1" style="font-size:1.1rem;">KRS Menunggu Persetujuan</div>
                <div class="text-secondary mb-4">Setujui atau tolak sebagai Dosen PA. Keputusan tercatat atas nama Anda.</div>

                @foreach ($pendingPlans as $pendingPlan)
                    <div class="note-item mb-3">
                        <div class="d-flex justify-content-between gap-3 flex-wrap align-items-center">
                            <div>
                                <div class="fw-bold text-dark">{{ $pendingPlan['year'] }} &middot; Semester {{ $pendingPlan['semester'] }}</div>
                                <div class="text-secondary small">{{ $pendingPlan['courses'] }} MK &middot; {{ $pendingPlan['credits'] }} SKS &middot; diajukan {{ $pendingPlan['submitted_at'] }}</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success advisor-action" wire:click="approvePlan({{ $pendingPlan['id'] }})">
                                    <i class="fas fa-check me-2"></i>Setujui
                                </button>
                                <button type="button" class="btn btn-outline-danger advisor-action" wire:click="rejectPlan({{ $pendingPlan['id'] }})">
                                    <i class="fas fa-times me-2"></i>Tolak
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div>
                    <label class="form-label fw-bold">Catatan keputusan (opsional, dipakai untuk aksi berikutnya)</label>
                    <input type="text" class="form-control filter-input" wire:model.defer="approvalNotes" placeholder="Contoh: Kurangi 1 MK, SKS berlebih.">
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-value">{{ $progress['gpa']['semester'] !== null ? number_format((float) $progress['gpa']['semester'], 2) : '-' }}</div>
                <div class="metric-label">IPS Terakhir</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-value">{{ $progress['credits']['remaining'] ?? 0 }}</div>
                <div class="metric-label">Sisa SKS</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-value">{{ $progress['attendance']['rate'] !== null ? $progress['attendance']['rate'].'%' : '-' }}</div>
                <div class="metric-label">Kehadiran</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card">
                <div class="metric-value" style="font-size:1.1rem;">{{ $this->money($progress['financial']['outstanding'] ?? 0) }}</div>
                <div class="metric-label">Outstanding</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        @foreach ($progress['risks'] as $risk)
            <div class="col-lg-3 col-md-6">
                <div class="risk-item">
                    <div class="d-flex justify-content-between gap-2 align-items-start mb-3">
                        <div class="fw-bold">{{ $risk['label'] }}</div>
                        <span class="badge-modern {{ $this->riskBadgeClass($risk['level']) }}">{{ $risk['level'] }}</span>
                    </div>
                    <div class="text-secondary small">{{ $risk['reason'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="modern-card h-100">
                <div class="card-body p-4">
                    <div class="fw-bold mb-1" style="font-size:1.1rem;">Catat Bimbingan</div>
                    <div class="text-secondary mb-4">Simpan hasil konsultasi, rekomendasi, dan rencana follow-up.</div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Topik</label>
                        <input type="text" class="form-control filter-input" wire:model.defer="noteForm.topic" placeholder="Contoh: Evaluasi KRS semester ini">
                        @error('noteForm.topic') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Dosen PA</label>
                        <textarea class="form-control filter-input" rows="5" wire:model.defer="noteForm.notes" placeholder="Ringkas kondisi mahasiswa, konteks diskusi, atau isu yang perlu dipantau."></textarea>
                        @error('noteForm.notes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Rekomendasi</label>
                        <textarea class="form-control filter-input" rows="3" wire:model.defer="noteForm.recommendation" placeholder="Langkah berikutnya untuk mahasiswa."></textarea>
                        @error('noteForm.recommendation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Tanggal Follow-up</label>
                            <input type="date" class="form-control filter-input" wire:model.defer="noteForm.follow_up_at">
                            @error('noteForm.follow_up_at') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Visibilitas</label>
                            <label class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" wire:model.defer="noteForm.visible_to_student">
                                <span class="form-check-label">Tampil ke student</span>
                            </label>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary advisor-action w-100 mt-4" wire:click="saveAdvisorNote">
                        <i class="fas fa-save me-2"></i>Simpan Catatan
                    </button>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="modern-card h-100">
                <div class="card-body p-4">
                    <div class="fw-bold mb-1" style="font-size:1.1rem;">Riwayat Bimbingan</div>
                    <div class="text-secondary mb-4">Catatan terdahulu dan follow-up yang masih terbuka.</div>

                    @forelse ($notes as $note)
                        <div class="note-item mb-3">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="fw-bold text-dark">{{ $note['topic'] }}</div>
                                    <div class="text-secondary small">{{ $note['created_at'] }}</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge-modern {{ $note['status'] === 'Done' ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">{{ $note['status'] }}</span>
                                    <span class="badge-modern {{ $note['visible_to_student'] ? 'bg-blue-lt text-blue' : 'bg-secondary-lt text-secondary' }}">
                                        {{ $note['visible_to_student'] ? 'Visible Student' : 'Internal' }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-secondary mt-3">{{ $note['notes'] }}</div>
                            @if ($note['recommendation'])
                                <div class="mt-3 text-secondary"><strong>Rekomendasi:</strong> {{ $note['recommendation'] }}</div>
                            @endif
                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-3">
                                <div class="text-secondary small">
                                    Follow-up: {{ $note['follow_up_at'] ?? '-' }}
                                </div>
                                @if ($note['status'] !== 'Done')
                                    <button type="button" class="btn btn-outline-success advisor-action" wire:click="markNoteDone({{ $note['id'] }})">
                                        <i class="fas fa-check me-2"></i>Tandai Selesai
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-secondary py-5">
                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                            <div>Belum ada catatan bimbingan untuk mahasiswa ini.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
