<?php

namespace App\Support\StudentService;

use App\Models\Academic\StudentProfile;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\ServiceLetterType;
use App\Support\Financial\FinancialClearanceService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceLetterRequestService
{
    public function create(
        ServiceLetterType $letterType,
        StudentProfile $studentProfile,
        array $payload,
        ?UploadedFile $attachment = null
    ): ServiceLetterRequest {
        if (! $letterType->is_active) {
            throw new \RuntimeException('Jenis surat tidak aktif.');
        }

        if ($letterType->requires_financial_clearance && $letterType->clearance_hold_type) {
            $hasHold = app(FinancialClearanceService::class)
                ->hasBlockingHold($studentProfile, [$letterType->clearance_hold_type]);

            if ($hasHold) {
                throw new \RuntimeException('Request belum bisa dibuat karena masih ada financial hold untuk layanan ini.');
            }
        }

        return DB::transaction(function () use ($letterType, $studentProfile, $payload, $attachment): ServiceLetterRequest {
            $attachmentPath = null;
            $attachmentName = null;

            if ($attachment) {
                $attachmentPath = $attachment->store('student-services/letter-attachments', 'public');
                $attachmentName = $attachment->getClientOriginalName();
            }

            $request = ServiceLetterRequest::create([
                'request_number' => app(ServiceLetterNumberService::class)->next(),
                'service_letter_type_id' => $letterType->id,
                'student_profile_id' => $studentProfile->id,
                'purpose' => $payload['purpose'],
                'request_data' => $payload['request_data'] ?? [],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->recordHistory($request, null, 'submitted', 'Request submitted by student.', auth()->id());

            return $request;
        });
    }

    public function setStatus(ServiceLetterRequest $request, string $status, ?string $notes, ?int $userId): ServiceLetterRequest
    {
        return DB::transaction(function () use ($request, $status, $notes, $userId): ServiceLetterRequest {
            $from = $request->status;
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

            $request->update($updates);
            $this->recordHistory($request, $from, $status, $notes, $userId);

            return $request->refresh();
        });
    }

    public function issue(ServiceLetterRequest $request, string $method, ?UploadedFile $uploadedFile, ?int $userId): ServiceLetterRequest
    {
        if ($request->status !== 'approved') {
            throw new \RuntimeException('Request harus approved sebelum diterbitkan.');
        }

        return DB::transaction(function () use ($request, $method, $uploadedFile, $userId): ServiceLetterRequest {
            $request->loadMissing(['letterType', 'studentProfile.user', 'studentProfile.studyProgram']);
            $path = null;
            $fileName = null;

            if ($method === 'manual_upload') {
                if (! $uploadedFile) {
                    throw new \RuntimeException('Upload file final wajib untuk mode manual.');
                }

                $path = $uploadedFile->store('student-services/issued-letters', 'public');
                $fileName = $uploadedFile->getClientOriginalName();
            } else {
                $path = $this->generatePdf($request);
            }

            $from = $request->status;
            $request->update([
                'status' => 'issued',
                'fulfillment_method' => $method,
                'generated_file_path' => $method === 'auto_generate' ? $path : $request->generated_file_path,
                'uploaded_file_path' => $method === 'manual_upload' ? $path : $request->uploaded_file_path,
                'uploaded_file_name' => $fileName,
                'issued_by' => $userId,
                'issued_at' => now(),
            ]);

            $this->recordHistory($request, $from, 'issued', 'Letter issued.', $userId);

            return $request->refresh();
        });
    }

    public function resubmit(ServiceLetterRequest $request, array $payload, ?UploadedFile $attachment = null): ServiceLetterRequest
    {
        if ($request->status !== 'revision_requested') {
            throw new \RuntimeException('Hanya request yang perlu perbaikan yang bisa disubmit ulang.');
        }

        return DB::transaction(function () use ($request, $payload, $attachment): ServiceLetterRequest {
            $attachmentPath = $request->attachment_path;
            $attachmentName = $request->attachment_name;

            if ($attachment) {
                if ($request->attachment_path) {
                    Storage::disk('public')->delete($request->attachment_path);
                }

                $attachmentPath = $attachment->store('student-services/letter-attachments', 'public');
                $attachmentName = $attachment->getClientOriginalName();
            }

            $from = $request->status;
            $request->update([
                'purpose' => $payload['purpose'],
                'request_data' => $payload['request_data'] ?? [],
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'submitted',
                'student_notes' => $payload['student_notes'] ?? null,
            ]);

            $this->recordHistory($request, $from, 'submitted', 'Request corrected and resubmitted by student.', auth()->id());

            return $request->refresh();
        });
    }

    public function recordHistory(ServiceLetterRequest $request, ?string $from, string $to, ?string $notes, ?int $userId): void
    {
        $request->histories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'changed_by' => $userId,
        ]);
    }

    private function generatePdf(ServiceLetterRequest $request): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.student-services.service-letter', [
            'request' => $request,
            'letterType' => $request->letterType,
            'studentProfile' => $request->studentProfile,
            'student' => $request->studentProfile->user,
            'data' => $request->request_data ?? [],
        ])->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        $path = 'student-services/issued-letters/'.$request->request_number.'.pdf';
        Storage::disk('public')->put($path, $dompdf->output());

        return $path;
    }
}
