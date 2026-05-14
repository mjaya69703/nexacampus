<?php

namespace App\Support\Financial;

use App\Models\Academic\StudentProfile;
use App\Models\Financial\FinancialClearancePolicy;
use App\Models\Financial\FinancialHold;
use App\Models\Financial\StudentInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialClearanceService
{
    public function evaluate(StudentProfile $studentProfile): array
    {
        $studentProfile->loadMissing('user');

        $policies = FinancialClearancePolicy::query()
            ->where('is_active', true)
            ->get()
            ->groupBy('invoice_type');

        if ($policies->isEmpty()) {
            $this->releaseResolvedHolds($studentProfile, collect());

            return $this->summary($studentProfile);
        }

        $invoices = StudentInvoice::query()
            ->with(['installments'])
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('invoice_type', $policies->keys())
            ->whereNotIn('status', ['draft', 'paid', 'cancelled'])
            ->where('outstanding_amount', '>', 0)
            ->get();

        $activePairs = collect();

        DB::transaction(function () use ($studentProfile, $policies, $invoices, $activePairs): void {
            foreach ($invoices as $invoice) {
                app(PaymentProcessingService::class)->refreshInstallmentOverdue($invoice);
                app(InvoiceStatusService::class)->refresh($invoice);
                $invoice->refresh()->load('installments');

                if (! $this->isOverdue($invoice)) {
                    continue;
                }

                /** @var Collection<int, FinancialClearancePolicy> $invoicePolicies */
                $invoicePolicies = $policies->get($invoice->invoice_type, collect());

                foreach ($invoicePolicies as $policy) {
                    $startsAt = $this->overdueDate($invoice);
                    $blockedAt = $startsAt?->copy()->addDays($policy->grace_days);

                    $activePairs->push($invoice->id.'-'.$policy->hold_type);

                    $hold = FinancialHold::query()
                        ->where('student_profile_id', $studentProfile->id)
                        ->where('student_invoice_id', $invoice->id)
                        ->where('hold_type', $policy->hold_type)
                        ->whereIn('status', ['active', 'waived'])
                        ->latest()
                        ->first();

                    if ($hold?->isWaived()) {
                        continue;
                    }

                    if (! $hold || $hold->status === 'waived') {
                        $hold = new FinancialHold([
                            'student_profile_id' => $studentProfile->id,
                            'student_invoice_id' => $invoice->id,
                            'hold_type' => $policy->hold_type,
                            'status' => 'active',
                        ]);
                    }

                    $hold->fill([
                        'financial_clearance_policy_id' => $policy->id,
                        'reason' => $this->holdReason($invoice, $policy),
                        'starts_at' => $startsAt,
                        'blocked_at' => $blockedAt,
                    ])->save();
                }
            }

            $this->releaseResolvedHolds($studentProfile, $activePairs);
        });

        return $this->summary($studentProfile);
    }

    public function hasBlockingHold(StudentProfile $studentProfile, array $holdTypes): bool
    {
        $summary = $this->evaluate($studentProfile);

        return collect($summary['blocking_holds'])
            ->whereIn('hold_type', $holdTypes)
            ->isNotEmpty();
    }

    public function release(FinancialHold $hold, ?int $releasedBy, ?string $notes = null): FinancialHold
    {
        $hold->update([
            'status' => 'released',
            'released_at' => now(),
            'released_by' => $releasedBy,
            'release_notes' => $notes,
        ]);

        return $hold->refresh();
    }

    public function waive(FinancialHold $hold, Carbon|string $until, ?int $releasedBy, ?string $notes = null): FinancialHold
    {
        $hold->update([
            'status' => 'waived',
            'waived_until' => Carbon::parse($until)->endOfDay(),
            'released_at' => now(),
            'released_by' => $releasedBy,
            'release_notes' => $notes,
        ]);

        return $hold->refresh();
    }

    public function summary(StudentProfile $studentProfile): array
    {
        $holds = FinancialHold::query()
            ->with(['invoice', 'policy'])
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('status', ['active', 'waived'])
            ->get()
            ->filter(fn (FinancialHold $hold) => $hold->status === 'active' || $hold->isWaived())
            ->values();

        $blockingHolds = $holds
            ->filter(fn (FinancialHold $hold) => $hold->isBlocking())
            ->values();

        $warningHolds = $holds
            ->reject(fn (FinancialHold $hold) => $hold->isBlocking())
            ->values();

        return [
            'holds' => $holds,
            'blocking_holds' => $blockingHolds,
            'warning_holds' => $warningHolds,
            'has_blocking_hold' => $blockingHolds->isNotEmpty(),
            'has_warning' => $holds->isNotEmpty(),
        ];
    }

    private function isOverdue(StudentInvoice $invoice): bool
    {
        if ((float) $invoice->outstanding_amount <= 0 || in_array($invoice->status, ['paid', 'cancelled', 'draft'], true)) {
            return false;
        }

        if ($invoice->installments->isNotEmpty()) {
            return $invoice->installments
                ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                ->contains(fn ($installment) => $installment->due_date?->isPast());
        }

        return $invoice->due_date?->isPast() ?? false;
    }

    private function overdueDate(StudentInvoice $invoice): ?Carbon
    {
        if ($invoice->installments->isNotEmpty()) {
            return $invoice->installments
                ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                ->filter(fn ($installment) => $installment->due_date?->isPast())
                ->sortBy('due_date')
                ->first()
                ?->due_date
                ?->copy()
                ->startOfDay();
        }

        return $invoice->due_date?->copy()->startOfDay();
    }

    private function holdReason(StudentInvoice $invoice, FinancialClearancePolicy $policy): string
    {
        return sprintf(
            '%s invoice %s is overdue and may block %s after %s day grace period.',
            str($invoice->invoice_type)->replace('_', ' ')->title(),
            $invoice->invoice_number,
            str($policy->hold_type)->replace('_', ' '),
            $policy->grace_days,
        );
    }

    private function releaseResolvedHolds(StudentProfile $studentProfile, Collection $activePairs): void
    {
        FinancialHold::query()
            ->where('student_profile_id', $studentProfile->id)
            ->whereIn('status', ['active', 'waived'])
            ->get()
            ->each(function (FinancialHold $hold) use ($activePairs): void {
                $pair = $hold->student_invoice_id.'-'.$hold->hold_type;

                if ($activePairs->contains($pair)) {
                    return;
                }

                $hold->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_notes' => 'Auto-released because invoice is no longer overdue/outstanding.',
                ]);
            });
    }
}
