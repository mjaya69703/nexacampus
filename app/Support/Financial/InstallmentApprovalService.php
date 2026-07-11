<?php

namespace App\Support\Financial;

use App\Models\Financial\InvoiceInstallmentRequest;
use App\Models\Financial\StudentInvoice;
use App\Models\Organization\ApprovalTemplate;
use App\Models\User;
use App\Support\Organization\ApprovalEngine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstallmentApprovalService
{
    public function createAndSubmit(StudentInvoice $invoice, array $payload, ?int $userId = null): InvoiceInstallmentRequest
    {
        return DB::transaction(function () use ($invoice, $payload, $userId): InvoiceInstallmentRequest {
            $request = $invoice->installmentRequests()->create($payload);
            $request->loadMissing(['invoice', 'studentProfile.user']);

            $template = ApprovalTemplate::query()
                ->where('code', 'INSTALLMENT_REQUEST_REVIEW')
                ->where('is_active', true)
                ->firstOrFail();

            $approval = app(ApprovalEngine::class)->submitFromTemplate(
                template: $template,
                subject: 'Pengajuan cicilan '.$request->invoice?->invoice_number,
                requester: $request->studentProfile?->user,
                approvable: $request,
                payload: [
                    'student_invoice_id' => $request->student_invoice_id,
                    'student_profile_id' => $request->student_profile_id,
                    'requested_tenor' => $request->requested_tenor,
                    'simulated_total_amount' => $request->simulated_total_amount,
                ],
                reference: $request->invoice?->invoice_number,
                notes: $request->student_reason,
                createdBy: $userId,
            );

            $request->update([
                'approval_request_id' => $approval->id,
                'status' => 'in_approval',
            ]);

            return $request->refresh();
        });
    }

    public function approve(InvoiceInstallmentRequest $request, ?int $reviewedBy = null, ?string $notes = null): InvoiceInstallmentRequest
    {
        if (! in_array($request->status, ['submitted', 'in_approval'], true)) {
            throw new RuntimeException('Hanya pengajuan submitted yang bisa disetujui.');
        }

        return DB::transaction(function () use ($request, $reviewedBy, $notes) {
            $request->loadMissing(['invoice', 'approvalRequest']);

            if (! $request->approvalRequest || $request->approvalRequest->status !== 'in_progress') {
                throw new RuntimeException('Approval pengajuan cicilan tidak sedang berjalan.');
            }

            $request->update([
                'finance_notes' => $notes,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
            ]);

            app(ApprovalEngine::class)->approve($request->approvalRequest, User::findOrFail($reviewedBy), $notes);

            return $request->refresh()->load(['invoice.installments', 'invoice.studentProfile.user', 'reviewedBy']);
        });
    }

    public function approveFromApproval(InvoiceInstallmentRequest $request, ?int $reviewedBy = null, ?string $notes = null): InvoiceInstallmentRequest
    {
        return DB::transaction(function () use ($request, $reviewedBy, $notes) {
            $request = $request->fresh(['invoice']);
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
                'finance_notes' => $notes ?: $request->finance_notes,
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
        if (! in_array($request->status, ['submitted', 'in_approval'], true)) {
            throw new RuntimeException('Hanya pengajuan submitted yang bisa ditolak.');
        }

        $request->loadMissing('approvalRequest');

        if ($request->approvalRequest && $request->approvalRequest->status === 'in_progress') {
            $request->update([
                'finance_notes' => $notes,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
            ]);

            app(ApprovalEngine::class)->reject($request->approvalRequest, User::findOrFail($reviewedBy), $notes);

            return $request->refresh()->load(['invoice.studentProfile.user', 'reviewedBy']);
        }

        return $this->rejectFromApproval($request, $reviewedBy, $notes);
    }

    public function rejectFromApproval(InvoiceInstallmentRequest $request, ?int $reviewedBy = null, ?string $notes = null): InvoiceInstallmentRequest
    {
        $request->update([
            'status' => 'rejected',
            'finance_notes' => $notes ?: $request->finance_notes,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        $request = $request->refresh()->load(['invoice.studentProfile.user', 'reviewedBy']);
        app(FinancialNotificationService::class)->installmentRejected($request);

        return $request;
    }
}
