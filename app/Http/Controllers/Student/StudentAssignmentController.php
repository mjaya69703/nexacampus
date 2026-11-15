<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentStatusHistory;
use App\Models\Academic\AssignmentSubmission;
use App\Models\Academic\AssignmentSubmissionFile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlanDetail;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tugas kuliah mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - Kelas diambil dari detail KRS aktif (Draft/Taken) yang rencana studinya
 *   masih berlaku. Sebelumnya semua detail ikut dihitung, sehingga kelas
 *   yang sudah dibatalkan tetap memunculkan tugas.
 * - Statistik dihitung dari seluruh tugas yang boleh diakses sebelum filter,
 *   jadi angka tidak berubah hanya karena mahasiswa mengetik kata kunci.
 * - Tugas yang lewat deadline tetapi masih menerima submission telat tidak
 *   lagi dilabeli "Missing" (masih bisa dikumpulkan).
 * - `max_files` dihormati bila kolomnya tersedia pada instalasi.
 * - Submission yang pernah di-soft-delete dipulihkan sebelum disimpan agar
 *   tidak melanggar unique (assignment_id, student_profile_id).
 * - Jawaban teks disanitasi sebelum disimpan karena nantinya dirender sebagai
 *   HTML oleh dosen.
 */
class StudentAssignmentController extends Controller
{
    private const FILTERS = ['all', 'open', 'submitted', 'graded', 'returned', 'late'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::FILTERS)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'all';
        $search = trim((string) ($validated['search'] ?? ''));

        $rows = $profile ? $this->assignmentRows($profile) : collect();

        $stats = [
            'total' => $rows->count(),
            'open' => $rows->whereIn('status', ['not_submitted'])->count(),
            'submitted' => $rows->whereIn('status', ['submitted', 'late', 'graded', 'returned'])->count(),
            'graded' => $rows->where('status', 'graded')->count(),
            'returned' => $rows->where('status', 'returned')->count(),
            'late' => $rows->whereIn('status', ['late', 'missing'])->count(),
        ];

        $filtered = $rows
            ->when($status === 'open', fn (Collection $items) => $items->where('status', 'not_submitted'))
            ->when($status === 'submitted', fn (Collection $items) => $items->whereIn('status', ['submitted', 'late', 'graded', 'returned']))
            ->when($status === 'graded', fn (Collection $items) => $items->where('status', 'graded'))
            ->when($status === 'returned', fn (Collection $items) => $items->where('status', 'returned'))
            ->when($status === 'late', fn (Collection $items) => $items->whereIn('status', ['late', 'missing']))
            ->when($search !== '', fn (Collection $items) => $items->filter(
                fn (array $row) => Str::contains(
                    Str::lower($row['title'].' '.$row['courseCode'].' '.$row['courseName']),
                    Str::lower($search),
                ),
            ))
            ->values();

