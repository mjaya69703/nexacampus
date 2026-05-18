<?php

namespace App\Mail\Financial;

use App\Models\Financial\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pembayaran Ditolak - '.$this->payment->payment_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'templates.email.financial.payment-rejected',
        );
    }
}
