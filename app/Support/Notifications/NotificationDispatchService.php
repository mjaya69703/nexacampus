<?php

namespace App\Support\Notifications;

use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\Thesis;
use App\Models\Academic\ThesisDefense;
use App\Models\Academic\ThesisMilestone;
use App\Models\Admission\AdmissionApplication;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use App\Models\Settings\NotificationLog;
use App\Models\Settings\NotificationSetting;
use App\Models\Settings\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationDispatchService
{
    public function whatsapp(User $user, string $eventKey, array $data = [], ?Model $source = null): NotificationLog
    {
        $setting = NotificationSetting::current();
        $template = NotificationTemplate::query()
            ->where('channel', 'whatsapp')
            ->where('event_key', $eventKey)
            ->first();

        $subject = $template?->title ?? str($eventKey)->replace('.', ' ')->title()->toString();
        $body = $this->render($template?->body ?? ($data['message'] ?? $subject), $data);

        $log = NotificationLog::query()->create([
            'event_key' => $eventKey,
            'channel' => 'whatsapp',
            'provider' => $setting->whatsapp_provider,
            'status' => 'queued',
            'user_id' => $user->id,
            'recipient_name' => $user->name,
            'recipient_phone' => $user->phone,
            'recipient_email' => $user->email,
            'subject' => $subject,
            'body' => $body,
            'payload' => $data,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
        ]);

        if (! $setting->whatsapp_enabled) {
            return $this->skip($log, 'WhatsApp channel is disabled.');
        }

        if (! $template || ! $template->is_active) {
            return $this->skip($log, 'WhatsApp template is missing or inactive.');
        }

        $recipient = $this->normalizePhone($user->phone);

        if (! $recipient) {
            return $this->skip($log, 'Recipient phone is empty or invalid.');
        }

        $health = app(WhatsAppProviderManager::class)->health($setting);

        if ($health['status'] !== 'ready') {
            return $this->fail($log, implode(' ', $health['issues'] ?? []) ?: $health['message']);
        }

        try {
            $result = app(WhatsAppProviderManager::class)->sendText($setting, $recipient, $body);

            $log->forceFill([
                'status' => 'sent',
                'recipient_phone' => $recipient,
                'provider_message_id' => $result['provider_message_id'] ?? null,
                'provider_response' => $result['provider_response'] ?? $result,
                'attempt_count' => $log->attempt_count + 1,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('WhatsApp notification failed.', [
                'event_key' => $eventKey,
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            $this->fail($log, $exception->getMessage());
        }

        return $log->refresh();
    }

    public function dispatch(User $user, string $eventKey, array $data = [], ?Model $source = null): ?NotificationLog
    {
        $whatsappLog = $this->whatsapp($user, $eventKey, $data, $source);

        $template = NotificationTemplate::query()
            ->where('channel', 'whatsapp')
            ->where('event_key', $eventKey)
            ->first();

        $subject = $template?->title ?? str($eventKey)->replace('.', ' ')->title()->toString();
        $body = $this->render($template?->body ?? ($data['message'] ?? $subject), $data);

        app(WebPushNotificationService::class)->send($user, $eventKey, $subject, $body, $data, $source);

        return $whatsappLog;
    }

    public function gradePublished(StudentGrade $grade): ?NotificationLog
    {
        $grade->loadMissing([
            'studyPlanDetail.studyPlan.studentProfile.user',
            'studyPlanDetail.courseOffering.course',
        ]);

        $user = $grade->studyPlanDetail?->studyPlan?->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'academic.grade_published', [
            'student_name' => $user->name,
            'course_name' => $grade->studyPlanDetail?->courseOffering?->course?->name ?? 'Mata kuliah',
            'letter_grade' => $grade->letter_grade ?? '-',
            'final_score' => $grade->final_score ?? '-',
        ], $grade);
    }

    public function studyPlanStatusUpdated(StudyPlan $studyPlan, ?string $notes = null): ?NotificationLog
    {
        $studyPlan->loadMissing(['studentProfile.user']);
        $user = $studyPlan->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'academic.study_plan_status_updated', [
            'student_name' => $user->name,
            'semester_no' => $studyPlan->semester_no ?? '-',
            'status_label' => $studyPlan->status,
            'notes' => $notes ?: '-',
        ], $studyPlan);
    }

    public function invoiceIssued(StudentInvoice $invoice): ?NotificationLog
    {
        $invoice->loadMissing(['studentProfile.user']);
        $user = $invoice->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'financial.invoice_issued', [
            'student_name' => $user->name,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => $this->money($invoice->total_amount),
            'due_date' => $invoice->due_date?->format('d M Y') ?? '-',
        ], $invoice);
    }

    public function invoiceOverdue(StudentInvoice $invoice): ?NotificationLog
    {
        $invoice->loadMissing(['studentProfile.user']);
        $user = $invoice->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'financial.invoice_overdue', [
            'student_name' => $user->name,
            'invoice_number' => $invoice->invoice_number,
            'outstanding_amount' => $this->money($invoice->outstanding_amount),
        ], $invoice);
    }

    public function paymentVerified(Payment $payment): ?NotificationLog
    {
        $payment->loadMissing(['invoice', 'studentProfile.user']);
        $user = $payment->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'financial.payment_verified', [
            'student_name' => $user->name,
            'payment_number' => $payment->payment_number,
            'amount' => $this->money($payment->amount),
            'invoice_number' => $payment->invoice?->invoice_number ?? '-',
        ], $payment);
    }

    public function paymentRejected(Payment $payment): ?NotificationLog
    {
        $payment->loadMissing(['invoice', 'studentProfile.user']);
        $user = $payment->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'financial.payment_rejected', [
            'student_name' => $user->name,
            'payment_number' => $payment->payment_number,
            'notes' => $payment->verification_notes ?: '-',
        ], $payment);
    }

    public function admissionStatusUpdated(AdmissionApplication $application, string $status): NotificationLog
    {
        $user = new User([
            'first_name' => $application->full_name,
            'last_name' => '',
            'email' => $application->email,
            'phone' => $application->phone,
        ]);

        return $this->whatsapp($user, 'admission.status_updated', [
            'applicant_name' => $application->full_name,
            'application_number' => $application->application_number,
            'status_label' => str($status)->replace('_', ' ')->title()->toString(),
        ], $application);
    }

    public function studentServiceStatusUpdated(Model $model, string $requestLabel, string $requestNumber, string $statusLabel, ?string $notes = null): ?NotificationLog
    {
        $model->loadMissing(['studentProfile.user']);
        $user = $model->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'student_service.status_updated', [
            'student_name' => $user->name,
            'request_label' => $requestLabel,
            'request_number' => $requestNumber,
            'status_label' => $statusLabel,
            'notes' => $notes ?: '-',
        ], $model);
    }

    public function thesisStatusUpdated(Thesis $thesis, ?string $notes = null): ?NotificationLog
    {
        $thesis->loadMissing('studentProfile.user');
        $user = $thesis->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'academic.thesis_status_updated', [
            'student_name' => $user->name,
            'thesis_title' => $thesis->title,
            'status_label' => $thesis->statusLabel(),
            'notes' => $notes ?: '-',
            'url' => route('student.thesis.index'),
        ], $thesis);
    }

    public function thesisMilestoneSubmitted(ThesisMilestone $milestone): void
    {
        $milestone->loadMissing([
            'thesis.activeSupervisors.lecturerProfile.user',
            'thesis.studentProfile.user',
        ]);

        $thesis = $milestone->thesis;

        if (! $thesis) {
            return;
        }

        $studentName = $thesis->studentProfile?->user?->name ?? 'Mahasiswa';

        foreach ($thesis->activeSupervisors as $supervisor) {
            $user = $supervisor->lecturerProfile?->user;

            if (! $user) {
                continue;
            }

            $this->dispatch($user, 'academic.thesis_milestone_submitted', [
                'lecturer_name' => $user->name,
                'student_name' => $studentName,
                'thesis_title' => $thesis->title,
                'milestone_title' => $milestone->title,
                'url' => route('lecturer.theses.show', $thesis),
            ], $milestone);
        }
    }

    public function thesisMilestoneReviewed(ThesisMilestone $milestone, ?string $feedback = null): ?NotificationLog
    {
        $milestone->loadMissing('thesis.studentProfile.user');
        $thesis = $milestone->thesis;
        $user = $thesis?->studentProfile?->user;

        if (! $thesis || ! $user) {
            return null;
        }

        return $this->dispatch($user, 'academic.thesis_milestone_reviewed', [
            'student_name' => $user->name,
            'thesis_title' => $thesis->title,
            'milestone_title' => $milestone->title,
            'status_label' => $milestone->statusLabel(),
            'feedback' => $feedback ?: '-',
            'url' => route('student.thesis.index'),
        ], $milestone);
    }

    public function thesisDefenseScheduled(ThesisDefense $defense): ?NotificationLog
    {
        $defense->loadMissing('thesis.studentProfile.user');
        $thesis = $defense->thesis;
        $user = $thesis?->studentProfile?->user;

        if (! $thesis || ! $user) {
            return null;
        }

        return $this->dispatch($user, 'academic.thesis_defense_scheduled', [
            'student_name' => $user->name,
            'thesis_title' => $thesis->title,
            'defense_type' => $defense->typeLabel(),
            'scheduled_at' => $defense->scheduled_at?->format('d M Y H:i') ?? '-',
            'room' => $defense->room ?: ($defense->meeting_link ?: 'Daring'),
            'url' => route('student.thesis.index'),
        ], $defense);
    }

    public function thesisPlagiarismUpdated(Thesis $thesis): ?NotificationLog
    {
        $thesis->loadMissing('studentProfile.user');
        $user = $thesis->studentProfile?->user;

        if (! $user) {
            return null;
        }

        return $this->dispatch($user, 'academic.thesis_plagiarism_updated', [
            'student_name' => $user->name,
            'thesis_title' => $thesis->title,
            'status_label' => $thesis->plagiarismStatusLabel(),
            'score' => $thesis->plagiarism_score !== null
                ? number_format((float) $thesis->plagiarism_score, 2, ',', '.').'%'
                : '-',
            'tool' => $thesis->plagiarism_tool ?: '-',
            'notes' => $thesis->plagiarism_notes ?: '-',
            'url' => route('student.thesis.index'),
        ], $thesis);
    }

    private function render(string $template, array $data): string
    {
        return preg_replace_callback('/{{\s*([\w.]+)\s*}}/', function (array $matches) use ($data) {
            $value = data_get($data, $matches[1], '-');

            return is_scalar($value) ? (string) $value : '-';
        }, $template);
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        return $phone;
    }

    private function money(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function skip(NotificationLog $log, string $message): NotificationLog
    {
        $log->forceFill([
            'status' => 'skipped',
            'error_message' => $message,
        ])->save();

        return $log->refresh();
    }

    private function fail(NotificationLog $log, string $message): NotificationLog
    {
        $log->forceFill([
            'status' => 'failed',
            'error_message' => $message,
            'attempt_count' => $log->attempt_count + 1,
            'failed_at' => now(),
        ])->save();

        return $log->refresh();
    }
}
