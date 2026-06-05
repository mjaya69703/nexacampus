<?php

namespace App\Support\StudentService;

use App\Mail\StudentService\StudentServiceStatusMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class StudentServiceNotificationService
{
    public function serviceLetter(Model $request, string $status, ?string $notes = null): void
    {
        $request->loadMissing(['studentProfile.user']);

        $this->notifyStudent(
            model: $request,
            status: $status,
            notes: $notes,
            requestLabel: 'Pengajuan surat',
            requestNumber: (string) $request->request_number,
            routeName: 'student.student-services.letters.show',
        );
    }

    public function leave(Model $application, string $status, ?string $notes = null): void
    {
        $application->loadMissing(['studentProfile.user']);

        $this->notifyStudent(
            model: $application,
            status: $status,
            notes: $notes,
            requestLabel: 'Pengajuan cuti',
            requestNumber: (string) $application->application_number,
            routeName: 'student.student-services.leaves.show',
        );
    }

    public function transfer(Model $request, string $status, ?string $notes = null): void
    {
        $request->loadMissing(['studentProfile.user']);

        $this->notifyStudent(
            model: $request,
            status: $status,
            notes: $notes,
            requestLabel: 'Pengajuan pindah',
            requestNumber: (string) $request->request_number,
            routeName: 'student.student-services.transfers.show',
        );
    }

    public function graduation(Model $application, string $status, ?string $notes = null): void
    {
        $application->loadMissing(['studentProfile.user']);

        $this->notifyStudent(
            model: $application,
            status: $status,
            notes: $notes,
            requestLabel: 'Pengajuan yudisium',
            requestNumber: (string) $application->application_number,
            routeName: 'student.student-services.graduations.show',
        );
    }

    public function complaint(Model $complaint, string $status, ?string $notes = null): void
    {
        $complaint->loadMissing(['studentProfile.user']);

        $this->notifyStudent(
            model: $complaint,
            status: $status,
            notes: $notes,
            requestLabel: 'Pengaduan',
            requestNumber: (string) $complaint->ticket_number,
            routeName: 'student.student-services.complaints.show',
        );
    }

    private function notifyStudent(
        Model $model,
        string $status,
        ?string $notes,
        string $requestLabel,
        string $requestNumber,
        string $routeName,
    ): void {
        $email = $model->studentProfile?->user?->email;

        if (! $email) {
            return;
        }

        $statusLabel = $this->statusLabel($status);

        $mail = new StudentServiceStatusMail(
            subjectLine: $requestLabel.' '.$requestNumber.' - '.$statusLabel,
            title: $requestLabel.' diperbarui',
            studentName: $model->studentProfile?->user?->name ?? 'Mahasiswa',
            requestLabel: $requestLabel,
            requestNumber: $requestNumber,
            statusLabel: $statusLabel,
            notes: $notes,
            actionUrl: route($routeName, ['id' => $model->id]),
        );

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $exception) {
            Log::warning('Student service email notification failed.', [
                'email' => $email,
                'mail' => $mail::class,
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Terkirim',
            'under_review', 'in_review' => 'Sedang Direview',
            'revision_requested' => 'Perlu Perbaikan',
            'approved' => 'Disetujui',
            'approved_pending_payment' => 'Disetujui, Menunggu Pembayaran',
            'issued' => 'Diterbitkan',
            'rejected' => 'Ditolak',
            'activated' => 'Cuti Aktif',
            'returned' => 'Kembali Aktif',
            'applied' => 'Sudah Diterapkan',
            'finalized' => 'Final',
            'waiting_student' => 'Menunggu Mahasiswa',
            'responded' => 'Sudah Dibalas',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
            'reopened' => 'Dibuka Lagi',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }
}
