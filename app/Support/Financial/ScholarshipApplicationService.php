<?php

namespace App\Support\Financial;

use App\Models\Financial\StudentInvoice;
use App\Models\Financial\StudentScholarship;
use RuntimeException;

class ScholarshipApplicationService
{
    public function applyAssignmentToInvoice(StudentScholarship $assignment, StudentInvoice $invoice, ?int $createdBy = null): void
    {
        $assignment->loadMissing('scholarship');

        if (! $assignment->matchesInvoice($invoice)) {
            throw new RuntimeException('Scholarship assignment tidak cocok dengan invoice ini.');
        }

        if (! $assignment->scholarship?->is_active) {
            throw new RuntimeException('Scholarship tidak aktif.');
        }

        $alreadyApplied = $invoice->adjustments()
            ->where('adjustment_type', 'scholarship')
            ->where('source_type', $assignment->getMorphClass())
            ->where('source_id', $assignment->id)
            ->exists();

        if ($alreadyApplied) {
            return;
        }

        $baseAmount = (float) $invoice->items()->sum('amount');
        $discount = $assignment->scholarship->calculateDiscount($baseAmount);

        if ($discount <= 0) {
            return;
        }

        app(InvoiceAdjustmentService::class)->apply(
            invoice: $invoice,
            type: 'scholarship',
            amount: $discount,
            reason: 'Scholarship: '.$assignment->scholarship->name,
            createdBy: $createdBy,
            source: $assignment
        );
    }

    public function applyMatchingScholarships(StudentInvoice $invoice, ?int $createdBy = null): void
    {
        $assignments = StudentScholarship::query()
            ->with('scholarship')
            ->where('student_profile_id', $invoice->student_profile_id)
            ->where('status', 'active')
            ->where(function ($query) use ($invoice) {
                $query->whereNull('academic_year_id')
                    ->orWhere('academic_year_id', $invoice->academic_year_id);
            })
            ->where(function ($query) use ($invoice) {
                $query->whereNull('semester')
                    ->orWhere('semester', $invoice->semester);
            })
            ->get();

        foreach ($assignments as $assignment) {
            $this->applyAssignmentToInvoice($assignment, $invoice, $createdBy);
        }
    }
}
