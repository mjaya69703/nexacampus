<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Financial\InvoiceInstallment;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use App\Support\Database\PortableOrder;
use App\Support\Financial\InstallmentApprovalService;
use App\Support\Financial\InstallmentSimulationService;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Financial\PaymentProcessingService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Tagihan & pembayaran mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - `canRequestInstallment()` menganggap pengajuan berstatus `in_approval`
 *   masih boleh diajukan ulang, padahal service langsung mengubah status
 *   menjadi `in_approval` setelah submit. Sekarang semua status aktif
 *   (submitted/in_approval) memblokir pengajuan ganda.
 * - Simulasi cicilan dihitung sekali di server untuk tenor 2-12 sehingga
 *   tidak perlu request Livewire setiap perubahan tenor.
 */
class StudentFinancialController extends Controller
{
    private const TENORS = [2, 3, 4, 5, 6, 8, 10, 12];

    public function invoices(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        $invoices = collect();

        if ($profile !== null) {
            StudentInvoice::query()
                ->where('student_profile_id', $profile->id)
                ->whereIn('status', ['issued', 'partially_paid'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->get()
                ->each(fn (StudentInvoice $invoice) => app(InvoiceStatusService::class)->refresh($invoice));

            $invoices = StudentInvoice::query()
                ->with(['academicYear', 'items'])
                ->where('student_profile_id', $profile->id)
                ->where('status', '!=', 'draft')
                ->orderByRaw(PortableOrder::priority('status', ['overdue', 'partially_paid', 'issued', 'paid', 'cancelled']))
                ->orderBy('due_date')
                ->orderByDesc('created_at')
                ->get();
        }

        $rows = $invoices->map(fn (StudentInvoice $invoice) => [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'type' => $invoice->invoice_type,
            'typeLabel' => $this->typeLabel($invoice->invoice_type),
            'status' => $invoice->status,
            'statusLabel' => $this->statusLabel($invoice->status),
            'statusTone' => $this->statusTone($invoice->status),
            'academicYear' => $invoice->academicYear?->name ?? '-',
            'semester' => $invoice->semester,
            'total' => (float) $invoice->total_amount,
            'paid' => (float) $invoice->paid_amount,
            'outstanding' => (float) $invoice->outstanding_amount,
            'dueDate' => $invoice->due_date?->format('d M Y') ?? '-',
            'isOverdue' => $invoice->due_date !== null && $invoice->due_date->isPast()
                && ! in_array($invoice->status, ['paid', 'cancelled'], true),
            'paidPercent' => $this->paidPercent($invoice),
            'itemsCount' => $invoice->items->count(),
            'url' => route('student.financial.invoices.show', ['id' => $invoice->id]),
        ])->values()->all();

        return Inertia::render('Student/Financial/Invoices', [
            'shell' => ShellProps::make($user, 'Financial', 'Tagihan Saya'),
            'hasProfile' => $profile !== null,
            'invoices' => $rows,
            'summary' => [
                'totalInvoices' => $invoices->count(),
                'outstanding' => (float) $invoices
                    ->whereNotIn('status', ['paid', 'cancelled'])
                    ->sum('outstanding_amount'),
                'overdue' => $invoices->where('status', 'overdue')->count(),
                'paid' => $invoices->where('status', 'paid')->count(),
            ],
            'urls' => [
                'index' => route('student.financial.invoices'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->first();

        abort_unless($profile !== null, 404, 'Profil mahasiswa tidak ditemukan.');

        $invoice = StudentInvoice::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', '!=', 'draft')
            ->findOrFail($id);

        app(InvoiceStatusService::class)->refresh($invoice);
        app(PaymentProcessingService::class)->refreshInstallmentOverdue($invoice);

        $invoice->load([
            'academicYear',
            'items',
            'adjustments.createdBy',
            'payments.verifiedBy',
            'installments',
            'installmentRequests.reviewedBy',
        ]);

        $canRequestInstallment = $this->canRequestInstallment($invoice);
        $simulations = [];

        if ($canRequestInstallment) {
            foreach (self::TENORS as $tenor) {
                try {
                    $simulations[] = app(InstallmentSimulationService::class)->simulate($invoice, $tenor);
                } catch (Throwable) {
                    // Abaikan tenor yang tidak memenuhi syarat nominal.
                }
            }
        }

        return Inertia::render('Student/Financial/InvoiceDetail', [
            'shell' => ShellProps::make($user, 'Financial', 'Detail Invoice'),
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'type' => $invoice->invoice_type,
                'typeLabel' => $this->typeLabel($invoice->invoice_type),
                'status' => $invoice->status,
                'statusLabel' => $this->statusLabel($invoice->status),
                'statusTone' => $this->statusTone($invoice->status),
                'academicYear' => $invoice->academicYear?->name ?? '-',
                'semester' => $invoice->semester,
                'total' => (float) $invoice->total_amount,
                'paid' => (float) $invoice->paid_amount,
                'outstanding' => (float) $invoice->outstanding_amount,
                'dueDate' => $invoice->due_date?->format('d M Y') ?? '-',
                'isOverdue' => $invoice->due_date !== null && $invoice->due_date->isPast()
                    && ! in_array($invoice->status, ['paid', 'cancelled'], true),
                'issuedAt' => ($invoice->issued_at ?? $invoice->created_at)?->format('d M Y') ?? '-',
                'notes' => $invoice->notes,
                'paidPercent' => $this->paidPercent($invoice),
                'canPay' => ! in_array($invoice->status, ['paid', 'cancelled'], true)
                    && (float) $invoice->outstanding_amount > 0,
                'canRequestInstallment' => $canRequestInstallment,
            ],
            'items' => $invoice->items->sortBy('sort_order')->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'type' => $item->item_type,
                'typeLabel' => $this->typeLabel($item->item_type),
                'amount' => (float) $item->amount,
            ])->values()->all(),
            'adjustments' => $invoice->adjustments->sortBy('created_at')->map(fn ($adjustment) => [
                'id' => $adjustment->id,
                'label' => $this->adjustmentLabel($adjustment->adjustment_type),
                'reason' => $adjustment->reason ?: 'Penyesuaian dari finance',
                'amount' => (float) $adjustment->amount,
                'date' => $adjustment->created_at?->format('d M Y'),
                'by' => $adjustment->createdBy?->name,
            ])->values()->all(),
            'installments' => $invoice->installments->sortBy('installment_no')->map(function (InvoiceInstallment $installment) {
                $total = (float) $installment->amount + (float) $installment->fee_amount;
                $paid = (float) $installment->paid_amount;

                return [
                    'id' => $installment->id,
                    'no' => $installment->installment_no,
                    'total' => $total,
                    'paid' => $paid,
                    'outstanding' => max(0, $total - $paid),
                    'dueDate' => $installment->due_date?->format('d M Y') ?? '-',
                    'status' => $installment->status,
                    'statusLabel' => $this->statusLabel($installment->status),
                    'statusTone' => $this->statusTone($installment->status),
                    'paidPercent' => $total > 0 ? min(100, (int) round(($paid / $total) * 100)) : 0,
                ];
            })->values()->all(),
            'payments' => $invoice->payments->sortByDesc('created_at')->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'number' => $payment->payment_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method,
                'methodLabel' => $this->typeLabel($payment->payment_method),
                'status' => $payment->status,
                'statusLabel' => $this->statusLabel($payment->status),
                'statusTone' => $this->statusTone($payment->status),
                'date' => $payment->paid_at?->format('d M Y H:i') ?? '-',
                'verifiedBy' => $payment->verifiedBy?->name,
                'receiptUrl' => $payment->status === 'verified'
                    ? route('student.financial.payments.receipt', ['payment' => $payment->id])
                    : null,
            ])->values()->all(),
            'installmentRequests' => $invoice->installmentRequests->sortByDesc('created_at')->map(
                fn (InvoiceInstallmentRequest $installmentRequest) => [
                    'id' => $installmentRequest->id,
                    'tenor' => $installmentRequest->requested_tenor,
                    'status' => $installmentRequest->status,
                    'statusLabel' => $this->statusLabel($installmentRequest->status),
                    'statusTone' => $this->statusTone($installmentRequest->status),
                    'feeAmount' => (float) $installmentRequest->requested_fee_amount,
                    'totalAmount' => (float) $installmentRequest->simulated_total_amount,
                    'reason' => $installmentRequest->student_reason,
                    'financeNotes' => $installmentRequest->finance_notes,
                    'submittedAt' => $installmentRequest->created_at?->format('d M Y H:i'),
                    'reviewedAt' => $installmentRequest->reviewed_at?->format('d M Y H:i'),
                ]
            )->values()->all(),
            'defaultPaymentAmount' => $this->defaultPaymentAmount($invoice),
            'simulations' => $simulations,
            'paymentMethods' => [
                ['value' => 'bank_transfer', 'label' => 'Transfer Bank'],
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => 'credit_card', 'label' => 'Kartu Kredit'],
                ['value' => 'e_wallet', 'label' => 'E-Wallet'],
            ],
            'urls' => [
                'index' => route('student.financial.invoices'),
                'pay' => route('student.financial.invoices.pay', ['id' => $invoice->id]),
                'installment' => route('student.financial.invoices.installment', ['id' => $invoice->id]),
            ],
        ]);
    }

