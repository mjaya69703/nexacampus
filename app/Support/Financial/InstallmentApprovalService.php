<?php

namespace App\Support\Financial;

use App\Models\Financial\InvoiceInstallmentRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstallmentApprovalService
{
    public function approve(InvoiceInstallmentRequest $request, ?int $reviewedBy = null, ?string $notes = null): InvoiceInstallmentRequest
    {
        if ($request->status !== 'submitted') {
            throw new RuntimeException('Hanya pengajuan submitted yang bisa disetujui.');
        }

        return DB::transaction(function () use ($request, $reviewedBy, $notes) {
            $request->loadMissing('invoice');
            $invoice = $request->invoice;

            if (! $invoice || in_array($invoice->status, ['draft', 'paid', 'cancelled'], true)) {
                throw new RuntimeException('Invoice tidak valid untuk cicilan.');
            }

            if ($invoice->installments()->exists()) {
                throw new RuntimeException('Invoice ini sudah memiliki jadwal cicilan.');
            }

            foreach (($request->simulation_snapshot['installments'] ?? []) as $row) {
                $invoice->installments()->create([
                    'invoice_installment_request_id' => $request->id,
                    'installment_no' => $row['installment_no'],
                    'amount' => $row['amount'],
                    'fee_amount' => $row['fee_amount'] ?? 0,
                    'paid_amount' => 0,
                    'due_date' => $row['due_date'],
                    'status' => 'pending',
                ]);
            }

            $request->update([
                'status' => 'approved',
                'finance_notes' => $notes,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
            ]);

            $request = $request->refresh()->load(['invoice.installments', 'invoice.studentProfile.user', 'reviewedBy']);
            app(FinancialNotificationService::class)->installmentApproved($request);

            return $request;
        });
    }

    public function reject(InvoiceInstallmentRequest $request, ?int $reviewedBy = null, ?string $notes = null): InvoiceInstallmentRequest
    {
        if ($request->status !== 'submitted') {
            throw new RuntimeException('Hanya pengajuan submitted yang bisa ditolak.');
        }

        $request->update([
            'status' => 'rejected',
            'finance_notes' => $notes,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        $request = $request->refresh()->load(['invoice.studentProfile.user', 'reviewedBy']);
        app(FinancialNotificationService::class)->installmentRejected($request);

        return $request;
    }
}
