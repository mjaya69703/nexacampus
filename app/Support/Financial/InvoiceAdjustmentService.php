<?php

namespace App\Support\Financial;

use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\StudentInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceAdjustmentService
{
    public const TYPES = [
        'correction' => 'Correction',
        'discount' => 'Discount',
        'scholarship' => 'Scholarship',
        'waiver' => 'Waiver',
        'penalty' => 'Penalty',
        'write_off' => 'Write Off',
    ];

    public function types(): array
    {
        return config('financial.adjustment_types', self::TYPES);
    }

    public function apply(
        StudentInvoice $invoice,
        string $type,
        float $amount,
        ?string $reason = null,
        ?int $createdBy = null,
        ?Model $source = null,
    ): InvoiceAdjustment {
        if (! array_key_exists($type, $this->types())) {
            throw new RuntimeException('Tipe adjustment tidak valid.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal adjustment wajib lebih dari nol.');
        }

        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            throw new RuntimeException('Adjustment hanya bisa diterapkan pada invoice yang sudah diterbitkan dan belum dibatalkan.');
        }

        $signedAmount = $this->signedAmount($type, $amount);

        return DB::transaction(function () use ($invoice, $type, $signedAmount, $reason, $createdBy, $source) {
            $lockedInvoice = StudentInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $currentTotal = $this->totalWithAdjustments($lockedInvoice);

            if ($currentTotal + $signedAmount < 0) {
                throw new RuntimeException('Adjustment membuat total invoice menjadi negatif.');
            }

            if ($currentTotal + $signedAmount < (float) $lockedInvoice->paid_amount) {
                throw new RuntimeException('Adjustment membuat total invoice lebih kecil dari nominal yang sudah dibayar. Gunakan credit/refund flow untuk selisihnya.');
            }

            $adjustment = InvoiceAdjustment::create([
                'student_invoice_id' => $lockedInvoice->id,
                'adjustment_type' => $type,
                'amount' => $signedAmount,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reason' => $reason,
                'created_by' => $createdBy,
            ]);

            app(InvoiceStatusService::class)->refresh($lockedInvoice);
            app(FinancialClearanceService::class)->evaluate($lockedInvoice->studentProfile);

            return $adjustment->refresh()->load(['invoice.studentProfile.user', 'createdBy']);
        });
    }

    public function totalWithAdjustments(StudentInvoice $invoice): float
    {
        return (float) $invoice->items()->sum('amount') + (float) $invoice->adjustments()->sum('amount');
    }

    private function signedAmount(string $type, float $amount): float
    {
        return match ($type) {
            'discount', 'scholarship', 'waiver', 'write_off' => -abs($amount),
            'penalty' => abs($amount),
            default => $amount,
        };
    }
}
