<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentInvoice;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan rekapitulasi keuangan — read-only (paritas Blade).
 *
 * Ekspor CSV/Excel/PDF ditangani FinancialReportExportController yang
 * sudah ada; halaman ini menyajikan ringkasan + tautan unduh.
 */
class FinancialReportController extends Controller
{
    public function index(Request $request): Response
    {
        $typeRows = StudentInvoice::query()
            ->selectRaw('invoice_type, COUNT(*) as invoice_count, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(outstanding_amount) as outstanding_amount')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy('invoice_type')
            ->orderBy('invoice_type')
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

        return Inertia::render('Admin/Financial/Report', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Laporan Keuangan'),
            'stats' => [
                'verifiedPayments' => (float) Payment::where('status', 'verified')->sum('amount'),
                'outstanding' => (float) StudentInvoice::whereNotIn('status', ['draft', 'cancelled', 'paid'])->sum('outstanding_amount'),
                'adjustments' => (float) InvoiceAdjustment::sum('amount'),
                'credits' => (float) StudentCreditBalance::sum('balance'),
            ],
            'typeRows' => $typeRows,
            'urls' => [
                'csv' => route('admin.financial.reports.export.csv'),
                'xlsx' => route('admin.financial.reports.export.xlsx'),
                'pdf' => route('admin.financial.reports.export.pdf'),
            ],
        ]);
    }

    private function invoiceTypeLabel(?string $type): string
    {
        return match ($type) {
            'tuition' => 'SPP / Uang Kuliah',
            'registration' => 'Biaya Pendaftaran',
            'exam' => 'Biaya Ujian Akhir',
            default => ucfirst(str_replace('_', ' ', (string) $type)),
        };
    }
}
