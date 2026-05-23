<?php

namespace App\Support\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\GraduationBatch;
use App\Models\StudentService\GraduationDocument;
use App\Models\StudentService\GraduationDocumentRequirement;
use App\Models\StudentService\GraduationPolicy;
use App\Support\Financial\FinancialClearanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GraduationApplicationService
{
    public function create(StudentProfile $studentProfile, array $payload, ?UploadedFile $attachment = null, array $documentUploads = []): GraduationApplication
    {
        $this->ensureCanSubmit($studentProfile);

        return DB::transaction(function () use ($studentProfile, $payload, $attachment, $documentUploads): GraduationApplication {
            [$attachmentPath, $attachmentName] = $this->storeAttachment($attachment);
            $batch = $this->graduationBatch($payload['graduation_batch_id'] ?? null, $studentProfile);
            $period = $batch->academicPeriod;

            $application = GraduationApplication::create([
                'application_number' => app(GraduationApplicationNumberService::class)->next(),
                'student_profile_id' => $studentProfile->id,
                'academic_period_id' => $period->id,
                'graduation_batch_id' => $batch->id,
                'graduation_period' => $period->name,
                'thesis_title' => $payload['thesis_title'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'eligibility_snapshot' => $this->eligibilityReport($studentProfile)['snapshot'],
                'admin_checklist' => $this->defaultChecklist(),
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->storeRequirementDocuments($application, $documentUploads, $studentProfile);
            $this->recordHistory($application, null, 'submitted', 'Graduation application submitted by student.', auth()->id());
            app(StudentServiceNotificationService::class)->graduation($application, 'submitted', 'Pengajuan yudisium berhasil dikirim.');

            return $application;
        });
    }

    public function resubmit(GraduationApplication $application, array $payload, ?UploadedFile $attachment = null, array $documentUploads = []): GraduationApplication
    {
        if ($application->status !== 'revision_requested') {
            throw new \RuntimeException('Hanya pengajuan yudisium yang perlu perbaikan yang bisa dikirim ulang.');
        }

        return DB::transaction(function () use ($application, $payload, $attachment, $documentUploads): GraduationApplication {
            $application->loadMissing('studentProfile');
            $batch = $this->graduationBatch($payload['graduation_batch_id'] ?? null, $application->studentProfile, true);
            $period = $batch->academicPeriod;
            $attachmentPath = $application->attachment_path;
            $attachmentName = $application->attachment_name;

            if ($attachment) {
                if ($application->attachment_path) {
                    Storage::disk('public')->delete($application->attachment_path);
                }

                [$attachmentPath, $attachmentName] = $this->storeAttachment($attachment);
            }

            $from = $application->status;
            $application->update([
                'academic_period_id' => $period->id,
                'graduation_batch_id' => $batch->id,
                'graduation_period' => $period->name,
                'thesis_title' => $payload['thesis_title'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'eligibility_snapshot' => $this->eligibilityReport($application->studentProfile, $application->id)['snapshot'],
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->storeRequirementDocuments($application, $documentUploads, $application->studentProfile);
            $this->recordHistory($application, $from, 'submitted', 'Graduation application corrected and resubmitted by student.', auth()->id());
            app(StudentServiceNotificationService::class)->graduation($application, 'submitted', 'Perbaikan pengajuan yudisium berhasil dikirim ulang.');

            return $application->refresh();
        });
    }

    public function setStatus(GraduationApplication $application, string $status, ?string $notes, ?int $userId): GraduationApplication
    {
        if ($application->status === 'finalized') {
            throw new \RuntimeException('Pengajuan yudisium yang sudah finalized tidak bisa diubah.');
        }

        return DB::transaction(function () use ($application, $status, $notes, $userId): GraduationApplication {
            $from = $application->status;
            $updates = [
                'status' => $status,
                'admin_notes' => $notes,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ];

            if ($status === 'approved') {
                $updates['approved_by'] = $userId;
                $updates['approved_at'] = now();
            }

            $application->update($updates);
            $this->recordHistory($application, $from, $status, $notes, $userId);
            app(StudentServiceNotificationService::class)->graduation($application, $status, $notes);

            return $application->refresh();
        });
    }

    public function approve(GraduationApplication $application, array $checklist, ?string $notes, ?int $userId): GraduationApplication
    {
        if (! in_array($application->status, ['submitted', 'under_review', 'revision_requested'], true)) {
            throw new \RuntimeException('Status pengajuan yudisium saat ini tidak bisa diapprove.');
        }

        $normalizedChecklist = $this->normalizeChecklist($checklist, $userId, $application->admin_checklist ?: []);

        if (! collect($normalizedChecklist)->every(fn (array $item) => (bool) ($item['checked'] ?? false))) {
            throw new \RuntimeException('Semua checklist review harus lengkap sebelum pengajuan yudisium bisa diapprove.');
        }

        return DB::transaction(function () use ($application, $normalizedChecklist, $notes, $userId): GraduationApplication {
            $from = $application->status;
            $application->loadMissing('studentProfile');
            $this->ensureRequiredDocumentsVerified($application);

            $application->update([
                'status' => 'approved',
                'eligibility_snapshot' => $this->eligibilityReport($application->studentProfile, $application->id)['snapshot'],
                'admin_checklist' => $normalizedChecklist,
                'admin_notes' => $notes,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $this->recordHistory($application, $from, 'approved', $notes, $userId);
            app(StudentServiceNotificationService::class)->graduation($application, 'approved', $notes);

            return $application->refresh();
        });
    }

    public function saveChecklist(GraduationApplication $application, array $checklist, ?string $notes, ?int $userId): GraduationApplication
    {
        if (! in_array($application->status, ['submitted', 'under_review', 'revision_requested'], true)) {
            throw new \RuntimeException('Checklist hanya bisa diubah selama pengajuan masih dalam proses review.');
        }

        return DB::transaction(function () use ($application, $checklist, $notes, $userId): GraduationApplication {
            $from = $application->status;
            $status = in_array($application->status, ['submitted', 'revision_requested'], true)
                ? 'under_review'
                : $application->status;

            $application->update([
                'status' => $status,
                'admin_checklist' => $this->normalizeChecklist($checklist, $userId, $application->admin_checklist ?: []),
                'admin_notes' => $notes,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            if ($from !== $status) {
                $this->recordHistory($application, $from, $status, 'Review checklist started by admin.', $userId);
                app(StudentServiceNotificationService::class)->graduation($application, $status, 'Review checklist dimulai oleh admin.');
            }

            return $application->refresh();
        });
    }

    public function finalize(GraduationApplication $application, ?string $graduationDate, ?string $notes, ?int $userId): GraduationApplication
    {
        if ($application->status !== 'approved') {
            throw new \RuntimeException('Pengajuan harus approved sebelum difinalisasi.');
        }

        return DB::transaction(function () use ($application, $notes, $userId): GraduationApplication {
            $application->loadMissing(['studentProfile', 'graduationBatch']);
            $from = $application->status;
            $date = $application->graduationBatch?->yudisium_date?->toDateString();

            if (! $date) {
                throw new \RuntimeException('Tanggal yudisium resmi pada batch belum diatur.');
            }

            $application->studentProfile()->update([
                'academic_status' => 'Lulus',
                'graduation_date' => $date,
                'is_active' => false,
                'updated_by' => $userId,
            ]);

            $application->update([
                'status' => 'finalized',
                'graduation_date' => $date,
                'finalized_by' => $userId,
                'finalized_at' => now(),
                'admin_notes' => $notes ?: $application->admin_notes,
            ]);

            $this->recordHistory($application, $from, 'finalized', $notes ?: 'Graduation finalized by admin.', $userId);
            app(StudentServiceNotificationService::class)->graduation($application, 'finalized', $notes ?: 'Yudisium sudah difinalisasi.');

            return $application->refresh();
        });
    }

    public function finalizeApprovedBatch(GraduationBatch $batch, ?string $notes, ?int $userId): int
    {
        if (! $batch->yudisium_date) {
            throw new \RuntimeException('Tanggal yudisium resmi pada batch wajib diisi sebelum bulk finalize.');
        }

        return DB::transaction(function () use ($batch, $notes, $userId): int {
            $applications = $batch->applications()
                ->where('status', 'approved')
                ->with(['studentProfile', 'graduationBatch'])
                ->get();

            foreach ($applications as $application) {
                $this->finalize($application, null, $notes ?: 'Bulk finalized from yudisium batch.', $userId);
            }

            if ($applications->isNotEmpty()) {
                $batch->update(['status' => 'finalized']);
            }

            return $applications->count();
        });
    }

    public function eligibilitySnapshot(StudentProfile $studentProfile): array
    {
        return $this->eligibilityReport($studentProfile)['snapshot'];
    }

    public function eligibilityReport(StudentProfile $studentProfile, ?int $ignoreApplicationId = null): array
    {
        $studentProfile->loadMissing(['studyProgram.faculty']);
        $policy = $this->resolvePolicy($studentProfile);
        $activeBatches = $this->activeGraduationBatches($studentProfile);
        $activeApplication = GraduationApplication::query()
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('status', ['submitted', 'under_review', 'revision_requested', 'approved'])
            ->when($ignoreApplicationId, fn ($query) => $query->whereKeyNot($ignoreApplicationId))
            ->latest()
            ->first();

        $latestResult = $studentProfile->studyResults()->latest('semester_no')->first();
        $passedCredits = (int) $studentProfile->transcriptEntries()
            ->where('is_best_grade', true)
            ->where('result_status', 'Passed')
            ->sum('credits');
        $hasIncompleteGrade = $studentProfile->transcriptEntries()
            ->where('is_best_grade', true)
            ->where('result_status', 'Incomplete')
            ->exists();
        $hasGraduationFinancialHold = app(FinancialClearanceService::class)->hasBlockingHold($studentProfile, ['graduation']);
        $currentSemester = (int) ($studentProfile->current_semester ?? 0);
        $latestGpa = $latestResult?->cumulative_gpa !== null ? (float) $latestResult->cumulative_gpa : null;

        $checks = [
            'active_status' => [
                'label' => 'Status mahasiswa aktif',
                'required' => (bool) $policy?->require_active_status,
                'passed' => ! $policy?->require_active_status || $studentProfile->academic_status === 'Aktif',
                'actual' => $studentProfile->academic_status,
                'expected' => 'Aktif',
                'message' => $studentProfile->academic_status === 'Aktif'
                    ? 'Status akademik kamu aktif.'
                    : 'Status akademik kamu saat ini '.$studentProfile->academic_status.'. Hubungi akademik kalau status ini belum sesuai.',
            ],
            'minimum_semester' => [
                'label' => 'Semester minimal',
                'required' => true,
                'passed' => $currentSemester >= (int) ($policy?->minimum_semester ?? 0),
                'actual' => $currentSemester,
                'expected' => (int) ($policy?->minimum_semester ?? 0),
                'message' => 'Semester kamu '.$currentSemester.' dari minimal '.(int) ($policy?->minimum_semester ?? 0).'.',
            ],
            'minimum_passed_credits' => [
                'label' => 'SKS lulus minimal',
                'required' => true,
                'passed' => $passedCredits >= (int) ($policy?->minimum_passed_credits ?? 0),
                'actual' => $passedCredits,
                'expected' => (int) ($policy?->minimum_passed_credits ?? 0),
                'message' => 'SKS lulus yang terbaca '.$passedCredits.' dari minimal '.(int) ($policy?->minimum_passed_credits ?? 0).'.',
            ],
            'minimum_gpa' => [
                'label' => 'IPK minimal',
                'required' => true,
                'passed' => $latestGpa !== null && $latestGpa >= (float) ($policy?->minimum_gpa ?? 0),
                'actual' => $latestGpa,
                'expected' => (float) ($policy?->minimum_gpa ?? 0),
                'message' => 'IPK kumulatif yang terbaca '.($latestGpa !== null ? number_format($latestGpa, 2) : 'belum tersedia').' dari minimal '.number_format((float) ($policy?->minimum_gpa ?? 0), 2).'.',
            ],
            'financial_clearance' => [
                'label' => 'Tidak ada financial hold graduation',
                'required' => (bool) $policy?->require_no_financial_hold,
                'passed' => ! $policy?->require_no_financial_hold || ! $hasGraduationFinancialHold,
                'actual' => $hasGraduationFinancialHold ? 'blocked' : 'clear',
                'expected' => 'clear',
                'message' => $hasGraduationFinancialHold
                    ? 'Masih ada financial hold untuk graduation. Selesaikan tagihan atau minta dispensasi finance.'
                    : 'Tidak ada financial hold graduation.',
            ],
            'incomplete_grade' => [
                'label' => 'Tidak ada nilai incomplete',
                'required' => (bool) $policy?->require_no_incomplete_grade,
                'passed' => ! $policy?->require_no_incomplete_grade || ! $hasIncompleteGrade,
                'actual' => $hasIncompleteGrade ? 'has incomplete' : 'clear',
                'expected' => 'clear',
                'message' => $hasIncompleteGrade
                    ? 'Masih ada nilai incomplete di transkrip.'
                    : 'Tidak ada nilai incomplete yang terbaca.',
            ],
            'open_yudisium_period' => [
                'label' => 'Periode yudisium sedang dibuka',
                'required' => (bool) $policy?->require_open_yudisium_period,
                'passed' => ! $policy?->require_open_yudisium_period || $activeBatches->isNotEmpty(),
                'actual' => $activeBatches->count(),
                'expected' => '>= 1',
                'message' => $activeBatches->isNotEmpty()
                    ? $activeBatches->count().' batch yudisium sedang dibuka.'
                    : 'Belum ada batch yudisium yang sedang dibuka.',
            ],
            'no_active_application' => [
                'label' => 'Tidak ada pengajuan yudisium aktif',
                'required' => true,
                'passed' => ! $activeApplication,
                'actual' => $activeApplication?->application_number ?? 'clear',
                'expected' => 'clear',
                'message' => $activeApplication
                    ? 'Masih ada pengajuan aktif '.$activeApplication->application_number.'. Selesaikan pengajuan itu dulu sebelum membuat pengajuan baru.'
                    : 'Tidak ada pengajuan yudisium aktif lainnya.',
            ],
        ];

        if (! $policy) {
            $checks['graduation_policy'] = [
                'label' => 'Policy yudisium aktif tersedia',
                'required' => true,
                'passed' => false,
                'actual' => 'missing',
                'expected' => 'active policy',
                'message' => 'Belum ada policy yudisium aktif. Admin akademik perlu mengaktifkan policy dulu.',
            ];
        }

        $snapshot = [
            'policy_id' => $policy?->id,
            'policy_name' => $policy?->name,
            'policy_scope' => $policy?->study_program_id ? 'Program Studi' : 'Global',
            'academic_status' => $studentProfile->academic_status,
            'is_active' => (bool) $studentProfile->is_active,
            'study_program' => $studentProfile->studyProgram?->name,
            'faculty' => $studentProfile->studyProgram?->faculty?->name,
            'current_semester' => $currentSemester,
            'passed_credits' => $passedCredits,
            'latest_cumulative_gpa' => $latestGpa,
            'has_incomplete_grade' => $hasIncompleteGrade,
            'has_graduation_financial_hold' => $hasGraduationFinancialHold,
            'active_application_number' => $activeApplication?->application_number,
            'checks' => $checks,
            'checked_at' => now()->toDateTimeString(),
        ];

        return [
            'policy' => $policy,
            'snapshot' => $snapshot,
            'checks' => $checks,
            'can_submit' => collect($checks)->every(fn (array $check) => ! ($check['required'] ?? true) || (bool) $check['passed']),
        ];
    }

    public function ensureCanSubmit(StudentProfile $studentProfile): void
    {
        $report = $this->eligibilityReport($studentProfile);

        if (! $report['can_submit']) {
            $failed = collect($report['checks'])
                ->filter(fn (array $check) => ($check['required'] ?? true) && ! $check['passed'])
                ->pluck('label')
                ->implode(', ');

            throw new \RuntimeException('Pengajuan yudisium belum bisa dikirim. Syarat belum terpenuhi: '.$failed.'.');
        }
    }

    public function resolvePolicy(StudentProfile $studentProfile): ?GraduationPolicy
    {
        return GraduationPolicy::query()
            ->where('is_active', true)
            ->where(function ($query) use ($studentProfile) {
                $query->where('study_program_id', $studentProfile->study_program_id)
                    ->orWhereNull('study_program_id');
            })
            ->orderByRaw('CASE WHEN study_program_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    public function activeGraduationBatches(StudentProfile $studentProfile)
    {
        return GraduationBatch::query()
            ->with(['academicPeriod.academicYear', 'studyProgram'])
            ->where('status', 'open')
            ->where(function ($query) use ($studentProfile) {
                $query->whereNull('study_program_id')
                    ->orWhere('study_program_id', $studentProfile->study_program_id);
            })
            ->whereHas('academicPeriod', function ($query) {
                $query->where('type', 'Yudisium')
                    ->where('is_active', true)
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>=', now());
            })
            ->orderBy('yudisium_date')
            ->orderBy('name')
            ->get();
    }

    public function documentRequirements(StudentProfile $studentProfile)
    {
        return GraduationDocumentRequirement::query()
            ->where('is_active', true)
            ->where(function ($query) use ($studentProfile) {
                $query->whereNull('study_program_id')
                    ->orWhere('study_program_id', $studentProfile->study_program_id);
            })
            ->orderByRaw('CASE WHEN study_program_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->unique('document_type')
            ->values();
    }

    public function verifyDocument(GraduationDocument $document, string $status, ?string $notes, ?int $userId): GraduationDocument
    {
        if (! in_array($status, ['pending', 'verified', 'rejected'], true)) {
            throw new \RuntimeException('Status dokumen yudisium tidak valid.');
        }

        $document->update([
            'verification_status' => $status,
            'verification_notes' => $notes,
            'verified_by' => $status === 'pending' ? null : $userId,
            'verified_at' => $status === 'pending' ? null : now(),
        ]);

        return $document->refresh();
    }

    public function defaultChecklist(): array
    {
        return [
            'transcript_checked' => $this->emptyChecklistItem(),
            'final_project_checked' => $this->emptyChecklistItem(),
            'library_clearance' => $this->emptyChecklistItem(),
            'lab_clearance' => $this->emptyChecklistItem(),
            'document_complete' => $this->emptyChecklistItem(),
        ];
    }

    public function recordHistory(GraduationApplication $application, ?string $from, string $to, ?string $notes, ?int $userId): void
    {
        $application->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'changed_by' => $userId,
        ]);
    }

    private function normalizeChecklist(array $checklist, ?int $userId = null, array $existing = []): array
    {
        return collect($this->defaultChecklist())
            ->map(function ($value, $key) use ($checklist, $existing, $userId) {
                $incoming = $checklist[$key] ?? [];
                $previous = $existing[$key] ?? [];
                $wasChecked = is_array($previous) ? (bool) ($previous['checked'] ?? false) : (bool) $previous;
                $isChecked = is_array($incoming) ? (bool) ($incoming['checked'] ?? false) : (bool) $incoming;

                return [
                    'checked' => $isChecked,
                    'checked_by' => $isChecked
                        ? ($previous['checked_by'] ?? $incoming['checked_by'] ?? $userId)
                        : null,
                    'checked_at' => $isChecked
                        ? ($wasChecked ? ($previous['checked_at'] ?? $incoming['checked_at'] ?? now()->toDateTimeString()) : ($incoming['checked_at'] ?? now()->toDateTimeString()))
                        : null,
                    'notes' => is_array($incoming)
                        ? (($incoming['notes'] ?? null) ?: null)
                        : (($previous['notes'] ?? null) ?: null),
                ];
            })
            ->all();
    }

    private function emptyChecklistItem(): array
    {
        return [
            'checked' => false,
            'checked_by' => null,
            'checked_at' => null,
            'notes' => null,
        ];
    }

    private function graduationBatch($batchId, StudentProfile $studentProfile, bool $allowReviewBatch = false): GraduationBatch
    {
        $batch = GraduationBatch::query()
            ->with(['academicPeriod'])
            ->whereKey($batchId)
            ->whereIn('status', $allowReviewBatch ? ['open', 'review'] : ['open'])
            ->where(function ($query) use ($studentProfile) {
                $query->whereNull('study_program_id')
                    ->orWhere('study_program_id', $studentProfile->study_program_id);
            })
            ->when(! $allowReviewBatch, function ($query) {
                $query->whereHas('academicPeriod', function ($periodQuery) {
                    $periodQuery->where('type', 'Yudisium')
                        ->where('is_active', true)
                        ->where('start_at', '<=', now())
                        ->where('end_at', '>=', now());
                });
            })
            ->first();

        if (! $batch) {
            throw new \RuntimeException('Batch yudisium aktif tidak valid atau belum dibuka untuk program studi kamu.');
        }

        return $batch;
    }

    private function storeAttachment(?UploadedFile $attachment): array
    {
        if (! $attachment) {
            return [null, null];
        }

        return [
            $attachment->store('student-services/graduation-attachments', 'public'),
            $attachment->getClientOriginalName(),
        ];
    }

    private function storeRequirementDocuments(GraduationApplication $application, array $documentUploads, StudentProfile $studentProfile): void
    {
        $requirements = $this->documentRequirements($studentProfile)->keyBy('id');

        foreach ($documentUploads as $requirementId => $upload) {
            if (! $upload instanceof UploadedFile || ! $requirements->has((int) $requirementId)) {
                continue;
            }

            $requirement = $requirements->get((int) $requirementId);
            $extension = strtolower($upload->getClientOriginalExtension());

            if (! in_array($extension, $requirement->allowedExtensionsList(), true)) {
                throw new \RuntimeException('File '.$requirement->label.' harus bertipe '.implode(', ', $requirement->allowedExtensionsList()).'.');
            }

            if ($requirement->max_size_kb && ($upload->getSize() / 1024) > $requirement->max_size_kb) {
                throw new \RuntimeException('File '.$requirement->label.' melebihi batas '.number_format($requirement->max_size_kb).' KB.');
            }

            $fileSize = (int) $upload->getSize();
            $filePath = $upload->store('student-services/graduation-documents', 'public');

            $existing = GraduationDocument::query()
                ->where('graduation_application_id', $application->id)
                ->where('graduation_document_requirement_id', $requirement->id)
                ->first();

            if ($existing) {
                Storage::disk('public')->delete($existing->file_path);
            }

            GraduationDocument::updateOrCreate(
                [
                    'graduation_application_id' => $application->id,
                    'graduation_document_requirement_id' => $requirement->id,
                ],
                [
                    'document_type' => $requirement->document_type,
                    'file_path' => $filePath,
                    'file_name' => $upload->getClientOriginalName(),
                    'file_size' => $fileSize,
                    'verification_status' => 'pending',
                    'verification_notes' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                ]
            );
        }
    }

    private function ensureRequiredDocumentsVerified(GraduationApplication $application): void
    {
        $application->loadMissing(['studentProfile', 'documents']);
        $documentsByRequirement = $application->documents->keyBy('graduation_document_requirement_id');
        $missingOrPending = $this->documentRequirements($application->studentProfile)
            ->filter(function (GraduationDocumentRequirement $requirement) use ($documentsByRequirement) {
                if (! $requirement->is_required) {
                    return false;
                }

                $document = $documentsByRequirement->get($requirement->id);

                return ! $document || $document->verification_status !== 'verified';
            })
            ->pluck('label')
            ->implode(', ');

        if ($missingOrPending !== '') {
            throw new \RuntimeException('Dokumen wajib belum lengkap/terverifikasi: '.$missingOrPending.'.');
        }
    }
}
