<?php

namespace App\Console\Commands;

use App\Models\Financial\StudentInvoice;
use App\Support\Financial\FinancialNotificationService;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Financial\PaymentProcessingService;
use Illuminate\Console\Command;

class RefreshFinancialOverdue extends Command
{
    protected $signature = 'financial:refresh-overdue';

    protected $description = 'Refresh overdue invoice and installment statuses.';

    public function handle(): int
    {
        $checked = 0;
        $overdue = 0;

        StudentInvoice::query()
            ->with(['installments', 'studentProfile.user'])
            ->whereNotIn('status', ['draft', 'cancelled', 'paid'])
            ->where('outstanding_amount', '>', 0)
            ->chunkById(100, function ($invoices) use (&$checked, &$overdue): void {
                foreach ($invoices as $invoice) {
                    $checked++;

                    app(PaymentProcessingService::class)->refreshInstallmentOverdue($invoice);
                    $invoice = app(InvoiceStatusService::class)->refresh($invoice->refresh());

                    if ($invoice->status === 'overdue') {
                        $overdue++;
                        app(FinancialNotificationService::class)->invoiceOverdue($invoice);
                    }
                }
            });

        $this->info("Checked {$checked} invoices. Overdue: {$overdue}.");

        return self::SUCCESS;
    }
}
