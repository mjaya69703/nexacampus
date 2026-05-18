<?php

namespace App\Console\Commands;

use App\Support\Financial\InvoiceScheduleService;
use Illuminate\Console\Command;

class RunFinancialInvoiceSchedules extends Command
{
    protected $signature = 'financial:run-invoice-schedules {--dry-run : Preview due schedules without creating invoices} {--limit= : Maximum schedules to process}';

    protected $description = 'Run due financial invoice publishing schedules.';

    public function handle(InvoiceScheduleService $service): int
    {
        $summary = $service->runDueSchedules(
            dryRun: (bool) $this->option('dry-run'),
            limit: $this->option('limit') ? (int) $this->option('limit') : null,
        );

        $this->info(sprintf(
            'Processed %s schedules. Created: %s, Skipped: %s, Failed: %s.',
            $summary['processed'],
            $summary['created'],
            $summary['skipped'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
