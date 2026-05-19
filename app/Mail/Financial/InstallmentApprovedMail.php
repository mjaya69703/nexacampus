<?php

namespace App\Mail\Financial;

use App\Models\Financial\InvoiceInstallmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstallmentApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InvoiceInstallmentRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengajuan Cicilan Disetujui - '.$this->request->invoice?->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'templates.email.financial.installment-approved',
        );
    }
}
