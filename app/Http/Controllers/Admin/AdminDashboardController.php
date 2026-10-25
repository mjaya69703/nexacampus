<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialHold;
use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\InvoiceSchedule;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\StudentScholarship;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\StudentComplaint;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\StudentService\StudentTransferRequest;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Dashboard operasional keuangan — read-only.
     *
     * Agregat penerimaan, tunggakan, aging, tren, metode bayar, antrean kerja
     * (jadwal jatuh tempo, hold aktif, pembayaran menunggu review).
     */
    public function financialDashboard(Request $request): Response
    {
        $stats = [
            'verifiedPayments' => (float) Payment::where('status', 'verified')->sum('amount'),
            'pendingPayments' => Payment::where('status', 'pending')->count(),
            'outstanding' => (float) StudentInvoice::whereNotIn('status', ['draft', 'cancelled', 'paid'])->sum('outstanding_amount'),
            'overdueInvoices' => StudentInvoice::where('status', 'overdue')->count(),
            'activeHolds' => FinancialHold::where('status', 'active')->count(),
            'dueSchedules' => InvoiceSchedule::where('status', 'pending')->where('is_active', true)->where('publish_at', '<=', now())->count(),
            'studentCredits' => (float) StudentCreditBalance::sum('balance'),
            'activeScholarships' => StudentScholarship::where('status', 'active')->count(),
            'netAdjustments' => (float) InvoiceAdjustment::sum('amount'),
        ];

        $statusRows = StudentInvoice::query()
            ->selectRaw('status, COUNT(*) as invoice_count, SUM(total_amount) as total_amount, SUM(outstanding_amount) as outstanding_amount')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $this->invoiceStatusLabel($row->status),
                'count' => (int) $row->invoice_count,
                'total' => (float) $row->total_amount,
                'outstanding' => (float) $row->outstanding_amount,
            ])
            ->values()
            ->all();

        $typeRows = StudentInvoice::query()
            ->selectRaw('invoice_type, COUNT(*) as invoice_count, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(outstanding_amount) as outstanding_amount')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy('invoice_type')
            ->orderByDesc('outstanding_amount')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'type' => $this->invoiceTypeLabel($row->invoice_type),
                'count' => (int) $row->invoice_count,
                'total' => (float) $row->total_amount,
                'paid' => (float) $row->paid_amount,
                'outstanding' => (float) $row->outstanding_amount,
            ])
            ->values()
            ->all();

        $totalBilled = collect($typeRows)->sum('total');
        $totalPaid = collect($typeRows)->sum('paid');
        $totalOutstanding = collect($typeRows)->sum('outstanding');

        return Inertia::render('Admin/Financial/Dashboard', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Dashboard Keuangan'),
            'can' => [
                'viewInvoices' => ActivePermission::check('student-invoice.viewAny'),
                'viewPayments' => ActivePermission::check('payment.viewAny'),
                'viewHolds' => ActivePermission::check('financial-hold.viewAny'),
                'runSchedule' => ActivePermission::check('invoice-schedule.update'),
            ],
            'stats' => $stats,
            'collection' => [
                'billed' => $totalBilled,
                'paid' => $totalPaid,
                'outstanding' => $totalOutstanding,
                'paidPercent' => $totalBilled > 0 ? min(100, round(($totalPaid / $totalBilled) * 100)) : 0,
                'outstandingPercent' => $totalBilled > 0 ? min(100, round(($totalOutstanding / $totalBilled) * 100)) : 0,
                'maxOutstanding' => max(1, (float) collect($typeRows)->max('outstanding')),
            ],
            'statusRows' => $statusRows,
            'typeRows' => $typeRows,
            'trend' => $this->revenueTrendRows(),
            'aging' => $this->agingRows(),
            'methods' => $this->paymentMethodRows(),
            'programs' => $this->programOutstandingRows(),
            'recentPayments' => Payment::query()
                ->with(['invoice:id,invoice_number', 'studentProfile.user'])
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'number' => $p->payment_number,
                    'invoice' => $p->invoice?->invoice_number,
                    'student' => $p->studentProfile?->user?->name,
                    'amount' => (float) $p->amount,
                    'status' => $p->status,
                    'url' => route('admin.financial.payments.show', $p),
                ])
                ->all(),
            'overdue' => StudentInvoice::query()
                ->with(['studentProfile.user'])
                ->where('status', 'overdue')
                ->where('outstanding_amount', '>', 0)
                ->orderBy('due_date')
                ->limit(6)
                ->get()
                ->map(fn ($inv) => [
                    'id' => $inv->id,
                    'number' => $inv->invoice_number,
                    'student' => $inv->studentProfile?->user?->name,
                    'outstanding' => (float) $inv->outstanding_amount,
                    'dueDate' => $inv->due_date?->format('d M Y'),
                    'url' => route('admin.financial.student-invoices.show', $inv),
                ])
                ->all(),
            'dueSchedules' => InvoiceSchedule::query()
                ->with('academicYear:id,name')
                ->where('status', 'pending')
                ->where('is_active', true)
                ->where('publish_at', '<=', now())
                ->orderBy('publish_at')
                ->limit(5)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'year' => $s->academicYear?->name,
                    'publishAt' => $s->publish_at?->format('d M Y H:i'),
                    'url' => route('admin.financial.invoice-schedules.edit', $s),
                ])
                ->all(),
            'holds' => FinancialHold::query()
                ->with(['studentProfile.user', 'invoice:id,invoice_number'])
                ->where('status', 'active')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'student' => $h->studentProfile?->user?->name,
                    'invoice' => $h->invoice?->invoice_number,
                    'holdType' => $h->hold_type,
                    'url' => route('admin.financial.financial-holds.show', $h),
                ])
                ->all(),
            'urls' => [
                'invoices' => route('admin.financial.student-invoices.index'),
                'payments' => route('admin.financial.payments.index'),
                'holds' => route('admin.financial.financial-holds.index'),
                'schedules' => route('admin.financial.invoice-schedules.index'),
                'reports' => route('admin.financial.financial-reports.index'),
            ],
        ]);
    }

    /**
     * Dashboard operasional layanan mahasiswa — antrean lintas layanan + SLA pengaduan.
     */
    public function studentServicesDashboard(Request $request): Response
    {
        $stats = [
            'pendingLetters' => ServiceLetterRequest::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved'])->count(),
            'pendingLeaves' => StudentLeaveApplication::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment', 'approved'])->count(),
            'pendingTransfers' => StudentTransferRequest::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment', 'approved'])->count(),
            'pendingGraduations' => GraduationApplication::whereIn('status', ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved'])->count(),
            'openComplaints' => StudentComplaint::whereNotIn('status', ['resolved', 'closed', 'rejected'])->count(),
            'overdueComplaints' => StudentComplaint::whereNotIn('status', ['resolved', 'closed', 'rejected'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];

        $letterStatuses = ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved'];
        $paymentStatuses = ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved_pending_payment', 'approved'];

        $letters = ServiceLetterRequest::with(['letterType', 'studentProfile.user'])
            ->whereIn('status', $letterStatuses)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ServiceLetterRequest $item) => [
                'id' => $item->id,
                'number' => $item->request_number,
                'student' => $item->studentProfile?->user?->name,
                'nim' => $item->studentProfile?->nim,
                'subtitle' => $item->letterType?->name ?? '-',
                'status' => $item->status,
                'statusLabel' => $this->serviceStatusLabel($item->status),
                'url' => route('admin.student-services.letter-requests.show', ['id' => $item->id]),
            ])
            ->all();

        $leaves = StudentLeaveApplication::with(['studentProfile.user', 'academicYear'])
            ->whereIn('status', $paymentStatuses)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (StudentLeaveApplication $item) => [
                'id' => $item->id,
                'number' => $item->application_number,
                'student' => $item->studentProfile?->user?->name,
                'nim' => $item->studentProfile?->nim,
                'subtitle' => $item->academicYear?->name ?? 'Tahun akademik belum diisi',
                'status' => $item->status,
                'statusLabel' => $this->serviceStatusLabel($item->status),
                'url' => route('admin.student-services.leave-applications.show', ['id' => $item->id]),
            ])
            ->all();

        $transfers = StudentTransferRequest::with(['studentProfile.user', 'fromStudyProgram', 'toStudyProgram'])
            ->whereIn('status', $paymentStatuses)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (StudentTransferRequest $item) => [
                'id' => $item->id,
                'number' => $item->request_number,
                'student' => $item->studentProfile?->user?->name,
                'nim' => $item->studentProfile?->nim,
                'subtitle' => ($item->fromStudyProgram?->name ?? '-').' -> '.($item->toStudyProgram?->name ?? '-'),
                'status' => $item->status,
                'statusLabel' => $this->serviceStatusLabel($item->status),
                'url' => route('admin.student-services.transfer-requests.show', ['id' => $item->id]),
            ])
            ->all();

        $graduations = GraduationApplication::with(['studentProfile.user', 'graduationBatch'])
            ->whereIn('status', $letterStatuses)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (GraduationApplication $item) => [
                'id' => $item->id,
                'number' => $item->application_number,
                'student' => $item->studentProfile?->user?->name,
                'nim' => $item->studentProfile?->nim,
                'subtitle' => $item->graduationBatch?->name ?? $item->graduation_period ?? '-',
                'status' => $item->status,
                'statusLabel' => $this->serviceStatusLabel($item->status),
                'url' => route('admin.student-services.graduation-applications.show', ['id' => $item->id]),
            ])
            ->all();

        $complaints = StudentComplaint::with(['studentProfile.user', 'category', 'assignedWorkUnit'])
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            ->orderByRaw('CASE WHEN due_at IS NOT NULL AND due_at < ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn (StudentComplaint $item) => [
                'id' => $item->id,
                'number' => $item->ticket_number,
                'subject' => $item->subject,
                'student' => $item->studentProfile?->user?->name ?? '-',
                'workUnit' => $item->assignedWorkUnit?->name ?? 'Belum diarahkan',
                'status' => $item->status,
                'statusLabel' => $this->serviceStatusLabel($item->status),
                'dueAt' => $item->due_at?->diffForHumans(),
                'isOverdue' => (bool) $item->due_at?->isPast(),
                'url' => route('admin.student-services.complaints.show', ['id' => $item->id]),
            ])
            ->all();

        return Inertia::render('Admin/StudentServices/Dashboard', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Dashboard Layanan'),
            'stats' => $stats,
            'queues' => [
                'letters' => ['title' => 'Antrian Pengajuan Surat', 'items' => $letters],
                'leaves' => ['title' => 'Antrian Pengajuan Cuti', 'items' => $leaves],
                'transfers' => ['title' => 'Antrian Pengajuan Pindah', 'items' => $transfers],
                'graduations' => ['title' => 'Antrian Pengajuan Yudisium', 'items' => $graduations],
            ],
            'complaints' => $complaints,
            'urls' => [
                'index' => route('admin.student-services.dashboard.index'),
                'complaints' => route('admin.student-services.complaints.index'),
            ],
        ]);
    }

    // ── Financial helpers ────────────────────────────────────────────────────

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    private function revenueTrendRows(): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));

        $payments = Payment::query()
            ->where('status', 'verified')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $months->first()->copy()->startOfMonth())
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at?->format('Y-m'));

        return $months
            ->map(fn (Carbon $month) => [
                'label' => $month->format('M Y'),
                'amount' => (float) ($payments->get($month->format('Y-m'), collect())->sum('amount')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    private function agingRows(): array
    {
        $buckets = [
            ['label' => 'Belum Jatuh Tempo', 'min' => null, 'max' => 0, 'amount' => 0.0],
            ['label' => '1-7 Hari', 'min' => 1, 'max' => 7, 'amount' => 0.0],
            ['label' => '8-30 Hari', 'min' => 8, 'max' => 30, 'amount' => 0.0],
            ['label' => '31-60 Hari', 'min' => 31, 'max' => 60, 'amount' => 0.0],
            ['label' => '> 60 Hari', 'min' => 61, 'max' => null, 'amount' => 0.0],
        ];

        StudentInvoice::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'paid'])
            ->where('outstanding_amount', '>', 0)
            ->get(['due_date', 'outstanding_amount'])
            ->each(function (StudentInvoice $invoice) use (&$buckets): void {
                $days = $invoice->due_date?->startOfDay()->diffInDays(now()->startOfDay(), false) ?? 0;

                foreach ($buckets as $index => $bucket) {
                    $min = $bucket['min'];
                    $max = $bucket['max'];

                    if (($min === null || $days >= $min) && ($max === null || $days <= $max)) {
                        $buckets[$index]['amount'] += (float) $invoice->outstanding_amount;

                        break;
                    }
                }
            });

        return $buckets;
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    private function paymentMethodRows(): array
    {
        return Payment::query()
            ->where('status', 'verified')
            ->selectRaw('payment_method, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (Payment $payment) => [
                'label' => $this->methodLabel($payment->payment_method),
                'amount' => (float) $payment->total_amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    private function programOutstandingRows(): array
    {
        return StudentInvoice::query()
            ->with('studentProfile.studyProgram')
            ->whereNotIn('status', ['draft', 'cancelled', 'paid'])
            ->where('outstanding_amount', '>', 0)
            ->get()
            ->groupBy(fn (StudentInvoice $invoice) => $invoice->studentProfile?->studyProgram?->name ?? 'Tanpa Prodi')
            ->map(fn (Collection $invoices, string $program) => [
                'label' => $program,
                'amount' => (float) $invoices->sum('outstanding_amount'),
            ])
            ->sortByDesc('amount')
            ->take(6)
            ->values()
            ->all();
    }

    private function invoiceStatusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'issued' => 'Aktif',
            'partially_paid' => 'Cicilan',
            'overdue' => 'Jatuh Tempo',
            'paid' => 'Lunas',
            'cancelled' => 'Dibatalkan',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    private function invoiceTypeLabel(?string $type): string
    {
        return match ($type) {
            'tuition' => 'SPP / Kuliah',
            'registration' => 'Pendaftaran',
            'exam' => 'Ujian Akhir',
            default => ucfirst(str_replace('_', ' ', (string) $type)),
        };
    }

    private function methodLabel(?string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'Transfer Bank',
            'virtual_account' => 'Virtual Account',
            'credit_card' => 'Kartu Kredit',
            'cash' => 'Tunai / Kasir',
            default => ucfirst(str_replace('_', ' ', (string) $method)),
        };
    }

    // ── Student service helpers ──────────────────────────────────────────────

    private function serviceStatusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Masuk',
            'in_approval' => 'Menunggu Approval',
            'under_review', 'in_review' => 'Direview',
            'revision_requested' => 'Perlu Perbaikan',
            'approved' => 'Approved',
            'approved_pending_payment' => 'Menunggu Bayar',
            'issued' => 'Terbit',
            'activated' => 'Aktif',
            'applied' => 'Diterapkan',
            'finalized' => 'Final',
            'waiting_student' => 'Menunggu Mahasiswa',
            'responded' => 'Dibalas',
            'reopened' => 'Dibuka Lagi',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }
}
