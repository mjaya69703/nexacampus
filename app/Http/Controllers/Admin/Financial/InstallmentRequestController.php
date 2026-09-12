<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Support\ActivePermission;
use App\Support\Financial\InstallmentApprovalService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Pengajuan cicilan — tinjau + setujui/tolak (paritas Blade).
 *
 * Pengajuan dibuat mahasiswa dari portal; approval penuh lewat
 * ApprovalEngine, admin mengeksekusi putusan dari halaman ini.
 */
class InstallmentRequestController extends Controller
{
    public const STATUSES = ['submitted', 'in_approval', 'approved', 'rejected', 'cancelled'];

    public function __construct(
        protected InstallmentApprovalService $approvals = new InstallmentApprovalService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'number' => 'nullable|string|max:100',
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = InvoiceInstallmentRequest::query()
            ->with([
                'invoice:id,invoice_number',
                'studentProfile.user:id,first_name,last_name',
                'studentProfile:id,nim,user_id',
            ]);

        if (filled($validated['number'] ?? null)) {
            $query->whereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', '%'.$validated['number'].'%'));
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

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $requests = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/InstallmentRequest/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Pengajuan Cicilan'),
            'can' => [
                'view' => ActivePermission::check('installment-request.view'),
                'review' => ActivePermission::check('installment-request.update'),
            ],
            'stats' => [
                'total' => InvoiceInstallmentRequest::count(),
                'submitted' => InvoiceInstallmentRequest::where('status', 'submitted')->count(),
                'approved' => InvoiceInstallmentRequest::where('status', 'approved')->count(),
            ],
            'data' => [
                'rows' => collect($requests->items())->values()->map(fn ($r, $i) => [
                    'id' => $r->id,
                    'no' => ($requests->firstItem() ?? 0) + $i,
                    'invoice' => $r->invoice?->invoice_number ?? '-',
                    'student' => $r->studentProfile?->user?->name ?? '-',
                    'nim' => $r->studentProfile?->nim,
                    'tenor' => (int) $r->requested_tenor.'× bayar',
                    'simulated' => $this->money($r->simulated_total_amount),
                    'status' => $r->status,
                    'statusTone' => $this->statusTone($r->status),
                    'statusText' => $this->statusText($r->status),
                    'at' => $r->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.financial.installment-requests.show', $r->id),
                ])->all(),
                'currentPage' => $requests->currentPage(),
                'lastPage' => $requests->lastPage(),
                'perPage' => $requests->perPage(),
                'total' => $requests->total(),
            ],
            'filters' => [
                'number' => $validated['number'] ?? '',
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.financial.installment-requests.index'),
                'export' => route('admin.financial.installment-requests.export'),
            ],
        ]);
    }

    public function show(Request $request, InvoiceInstallmentRequest $installmentRequest): Response
    {
        $installmentRequest->load([
            'invoice',
            'studentProfile.user',
            'studentProfile.studyProgram',
            'reviewedBy',
            'installments',
            'approvalRequest.steps.actedBy',
        ]);

        return Inertia::render('Admin/Financial/InstallmentRequest/Show', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Detail Pengajuan Cicilan'),
            'request' => [
                'id' => $installmentRequest->id,
                'invoice' => $installmentRequest->invoice?->invoice_number,
                'invoiceUrl' => $installmentRequest->invoice
                    ? route('admin.financial.student-invoices.show', $installmentRequest->invoice)
                    : null,
                'student' => $installmentRequest->studentProfile?->user?->name ?? '-',
                'nim' => $installmentRequest->studentProfile?->nim,
                'program' => $installmentRequest->studentProfile?->studyProgram?->name,
                'tenor' => (int) $installmentRequest->requested_tenor,
                'fee' => $this->money($installmentRequest->requested_fee_amount),
                'simulated' => $this->money($installmentRequest->simulated_total_amount),
                'simulation' => collect($installmentRequest->simulation_snapshot['installments'] ?? [])->map(fn ($row) => [
                    'no' => $row['installment_no'] ?? '-',
                    'amount' => $this->money($row['amount'] ?? 0),
                    'fee' => $this->money($row['fee_amount'] ?? 0),
                    'dueDate' => $row['due_date'] ?? '-',
                ])->values()->all(),
                'reason' => $installmentRequest->student_reason,
                'financeNotes' => $installmentRequest->finance_notes,
                'status' => $installmentRequest->status,
                'statusTone' => $this->statusTone($installmentRequest->status),
                'statusText' => $this->statusText($installmentRequest->status),
                'reviewedBy' => $installmentRequest->reviewedBy?->name,
                'reviewedAt' => $installmentRequest->reviewed_at?->format('d M Y H:i'),
                'at' => $installmentRequest->created_at?->format('d M Y H:i'),
                'isReviewable' => in_array($installmentRequest->status, ['submitted', 'in_approval'], true),
                'installments' => $installmentRequest->installments->sortBy('installment_no')->values()->map(fn ($inst) => [
                    'no' => $inst->installment_no,
                    'amount' => $this->money($inst->amount),
                    'dueDate' => $inst->due_date?->format('d M Y'),
                    'status' => $inst->status,
                ])->all(),
                'approvalSteps' => $installmentRequest->approvalRequest?->steps->map(fn ($step) => [
                    'name' => $step->name ?? 'Tahap',
                    'status' => $step->status,
                    'by' => $step->actedBy?->name,
                    'at' => $step->acted_at?->format('d M Y H:i'),
                ])->values()->all() ?? [],
            ],
            'can' => [
                'review' => ActivePermission::check('installment-request.update'),
            ],
            'urls' => [
                'index' => route('admin.financial.installment-requests.index'),
                'approve' => route('admin.financial.installment-requests.approve', $installmentRequest),
                'reject' => route('admin.financial.installment-requests.reject', $installmentRequest),
            ],
        ]);
    }

    public function approve(Request $request, InvoiceInstallmentRequest $installmentRequest)
    {
        $validated = $request->validate([
            'finance_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->approvals->approve($installmentRequest, $request->user()->id, $validated['finance_notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengajuan cicilan disetujui.');
    }

    public function reject(Request $request, InvoiceInstallmentRequest $installmentRequest)
    {
        $validated = $request->validate([
            'finance_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->approvals->reject($installmentRequest, $request->user()->id, $validated['finance_notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengajuan cicilan ditolak.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = InvoiceInstallmentRequest::query()
            ->with(['invoice', 'studentProfile.user']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Invoice', 'Mahasiswa', 'NIM', 'Tenor', 'Simulasi Total', 'Status', 'Diajukan']], null, 'A1');

        foreach ($rows as $i => $installmentRequest) {
            $sheet->fromArray([
                $installmentRequest->invoice?->invoice_number,
                $installmentRequest->studentProfile?->user?->name,
                $installmentRequest->studentProfile?->nim,
                (int) $installmentRequest->requested_tenor,
                (float) $installmentRequest->simulated_total_amount,
                $installmentRequest->status,
                $installmentRequest->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'installment-requests-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'approved' => 'green',
            'submitted', 'in_approval' => 'amber',
            'rejected' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    private function statusText(?string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'submitted' => 'Menunggu Review',
            'in_approval' => 'Proses Approval',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
