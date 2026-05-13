<?php

namespace App\Support\Financial;

use App\Models\Financial\InvoiceInstallment;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentProcessingService
{
    public function submitProof(
        StudentInvoice $invoice,
        float $amount,
        string $proofPath,
        string $paymentMethod = 'bank_transfer',
        ?string $transactionReference = null,
        ?string $notes = null,
        ?int $submittedBy = null,
        ?int $installmentId = null,
    ): Payment {
        if (! $invoice->isVisibleToStudent() || in_array($invoice->status, ['paid', 'cancelled'], true)) {
            throw new RuntimeException('Invoice tidak tersedia untuk pembayaran.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pembayaran wajib lebih dari nol.');
        }

        if ($amount > (float) $invoice->outstanding_amount) {
            throw new RuntimeException('Nominal pembayaran melebihi outstanding invoice.');
        }

        $installment = null;

        if ($installmentId) {
            $installment = $invoice->installments()
                ->whereKey($installmentId)
                ->whereNotIn('status', ['paid'])
                ->firstOrFail();

            $installmentOutstanding = (float) $installment->amount + (float) $installment->fee_amount - (float) $installment->paid_amount;

            if ($amount > $installmentOutstanding) {
                throw new RuntimeException('Nominal pembayaran melebihi sisa cicilan yang dipilih.');
            }
        }

        return DB::transaction(fn () => Payment::create([
            'payment_number' => app(PaymentNumberService::class)->generate(),
            'student_invoice_id' => $invoice->id,
            'student_profile_id' => $invoice->student_profile_id,
            'invoice_installment_id' => $installment?->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_reference' => $transactionReference,
            'proof_file_path' => $proofPath,
            'status' => 'pending',
            'paid_at' => now(),
            'submitted_by' => $submittedBy,
            'notes' => $notes,
        ]));
    }

    public function verify(Payment $payment, ?int $verifiedBy = null, ?string $notes = null): Payment
    {
        if ($payment->status !== 'pending') {
            throw new RuntimeException('Hanya payment pending yang bisa diverifikasi.');
        }

        return DB::transaction(function () use ($payment, $verifiedBy, $notes) {
            $payment->loadMissing('invoice.installments');
            $invoice = $payment->invoice()->lockForUpdate()->firstOrFail();

            $payment->update([
                'status' => 'verified',
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
                'verification_notes' => $notes,
            ]);

            if ($payment->invoice_installment_id) {
                $this->applyToInstallment($payment->installment()->lockForUpdate()->firstOrFail(), (float) $payment->amount);
            } elseif ($invoice->installments()->exists()) {
                $this->allocateToInstallments($invoice, (float) $payment->amount);
            }

            $invoice->update([
                'paid_amount' => (float) $invoice->paid_amount + (float) $payment->amount,
            ]);

            $this->refreshInstallmentOverdue($invoice);
            app(InvoiceStatusService::class)->refresh($invoice->refresh());

            return $payment->refresh()->load(['invoice', 'studentProfile.user', 'verifiedBy']);
        });
    }

    public function reject(Payment $payment, ?int $verifiedBy = null, ?string $notes = null): Payment
    {
        if ($payment->status !== 'pending') {
            throw new RuntimeException('Hanya payment pending yang bisa ditolak.');
        }

        $payment->update([
            'status' => 'rejected',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);

        return $payment->refresh()->load(['invoice', 'studentProfile.user', 'verifiedBy']);
    }

    public function refreshInstallmentOverdue(StudentInvoice $invoice): void
    {
        $invoice->installments()
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    private function allocateToInstallments(StudentInvoice $invoice, float $amount): void
    {
        $remaining = $amount;

        foreach ($invoice->installments()->orderBy('installment_no')->lockForUpdate()->get() as $installment) {
            if ($remaining <= 0) {
                break;
            }

            if ($installment->status === 'paid') {
                continue;
            }

            $outstanding = (float) $installment->amount + (float) $installment->fee_amount - (float) $installment->paid_amount;
            $applied = min($remaining, $outstanding);
            $this->applyToInstallment($installment, $applied);
            $remaining -= $applied;
        }
    }

    private function applyToInstallment(InvoiceInstallment $installment, float $amount): void
    {
        $paidAmount = (float) $installment->paid_amount + $amount;
        $totalAmount = (float) $installment->amount + (float) $installment->fee_amount;

        $installment->update([
            'paid_amount' => $paidAmount,
            'status' => $paidAmount >= $totalAmount ? 'paid' : 'partially_paid',
        ]);
    }
}
