<?php

namespace App\Mail;

use App\Models\Admission\AdmissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionApplicationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdmissionApplication $application,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Admission Application Received - '.$this->application->application_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'templates.email.admission-application-submitted',
        );
    }
}
