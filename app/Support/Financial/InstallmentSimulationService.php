<?php

namespace App\Support\Financial;

use App\Models\Financial\StudentInvoice;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class InstallmentSimulationService
{
    public function simulate(StudentInvoice $invoice, int $tenor, float $feeAmount = 0): array
    {
        if ($tenor < 2 || $tenor > 12) {
            throw new InvalidArgumentException('Tenor cicilan harus antara 2 sampai 12 kali.');
        }

        $baseAmount = (float) $invoice->outstanding_amount;

        if ($baseAmount <= 0) {
            throw new InvalidArgumentException('Invoice tidak memiliki outstanding amount.');
        }

        $feeAmount = max(0, $feeAmount);
        $totalAmount = $baseAmount + $feeAmount;
        $baseCents = (int) round($baseAmount * 100);
        $feeCents = (int) round($feeAmount * 100);
        $basePerInstallment = intdiv($baseCents, $tenor);
        $baseRemainder = $baseCents % $tenor;
        $feePerInstallment = intdiv($feeCents, $tenor);
        $feeRemainder = $feeCents % $tenor;
        $firstDueDate = $this->firstDueDate($invoice);

        $rows = [];

        for ($i = 1; $i <= $tenor; $i++) {
            $amountCents = $basePerInstallment + ($i <= $baseRemainder ? 1 : 0);
            $rowFeeCents = $feePerInstallment + ($i <= $feeRemainder ? 1 : 0);

            $rows[] = [
                'installment_no' => $i,
                'amount' => round($amountCents / 100, 2),
                'fee_amount' => round($rowFeeCents / 100, 2),
                'total_amount' => round(($amountCents + $rowFeeCents) / 100, 2),
                'due_date' => $firstDueDate->addMonthsNoOverflow($i - 1)->toDateString(),
            ];
        }

        return [
            'tenor' => $tenor,
            'base_amount' => round($baseAmount, 2),
            'fee_amount' => round($feeAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'installments' => $rows,
        ];
    }

    private function firstDueDate(StudentInvoice $invoice): CarbonImmutable
    {
        $dueDate = $invoice->due_date
            ? CarbonImmutable::parse($invoice->due_date)
            : CarbonImmutable::now()->addDays(7);

        if ($dueDate->isPast()) {
            return CarbonImmutable::now()->addDays(7)->startOfDay();
        }

        return $dueDate->startOfDay();
    }
}
