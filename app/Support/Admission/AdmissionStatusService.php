<?php

namespace App\Support\Admission;

use App\Mail\AdmissionStatusUpdated;
use App\Models\Admission\AdmissionApplication;
use App\Support\Notifications\NotificationDispatchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdmissionStatusService
{
    public function change(
        AdmissionApplication $application,
        string $status,
        ?string $notes = null,
        ?int $userId = null,
    ): void {
        $from = $application->status;

        $application->fill([
            'status' => $status,
            'review_notes' => $notes ?: $application->review_notes,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'accepted_at' => $status === 'accepted' ? now() : $application->accepted_at,
            'updated_by' => $userId,
        ])->save();

        $application->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $status,
            'notes' => $notes,
            'changed_by' => $userId,
        ]);

        $portalUrl = route('admission.portal', [
            'applicationNumber' => $application->application_number,
            'token' => $application->access_token,
        ]);

        try {
            Mail::to($application->email)->send(
                new AdmissionStatusUpdated($application->load(['period', 'studyProgram']), $from, $status, $portalUrl),
            );
        } catch (Throwable $exception) {
            Log::warning('Admission status email failed.', [
                'application_id' => $application->id,
                'email' => $application->email,
                'from_status' => $from,
                'to_status' => $status,
                'message' => $exception->getMessage(),
            ]);
        }

        app(NotificationDispatchService::class)->admissionStatusUpdated($application->refresh(), $status);
    }
}
