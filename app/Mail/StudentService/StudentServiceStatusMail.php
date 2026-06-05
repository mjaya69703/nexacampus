<?php

namespace App\Mail\StudentService;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentServiceStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $title,
        public string $studentName,
        public string $requestLabel,
        public string $requestNumber,
        public string $statusLabel,
        public ?string $notes = null,
        public ?string $actionUrl = null,
        public string $actionLabel = 'Lihat Detail',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'templates.email.student-services.status-updated');
    }
}
