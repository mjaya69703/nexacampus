<?php

namespace App\Mail\Academic;

use App\Models\Academic\Assignment;
use App\Models\Academic\StudentProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assignment $assignment,
        public StudentProfile $studentProfile,
        public string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reminder Deadline Tugas: '.$this->assignment->title);
    }

    public function content(): Content
    {
        return new Content(view: 'templates.email.academic.assignment-due-reminder');
    }
}