        return Inertia::render('Student/Assignments/Index', [
            'shell' => ShellProps::make($user, 'Academic', 'Assignments'),
            'hasProfile' => $profile !== null,
            'student' => $profile ? [
                'name' => $user->name,
                'nim' => $profile->nim,
                'program' => $profile->studyProgram?->name ?? '-',
                'semester' => $profile->current_semester,
            ] : null,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'stats' => $stats,
            'assignments' => $filtered->all(),
            'urls' => [
                'index' => route('student.assignments.index'),
                'show' => route('student.assignments.show', ['id' => '__ID__']),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $assignment = Assignment::query()
            ->published()
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'files',
            ])
            ->findOrFail($id);

        $this->assertEnrollment($profile, $assignment);

        $submission = AssignmentSubmission::query()
            ->with(['files', 'grade'])
            ->where('assignment_id', $assignment->id)
            ->where('student_profile_id', $profile->id)
            ->first();

        return Inertia::render('Student/Assignments/Show', [
            'shell' => ShellProps::make($user, 'Academic', 'Assignment'),
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'courseCode' => $assignment->courseOffering?->course?->code ?? $assignment->courseOffering?->code ?? '-',
                'courseName' => $assignment->courseOffering?->course?->name ?? $assignment->courseOffering?->label ?? '-',
                'classLabel' => $assignment->courseOffering?->label,
                'academicYear' => $assignment->courseOffering?->academicYear?->name ?? '-',
                'dueAt' => $assignment->due_at?->format('d M Y H:i'),
                'dueHuman' => $assignment->due_at?->diffForHumans(),
                'isPastDue' => $assignment->isPastDue(),
                'maxScore' => (float) $assignment->max_score,
                'allowTextSubmission' => (bool) $assignment->allow_text_submission,
                'allowFileSubmission' => (bool) $assignment->allow_file_submission,
                'allowResubmission' => (bool) $assignment->allow_resubmission,
                'acceptLateSubmission' => (bool) $assignment->accept_late_submission,
                'allowedExtensions' => $assignment->allowedExtensions(),
                'maxFileSizeLabel' => $assignment->formattedMaxFileSize(),
                'maxFiles' => $this->maxFiles($assignment),
                'canSubmit' => $this->canSubmit($assignment, $submission),
                'submissionLockedByGrade' => $submission?->grade?->status === 'graded',
                'statusLabel' => $this->statusLabel($assignment, $submission),
                'statusTone' => $this->statusTone($this->statusKey($assignment, $submission)),
            ],
            'instructionFiles' => $assignment->files
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'fileName' => $file->file_name,
                    'fileType' => $file->file_type,
                    'sizeLabel' => $file->formatted_file_size,
                    'previewUrl' => route('student.assignments.instructions.preview', ['file' => $file->id]),
                ])
                ->values()
                ->all(),
            'submission' => $submission ? [
                'id' => $submission->id,
                'content' => $submission->content,
                'status' => $submission->status,
                'submittedAt' => $submission->submitted_at?->format('d M Y H:i'),
                'lastResubmittedAt' => $submission->last_resubmitted_at?->format('d M Y H:i'),
                'files' => $submission->files
                    ->map(fn (AssignmentSubmissionFile $file) => [
                        'id' => $file->id,
                        'fileName' => $file->file_name,
                        'fileType' => $file->file_type,
                        'sizeLabel' => $file->formatted_file_size,
                        'previewUrl' => route('student.assignments.submissions.files.preview', ['file' => $file->id]),
                    ])
                    ->values()
                    ->all(),
                'grade' => $submission->grade ? [
                    'status' => $submission->grade->status,
                    'score' => $submission->grade->score !== null ? (float) $submission->grade->score : null,
                    'maxScore' => (float) $assignment->max_score,
                    'feedback' => $submission->grade->feedback,
                    'returnNote' => $submission->grade->return_note,
                    'gradedAt' => $submission->grade->graded_at?->format('d M Y H:i'),
                ] : null,
            ] : null,
            'urls' => [
                'index' => route('student.assignments.index'),
                'submit' => route('student.assignments.submission.store', ['id' => '__ID__']),
                'removeFile' => route('student.assignments.submissions.files.destroy', ['file' => '__ID__']),
            ],
        ]);
    }

    public function submit(Request $request, int $id): RedirectResponse
    {
        $profile = $this->studentProfile($request);

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $assignment = Assignment::query()->published()->findOrFail($id);
        $this->assertEnrollment($profile, $assignment);

        $submission = AssignmentSubmission::query()
            ->with(['files', 'grade'])
            ->where('assignment_id', $assignment->id)
            ->where('student_profile_id', $profile->id)
            ->first();

        if (! $this->canSubmit($assignment, $submission)) {
            throw ValidationException::withMessages([
                'content' => 'Submission tidak bisa diubah lagi.',
            ]);
        }

        $rules = [
            'content' => $assignment->allow_text_submission
                ? ['nullable', 'string', 'max:20000']
                : ['prohibited'],
        ];

        if ($assignment->allow_file_submission) {
            $rules['files'] = ['nullable', 'array'];
            $rules['files.*'] = [
                'file',
                'max:'.$assignment->max_file_size_kb,
                'extensions:'.implode(',', $assignment->allowedExtensions()),
            ];
        } else {
            $rules['files'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        $content = trim((string) ($validated['content'] ?? ''));
        $uploadedFiles = $request->file('files', []);
        $existingFiles = $submission?->files->count() ?? 0;
        $maxFiles = $this->maxFiles($assignment);

        if ($maxFiles !== null && $existingFiles + count($uploadedFiles) > $maxFiles) {
            throw ValidationException::withMessages([
                'files' => 'Maksimal '.$maxFiles.' berkas untuk tugas ini.',
            ]);
        }

        if (blank(strip_tags($content)) && $uploadedFiles === [] && $existingFiles === 0) {
            throw ValidationException::withMessages([
                'content' => 'Isi jawaban atau unggah minimal satu berkas.',
            ]);
        }

        DB::transaction(function () use (
            $assignment,
            $profile,
            $submission,
            $content,
            $uploadedFiles
        ): void {
            $now = now();
            $status = $assignment->due_at && $now->gt($assignment->due_at) ? 'late' : 'submitted';

            // Unique (assignment_id, student_profile_id) mencakup baris yang
            // sudah di-soft-delete, jadi baris lama harus dipulihkan dulu.
            $target = AssignmentSubmission::query()
                ->withTrashed()
                ->firstOrNew([
                    'assignment_id' => $assignment->id,
                    'student_profile_id' => $profile->id,
                ]);

            if ($target->exists && $target->trashed()) {
                $target->restore();
            }

            $target->fill([
                'content' => $content !== '' ? $this->sanitizeHtml($content) : null,
                'status' => $status,
                'submitted_at' => $submission?->submitted_at ?? $now,
                'last_resubmitted_at' => $submission ? $now : null,
            ])->save();

            $target->load('grade');

            if ($target->grade?->status === 'returned') {
                $target->grade->update([
                    'status' => 'resubmitted',
                    'score' => null,
                    'feedback' => null,
                    'graded_at' => null,
                    'graded_by' => null,
                ]);
            }

            foreach ($uploadedFiles as $file) {
                if (! $file) {
                    continue;
                }

                $target->files()->create([
                    'file_path' => $file->store('assignments/submissions', 'public'),
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => Str::lower($file->getClientOriginalExtension()),
                    'file_size' => $file->getSize(),
                ]);
            }

            AssignmentStatusHistory::query()->create([
                'assignment_id' => $assignment->id,
                'assignment_submission_id' => $target->id,
                'actor_id' => auth()->id(),
                'status' => $status,
                'note' => $submission ? 'Submission resubmitted.' : 'Submission created.',
            ]);
        });

        return back()->with('success', 'Submission berhasil dikirim.');
    }

    public function removeFile(Request $request, AssignmentSubmissionFile $file): RedirectResponse
    {
        $profile = $this->studentProfile($request);

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $file->loadMissing('submission.assignment');
        $submission = $file->submission;
        $assignment = $submission?->assignment;

        abort_if($submission === null || $assignment === null, 404);
        abort_if((int) $submission->student_profile_id !== (int) $profile->id, 403);

        $this->assertEnrollment($profile, $assignment);

        if (! $this->canSubmit($assignment, $submission)) {
            throw ValidationException::withMessages([
                'files' => 'Lampiran tidak bisa dihapus lagi.',
            ]);
        }

        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assignmentRows(StudentProfile $profile): Collection
    {
        $offeringIds = $this->enrolledOfferingIds($profile);

        if ($offeringIds->isEmpty()) {
            return collect();
        }

        return Assignment::query()
            ->published()
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'submissions' => fn ($query) => $query
                    ->where('student_profile_id', $profile->id)
                    ->with(['files', 'grade']),
            ])
            ->whereIn('course_offering_id', $offeringIds)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->get()
            ->map(function (Assignment $assignment): array {
                $submission = $assignment->submissions->first();

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'courseCode' => $assignment->courseOffering?->course?->code ?? $assignment->courseOffering?->code ?? '-',
                    'courseName' => $assignment->courseOffering?->course?->name ?? $assignment->courseOffering?->label ?? '-',
                    'classLabel' => $assignment->courseOffering?->label,
                    'academicYear' => $assignment->courseOffering?->academicYear?->name ?? '-',
                    'dueAt' => $assignment->due_at?->format('d M Y H:i'),
                    'dueHuman' => $assignment->due_at?->diffForHumans(),
                    'isPastDue' => $assignment->isPastDue(),
                    'status' => $this->statusKey($assignment, $submission),
                    'statusLabel' => $this->statusLabel($assignment, $submission),
                    'statusTone' => $this->statusTone($this->statusKey($assignment, $submission)),
                    'canSubmit' => $this->canSubmit($assignment, $submission),
                    'score' => $submission?->grade?->score !== null ? (float) $submission->grade->score : null,
                    'maxScore' => (float) $assignment->max_score,
                    'submittedAt' => $submission?->submitted_at?->format('d M Y H:i'),
                    'filesCount' => $submission?->files->count() ?? 0,
                ];
            });
    }

    private function statusKey(Assignment $assignment, ?AssignmentSubmission $submission): string
    {
        if ($submission === null) {
            return $this->unsubmittedStatus($assignment);
        }

        $gradeStatus = in_array($submission->grade?->status, ['graded', 'returned'], true)
            ? $submission->grade->status
            : null;

        return $gradeStatus ?? $submission->status ?? 'submitted';
    }

    private function unsubmittedStatus(Assignment $assignment): string
    {
        if (! $assignment->isPastDue()) {
            return 'not_submitted';
        }

        // Tugas yang masih menerima submission telat belum "hilang".
        return $assignment->accept_late_submission ? 'not_submitted' : 'missing';
    }

    private function statusLabel(Assignment $assignment, ?AssignmentSubmission $submission): string
    {
        return match ($this->statusKey($assignment, $submission)) {
            'submitted' => 'Submitted',
            'late' => 'Submitted Late',
            'graded' => 'Dinilai',
            'returned' => 'Perlu Revisi',
            'missing' => 'Missing',
            default => 'Belum Submit',
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'submitted', 'not_submitted' => 'green',
            'late', 'returned' => 'amber',
            'graded' => 'brand',
            'missing' => 'red',
            default => 'gray',
        };
    }

    private function canSubmit(Assignment $assignment, ?AssignmentSubmission $submission): bool
    {
        if (! $assignment->canAcceptSubmission()) {
            return false;
        }

        if ($submission === null) {
            return true;
        }

        if ($submission->grade?->status === 'graded') {
            return false;
        }

        if ($submission->grade?->status === 'returned') {
            return true;
        }

        return $assignment->allow_resubmission && ! $assignment->isPastDue();
    }

    private function maxFiles(Assignment $assignment): ?int
    {
        $value = (int) ($assignment->max_files ?? 0);

        return $value > 0 ? $value : null;
    }

    private function studentProfile(Request $request): ?StudentProfile
    {
        return $request->user()?->studentProfile()->with('studyProgram')->first();
    }

    /**
     * @return Collection<int, int>
     */
    private function enrolledOfferingIds(StudentProfile $profile): Collection
    {
        return StudyPlanDetail::query()
            ->activeEnrollment()
            ->whereHas('studyPlan', fn ($query) => $query->where('student_profile_id', $profile->id))
            ->pluck('course_offering_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function assertEnrollment(StudentProfile $profile, Assignment $assignment): void
    {
        abort_unless(
            $this->enrolledOfferingIds($profile)->contains((int) $assignment->course_offering_id),
            403,
            'Anda tidak terdaftar pada kelas ini.',
        );
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><a><blockquote>'
            .'<code><pre><h2><h3><h4><span><div><table><thead><tbody><tr><th><td>';

        $clean = strip_tags($html, $allowed);

        // Buang event handler dan URL javascript: dari atribut yang tersisa.
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace(
            '/(href|src)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\'|javascript:[^\s>]+)/i',
            '$1="#"',
            $clean,
        ) ?? '';

        return trim($clean);
    }
}
