<?php

namespace App\Support\Academic;

use App\Enums\PlagiarismStatus;
use App\Enums\ThesisDefenseStatus;
use App\Enums\ThesisMilestoneStatus;
use App\Enums\ThesisStatus;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\Thesis;
use App\Models\Academic\ThesisDefense;
use App\Models\Academic\ThesisDocument;
use App\Models\Academic\ThesisMilestone;
use App\Models\Academic\ThesisSupervisor;
use App\Models\User;
use App\Support\Notifications\NotificationDispatchService;
use App\Support\StudentGradeCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alur kerja Skripsi/Tugas Akhir.
 *
 * Service ini menjaga transisi status agar tidak tersebar di controller:
 * pengajuan proposal → persetujuan & penetapan pembimbing → bimbingan
 * milestone → penjadwalan sidang → penilaian → finalisasi & arsip.
 */
class ThesisService
{
    public function __construct(
        private readonly StudentGradeCalculator $gradeCalculator,
        private readonly NotificationDispatchService $notifications,
    ) {}

    /**
     * @throws ValidationException
     */
    public function ensureNoActiveThesis(StudentProfile $student, ?int $ignoreId = null): void
    {
        $exists = Thesis::query()
            ->where('student_profile_id', $student->id)
            ->whereIn('status', ThesisStatus::activeValues())
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'student_profile_id' => 'Mahasiswa ini masih memiliki skripsi/tugas akhir aktif. Selesaikan atau batalkan terlebih dahulu.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProposal(StudentProfile $student, array $data, ?User $actor = null): Thesis
    {
        $this->ensureNoActiveThesis($student);

        return DB::transaction(function () use ($student, $data, $actor) {
            $thesis = Thesis::create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $data['academic_year_id'],
                'type' => $data['type'] ?? Thesis::TYPE_SKRIPSI,
                'title' => $data['title'],
                'topic' => $data['topic'] ?? null,
                'abstract' => $data['abstract'] ?? null,
                'keywords' => $data['keywords'] ?? null,
                'status' => ThesisStatus::SUBMITTED->value,
                'proposal_submitted_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->createDefaultMilestones($thesis, $actor);

            return $thesis;
        });
    }

    public function createDefaultMilestones(Thesis $thesis, ?User $actor = null): void
    {
        if ($thesis->milestones()->exists()) {
            return;
        }

        $defaults = [
            ['title' => 'Penyusunan Proposal', 'description' => 'Finalisasi proposal penelitian dan studi literatur.', 'sequence' => 1],
            ['title' => 'Bimbingan Bab I - III', 'description' => 'Pendalaman metodologi, pengumpulan data, dan analisis awal.', 'sequence' => 2],
            ['title' => 'Bimbingan Bab IV - V', 'description' => 'Penyusunan hasil, pembahasan, dan kesimpulan.', 'sequence' => 3],
            ['title' => 'Seminar Hasil', 'description' => 'Presentasi hasil penelitian di depan pembimbing dan penguji.', 'sequence' => 4],
            ['title' => 'Finalisasi Laporan & Sidang', 'description' => 'Perbaikan akhir naskah dan persiapan sidang.', 'sequence' => 5],
        ];

        foreach ($defaults as $milestone) {
            $thesis->milestones()->create([
                ...$milestone,
                'status' => ThesisMilestoneStatus::PENDING->value,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
        }
    }

    /**
     * @param  array<int, int|string>  $supervisorIds
     */
    public function approve(Thesis $thesis, User $actor, array $supervisorIds, ?string $note = null): void
    {
        $supervisorIds = collect($supervisorIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($supervisorIds->isEmpty()) {
            throw ValidationException::withMessages([
                'supervisor_ids' => 'Pilih minimal satu dosen pembimbing.',
            ]);
        }

        DB::transaction(function () use ($thesis, $actor, $supervisorIds, $note) {
            $thesis->update([
                'status' => ThesisStatus::IN_PROGRESS->value,
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'rejected_at' => null,
                'rejection_reason' => null,
                'notes' => $note ?? $thesis->notes,
                'updated_by' => $actor->id,
            ]);

            $this->syncSupervisors($thesis, $supervisorIds->all(), $actor);
            $this->createDefaultMilestones($thesis, $actor);
            $this->syncStatus($thesis->refresh());
        });

        $this->notifications->thesisStatusUpdated($thesis->refresh(), $note);
    }

    /**
     * @param  array<int, int>  $supervisorIds
     */
    private function syncSupervisors(Thesis $thesis, array $supervisorIds, User $actor): void
    {
        foreach ($thesis->supervisors()->withTrashed()->get() as $existing) {
            if (! in_array((int) $existing->lecturer_profile_id, $supervisorIds, true)) {
                $existing->update(['is_active' => false, 'updated_by' => $actor->id]);
            }
        }

        foreach ($supervisorIds as $index => $lecturerProfileId) {
            $supervisor = ThesisSupervisor::withTrashed()
                ->where('thesis_id', $thesis->id)
                ->where('lecturer_profile_id', $lecturerProfileId)
                ->first();

            $payload = [
                'role' => $index === 0 ? 'primary' : 'secondary',
                'assigned_at' => now()->toDateString(),
                'is_active' => true,
                'updated_by' => $actor->id,
            ];

            if ($supervisor) {
                if ($supervisor->trashed()) {
                    $supervisor->restore();
                }

                $supervisor->update($payload);
            } else {
                $thesis->supervisors()->create([
                    ...$payload,
                    'lecturer_profile_id' => $lecturerProfileId,
                    'created_by' => $actor->id,
                ]);
            }
        }
    }

    public function reject(Thesis $thesis, User $actor, string $reason): void
    {
        $thesis->update([
            'status' => ThesisStatus::REJECTED->value,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'approved_at' => null,
            'approved_by' => null,
            'updated_by' => $actor->id,
        ]);

        $this->notifications->thesisStatusUpdated($thesis->refresh(), $reason);
    }

    public function cancel(Thesis $thesis, ?User $actor = null, ?string $reason = null): void
    {
        $thesis->update([
            'status' => ThesisStatus::CANCELLED->value,
            'notes' => $reason ?? $thesis->notes,
            'updated_by' => $actor?->id,
        ]);

        $this->notifications->thesisStatusUpdated($thesis->refresh(), $reason);
    }

    public function submitMilestone(ThesisMilestone $milestone, ?User $actor = null, ?string $note = null): void
    {
        $milestone->update([
            'status' => ThesisMilestoneStatus::SUBMITTED->value,
            'submitted_at' => now(),
            'feedback' => $note ?? $milestone->feedback,
            'updated_by' => $actor?->id,
        ]);

        $this->notifications->thesisMilestoneSubmitted($milestone->refresh());
    }

    public function reviewMilestone(ThesisMilestone $milestone, User $reviewer, string $status, ?string $feedback = null): void
    {
        if (! in_array($status, [ThesisMilestoneStatus::APPROVED->value, ThesisMilestoneStatus::REVISION->value], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status review milestone tidak valid.',
            ]);
        }

        $previousStatus = $milestone->thesis()->value('status');

        DB::transaction(function () use ($milestone, $reviewer, $status, $feedback) {
            $milestone->update([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'feedback' => $feedback,
                'updated_by' => $reviewer->id,
            ]);

            $this->syncStatus($milestone->thesis()->firstOrFail());
        });

        $thesis = $milestone->thesis()->firstOrFail();

        $this->notifications->thesisMilestoneReviewed($milestone->refresh(), $feedback);

        if ($previousStatus !== $thesis->status) {
            $this->notifications->thesisStatusUpdated(
                $thesis,
                $thesis->status === ThesisStatus::DEFENSE_READY->value
                    ? 'Seluruh milestone bimbingan telah disetujui. Skripsi siap dijadwalkan sidang.'
                    : null,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function scheduleDefense(Thesis $thesis, array $data, User $actor): ThesisDefense
    {
        $previousStatus = $thesis->status;

        $defense = DB::transaction(function () use ($thesis, $data, $actor) {
            $defense = $thesis->defenses()->create([
                'type' => $data['type'],
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 90,
                'room' => $data['room'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'status' => ThesisDefenseStatus::SCHEDULED->value,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($data['examiners'] ?? [] as $examiner) {
                $defense->examiners()->create([
                    'lecturer_profile_id' => $examiner['lecturer_profile_id'],
                    'role' => $examiner['role'] ?? 'examiner',
                ]);
            }

            if ($defense->type === 'result' && $thesis->status === ThesisStatus::IN_PROGRESS->value) {
                $thesis->update([
                    'status' => ThesisStatus::DEFENSE_READY->value,
                    'updated_by' => $actor->id,
                ]);
            }

            return $defense;
        });

        $this->notifications->thesisDefenseScheduled($defense->refresh());

        $thesis->refresh();

        if ($previousStatus !== $thesis->status) {
            $this->notifications->thesisStatusUpdated($thesis, 'Jadwal sidang skripsi telah ditetapkan.');
        }

        return $defense;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function completeDefense(ThesisDefense $defense, User $actor, array $data): void
    {
        DB::transaction(function () use ($defense, $actor, $data) {
            $scores = collect($data['examiner_scores'] ?? []);

            foreach ($defense->examiners as $examiner) {
                $score = $scores->get((string) $examiner->id, $scores->get($examiner->id));

                if ($score === null || $score === '') {
                    continue;
                }

                $examiner->update([
                    'score' => $score,
                    'notes' => $data['examiner_notes'][$examiner->id] ?? $examiner->notes,
                ]);
            }

            $defense->refresh()->load('examiners');

            $score = $data['score'] ?? $defense->averageExaminerScore();
            $score = $score === null ? null : round((float) $score, 2);

            $defense->update([
                'status' => ThesisDefenseStatus::COMPLETED->value,
                'result' => $data['result'],
                'score' => $score,
                'grade' => $this->gradeForScore($score),
                'notes' => $data['notes'] ?? $defense->notes,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $thesis = $defense->thesis()->firstOrFail();

            if ($defense->type === 'result') {
                $thesis->update([
                    'status' => ThesisStatus::DEFENDED->value,
                    'final_score' => $score,
                    'final_grade' => $this->gradeForScore($score),
                    'updated_by' => $actor->id,
                ]);
            } else {
                $this->syncStatus($thesis);
            }
        });

        $thesis = $defense->thesis()->firstOrFail();
        $resultLabel = $defense->refresh()->resultLabel();

        $this->notifications->thesisStatusUpdated(
            $thesis,
            trim('Hasil '.strtolower($defense->typeLabel()).': '.($resultLabel ?? 'telah dinilai').'.'),
        );
    }

    public function finalize(Thesis $thesis, User $actor, ?float $score = null, bool $publish = false): void
    {
        $resultDefense = $thesis->defenses()
            ->where('type', 'result')
            ->where('status', ThesisDefenseStatus::COMPLETED->value)
            ->latest('completed_at')
            ->first();

        if (! $resultDefense && $thesis->status !== ThesisStatus::DEFENDED->value) {
            throw ValidationException::withMessages([
                'final_score' => 'Sidang hasil belum diselesaikan. Selesaikan penilaian sidang terlebih dahulu.',
            ]);
        }

        $score = $score ?? ($thesis->final_score !== null ? (float) $thesis->final_score : null);
        $score = $score ?? $resultDefense?->score;

        if ($score === null) {
            throw ValidationException::withMessages([
                'final_score' => 'Nilai akhir wajib diisi.',
            ]);
        }

        if ($publish && $thesis->plagiarismStatusEnum() === PlagiarismStatus::FLAGGED) {
            throw ValidationException::withMessages([
                'publish' => 'Karya tidak dapat dipublikasikan ke repositori karena hasil cek plagiarisme masih ditandai.',
            ]);
        }

        $thesis->update([
            'status' => ThesisStatus::COMPLETED->value,
            'final_score' => round((float) $score, 2),
            'final_grade' => $this->gradeForScore((float) $score),
            'completed_at' => now(),
            'repository_published' => $publish,
            'repository_published_at' => $publish ? now() : $thesis->repository_published_at,
            'updated_by' => $actor->id,
        ]);

        $this->notifications->thesisStatusUpdated(
            $thesis->refresh(),
            $publish
                ? 'Skripsi telah difinalisasi dan dipublikasikan ke repositori kampus.'
                : 'Skripsi telah difinalisasi.',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePlagiarism(Thesis $thesis, User $actor, array $data): void
    {
        $status = PlagiarismStatus::tryFrom((string) ($data['plagiarism_status'] ?? '')) ?? PlagiarismStatus::NOT_CHECKED;
        $checkedAt = $data['plagiarism_checked_at'] ?? null;

        if ($status === PlagiarismStatus::NOT_CHECKED) {
            $checkedAt = null;
        } elseif ($checkedAt === null) {
            $checkedAt = now();
        }

        $payload = [
            'plagiarism_status' => $status->value,
            'plagiarism_score' => $data['plagiarism_score'] ?? null,
            'plagiarism_tool' => $data['plagiarism_tool'] ?? null,
            'plagiarism_checked_at' => $checkedAt,
            'plagiarism_notes' => $data['plagiarism_notes'] ?? null,
            'updated_by' => $actor->id,
        ];

        if ($status === PlagiarismStatus::FLAGGED) {
            $payload['repository_published'] = false;
            $payload['repository_published_at'] = null;
        }

        $thesis->update($payload);

        $this->notifications->thesisPlagiarismUpdated($thesis->refresh());
    }

    public function syncStatus(Thesis $thesis): void
    {
        if ($thesis->status === ThesisStatus::COMPLETED->value || $thesis->status === ThesisStatus::CANCELLED->value) {
            return;
        }

        $hasCompletedResultDefense = $thesis->defenses()
            ->where('type', 'result')
            ->where('status', ThesisDefenseStatus::COMPLETED->value)
            ->exists();

        if ($hasCompletedResultDefense) {
            if ($thesis->status !== ThesisStatus::DEFENDED->value) {
                $thesis->update(['status' => ThesisStatus::DEFENDED->value]);
            }

            return;
        }

        if (! in_array($thesis->status, [ThesisStatus::IN_PROGRESS->value, ThesisStatus::DEFENSE_READY->value], true)) {
            return;
        }

        $thesis->update([
            'status' => $thesis->allMilestonesApproved()
                ? ThesisStatus::DEFENSE_READY->value
                : ThesisStatus::IN_PROGRESS->value,
        ]);
    }

    public function gradeForScore(?float $score): ?string
    {
        return $this->gradeCalculator->resolveLetterGrade($score);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeDocument(Thesis $thesis, User $actor, UploadedFile $file, array $data): ThesisDocument
    {
        $category = $data['category'] ?? 'other';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'dokumen';
        $filename = $base.'-'.now()->format('YmdHis').'.'.$extension;
        $path = $file->storeAs('thesis/'.$thesis->id, $filename, 'public');

        $version = (int) $thesis->documents()
            ->where('category', $category)
            ->max('version') + 1;

        return $thesis->documents()->create([
            'uploaded_by' => $actor->id,
            'category' => $category,
            'title' => $data['title'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'version' => $version,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function storeProposalFile(Thesis $thesis, User $actor, UploadedFile $file): ThesisDocument
    {
        $document = $this->storeDocument($thesis, $actor, $file, [
            'category' => 'proposal',
            'title' => 'Proposal Penelitian',
        ]);

        $thesis->update([
            'proposal_file_path' => $document->file_path,
            'proposal_file_name' => $document->file_name,
            'proposal_submitted_at' => now(),
        ]);

        return $document;
    }

    public function deleteDocument(ThesisDocument $document): void
    {
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();
    }
}
