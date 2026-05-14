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
        $baseAmount = (int) round($baseAmount);
        $feeAmount = (int) round($feeAmount);
        $totalAmount = $baseAmount + $feeAmount;
        $basePerInstallment = intdiv($baseAmount, $tenor);
        $baseRemainder = $baseAmount % $tenor;
        $feePerInstallment = intdiv($feeAmount, $tenor);
        $feeRemainder = $feeAmount % $tenor;
        $firstDueDate = $this->firstDueDate($invoice);

        $rows = [];

        for ($i = 1; $i <= $tenor; $i++) {
            $amount = $basePerInstallment + ($i === $tenor ? $baseRemainder : 0);
            $rowFeeAmount = $feePerInstallment + ($i === $tenor ? $feeRemainder : 0);

            $rows[] = [
                'installment_no' => $i,
                'amount' => $amount,
                'fee_amount' => $rowFeeAmount,
                'total_amount' => $amount + $rowFeeAmount,
                'due_date' => $firstDueDate->addMonthsNoOverflow($i - 1)->toDateString(),
            ];
        }

        return [
            'tenor' => $tenor,
            'base_amount' => $baseAmount,
            'fee_amount' => $feeAmount,
            'total_amount' => $totalAmount,
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
