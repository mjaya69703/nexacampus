<?php

namespace App\Support\Financial;

use App\Models\Financial\StudentInvoice;
use RuntimeException;

class InvoicePublishingService
{
    public function issue(StudentInvoice $invoice, ?int $userId = null): StudentInvoice
    {
        if ($invoice->status !== 'draft') {
            throw new RuntimeException('Hanya invoice draft yang bisa diterbitkan.');
        }

        if ($invoice->items()->count() === 0) {
            throw new RuntimeException('Invoice wajib memiliki minimal satu item.');
        }

        $invoice->update([
            'status' => 'issued',
            'issued_at' => now(),
            'issued_by' => $userId,
            'updated_by' => $userId,
        ]);

        $invoice = app(InvoiceStatusService::class)->refresh($invoice->refresh());
        app(ScholarshipApplicationService::class)->applyMatchingScholarships($invoice, $userId);
        app(FinancialNotificationService::class)->invoiceIssued($invoice->refresh());

        return app(InvoiceStatusService::class)->refresh($invoice->refresh());
    }
}
