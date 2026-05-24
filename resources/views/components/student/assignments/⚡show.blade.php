<?php

use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentStatusHistory;
use App\Models\Academic\AssignmentSubmission;
use App\Models\Academic\AssignmentSubmissionFile;
use App\Models\Academic\StudyPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Assignment $assignment;
    public ?AssignmentSubmission $submission = null;
    public string $content = '';
    public array $files = [];

    public function mount(int $id): void
    {
        $this->assignment = Assignment::query()
            ->published()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram', 'files'])
            ->findOrFail($id);

        $this->authorizeEnrollment();
        $this->loadSubmission();
    }

    public function loadSubmission(): void
    {
        $this->submission = AssignmentSubmission::query()
            ->with(['files', 'grade'])
            ->where('assignment_id', $this->assignment->id)
            ->where('student_profile_id', auth()->user()?->studentProfile?->id)
            ->first();

        $this->content = $this->submission?->content ?? '';
    }

    public function submit(): void
    {
        $this->authorizeEnrollment();

        if (! $this->canSubmit()) {
            $this->addError('content', 'Submission tidak bisa diubah lagi.');

            return;
        }

        $rules = [
            'content' => [$this->assignment->allow_text_submission ? 'nullable' : 'prohibited', 'string'],
        ];

        if ($this->assignment->allow_file_submission) {
            $rules['files.*'] = ['nullable', 'file', 'max:'.$this->assignment->max_file_size_kb, 'extensions:'.implode(',', $this->assignment->allowedExtensions())];
        } else {
            $rules['files'] = ['prohibited'];
        }

        $this->validate($rules);

        $existingFileCount = $this->submission?->files?->count() ?? 0;

        if (blank(strip_tags($this->content)) && empty($this->files) && $existingFileCount === 0) {
            $this->addError('content', 'Isi jawaban atau upload minimal satu file.');

            return;
        }

        DB::transaction(function () {
            $now = now();
            $status = $this->assignment->due_at && $now->gt($this->assignment->due_at) ? 'late' : 'submitted';
            $submissionPayload = [
                'content' => $this->content ?: null,
                'status' => $status,
                'submitted_at' => $this->submission?->submitted_at ?? $now,
                'last_resubmitted_at' => $this->submission ? $now : null,
            ];

            if (Schema::hasColumn('assignment_submissions', 'submission_text')) {
                $submissionPayload['submission_text'] = $this->content ?: null;
            }

            $submission = AssignmentSubmission::query()->updateOrCreate(
                [
                    'assignment_id' => $this->assignment->id,
                    'student_profile_id' => auth()->user()->studentProfile->id,
                ],
                $submissionPayload
            );

            if ($submission->grade?->status === 'returned') {
                $submission->grade()->update([
                    'status' => 'resubmitted',
                    'score' => null,
                    'feedback' => null,
                    'graded_at' => null,
                    'graded_by' => null,
                ]);
            }

            foreach ($this->files as $file) {
                if (! $file) {
                    continue;
                }

                $path = $file->store('assignments/submissions', 'public');

                $filePayload = [
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => strtolower($file->getClientOriginalExtension()),
                    'file_size' => $file->getSize(),
                ];

                if (Schema::hasColumn('assignment_submission_files', 'submission_id')) {
                    $filePayload['submission_id'] = $submission->id;
                }

                if (Schema::hasColumn('assignment_submission_files', 'original_name')) {
                    $filePayload['original_name'] = $file->getClientOriginalName();
                }

                $submission->files()->create($filePayload);
            }

            AssignmentStatusHistory::query()->create([
                'assignment_id' => $this->assignment->id,
                'assignment_submission_id' => $submission->id,
                'actor_id' => auth()->id(),
                'status' => $status,
                'note' => $this->submission ? 'Submission resubmitted.' : 'Submission created.',
            ]);
        });

        $this->files = [];
        $this->loadSubmission();
        session()->flash('success', 'Submission berhasil dikirim.');
    }

    public function removeFile(int $fileId): void
    {
        $this->authorizeEnrollment();

        if (! $this->canSubmit()) {
            throw ValidationException::withMessages(['files' => 'Lampiran tidak bisa dihapus lagi.']);
        }

        $file = AssignmentSubmissionFile::query()
            ->whereHas('submission', function ($query) {
                $query->where('assignment_id', $this->assignment->id)
                    ->where('student_profile_id', auth()->user()?->studentProfile?->id);
            })
            ->findOrFail($fileId);

        Storage::disk('public')->delete($file->file_path);
        $file->delete();
        $this->loadSubmission();
    }

    public function canSubmit(): bool
    {
        if (! $this->assignment->canAcceptSubmission()) {
            return false;
        }

        if (! $this->submission) {
            return true;
        }

        if ($this->submission->grade?->status === 'graded') {
            return false;
        }

        if ($this->submission->grade?->status === 'returned') {
            return true;
        }

        return $this->assignment->allow_resubmission && ! $this->assignment->isPastDue();
    }

    public function statusLabel(): string
    {
        if (! $this->submission) {
            return $this->assignment->isPastDue() ? 'Missing' : 'Belum Submit';
        }

        $gradeStatus = in_array($this->submission->grade?->status, ['graded', 'returned'], true)
            ? $this->submission->grade->status
            : null;

        return match ($gradeStatus ?? $this->submission->status) {
            'submitted' => 'Submitted',
            'late' => 'Submitted Late',
            'graded' => 'Dinilai',
            'returned' => 'Perlu Revisi',
            default => 'Belum Submit',
        };
    }

    private function authorizeEnrollment(): void
    {
        $studentProfileId = auth()->user()?->studentProfile?->id;

        abort_unless($studentProfileId && StudyPlan::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereHas('details', fn ($query) => $query->where('course_offering_id', $this->assignment->course_offering_id))
            ->exists(), 403);
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic',
            'pages' => 'Detail Tugas',
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
                        <div style="opacity:.86;font-weight:700;">Tugas Perkuliahan</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">{{ $assignment->title }}</h1>
                        <div style="opacity:.9;">{{ $assignment->courseOffering?->course?->code }} - {{ $assignment->courseOffering?->course?->name }} / {{ $assignment->courseOffering?->label }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-calendar"></i>{{ $assignment->courseOffering?->academicYear?->name ?? '-' }}</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-clock"></i>{{ $assignment->due_at?->format('d M Y H:i') }}</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-star"></i>Max {{ number_format((float) $assignment->max_score, 2) }}</span>
                        </div>
                    </div>
                </div>
                <a href="{{ route('student.assignments.index') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;"><i class="fas fa-arrow-left"></i>Kembali</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary">Status</div><div class="h4 fw-bold mb-0">{{ $this->statusLabel() }}</div></div></div>
        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary">Deadline</div><div class="h4 fw-bold mb-0 {{ $assignment->isPastDue() ? 'text-danger' : 'text-primary' }}">{{ $assignment->isPastDue() ? 'Lewat' : 'Aktif' }}</div></div></div>
        <div class="col-md-4"><div class="assignment-panel h-100"><div class="text-secondary">Nilai</div><div class="h4 fw-bold mb-0 text-success">{{ $submission?->grade?->status === 'graded' ? number_format((float) $submission->grade->score, 2).' / '.number_format((float) $assignment->max_score, 2) : '-' }}</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card assignment-card mb-4">
                <div class="card-header py-3"><h3 class="card-title mb-0" style="font-weight:800;">Instruksi</h3></div>
                <div class="card-body p-4" style="line-height:1.65;color:#334155;">
                    {!! $assignment->description ?: '<span class="text-secondary">Tidak ada instruksi tambahan.</span>' !!}

                    @if ($assignment->files->count())
                        <div class="mt-4">
                            <div class="fw-bold mb-2">Lampiran Instruksi</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($assignment->files as $file)
                                    <a href="{{ route('student.assignments.instructions.preview', ['file' => $file->id]) }}" target="_blank" class="assignment-attachment">
                                        <i class="fas fa-paperclip"></i>{{ $file->file_name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card assignment-card">
                <div class="card-header py-3"><h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-paper-plane me-2 text-primary"></i>Submission Kamu</h3></div>
                <div class="card-body p-4">
                    @if ($submission)
                        <div class="assignment-panel mb-3" style="background:linear-gradient(135deg,#ecfdf5 0%,#f0fdf4 100%);border-color:#a7f3d0;">
                            <div class="fw-bold text-success">Submission tersimpan</div>
                            <div class="text-secondary small">{{ $submission->submitted_at?->format('d M Y H:i') }}</div>
                        </div>

                        @if ($submission->content)
                            <div class="border rounded p-3 mb-3" style="background:#f8fafc;line-height:1.6;">{!! $submission->content !!}</div>
                        @endif

                        @if ($submission->files->count())
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach ($submission->files as $file)
                                    <span class="assignment-attachment">
                                        <a href="{{ route('student.assignments.submissions.files.preview', ['file' => $file->id]) }}" target="_blank" class="text-reset text-decoration-none">
                                            <i class="fas fa-paperclip"></i>{{ $file->file_name }}
                                        </a>
                                        @if ($this->canSubmit())
                                            <button type="button" class="btn p-0 ms-1 text-danger" wire:click="removeFile({{ $file->id }})" title="Hapus lampiran"><i class="fas fa-times"></i></button>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if ($submission->grade?->status === 'graded')
                            <div class="assignment-panel mb-3" style="background:#eff6ff;border-color:#bfdbfe;">
                                <div class="d-flex justify-content-between gap-3 flex-wrap">
                                    <div>
                                        <div class="fw-bold text-primary">Hasil Penilaian</div>
                                        <div class="text-secondary small">Dinilai {{ $submission->grade->graded_at?->format('d M Y H:i') ?? '-' }}</div>
                                    </div>
                                    <div class="h3 mb-0 text-primary">{{ number_format((float) $submission->grade->score, 2) }} / {{ number_format((float) $assignment->max_score, 2) }}</div>
                                </div>
                                @if ($submission->grade->feedback)
                                    <div class="border rounded p-3 mt-3 bg-white">{!! $submission->grade->feedback !!}</div>
                                @endif
                            </div>
                        @endif

                        @if ($submission->grade?->status === 'returned')
                            <div class="alert alert-warning">
                                <div class="fw-bold mb-1">Dosen meminta revisi</div>
                                <div>{!! $submission->grade->return_note ?: 'Periksa kembali jawaban dan kirim ulang sebelum deadline.' !!}</div>
                            </div>
                        @endif
                    @endif

                    @if ($this->canSubmit())
                        <form wire:submit.prevent="submit" class="assignment-shell">
                            @if ($assignment->allow_text_submission)
                                <div>
                                    <label class="form-label">Jawaban Teks</label>
                                    <livewire:jodit-text-editor wire:model.live="content" identifier="student-assignment-submission-{{ $assignment->id }}" :height="260" />
                                    @error('content') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            @endif

                            @if ($assignment->allow_file_submission)
                                <div class="assignment-dropzone">
                                    <label class="form-label">Upload File</label>
                                    <input type="file" class="form-control" wire:model="files" multiple accept=".{{ implode(',.', $assignment->allowedExtensions()) }}">
                                    <div class="form-hint mt-2">Allowed: {{ implode(', ', $assignment->allowedExtensions()) }}. Max {{ $assignment->formattedMaxFileSize() }}/file.</div>
                                    @error('files.*') <small class="text-danger d-block mt-2">{{ $message }}</small> @enderror
                                    @error('files') <small class="text-danger d-block mt-2">{{ $message }}</small> @enderror
                                </div>
                            @endif

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="assignment-action" wire:loading.attr="disabled" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                    <span wire:loading.remove><i class="fas fa-paper-plane me-2"></i>{{ $submission ? 'Kirim Ulang' : 'Kirim Submission' }}</span>
                                    <span wire:loading><i class="fas fa-spinner fa-spin"></i>Memproses...</span>
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-warning mb-0">{{ $assignment->isPastDue() && ! $assignment->accept_late_submission ? 'Deadline sudah lewat dan tugas ini tidak menerima submission telat.' : 'Submission tidak bisa diubah lagi.' }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="assignment-shell">
                <div class="assignment-panel">
                    <div class="fw-bold mb-2"><i class="fas fa-sliders text-primary me-2"></i>Aturan Submit</div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Jawaban teks</span><strong>{{ $assignment->allow_text_submission ? 'Ya' : 'Tidak' }}</strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Upload file</span><strong>{{ $assignment->allow_file_submission ? 'Ya' : 'Tidak' }}</strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Resubmit</span><strong>{{ $assignment->allow_resubmission ? 'Ya' : 'Tidak' }}</strong></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Telat diterima</span><strong>{{ $assignment->accept_late_submission ? 'Ya' : 'Tidak' }}</strong></div>
                    <div class="pt-2"><span class="text-secondary">File:</span> <strong>{{ implode(', ', $assignment->allowedExtensions()) }}</strong></div>
                </div>
                <div class="assignment-panel">
                    <div class="fw-bold mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>Tips</div>
                    <div class="text-secondary small">Pastikan lampiran muncul di daftar sebelum mengirim. Kalau dosen meminta revisi, baca catatannya dulu lalu kirim ulang jawaban final.</div>
                </div>
            </div>
        </div>
    </div>
</div>
