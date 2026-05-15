<?php

namespace App\Support\Financial;

use App\Models\Academic\StudentProfile;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentCreditTransaction;
use App\Models\Financial\StudentInvoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StudentCreditService
{
    public function balanceFor(StudentProfile|int $studentProfile): StudentCreditBalance
    {
        $studentProfileId = $studentProfile instanceof StudentProfile ? $studentProfile->id : $studentProfile;

        return StudentCreditBalance::query()->firstOrCreate(
            ['student_profile_id' => $studentProfileId],
            ['balance' => 0]
        );
    }

    public function addOverpayment(Payment $payment, float $amount, ?int $createdBy = null, ?string $notes = null): ?StudentCreditTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return $this->record(
            studentProfileId: $payment->student_profile_id,
            amount: $amount,
            type: 'overpayment',
            invoice: $payment->invoice,
            payment: $payment,
            createdBy: $createdBy,
            notes: $notes ?: 'Kelebihan pembayaran dari '.$payment->payment_number.'.'
        );
    }

    public function refund(StudentProfile $studentProfile, float $amount, ?int $createdBy = null, ?string $notes = null): StudentCreditTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Nominal refund wajib lebih dari nol.');
        }

        $balance = $this->balanceFor($studentProfile);

        if ((float) $balance->balance < $amount) {
            throw new RuntimeException('Saldo kredit mahasiswa tidak mencukupi untuk refund.');
        }

        return $this->record(
            studentProfileId: $studentProfile->id,
            amount: -abs($amount),
            type: 'refund',
            createdBy: $createdBy,
            notes: $notes
        );
    }

    public function manualAdjustment(StudentProfile $studentProfile, float $amount, ?int $createdBy = null, ?string $notes = null): StudentCreditTransaction
    {
        if ($amount == 0.0) {
            throw new RuntimeException('Nominal adjustment kredit tidak boleh nol.');
        }

        return $this->record(
            studentProfileId: $studentProfile->id,
            amount: $amount,
            type: 'manual_adjustment',
            createdBy: $createdBy,
            notes: $notes
        );
    }

    private function record(
        int $studentProfileId,
        float $amount,
        string $type,
        ?StudentInvoice $invoice = null,
        ?Payment $payment = null,
        ?int $createdBy = null,
        ?string $notes = null,
    ): StudentCreditTransaction {
        return DB::transaction(function () use ($studentProfileId, $amount, $type, $invoice, $payment, $createdBy, $notes) {
            $this->balanceFor($studentProfileId);
            $balance = StudentCreditBalance::query()
                ->where('student_profile_id', $studentProfileId)
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = StudentCreditTransaction::create([
                'student_profile_id' => $studentProfileId,
                'student_invoice_id' => $invoice?->id,
                'payment_id' => $payment?->id,
                'transaction_type' => $type,
                'amount' => $amount,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            $balance->update([
                'balance' => (float) $balance->balance + $amount,
            ]);

            return $transaction;
        });
    }
}
