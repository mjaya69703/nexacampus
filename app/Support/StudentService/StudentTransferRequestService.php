<?php

namespace App\Support\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\StudentInvoice;
use App\Models\Organization\ApprovalTemplate;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\Financial\InvoiceGenerationService;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Organization\ApprovalEngine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentTransferRequestService
{
    public function create(StudentProfile $studentProfile, array $payload, ?UploadedFile $attachment = null): StudentTransferRequest
    {
        return DB::transaction(function () use ($studentProfile, $payload, $attachment): StudentTransferRequest {
            $payload = $this->normalizePayload($studentProfile, $payload);
            [$attachmentPath, $attachmentName] = $this->storeAttachment($attachment);

            $request = StudentTransferRequest::create([
                'request_number' => app(StudentTransferNumberService::class)->next(),
                'student_profile_id' => $studentProfile->id,
                'from_study_program_id' => $studentProfile->study_program_id,
                'to_study_program_id' => $payload['to_study_program_id'],
                'current_semester' => $studentProfile->current_semester,
                'recommended_semester' => $payload['recommended_semester'] ?? null,
                'transfer_type' => $payload['transfer_type'] ?? 'study_program',
                'from_class_type' => $studentProfile->class_type,
                'to_class_type' => $payload['to_class_type'] ?? null,
                'reason' => $payload['reason'],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->recordHistory($request, null, 'submitted', 'Transfer request submitted by student.', auth()->id());
            app(StudentServiceNotificationService::class)->transfer($request, 'submitted', 'Pengajuan pindah berhasil dikirim.');

            return $this->submitForApproval($request, auth()->id());
        });
    }

    public function resubmit(StudentTransferRequest $request, array $payload, ?UploadedFile $attachment = null): StudentTransferRequest
    {
        if ($request->status !== 'revision_requested') {
            throw new \RuntimeException('Hanya pengajuan yang perlu perbaikan yang bisa dikirim ulang.');
        }

        return DB::transaction(function () use ($request, $payload, $attachment): StudentTransferRequest {
            $request->loadMissing('studentProfile');
            $payload = $this->normalizePayload($request->studentProfile, $payload);

            $attachmentPath = $request->attachment_path;
            $attachmentName = $request->attachment_name;

            if ($attachment) {
                if ($request->attachment_path) {
                    Storage::disk('public')->delete($request->attachment_path);
                }

                [$attachmentPath, $attachmentName] = $this->storeAttachment($attachment);
            }

            $from = $request->status;
            $request->update([
                'to_study_program_id' => $payload['to_study_program_id'],
                'recommended_semester' => $payload['recommended_semester'] ?? null,
                'transfer_type' => $payload['transfer_type'] ?? $request->transfer_type,
                'from_class_type' => $request->studentProfile?->class_type,
                'to_class_type' => $payload['to_class_type'] ?? null,
                'reason' => $payload['reason'],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->recordHistory($request, $from, 'submitted', 'Transfer request corrected and resubmitted by student.', auth()->id());
            app(StudentServiceNotificationService::class)->transfer($request, 'submitted', 'Perbaikan pengajuan pindah berhasil dikirim ulang.');

            return $this->submitForApproval($request->refresh(), auth()->id());
        });
    }

    public function setStatus(StudentTransferRequest $request, string $status, ?string $notes, ?int $userId): StudentTransferRequest
    {
        return DB::transaction(function () use ($request, $status, $notes, $userId): StudentTransferRequest {
            $from = $request->status;

            if ($status === 'revision_requested' && $request->approvalRequest && $request->approvalRequest->status === 'in_progress') {
                app(ApprovalEngine::class)->cancel($request->approvalRequest, $userId ? User::find($userId) : null, $notes);
                $request->refresh();
            }

            $request->update([
                'status' => $status,
                'admin_notes' => $notes,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            $this->recordHistory($request, $from, $status, $notes, $userId);
            app(StudentServiceNotificationService::class)->transfer($request, $status, $notes);

            return $request->refresh();
        });
    }

    public function approve(
        StudentTransferRequest $request,
        array $evaluation,
        ?int $userId,
        float $feeAmount = 0,
        ?string $feeDueDate = null,
    ): StudentTransferRequest {
        if (! in_array($request->status, ['submitted', 'under_review', 'revision_requested', 'in_approval'], true)) {
            throw new \RuntimeException('Status pengajuan transfer saat ini tidak bisa diapprove.');
        }

        return DB::transaction(function () use ($request, $evaluation, $userId, $feeAmount, $feeDueDate): StudentTransferRequest {
            $feeAmount = max(0, $feeAmount);
            $feeDueDate = $feeDueDate ?: now()->addDays(7)->toDateString();

            $request->update([
                'admin_notes' => $evaluation['admin_notes'] ?? null,
                'academic_evaluation_notes' => $evaluation['academic_evaluation_notes'] ?? null,
                'credit_mapping_notes' => $evaluation['credit_mapping_notes'] ?? null,
                'finance_notes' => $evaluation['finance_notes'] ?? null,
                'recommended_semester' => $evaluation['recommended_semester'] ?? $request->recommended_semester,
                'transfer_fee_amount' => $feeAmount,
                'transfer_fee_due_date' => $feeAmount > 0 ? $feeDueDate : null,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            if (! $request->approvalRequest || $request->approvalRequest->status !== 'in_progress') {
                $this->submitForApproval($request->refresh(), $request->studentProfile?->user_id);
                $request->refresh();
            }

            app(ApprovalEngine::class)->approve($request->approvalRequest, User::findOrFail($userId), $evaluation['admin_notes'] ?? null);

            return $request->refresh();
        });
    }

    public function reject(StudentTransferRequest $request, ?string $notes, ?int $userId): StudentTransferRequest
    {
        if (! $request->approvalRequest || $request->approvalRequest->status !== 'in_progress') {
            throw new \RuntimeException('Approval pengajuan transfer tidak sedang berjalan.');
        }

        app(ApprovalEngine::class)->reject($request->approvalRequest, User::findOrFail($userId), $notes);

        return $request->refresh();
    }

    public function approveFromApproval(StudentTransferRequest $request, ?int $userId, ?string $notes = null): StudentTransferRequest
    {
        return DB::transaction(function () use ($request, $userId, $notes): StudentTransferRequest {
            $from = $request->status;
            $request = $request->fresh(['studentProfile', 'transferFeeInvoice']);

            if (in_array($request->status, ['approved', 'approved_pending_payment', 'applied'], true)) {
                return $request;
            }

            $invoice = null;
            $feeAmount = max(0, (float) $request->transfer_fee_amount);
            $status = $feeAmount > 0 ? 'approved_pending_payment' : 'approved';

            if ($feeAmount > 0) {
                $invoice = $this->createTransferFeeInvoice(
                    $request,
                    $feeAmount,
                    $request->transfer_fee_due_date?->toDateString() ?: now()->addDays(7)->toDateString(),
                    $userId,
                );
            }

            $request->update([
                'status' => $status,
                'admin_notes' => $notes ?: $request->admin_notes,
                'transfer_fee_invoice_id' => $invoice?->id,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $historyNotes = $feeAmount > 0
                ? trim(($notes ? $notes.' ' : '').'Biaya transfer dibuat sebagai invoice '.$invoice?->invoice_number.'.')
                : $notes;

            $this->recordHistory($request, $from, $status, $historyNotes, $userId);
            app(StudentServiceNotificationService::class)->transfer($request, $status, $historyNotes);

            return $request->refresh();
        });
    }

    public function rejectFromApproval(StudentTransferRequest $request, ?int $userId, ?string $notes = null): StudentTransferRequest
    {
        return $this->setStatus($request, 'rejected', $notes, $userId);
    }

    public function requestRevisionFromApproval(StudentTransferRequest $request, ?int $userId, ?string $notes = null): StudentTransferRequest
    {
        return $this->setStatus($request, 'revision_requested', $notes, $userId);
    }

    public function markFeePaid(StudentTransferRequest $request, ?int $userId = null): StudentTransferRequest
    {
        if ($request->status !== 'approved_pending_payment') {
            return $request;
        }

        return DB::transaction(function () use ($request, $userId): StudentTransferRequest {
            $request = StudentTransferRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== 'approved_pending_payment') {
                return $request;
            }

            $request->update(['status' => 'approved']);
            $notes = 'Pembayaran biaya pindah telah lunas. Pengajuan siap diterapkan.';
            $this->recordHistory($request, 'approved_pending_payment', 'approved', $notes, $userId);
            app(StudentServiceNotificationService::class)->transfer($request, 'approved', $notes);

            return $request->refresh();
        });
    }

    public function applyTransfer(StudentTransferRequest $request, ?int $userId, ?string $notes = null): StudentTransferRequest
    {
        if (! in_array($request->status, ['approved', 'approved_pending_payment'], true)) {
            throw new \RuntimeException('Transfer harus approved sebelum diterapkan.');
        }

        return DB::transaction(function () use ($request, $userId, $notes): StudentTransferRequest {
            $request->loadMissing(['transferFeeInvoice', 'studentProfile']);

            if ($request->transferFeeInvoice) {
                app(InvoiceStatusService::class)->refresh($request->transferFeeInvoice);
                $request->transferFeeInvoice->refresh();

                if ($request->transferFeeInvoice->status !== 'paid') {
                    throw new \RuntimeException('Biaya transfer harus lunas sebelum transfer diterapkan.');
                }
            }

            $from = $request->status;
            $updates = [
                'updated_by' => $userId,
            ];

            if ($request->transfer_type === 'class_type') {
                $updates['class_type'] = $request->to_class_type;
            } else {
                $updates['study_program_id'] = $request->to_study_program_id;
            }

            if ($request->recommended_semester) {
                $updates['current_semester'] = $request->recommended_semester;
            }

            $request->studentProfile()->update($updates);

            $request->update([
                'status' => 'applied',
                'applied_by' => $userId,
                'applied_at' => now(),
                'admin_notes' => $notes ?: $request->admin_notes,
            ]);

            $this->recordHistory($request, $from, 'applied', $notes ?: 'Transfer applied by admin.', $userId);
            app(StudentServiceNotificationService::class)->transfer($request, 'applied', $notes ?: 'Pindah internal sudah diterapkan ke profil akademik kamu.');

            return $request->refresh();
        });
    }

    public function recordHistory(StudentTransferRequest $request, ?string $from, string $to, ?string $notes, ?int $userId): void
    {
        $request->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'changed_by' => $userId,
        ]);
    }

    private function submitForApproval(StudentTransferRequest $request, ?int $userId = null): StudentTransferRequest
    {
        $request->loadMissing(['studentProfile.user', 'fromStudyProgram', 'toStudyProgram']);

        $template = ApprovalTemplate::query()
            ->where('code', 'STUDENT_TRANSFER_REVIEW')
            ->where('is_active', true)
            ->firstOrFail();

        $approval = app(ApprovalEngine::class)->submitFromTemplate(
            template: $template,
            subject: 'Pengajuan pindah '.$request->studentProfile?->user?->name.' '.$request->request_number,
            requester: $request->studentProfile?->user,
            approvable: $request,
            payload: [
                'student_profile_id' => $request->student_profile_id,
                'from_study_program_id' => $request->from_study_program_id,
                'to_study_program_id' => $request->to_study_program_id,
                'transfer_type' => $request->transfer_type,
            ],
            reference: $request->request_number,
            notes: $request->student_notes ?: $request->reason,
            createdBy: $userId,
        );

        $from = $request->status;
        $request->update([
            'approval_request_id' => $approval->id,
            'status' => 'in_approval',
        ]);
        $this->recordHistory($request, $from, 'in_approval', $approval->waitingMessage(), $userId);

        return $request->refresh();
    }

    private function createTransferFeeInvoice(
        StudentTransferRequest $request,
        float $feeAmount,
        string $feeDueDate,
        ?int $userId,
    ): StudentInvoice {
        if ($request->transfer_fee_invoice_id) {
            return $request->transferFeeInvoice()->firstOrFail();
        }

        $request->loadMissing('studentProfile');

        $invoice = app(InvoiceGenerationService::class)->createCustomInvoice(
            studentProfile: $request->studentProfile,
            items: [[
                'item_type' => 'fee',
                'description' => 'Biaya Transfer Program '.$request->request_number,
                'amount' => $feeAmount,
            ]],
            invoiceType: 'transfer',
            dueDate: $feeDueDate,
            academicYear: null,
            semester: $request->recommended_semester ?: $request->current_semester,
            notes: 'Invoice biaya transfer dari pengajuan '.$request->request_number.'.',
            createdBy: $userId,
            issueImmediately: true,
        );

        $invoice->update([
            'source_type' => $request::class,
            'source_id' => $request->id,
        ]);

        return $invoice->refresh();
    }

    private function normalizePayload(StudentProfile $studentProfile, array $payload): array
    {
        $studentProfile->loadMissing('studyProgram.faculty');

        $transferType = $payload['transfer_type'] ?? 'study_program';
        $currentProgram = $studentProfile->studyProgram;

        if (! $currentProgram) {
            throw new \RuntimeException('Program studi mahasiswa belum tersedia.');
        }

        if ($transferType === 'class_type') {
            $payload['to_study_program_id'] = $currentProgram->id;
            $toClassType = $payload['to_class_type'] ?? null;

            if (! $toClassType) {
                throw new \RuntimeException('Kelas tujuan wajib dipilih untuk pindah kelas.');
            }

            if ($studentProfile->class_type && $studentProfile->class_type === $toClassType) {
                throw new \RuntimeException('Kelas tujuan harus berbeda dari kelas mahasiswa saat ini.');
            }

            return $payload;
        }

        $targetProgram = StudyProgram::with('faculty')->find($payload['to_study_program_id'] ?? null);

        if (! $targetProgram) {
            throw new \RuntimeException('Program studi tujuan tidak valid.');
        }

        if ($targetProgram->is($currentProgram)) {
            throw new \RuntimeException('Program studi tujuan harus berbeda dari program studi saat ini.');
        }

        $currentFacultyId = $currentProgram->faculty_id;
        $targetFacultyId = $targetProgram->faculty_id;

        if ($transferType === 'faculty' && $currentFacultyId === $targetFacultyId) {
            throw new \RuntimeException('Pindah fakultas wajib memilih program studi dari fakultas berbeda.');
        }

        if ($transferType === 'faculty' && ($payload['target_faculty_id'] ?? null) && (int) $payload['target_faculty_id'] !== (int) $targetFacultyId) {
            throw new \RuntimeException('Program studi tujuan harus sesuai dengan fakultas tujuan yang dipilih.');
        }

        if ($transferType === 'study_program' && $currentFacultyId !== $targetFacultyId) {
            throw new \RuntimeException('Pindah program studi hanya bisa memilih prodi dalam fakultas yang sama. Pilih jenis Pindah Fakultas untuk target lintas fakultas.');
        }

        $payload['to_class_type'] = null;

        return $payload;
    }

    private function storeAttachment(?UploadedFile $attachment): array
    {
        if (! $attachment) {
            return [null, null];
        }

        return [
            $attachment->store('student-services/transfer-attachments', 'public'),
            $attachment->getClientOriginalName(),
        ];
    }
}
