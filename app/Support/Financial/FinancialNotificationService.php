<?php

namespace App\Support\Financial;

use App\Mail\Financial\InstallmentApprovedMail;
use App\Mail\Financial\InstallmentRejectedMail;
use App\Mail\Financial\InvoiceIssuedMail;
use App\Mail\Financial\InvoiceOverdueMail;
use App\Mail\Financial\PaymentRejectedMail;
use App\Mail\Financial\PaymentVerifiedMail;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FinancialNotificationService
{
    public function invoiceIssued(StudentInvoice $invoice): void
    {
        if ($invoice->issued_notified_at || ! $this->hasStudentEmail($invoice)) {
            return;
        }

        $this->send($invoice->studentProfile->user->email, new InvoiceIssuedMail($invoice));

        $invoice->forceFill(['issued_notified_at' => now()])->save();
    }

    public function invoiceOverdue(StudentInvoice $invoice): void
    {
        if ($invoice->overdue_notified_at || ! $this->hasStudentEmail($invoice)) {
            return;
        }

        $this->send($invoice->studentProfile->user->email, new InvoiceOverdueMail($invoice));

        $invoice->forceFill(['overdue_notified_at' => now()])->save();
    }

    public function paymentVerified(Payment $payment): void
    {
        $payment->loadMissing(['invoice', 'studentProfile.user']);

        if (! $payment->studentProfile?->user?->email) {
            return;
        }

        $this->send($payment->studentProfile->user->email, new PaymentVerifiedMail($payment));
    }

    public function paymentRejected(Payment $payment): void
    {
        $payment->loadMissing(['invoice', 'studentProfile.user']);

        if (! $payment->studentProfile?->user?->email) {
            return;
        }

        $this->send($payment->studentProfile->user->email, new PaymentRejectedMail($payment));
    }

    public function installmentApproved(InvoiceInstallmentRequest $request): void
    {
        $request->loadMissing(['invoice.studentProfile.user']);

        if (! $request->invoice?->studentProfile?->user?->email) {
            return;
        }

        $this->send($request->invoice->studentProfile->user->email, new InstallmentApprovedMail($request));
    }

    public function installmentRejected(InvoiceInstallmentRequest $request): void
    {
        $request->loadMissing(['invoice.studentProfile.user']);

        if (! $request->invoice?->studentProfile?->user?->email) {
            return;
        }

        $this->send($request->invoice->studentProfile->user->email, new InstallmentRejectedMail($request));
    }

    private function hasStudentEmail(StudentInvoice $invoice): bool
    {
        $invoice->loadMissing('studentProfile.user');

        return filled($invoice->studentProfile?->user?->email);
    }

    private function send(string $email, object $mail): void
    {
        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $exception) {
            Log::warning('Financial email notification failed.', [
                'email' => $email,
                'mail' => $mail::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
