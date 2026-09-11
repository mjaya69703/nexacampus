<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\Payment;
use App\Support\ActivePermission;
use App\Support\Financial\PaymentProcessingService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Pembayaran mahasiswa — read-only + verifikasi (paritas Blade).
 *
 * Tidak ada create/edit/delete manual: pembayaran masuk lewat portal
 * mahasiswa, admin hanya verifikasi / tolak + cetak kwitansi.
 */
class PaymentController extends Controller
{
    public const STATUSES = ['pending', 'verified', 'rejected', 'failed'];
    public const METHODS = ['bank_transfer', 'virtual_account', 'credit_card', 'cash'];

    public function __construct(
        protected PaymentProcessingService $payments = new PaymentProcessingService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'number' => 'nullable|string|max:100',
            'invoice' => 'nullable|string|max:100',
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'method' => 'nullable|string|max:30',
            'status' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at,amount,paid_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = Payment::query()
            ->with([
                'invoice:id,invoice_number',
                'studentProfile.user:id,first_name,last_name',
                'studentProfile:id,nim,user_id',
                'installment:id,installment_no',
            ]);

        if (filled($validated['number'] ?? null)) {
            $query->where('payment_number', 'like', '%'.$validated['number'].'%');
        }

        if (filled($validated['invoice'] ?? null)) {
            $query->whereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', '%'.$validated['invoice'].'%'));
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('studentProfile.user', fn ($u) => $u
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($validated['nim'] ?? null)) {
            $query->whereHas('studentProfile', fn ($s) => $s->where('nim', 'like', '%'.$validated['nim'].'%'));
        }

        foreach (['payment_method' => 'method', 'status' => 'status'] as $column => $key) {
            if (filled($validated[$key] ?? null)) {
                $query->where($column, $validated[$key]);
            }
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $payments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/Payment/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Pembayaran Mahasiswa'),
            'can' => [
                'view' => ActivePermission::check('payment.view'),
                'verify' => ActivePermission::check('payment.update'),
            ],
            'stats' => [
                'total' => Payment::count(),
                'pending' => Payment::where('status', 'pending')->count(),
                'verified' => Payment::where('status', 'verified')->count(),
                'verifiedAmount' => (float) Payment::where('status', 'verified')->sum('amount'),
            ],
            'data' => [
                'rows' => collect($payments->items())->values()->map(fn ($p, $i) => [
                    'id' => $p->id,
                    'no' => ($payments->firstItem() ?? 0) + $i,
                    'number' => $p->payment_number,
                    'invoice' => $p->invoice?->invoice_number ?? '-',
                    'student' => $p->studentProfile?->user?->name ?? '-',
                    'nim' => $p->studentProfile?->nim,
                    'target' => $p->installment ? 'Cicilan ke-'.$p->installment->installment_no : 'Tagihan utama',
                    'amount' => $this->money($p->amount),
                    'method' => $p->payment_method,
                    'methodText' => $this->methodLabel($p->payment_method),
                    'status' => $p->status,
                    'statusTone' => $this->statusTone($p->status),
                    'statusText' => $this->statusText($p->status),
                    'paidAt' => $p->paid_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.financial.payments.show', $p->id),
                ])->all(),
                'currentPage' => $payments->currentPage(),
                'lastPage' => $payments->lastPage(),
                'perPage' => $payments->perPage(),
                'total' => $payments->total(),
            ],
            'filters' => [
                'number' => $validated['number'] ?? '',
                'invoice' => $validated['invoice'] ?? '',
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'method' => $validated['method'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'methods' => self::METHODS,
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.financial.payments.index'),
                'export' => route('admin.financial.payments.export'),
            ],
        ]);
    }

    public function show(Request $request, Payment $payment): Response
    {
        $payment->load([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'installment',
            'creditTransactions',
            'submittedBy',
            'verifiedBy',
        ]);

        $proofUrl = $payment->proof_file_path
            ? Storage::disk('public')->url($payment->proof_file_path)
            : null;

        $extension = $payment->proof_file_path
            ? strtolower(pathinfo($payment->proof_file_path, PATHINFO_EXTENSION))
            : null;

        return Inertia::render('Admin/Financial/Payment/Show', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Detail Pembayaran'),
            'payment' => [
                'id' => $payment->id,
                'number' => $payment->payment_number,
                'invoice' => $payment->invoice?->invoice_number,
                'invoiceUrl' => $payment->invoice
                    ? route('admin.financial.student-invoices.show', $payment->invoice)
                    : null,
                'student' => $payment->studentProfile?->user?->name ?? '-',
                'nim' => $payment->studentProfile?->nim,
                'program' => $payment->studentProfile?->studyProgram?->name,
                'target' => $payment->installment ? 'Cicilan ke-'.$payment->installment->installment_no : 'Tagihan utama',
                'amount' => $this->money($payment->amount),
                'method' => $payment->payment_method,
                'methodText' => $this->methodLabel($payment->payment_method),
                'reference' => $payment->transaction_reference,
                'status' => $payment->status,
                'statusTone' => $this->statusTone($payment->status),
                'statusText' => $this->statusText($payment->status),
                'paidAt' => $payment->paid_at?->format('d M Y H:i'),
                'submittedBy' => $payment->submittedBy?->name,
                'verifiedAt' => $payment->verified_at?->format('d M Y H:i'),
                'verifiedBy' => $payment->verifiedBy?->name,
                'notes' => $payment->notes,
                'verificationNotes' => $payment->verification_notes,
                'proofUrl' => $proofUrl,
                'proofIsImage' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
                'proofIsPdf' => $extension === 'pdf',
                'isPending' => $payment->status === 'pending',
                'receiptUrl' => $payment->status === 'verified'
                    ? route('admin.financial.payments.receipt', $payment)
                    : null,
            ],
            'can' => [
                'verify' => ActivePermission::check('payment.update'),
            ],
            'urls' => [
                'index' => route('admin.financial.payments.index'),
                'verify' => route('admin.financial.payments.verify', $payment),
                'reject' => route('admin.financial.payments.reject', $payment),
            ],
        ]);
    }

    public function verify(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'verification_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->payments->verify($payment, $request->user()->id, $validated['verification_notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pembayaran berhasil diverifikasi.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'verification_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->payments->reject($payment, $request->user()->id, $validated['verification_notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pembayaran ditolak.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Payment::query()
            ->with(['invoice', 'studentProfile.user', 'installment']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nomor Bayar', 'Invoice', 'Mahasiswa', 'NIM', 'Target', 'Nominal', 'Metode', 'Status', 'Waktu Bayar']], null, 'A1');

        foreach ($rows as $i => $payment) {
            $sheet->fromArray([
                $payment->payment_number,
                $payment->invoice?->invoice_number,
                $payment->studentProfile?->user?->name,
                $payment->studentProfile?->nim,
                $payment->installment ? 'Cicilan ke-'.$payment->installment->installment_no : 'Tagihan utama',
                (float) $payment->amount,
                $payment->payment_method,
                $payment->status,
                $payment->paid_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'payments-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function methodLabel(?string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'Transfer Bank',
            'virtual_account' => 'Virtual Account (VA)',
            'credit_card' => 'Kartu Kredit',
            'cash' => 'Tunai / Kasir',
            default => str((string) $method)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'verified' => 'green',
            'pending' => 'amber',
            'rejected', 'failed' => 'red',
            default => 'gray',
        };
    }

    private function statusText(?string $status): string
    {
        return match ($status) {
            'verified' => 'Terverifikasi',
            'pending' => 'Menunggu Review',
            'rejected' => 'Ditolak',
            'failed' => 'Gagal',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
