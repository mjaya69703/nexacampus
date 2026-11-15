<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\InvoiceAdjustment;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Log penyesuaian tagihan — read-only (paritas Blade).
 *
 * Penyesuaian dibuat dari halaman detail tagihan; halaman ini hanya
 * mencatat riwayatnya dengan tautan ke invoice terkait.
 */
class InvoiceAdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'number' => 'nullable|string|max:100',
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:30',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = InvoiceAdjustment::query()
            ->with(['invoice.studentProfile.user', 'createdBy:id,first_name,last_name']);

        if (filled($validated['number'] ?? null)) {
            $query->whereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', '%'.$validated['number'].'%'));
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('invoice.studentProfile.user', fn ($u) => $u
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('adjustment_type', $validated['type']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $adjustments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/InvoiceAdjustment/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Penyesuaian Tagihan'),
            'can' => [
                'viewInvoice' => ActivePermission::check('student-invoice.view'),
            ],
            'stats' => [
                'total' => InvoiceAdjustment::count(),
                'discount' => (float) InvoiceAdjustment::where('amount', '<', 0)->sum('amount'),
                'penalty' => (float) InvoiceAdjustment::where('amount', '>', 0)->sum('amount'),
            ],
            'data' => [
                'rows' => collect($adjustments->items())->values()->map(fn ($adj, $i) => [
                    'id' => $adj->id,
                    'no' => ($adjustments->firstItem() ?? 0) + $i,
                    'invoice' => $adj->invoice?->invoice_number ?? '-',
                    'student' => $adj->invoice?->studentProfile?->user?->name ?? '-',
                    'type' => $adj->adjustment_type,
                    'typeText' => $this->typeLabel($adj->adjustment_type),
                    'amount' => $this->signedMoney($adj->amount),
                    'by' => $adj->createdBy?->name ?? '-',
                    'at' => $adj->created_at?->format('d M Y H:i'),
                    'invoiceUrl' => $adj->invoice
                        ? route('admin.financial.student-invoices.show', $adj->invoice)
                        : null,
                ])->all(),
                'currentPage' => $adjustments->currentPage(),
                'lastPage' => $adjustments->lastPage(),
                'perPage' => $adjustments->perPage(),
                'total' => $adjustments->total(),
            ],
            'filters' => [
                'number' => $validated['number'] ?? '',
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'types' => array_keys(config('financial.adjustment_types', [])),
            'urls' => [
                'index' => route('admin.financial.invoice-adjustments.index'),
                'export' => route('admin.financial.invoice-adjustments.export'),
                'exportPdf' => route('admin.financial.exports.pdf', ['resource' => 'invoice-adjustments']),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = InvoiceAdjustment::query()
            ->with(['invoice.studentProfile.user', 'createdBy']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Invoice', 'Mahasiswa', 'Jenis', 'Nominal', 'Alasan', 'Oleh', 'Waktu']], null, 'A1');

        foreach ($rows as $i => $adjustment) {
            $sheet->fromArray([
                $adjustment->invoice?->invoice_number,
                $adjustment->invoice?->studentProfile?->user?->name,
                $adjustment->adjustment_type,
                (float) $adjustment->amount,
                $adjustment->reason,
                $adjustment->createdBy?->name,
                $adjustment->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'invoice-adjustments-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'correction' => 'Koreksi Tagihan',
            'discount' => 'Potongan (Discount)',
            'scholarship' => 'Potongan Beasiswa',
            'waiver' => 'Pembebasan (Waiver)',
            'penalty' => 'Denda / Sanksi',
            'write_off' => 'Penghapusan (Write Off)',
            default => str((string) $type)->replace('_', ' ')->title()->toString(),
        };
    }

    private function signedMoney(float|string|null $amount): string
    {
        $amount = (float) $amount;
        $prefix = $amount < 0 ? '-Rp ' : 'Rp ';

        return $prefix.number_format(abs($amount), 0, ',', '.');
    }
}
