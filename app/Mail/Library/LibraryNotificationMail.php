<?php

namespace App\Mail\Library;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LibraryNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{label: string, value: string}>  $details
     */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public string $intro,
        public array $details = [],
        public ?string $actionUrl = null,
        public string $actionLabel = 'Buka Portal Perpustakaan',
        public ?string $footer = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'templates.email.library.notification');
    }
}
