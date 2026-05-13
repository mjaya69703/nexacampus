<?php

namespace App\Support\Financial;

use App\Models\Financial\StudentInvoice;

class InvoiceNumberService
{
    public function generate(): string
    {
        $prefix = 'INV-'.now()->format('Y');
        $next = ((int) StudentInvoice::withTrashed()->max('id')) + 1;

        do {
            $invoiceNumber = $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (StudentInvoice::withTrashed()->where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }
}
