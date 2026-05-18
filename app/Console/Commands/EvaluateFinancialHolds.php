<?php

namespace App\Console\Commands;

use App\Models\Academic\StudentProfile;
use App\Support\Financial\FinancialClearanceService;
use Illuminate\Console\Command;

class EvaluateFinancialHolds extends Command
{
    protected $signature = 'financial:evaluate-holds';

    protected $description = 'Evaluate financial clearance holds for students with unpaid invoices or active holds.';

    public function handle(FinancialClearanceService $clearanceService): int
    {
        $evaluated = 0;

        StudentProfile::query()
            ->where(function ($query) {
                $query->whereHas('financialHolds', fn ($query) => $query->whereIn('status', ['active', 'waived']))
                    ->orWhereHas('invoices', fn ($query) => $query
                        ->whereNotIn('status', ['draft', 'paid', 'cancelled'])
                        ->where('outstanding_amount', '>', 0));
            })
            ->chunkById(100, function ($students) use (&$evaluated, $clearanceService): void {
                foreach ($students as $student) {
                    $clearanceService->evaluate($student);
                    $evaluated++;
                }
            });

        $this->info("Evaluated {$evaluated} student financial clearance summaries.");

        return self::SUCCESS;
    }
}
