<?php

namespace App\Support\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\StudentService\StudentComplaint;
use App\Models\StudentService\StudentComplaintCategory;
use App\Models\StudentService\StudentComplaintMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StudentComplaintService
{
    private const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
    ];

    public function create(StudentProfile $studentProfile, array $payload, array $attachments = []): StudentComplaint
    {
        return DB::transaction(function () use ($studentProfile, $payload, $attachments): StudentComplaint {
            $category = StudentComplaintCategory::findOrFail($payload['student_complaint_category_id']);
            $dueAt = now()->addHours(max(1, (int) $category->default_sla_hours));

            $complaint = StudentComplaint::create([
                'ticket_number' => app(StudentComplaintNumberService::class)->next(),
                'student_profile_id' => $studentProfile->id,
                'student_complaint_category_id' => $category->id,
                'assigned_work_unit_id' => $category->default_work_unit_id,
                'subject' => $payload['subject'],
                'description' => $payload['description'],
                'priority' => $payload['priority'] ?? 'normal',
                'status' => 'submitted',
                'due_at' => $dueAt,
                'last_message_at' => now(),
            ]);

            $message = $this->addMessage($complaint, auth()->id(), 'student', $payload['description'], $attachments);
            $this->recordHistory($complaint, null, 'submitted', 'Pengaduan dibuat oleh mahasiswa.', auth()->id());
            app(StudentServiceNotificationService::class)->complaint($complaint, 'submitted', 'Pengaduan berhasil dikirim dan akan diproses unit terkait.');

            return $complaint->refresh()->load(['category', 'assignedWorkUnit', 'messages.attachments']);
        });
    }

    public function addMessage(
        StudentComplaint $complaint,
        ?int $userId,
        string $senderType,
        string $message,
        array|UploadedFile|null $attachments = [],
        bool $internal = false,
    ): StudentComplaintMessage {
        return DB::transaction(function () use ($complaint, $userId, $senderType, $message, $attachments, $internal): StudentComplaintMessage {
            $reply = $complaint->messages()->create([
                'user_id' => $userId,
                'sender_type' => $senderType,
                'message' => $message,
                'is_internal_note' => $internal,
            ]);

            foreach ($this->normalizeAttachments($attachments) as $attachment) {
                $this->storeAttachment($complaint, $reply, $attachment);
            }

            if (! $internal) {
                $fromStatus = $complaint->status;
                $nextStatus = $senderType === 'student'
                    ? (in_array($complaint->status, ['waiting_student', 'responded', 'resolved'], true) ? 'reopened' : $complaint->status)
                    : 'responded';

                $complaint->update([
                    'status' => $nextStatus,
                    'last_message_at' => now(),
                    'resolved_at' => $nextStatus === 'resolved' ? now() : null,
                    'closed_at' => null,
                ]);

                if ($fromStatus !== $nextStatus) {
                    $notes = $senderType === 'student'
                        ? 'Mahasiswa mengirim balasan tambahan.'
                        : 'Staff mengirim balasan.';
                    $this->recordHistory($complaint, $fromStatus, $nextStatus, $notes, $userId);
                    app(StudentServiceNotificationService::class)->complaint($complaint, $nextStatus, $notes);
                } elseif ($senderType === 'admin') {
                    app(StudentServiceNotificationService::class)->complaint($complaint, $nextStatus, 'Staff mengirim balasan baru.');
                }
            }

            return $reply->refresh()->load('attachments');
        });
    }

    public function assign(StudentComplaint $complaint, ?int $workUnitId, ?int $userId, ?string $notes, ?int $actorId): StudentComplaint
    {
        return DB::transaction(function () use ($complaint, $workUnitId, $userId, $notes, $actorId): StudentComplaint {
            $from = $complaint->status;
            $status = in_array($complaint->status, ['submitted', 'reopened'], true) ? 'in_review' : $complaint->status;

            $complaint->update([
                'assigned_work_unit_id' => $workUnitId,
                'assigned_user_id' => $userId,
                'status' => $status,
            ]);

            if ($from !== $status) {
                $this->recordHistory($complaint, $from, $status, $notes ?: 'Pengaduan mulai direview.', $actorId);
                app(StudentServiceNotificationService::class)->complaint($complaint, $status, $notes ?: 'Pengaduan mulai direview.');
            }

            return $complaint->refresh();
        });
    }

    public function setStatus(StudentComplaint $complaint, string $status, ?string $notes, ?int $actorId): StudentComplaint
    {
        return DB::transaction(function () use ($complaint, $status, $notes, $actorId): StudentComplaint {
            $from = $complaint->status;
            $updates = ['status' => $status];

            if ($status === 'resolved') {
                $updates['resolved_at'] = now();
            }

            if ($status === 'closed') {
                $updates['closed_at'] = now();
            }

            if (in_array($status, ['reopened', 'in_review', 'waiting_student'], true)) {
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }

            $complaint->update($updates);
            $this->recordHistory($complaint, $from, $status, $notes, $actorId);
            app(StudentServiceNotificationService::class)->complaint($complaint, $status, $notes);

            return $complaint->refresh();
        });
    }

    public function recordHistory(StudentComplaint $complaint, ?string $from, string $to, ?string $notes, ?int $actorId): void
    {
        $complaint->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'changed_by' => $actorId,
        ]);
    }

    private function storeAttachment(StudentComplaint $complaint, StudentComplaintMessage $message, UploadedFile $attachment): void
    {
        $extension = strtolower((string) $attachment->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Format lampiran tidak didukung. Gunakan gambar, PDF, Word, Excel, PowerPoint, atau TXT.');
        }

        $fileName = $attachment->getClientOriginalName();
        $path = $attachment->store('student-services/complaint-attachments', 'public');
        $fileSize = $this->storedFileSize($path);

        if ($fileSize > self::MAX_ATTACHMENT_BYTES) {
            Storage::disk('public')->delete($path);

            throw new \RuntimeException('Ukuran lampiran '.$fileName.' melebihi batas 5 MB.');
        }

        $complaint->attachments()->create([
            'student_complaint_message_id' => $message->id,
            'file_path' => $path,
            'file_name' => $fileName,
            'mime_type' => Storage::disk('public')->mimeType($path) ?: $attachment->getClientMimeType(),
            'file_size' => $fileSize,
        ]);
    }

    private function storedFileSize(string $path): int
    {
        try {
            return (int) Storage::disk('public')->size($path);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function normalizeAttachments(array|UploadedFile|null $attachments): array
    {
        if ($attachments instanceof UploadedFile) {
            return [$attachments];
        }

        if (! is_array($attachments)) {
            return [];
        }

        return array_values(array_filter($attachments, fn ($attachment) => $attachment instanceof UploadedFile));
    }
}
