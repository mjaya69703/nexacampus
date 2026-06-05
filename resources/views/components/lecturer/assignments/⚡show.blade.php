<?php

use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentGrade;
use App\Models\Academic\AssignmentStatusHistory;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use App\Support\AssignmentGradeBookSyncService;
use App\Support\AssignmentReportExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public Assignment $assignment;
    public array $submissions = [];
    public array $gradeRows = [];
    public array $returnRows = [];
    public array $assignmentStats = [];
    public string $submissionFilter = 'all';
    public string $studentSearch = '';
    public float|int|string $gradeBookWeight = 10;
    public string $gradeBookComponentName = '';

    public function mount(int $id): void
    {
        $this->assignment = Assignment::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram', 'files'])
            ->findOrFail($id);

        $this->authorizeAssignment();
        $this->gradeBookComponentName = app(AssignmentGradeBookSyncService::class)->componentName($this->assignment);
        $this->loadSubmissions();
    }

    public function updatedSubmissionFilter(): void
    {
        $this->loadSubmissions();
    }

    public function updatedStudentSearch(): void
    {
        $this->loadSubmissions();
    }

    public function loadSubmissions(): void
    {
        $students = StudyPlanDetail::query()
            ->where('course_offering_id', $this->assignment->course_offering_id)
            ->whereHas('studyPlan.studentProfile.user')
            ->with(['studyPlan.studentProfile.user'])
            ->get()
            ->map(fn (StudyPlanDetail $detail) => $detail->studyPlan?->studentProfile)
            ->filter()
            ->unique('id')
            ->values();

        $submissionMap = $this->assignment->submissions()
            ->with(['studentProfile.user', 'files', 'grade'])
            ->get()
            ->keyBy('student_profile_id');

        $search = strtolower(trim($this->studentSearch));

        $this->submissions = $students->map(function ($student) use ($submissionMap) {
            $submission = $submissionMap->get($student->id);
            $grade = $submission?->grade;
            $gradeStatus = in_array($grade?->status, ['graded', 'returned'], true) ? $grade->status : null;
            $status = $gradeStatus ?? $submission?->status ?? ($this->assignment->isPastDue() ? 'missing' : 'not_submitted');

            if ($status === 'submitted' && $submission?->submitted_at && $submission->submitted_at->gt($this->assignment->due_at)) {
                $status = 'late';
            }

            return [
                'student_id' => $student->id,
                'submission_id' => $submission?->id,
                'name' => $student->user?->name ?? '-',
                'nim' => $student->nim ?? '-',
                'status' => $status,
                'submitted_at' => $submission?->submitted_at?->format('d M Y H:i') ?? '-',
                'content' => $submission?->content,
                'score' => $grade?->score,
                'feedback' => $grade?->feedback,
                'graded_at' => $grade?->graded_at?->format('d M Y H:i'),
                'return_note' => $grade?->return_note,
                'files' => $submission?->files?->map(fn ($file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'size' => $file->formatted_file_size,
                ])->values()->all() ?? [],
            ];
        })
            ->filter(function ($row) use ($search) {
                if ($search === '') {
                    return true;
                }

                return str_contains(strtolower($row['name']), $search)
                    || str_contains(strtolower($row['nim']), $search);
            })
            ->filter(fn ($row) => match ($this->submissionFilter) {
                'review' => in_array($row['status'], ['submitted', 'late'], true),
                'graded' => $row['status'] === 'graded',
                'returned' => $row['status'] === 'returned',
                'missing' => in_array($row['status'], ['missing', 'not_submitted'], true),
                default => true,
            })
            ->values()
            ->all();

        $this->gradeRows = collect($this->submissions)
            ->whereNotNull('submission_id')
            ->mapWithKeys(fn ($row) => [
                $row['submission_id'] => [
                    'score' => $row['score'] !== null ? (string) $row['score'] : '',
                    'feedback' => $row['feedback'] ?? '',
                ],
            ])
            ->all();

        $this->returnRows = collect($this->submissions)
            ->whereNotNull('submission_id')
            ->mapWithKeys(fn ($row) => [
                $row['submission_id'] => [
                    'note' => $row['return_note'] ?? '',
                ],
            ])
            ->all();

        $reportService = app(AssignmentReportExportService::class);
        $this->assignmentStats = $reportService->statistics($reportService->rows($this->assignment));
    }

    public function saveGrade(int $submissionId): void
    {
        $this->authorizeAssignment();

        $submission = $this->assignment->submissions()->findOrFail($submissionId);

        $validated = $this->validate([
            "gradeRows.{$submissionId}.score" => ['required', 'numeric', 'min:0', 'max:'.$this->assignment->max_score],
            "gradeRows.{$submissionId}.feedback" => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($submission, $submissionId, $validated) {
            $row = $validated['gradeRows'][$submissionId];

            AssignmentGrade::query()->updateOrCreate(
                ['assignment_submission_id' => $submission->id],
                [
                    'score' => $row['score'],
                    'feedback' => $row['feedback'] ?: null,
                    'status' => 'graded',
                    'graded_at' => now(),
                    'graded_by' => auth()->id(),
                    'returned_at' => null,
                    'returned_by' => null,
                    'return_note' => null,
                ]
            );

            $submission->update(['status' => 'graded']);

            AssignmentStatusHistory::query()->create([
                'assignment_id' => $this->assignment->id,
                'assignment_submission_id' => $submission->id,
                'actor_id' => auth()->id(),
                'status' => 'graded',
                'note' => 'Submission graded.',
                'meta' => ['score' => $row['score']],
            ]);
        });

        session()->flash('success', 'Nilai berhasil disimpan.');
        $this->loadSubmissions();
    }

    public function returnSubmission(int $submissionId): void
    {
        $this->authorizeAssignment();

        $submission = $this->assignment->submissions()->findOrFail($submissionId);

        $validated = $this->validate([
            "returnRows.{$submissionId}.note" => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($submission, $submissionId, $validated) {
            AssignmentGrade::query()->updateOrCreate(
                ['assignment_submission_id' => $submission->id],
                [
                    'score' => null,
                    'feedback' => null,
                    'status' => 'returned',
                    'graded_at' => null,
                    'graded_by' => null,
                    'returned_at' => now(),
                    'returned_by' => auth()->id(),
                    'return_note' => $validated['returnRows'][$submissionId]['note'],
                ]
            );

            $submission->update(['status' => 'returned']);

            AssignmentStatusHistory::query()->create([
                'assignment_id' => $this->assignment->id,
                'assignment_submission_id' => $submission->id,
                'actor_id' => auth()->id(),
                'status' => 'returned',
                'note' => $validated['returnRows'][$submissionId]['note'],
            ]);
        });

        session()->flash('success', 'Submission dikembalikan untuk revisi.');
        $this->loadSubmissions();
    }

    public function syncToGradeBook(AssignmentGradeBookSyncService $syncService): void
    {
        $this->authorizeAssignment();

        $validated = $this->validate([
            'gradeBookWeight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $result = $syncService->sync($this->assignment, (float) $validated['gradeBookWeight'], auth()->id());

        session()->flash(
            'success',
            "Grade book disinkronkan: {$result['synced']} mahasiswa masuk komponen {$result['component_name']}."
        );

        $this->loadSubmissions();
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Submitted',
            'late' => 'Late',
            'graded' => 'Dinilai',
            'returned' => 'Revisi',
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

    private function authorizeAssignment(): void
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        abort_unless($lecturerProfileId && CourseOfferingLecturer::query()
            ->where('course_offering_id', $this->assignment->course_offering_id)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->exists(), 403);
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Review Tugas',
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
                        <div style="opacity:.86;font-weight:700;">Review Submission</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">{{ $assignment->title }}</h1>
                        <div style="opacity:.9;">{{ $assignment->courseOffering?->course?->code }} - {{ $assignment->courseOffering?->course?->name }} / {{ $assignment->courseOffering?->label }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ $assignment->due_at?->format('d M Y H:i') }}</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-star"></i>Max {{ number_format((float) $assignment->max_score, 2) }}</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-users"></i>{{ count($submissions) }} mahasiswa tampil</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('lecturer.assignments.report.csv', ['assignment' => $assignment->id]) }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#166534;"><i class="fas fa-file-csv"></i>CSV</a>
                    <a href="{{ route('lecturer.assignments.report.xlsx', ['assignment' => $assignment->id]) }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#047857;"><i class="fas fa-file-excel"></i>Excel</a>
                    <a href="{{ route('lecturer.assignments.report.pdf', ['assignment' => $assignment->id]) }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#b91c1c;"><i class="fas fa-file-pdf"></i>PDF</a>
                    <a href="{{ route('lecturer.assignments.index') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;"><i class="fas fa-arrow-left"></i>Kembali</a>
                    <a href="{{ route('lecturer.assignments.edit', ['id' => $assignment->id]) }}" class="assignment-action" style="background:rgba(255,255,255,.2);color:white;"><i class="fas fa-pen"></i>Edit</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card assignment-card h-100">
                <div class="card-header py-3"><h3 class="card-title mb-0" style="font-weight:800;">Instruksi</h3></div>
                <div class="card-body p-4" style="line-height:1.65;color:#334155;">
                    {!! $assignment->description ?: '<span class="text-secondary">Tidak ada instruksi tambahan.</span>' !!}

                    @if ($assignment->files->count())
                        <div class="mt-4">
                            <div class="fw-bold mb-2">Lampiran Instruksi</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($assignment->files as $file)
                                    <a href="{{ route('lecturer.assignments.instructions.preview', ['file' => $file->id]) }}" target="_blank" class="assignment-attachment">
                                        <i class="fas fa-paperclip"></i>{{ $file->file_name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="row g-3">
                <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Submitted</div><div class="h3 fw-bold mb-0 text-success">{{ collect($submissions)->whereIn('status', ['submitted', 'late', 'graded', 'returned'])->count() }}</div></div></div>
                <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Perlu Review</div><div class="h3 fw-bold mb-0 text-primary">{{ collect($submissions)->whereIn('status', ['submitted', 'late'])->count() }}</div></div></div>
                <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Dinilai</div><div class="h3 fw-bold mb-0 text-info">{{ collect($submissions)->where('status', 'graded')->count() }}</div></div></div>
                <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Missing</div><div class="h3 fw-bold mb-0 text-danger">{{ collect($submissions)->whereIn('status', ['missing', 'not_submitted'])->count() }}</div></div></div>
            </div>

            <div class="assignment-panel mt-3" style="background:linear-gradient(135deg,#eef2ff 0%,#f8fafc 100%);border-color:#c7d2fe;">
                <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                    <div>
                        <div class="fw-bold"><i class="fas fa-chart-line text-primary me-2"></i>Grade Book Integration</div>
                        <div class="text-secondary small mt-1">{{ $gradeBookComponentName }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="assignment-pill"><i class="fas fa-upload"></i>Submit {{ $assignmentStats['submitted_count'] ?? 0 }}/{{ $assignmentStats['total_students'] ?? 0 }}</span>
                            <span class="assignment-pill"><i class="fas fa-check"></i>Graded {{ $assignmentStats['graded_count'] ?? 0 }}</span>
                            <span class="assignment-pill"><i class="fas fa-percent"></i>Rate {{ $assignmentStats['submission_rate'] ?? 0 }}%</span>
                            <span class="assignment-pill"><i class="fas fa-gauge-high"></i>Avg {{ $assignmentStats['average_score'] ?? '-' }}</span>
                        </div>
                    </div>
                    <div style="min-width:240px;">
                        <label class="form-label">Bobot Komponen (%)</label>
                        <div class="d-flex gap-2">
                            <input type="number" class="form-control" min="0" max="100" step="0.01" wire:model.defer="gradeBookWeight">
                            <button type="button" class="assignment-action" wire:click="syncToGradeBook" wire:loading.attr="disabled" style="background:#4f46e5;color:white;white-space:nowrap;">
                                <i class="fas fa-arrows-rotate"></i>Sync
                            </button>
                        </div>
                        <div class="form-hint mt-2">Sync hanya untuk grade book Draft. Sistem akan batal kalau total bobot melewati 100%.</div>
                        @error('gradeBookWeight') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
            </div>

            <div class="assignment-panel mt-3">
                <div class="row g-2">
                    <div class="col-md-7">
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari nama atau NIM...">
                    </div>
                    <div class="col-md-5">
                        <select class="form-control" wire:model.live="submissionFilter">
                            <option value="all">Semua Status</option>
                            <option value="review">Perlu Review</option>
                            <option value="graded">Dinilai</option>
                            <option value="returned">Revisi</option>
                            <option value="missing">Belum Submit</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card assignment-card mt-4">
        <div class="card-header py-3"><h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-user-graduate me-2 text-primary"></i>Submission Mahasiswa</h3></div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($submissions as $row)
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-start">
                            <div class="col-xl-3">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;"><i class="fas fa-user"></i></span>
                                    <div>
                                        <div class="fw-bold">{{ $row['name'] }}</div>
                                        <div class="text-secondary small">{{ $row['nim'] }}</div>
                                        <span class="assignment-pill mt-2" style="{{ $this->statusStyle($row['status']) }}">{{ $this->statusLabel($row['status']) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-5">
                                <div class="text-secondary small mb-2"><i class="fas fa-clock me-1"></i>{{ $row['submitted_at'] }} <span class="ms-2"><i class="fas fa-paperclip me-1"></i>{{ count($row['files']) }} file</span></div>
                                @if ($row['content'])
                                    <div class="border rounded p-3 mb-2" style="background:#f8fafc;line-height:1.6;">{!! $row['content'] !!}</div>
                                @endif
                                @if (count($row['files']))
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($row['files'] as $file)
                                            <a href="{{ route('lecturer.assignments.submissions.files.preview', ['file' => $file['id']]) }}" target="_blank" class="assignment-attachment">
                                                <i class="fas fa-paperclip"></i>{{ $file['name'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @elseif (! $row['content'])
                                    <div class="text-secondary">Belum ada submission.</div>
                                @endif
                            </div>
                            <div class="col-xl-4">
                                @if ($row['submission_id'])
                                    <div class="assignment-panel">
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <label class="form-label">Nilai</label>
                                                <input type="number" class="form-control" min="0" max="{{ $assignment->max_score }}" step="0.01" wire:model.defer="gradeRows.{{ $row['submission_id'] }}.score">
                                                @error("gradeRows.{$row['submission_id']}.score") <small class="text-danger">{{ $message }}</small> @enderror
                                            </div>
                                            <div class="col-7">
                                                <label class="form-label">Feedback</label>
                                                <textarea rows="2" class="form-control" wire:model.defer="gradeRows.{{ $row['submission_id'] }}.feedback"></textarea>
                                                @error("gradeRows.{$row['submission_id']}.feedback") <small class="text-danger">{{ $message }}</small> @enderror
                                            </div>
                                            <div class="col-12 d-flex justify-content-end">
                                                <button type="button" class="assignment-action" wire:click="saveGrade({{ $row['submission_id'] }})" wire:loading.attr="disabled" style="background:#2563eb;color:white;">
                                                    <i class="fas fa-check"></i>Simpan Nilai
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="assignment-panel mt-2" style="background:#fffbeb;border-color:#fde68a;">
                                        <label class="form-label">Catatan Revisi</label>
                                        <textarea rows="2" class="form-control" wire:model.defer="returnRows.{{ $row['submission_id'] }}.note" placeholder="Apa yang perlu diperbaiki?"></textarea>
                                        @error("returnRows.{$row['submission_id']}.note") <small class="text-danger">{{ $message }}</small> @enderror
                                        <div class="d-flex justify-content-end mt-2">
                                            <button type="button" class="btn" wire:click="returnSubmission({{ $row['submission_id'] }})" wire:loading.attr="disabled" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:10px;font-weight:800;">
                                                <i class="fas fa-rotate-left me-1"></i>Minta Revisi
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <div class="assignment-panel text-secondary">Menunggu submission mahasiswa.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div>Tidak ada mahasiswa sesuai filter.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
