<?php

namespace App\Support\Financial;

use App\Models\Financial\StudentInvoice;

class InvoiceStatusService
{
    public function refresh(StudentInvoice $invoice): StudentInvoice
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            return $invoice;
        }

        $totalAmount = app(InvoiceAdjustmentService::class)->totalWithAdjustments($invoice);
        $paidAmount = (float) $invoice->paid_amount;
        $outstandingAmount = max(0, $totalAmount - $paidAmount);

        $hasInstallments = $invoice->installments()->exists();
        $hasOverdueInstallment = $hasInstallments
            && $invoice->installments()
                ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->exists();

        $status = match (true) {
            $outstandingAmount <= 0 => 'paid',
            $hasOverdueInstallment => 'overdue',
            $paidAmount > 0 => 'partially_paid',
            ! $hasInstallments && $invoice->due_date?->isPast() => 'overdue',
            default => 'issued',
        };

        $invoice->update([
            'total_amount' => $totalAmount,
            'outstanding_amount' => $outstandingAmount,
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($invoice->paid_at ?: now()) : null,
        ]);

        return $invoice->refresh();
    }
}
