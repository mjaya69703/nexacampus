<?php

namespace App\Mail;

use App\Models\Admission\AdmissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdmissionApplication $application,
        public string $fromStatus,
        public string $toStatus,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Admission Status Updated - '.$this->application->application_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'templates.email.admission-status-updated',
        );
    }
}
