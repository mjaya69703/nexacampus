<?php

namespace App\Mail;

use App\Models\Academic\StudentProfile;
use App\Models\Admission\AdmissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdmissionConvertedToStudent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdmissionApplication $application,
        public StudentProfile $studentProfile,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to NexaCampus - NIM '.$this->studentProfile->nim,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'templates.email.admission-converted-to-student',
        );
    }
}