    public function pay(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $invoice = StudentInvoice::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', '!=', 'draft')
            ->findOrFail($id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:bank_transfer,cash,credit_card,e_wallet'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ]);

        try {
            $path = $request->file('proof')->store('financial/payment-proofs', 'public');

            app(PaymentProcessingService::class)->submitProof(
                invoice: $invoice,
                amount: (float) $validated['amount'],
                proofPath: $path,
                paymentMethod: $validated['method'],
                transactionReference: $validated['reference'] ?? null,
                notes: $validated['notes'] ?? null,
                submittedBy: $request->user()->id,
            );
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi finance.');
    }

    public function requestInstallment(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->studentProfile()->first();

        abort_unless($profile !== null, 403, 'Profil mahasiswa tidak ditemukan.');

        $invoice = StudentInvoice::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', '!=', 'draft')
            ->findOrFail($id);

        $validated = $request->validate([
            'tenor' => ['required', 'integer', 'min:2', 'max:12'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->canRequestInstallment($invoice)) {
            return back()->with('error', 'Invoice ini tidak tersedia untuk pengajuan cicilan.');
        }

        try {
            $simulation = app(InstallmentSimulationService::class)->simulate($invoice, (int) $validated['tenor']);

            app(InstallmentApprovalService::class)->createAndSubmit($invoice, [
                'student_profile_id' => $invoice->student_profile_id,
                'requested_tenor' => (int) $validated['tenor'],
                'requested_fee_amount' => $simulation['fee_amount'],
                'simulated_total_amount' => $simulation['total_amount'],
                'simulation_snapshot' => $simulation,
                'status' => 'submitted',
                'student_reason' => $validated['reason'] ?? null,
            ], $request->user()->id);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengajuan cicilan berhasil dikirim dan menunggu approval finance.');
    }

    private function canRequestInstallment(StudentInvoice $invoice): bool
    {
        $hasActiveRequest = $invoice->installmentRequests()
            ->whereIn('status', ['submitted', 'in_approval'])
            ->exists();

        return ! in_array($invoice->status, ['paid', 'cancelled'], true)
            && (float) $invoice->outstanding_amount > 0
            && ! $invoice->installments()->exists()
            && ! $hasActiveRequest;
    }

    private function defaultPaymentAmount(StudentInvoice $invoice): float
    {
        $next = $invoice->installments
            ->whereNotIn('status', ['paid'])
            ->sortBy('installment_no')
            ->first();

        if ($next !== null) {
            return max(0, (float) $next->amount + (float) $next->fee_amount - (float) $next->paid_amount);
        }

        return (float) $invoice->outstanding_amount;
    }

    private function paidPercent(StudentInvoice $invoice): int
    {
        $total = (float) $invoice->total_amount;

        if ($total <= 0) {
            return 0;
        }

        return min(100, (int) round(((float) $invoice->paid_amount / $total) * 100));
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'paid', 'verified', 'approved' => 'green',
            'partially_paid', 'issued' => 'blue',
            'overdue', 'rejected', 'failed' => 'red',
            'cancelled', 'closed' => 'gray',
            'pending', 'submitted', 'in_approval' => 'amber',
            default => 'gray',
        };
    }

    private function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function typeLabel(?string $type): string
    {
        return str($type ?: 'invoice')->replace('_', ' ')->title()->toString();
    }

    private function adjustmentLabel(?string $type): string
    {
        return match ($type) {
            'scholarship' => 'Beasiswa',
            'discount' => 'Diskon',
            'waiver' => 'Keringanan',
            'penalty' => 'Denda',
            'correction' => 'Koreksi',
            'write_off' => 'Penghapusan',
            default => $this->typeLabel($type),
        };
    }
}
