<?php

namespace App\Support\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Financial\StudentInvoice;
use App\Models\StudentService\StudentLeaveApplication;
use App\Support\Financial\InvoiceGenerationService;
use App\Support\Financial\InvoiceStatusService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentLeaveApplicationService
{
    public function create(StudentProfile $studentProfile, array $payload, ?UploadedFile $attachment = null): StudentLeaveApplication
    {
        return DB::transaction(function () use ($studentProfile, $payload, $attachment): StudentLeaveApplication {
            [$attachmentPath, $attachmentName] = $this->storeAttachment($attachment);

            $application = StudentLeaveApplication::create([
                'application_number' => app(StudentLeaveNumberService::class)->next(),
                'student_profile_id' => $studentProfile->id,
                'academic_year_id' => $payload['academic_year_id'] ?? null,
                'semester' => $payload['semester'] ?? null,
                'duration_semesters' => $payload['duration_semesters'],
                'reason_category' => $payload['reason_category'],
                'reason' => $payload['reason'],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->recordHistory($application, null, 'submitted', 'Leave application submitted by student.', auth()->id());
            app(StudentServiceNotificationService::class)->leave($application, 'submitted', 'Pengajuan cuti berhasil dikirim.');

            return $application;
        });
    }

    public function resubmit(StudentLeaveApplication $application, array $payload, ?UploadedFile $attachment = null): StudentLeaveApplication
    {
        if ($application->status !== 'revision_requested') {
            throw new \RuntimeException('Hanya pengajuan yang perlu perbaikan yang bisa dikirim ulang.');
        }

        return DB::transaction(function () use ($application, $payload, $attachment): StudentLeaveApplication {
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
                'academic_year_id' => $payload['academic_year_id'] ?? null,
                'semester' => $payload['semester'] ?? null,
                'duration_semesters' => $payload['duration_semesters'],
                'reason_category' => $payload['reason_category'],
                'reason' => $payload['reason'],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->recordHistory($application, $from, 'submitted', 'Leave application corrected and resubmitted by student.', auth()->id());
            app(StudentServiceNotificationService::class)->leave($application, 'submitted', 'Perbaikan pengajuan cuti berhasil dikirim ulang.');

            return $application->refresh();
        });
    }

    public function setStatus(StudentLeaveApplication $application, string $status, ?string $notes, ?int $userId): StudentLeaveApplication
    {
        return DB::transaction(function () use ($application, $status, $notes, $userId): StudentLeaveApplication {
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
            app(StudentServiceNotificationService::class)->leave($application, $status, $notes);

            return $application->refresh();
        });
    }

    public function approve(
        StudentLeaveApplication $application,
        ?string $notes,
        ?int $userId,
        float $feeAmount = 0,
        ?string $feeDueDate = null,
    ): StudentLeaveApplication {
        if (! in_array($application->status, ['submitted', 'under_review', 'revision_requested'], true)) {
            throw new \RuntimeException('Status pengajuan cuti saat ini tidak bisa diapprove.');
        }

        return DB::transaction(function () use ($application, $notes, $userId, $feeAmount, $feeDueDate): StudentLeaveApplication {
            $from = $application->status;
            $invoice = null;
            $feeAmount = max(0, $feeAmount);
            $feeDueDate = $feeDueDate ?: now()->addDays(7)->toDateString();
            $status = $feeAmount > 0 ? 'approved_pending_payment' : 'approved';

            if ($feeAmount > 0) {
                $invoice = $this->createLeaveFeeInvoice($application, $feeAmount, $feeDueDate, $userId);
            }

            $application->update([
                'status' => $status,
                'admin_notes' => $notes,
                'leave_fee_amount' => $feeAmount,
                'leave_fee_due_date' => $feeAmount > 0 ? $feeDueDate : null,
                'leave_fee_invoice_id' => $invoice?->id,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $historyNotes = $feeAmount > 0
                ? trim(($notes ? $notes.' ' : '').'Biaya cuti dibuat sebagai invoice '.$invoice?->invoice_number.'.')
                : $notes;

            $this->recordHistory($application, $from, $status, $historyNotes, $userId);
            app(StudentServiceNotificationService::class)->leave($application, $status, $historyNotes);

            return $application->refresh();
        });
    }

    public function activate(StudentLeaveApplication $application, ?int $userId, ?string $notes = null): StudentLeaveApplication
    {
        if (! in_array($application->status, ['approved', 'approved_pending_payment'], true)) {
            throw new \RuntimeException('Pengajuan cuti harus approved sebelum diaktifkan.');
        }

        return DB::transaction(function () use ($application, $userId, $notes): StudentLeaveApplication {
            $application->loadMissing('leaveFeeInvoice');

            if ($application->leaveFeeInvoice) {
                app(InvoiceStatusService::class)->refresh($application->leaveFeeInvoice);
                $application->leaveFeeInvoice->refresh();

                if ($application->leaveFeeInvoice->status !== 'paid') {
                    throw new \RuntimeException('Biaya cuti harus lunas sebelum cuti diaktifkan.');
                }
            }

            $from = $application->status;
            $application->studentProfile()->update([
                'academic_status' => 'Cuti',
                'is_active' => false,
                'updated_by' => $userId,
            ]);

            $this->syncLeaveRegistration($application, $userId, $notes);

            $application->update([
                'status' => 'activated',
                'activated_by' => $userId,
                'activated_at' => now(),
                'admin_notes' => $notes ?: $application->admin_notes,
            ]);

            $this->recordHistory($application, $from, 'activated', $notes ?: 'Leave activated by admin.', $userId);
            app(StudentServiceNotificationService::class)->leave($application, 'activated', $notes ?: 'Cuti akademik kamu sudah diaktifkan.');

            return $application->refresh();
        });
    }

    public function returnToActive(StudentLeaveApplication $application, ?int $userId, ?string $notes = null): StudentLeaveApplication
    {
        if ($application->status !== 'activated') {
            throw new \RuntimeException('Hanya cuti aktif yang bisa dikembalikan ke aktif.');
        }

        return DB::transaction(function () use ($application, $userId, $notes): StudentLeaveApplication {
            $from = $application->status;
            $application->studentProfile()->update([
                'academic_status' => 'Aktif',
                'is_active' => true,
                'updated_by' => $userId,
            ]);

            $application->update([
                'status' => 'returned',
                'returned_by' => $userId,
                'returned_at' => now(),
                'admin_notes' => $notes ?: $application->admin_notes,
            ]);

            $this->recordHistory($application, $from, 'returned', $notes ?: 'Student returned to active status.', $userId);
            app(StudentServiceNotificationService::class)->leave($application, 'returned', $notes ?: 'Status akademik kamu sudah dikembalikan menjadi aktif.');

            return $application->refresh();
        });
    }

    public function recordHistory(StudentLeaveApplication $application, ?string $from, string $to, ?string $notes, ?int $userId): void
    {
        $application->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'changed_by' => $userId,
        ]);
    }

    private function storeAttachment(?UploadedFile $attachment): array
    {
        if (! $attachment) {
            return [null, null];
        }

        return [
            $attachment->store('student-services/leave-attachments', 'public'),
            $attachment->getClientOriginalName(),
        ];
    }

    private function createLeaveFeeInvoice(
        StudentLeaveApplication $application,
        float $feeAmount,
        string $feeDueDate,
        ?int $userId,
    ): StudentInvoice {
        if ($application->leave_fee_invoice_id) {
            return $application->leaveFeeInvoice()->firstOrFail();
        }

        $application->loadMissing(['studentProfile', 'academicYear']);

        $invoice = app(InvoiceGenerationService::class)->createCustomInvoice(
            studentProfile: $application->studentProfile,
            items: [[
                'item_type' => 'fee',
                'description' => 'Biaya Cuti Akademik '.$application->application_number,
                'amount' => $feeAmount,
            ]],
            invoiceType: 'leave',
            dueDate: $feeDueDate,
            academicYear: $application->academicYear,
            semester: $application->semester,
            notes: 'Invoice biaya cuti dari pengajuan '.$application->application_number.'.',
            createdBy: $userId,
            issueImmediately: true,
        );

        $invoice->update([
            'source_type' => $application::class,
            'source_id' => $application->id,
        ]);

        return $invoice->refresh();
    }

    private function syncLeaveRegistration(StudentLeaveApplication $application, ?int $userId, ?string $notes = null): void
    {
        if (! $application->academic_year_id) {
            return;
        }

        $studentProfile = $application->studentProfile;

        if (! $studentProfile) {
            return;
        }

        $registration = StudentRegistration::firstOrNew([
            'student_profile_id' => $studentProfile->id,
            'academic_year_id' => $application->academic_year_id,
        ]);

        $registration->fill([
            'semester_no' => $application->semester,
            'academic_status' => 'Cuti',
            'registration_status' => 'Approved',
            'registration_date' => Carbon::today(),
            'submitted_at' => $registration->submitted_at ?: $application->created_at,
            'approved_at' => now(),
            'approved_by' => $userId,
            'notes' => $notes ?: 'Registrasi cuti dibuat otomatis dari pengajuan '.$application->application_number.'.',
            'is_active' => true,
            'updated_by' => $userId,
        ]);

        if (! $registration->exists) {
            $registration->created_by = $userId;
        }

        $registration->save();
    }
}
