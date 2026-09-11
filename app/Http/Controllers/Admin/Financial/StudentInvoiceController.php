<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\StudentScholarship;
use App\Support\ActivePermission;
use App\Support\Financial\InvoiceAdjustmentService;
use App\Support\Financial\InvoiceGenerationService;
use App\Support\Financial\InvoicePublishingService;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Financial\ScholarshipApplicationService;
use App\Support\Inertia\ShellProps;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CRUD tagihan mahasiswa — memakai kit CRUD shared.
 *
 * Paritas Blade lama: refresh overdue saat daftar dibuka, terbitkan
 * (draft→issued), edit hanya bila isEditable, batal = status cancelled
 * (bukan hapus baris), penyesuaian + beasiswa dari halaman detail,
 * cetak PDF invoice.
 */
class StudentInvoiceController extends Controller
{
    public const STATUSES = ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled'];
    public const KINDS = ['tuition', 'custom'];
    public const MODES = ['single', 'selected_students', 'active_students'];

    public function __construct(
        protected InvoiceStatusService $statuses = new InvoiceStatusService,
        protected InvoicePublishingService $publishing = new InvoicePublishingService,
        protected InvoiceGenerationService $generation = new InvoiceGenerationService,
        protected InvoiceAdjustmentService $adjustments = new InvoiceAdjustmentService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'number' => 'nullable|string|max:100',
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'year' => 'nullable|integer|exists:academic_years,id',
            'status' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at,total_amount,due_date',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentInvoice::query()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name',
                'studentProfile:id,nim,user_id,study_program_id',
                'academicYear:id,name',
            ]);

        if (filled($validated['number'] ?? null)) {
            $query->where('invoice_number', 'like', '%'.$validated['number'].'%');
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

        if (filled($validated['type'] ?? null)) {
            $query->where('invoice_type', $validated['type']);
        }

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $invoices = $query->paginate($perPage)->withQueryString();

        // Paritas Blade: segarkan status overdue untuk baris yang tampil.
        foreach ($invoices->items() as $invoice) {
            if (in_array($invoice->status, ['issued', 'partially_paid'], true)
                && $invoice->due_date?->isPast()) {
                $this->statuses->refresh($invoice);
            }
        }

        return Inertia::render('Admin/Financial/StudentInvoice/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Tagihan Mahasiswa'),
            'can' => [
                'create' => ActivePermission::check('student-invoice.create'),
                'update' => ActivePermission::check('student-invoice.update'),
                'delete' => ActivePermission::check('student-invoice.delete'),
                'view' => ActivePermission::check('student-invoice.view'),
            ],
            'stats' => [
                'total' => StudentInvoice::count(),
                'draft' => StudentInvoice::where('status', 'draft')->count(),
                'issued' => StudentInvoice::where('status', 'issued')->count(),
                'overdue' => StudentInvoice::where('status', 'overdue')->count(),
                'outstanding' => (float) StudentInvoice::whereNotIn('status', ['paid', 'cancelled'])->sum('outstanding_amount'),
            ],
            'data' => [
                'rows' => collect($invoices->items())->values()->map(fn ($inv, $i) => $this->rowPayload($inv, ($invoices->firstItem() ?? 0) + $i))->all(),
                'currentPage' => $invoices->currentPage(),
                'lastPage' => $invoices->lastPage(),
                'perPage' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
            'filters' => [
                'number' => $validated['number'] ?? '',
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'type' => $validated['type'] ?? '',
                'year' => $validated['year'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'types' => array_keys(config('financial.invoice_types', [])),
            'statuses' => self::STATUSES,
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'urls' => [
                'index' => route('admin.financial.student-invoices.index'),
                'create' => route('admin.financial.student-invoices.create'),
                'store' => route('admin.financial.student-invoices.store'),
                'export' => route('admin.financial.student-invoices.export'),
                'bulkCancel' => route('admin.financial.student-invoices.bulk-cancel'),
            ],
        ]);
    }

    public function show(Request $request, StudentInvoice $studentInvoice): Response
    {
        $this->statuses->refresh($studentInvoice->fresh());

        $invoice = $studentInvoice->fresh()->load([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'academicYear',
            'items',
            'adjustments.createdBy',
            'payments.verifiedBy',
            'installments',
            'installmentRequests.reviewedBy',
            'issuedBy',
            'cancelledBy',
        ]);

        return Inertia::render('Admin/Financial/StudentInvoice/Show', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Detail Tagihan'),
            'invoice' => $this->detailPayload($invoice),
            'adjustmentTypes' => $this->adjustments->types(),
            'scholarshipOptions' => $this->scholarshipOptions($invoice),
            'can' => [
                'update' => ActivePermission::check('student-invoice.update'),
                'cancel' => ActivePermission::check('student-invoice.delete'),
            ],
            'urls' => [
                'index' => route('admin.financial.student-invoices.index'),
                'edit' => route('admin.financial.student-invoices.edit', $invoice),
                'issue' => route('admin.financial.student-invoices.issue', $invoice),
                'cancel' => route('admin.financial.student-invoices.cancel', $invoice),
                'pdf' => route('admin.financial.student-invoices.pdf', $invoice),
                'adjust' => route('admin.financial.student-invoices.adjust', $invoice),
                'applyScholarship' => route('admin.financial.student-invoices.apply-scholarship', $invoice),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Financial/StudentInvoice/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Terbitkan Tagihan'),
            'mode' => 'create',
            'form' => [
                'invoice_kind' => 'tuition',
                'generation_mode' => 'single',
                'student_profile_id' => '',
                'academic_year_id' => '',
                'semester' => '1',
                'due_date' => '',
                'invoice_type' => 'tuition',
                'notes' => '',
                'issue_immediately' => true,
            ],
            'items' => [['item_type' => 'fee', 'description' => '', 'amount' => '']],
            'selectedStudents' => [],
            'years' => $this->yearOptions(),
            'invoiceTypes' => $this->invoiceTypeOptions(),
            'urls' => [
                'index' => route('admin.financial.student-invoices.index'),
                'submit' => route('admin.financial.student-invoices.store'),
                'searchStudents' => route('admin.financial.student-invoices.search-students'),
            ],
        ]);
    }

    public function store(Request $request, InvoiceGenerationService $generation)
    {
        $validated = $request->validate($this->createRules($request));

        $academicYear = filled($validated['academic_year_id'] ?? null)
            ? AcademicYear::find($validated['academic_year_id'])
            : null;

        $students = $this->studentsForGeneration($validated, $request->input('student_profile_ids', []));
        $created = 0;
        $errors = [];

        if ($students->isEmpty()) {
            return back()->with('error', 'Tidak ada mahasiswa aktif yang cocok dengan target invoice.');
        }

        foreach ($students as $student) {
            try {
                if ($validated['invoice_kind'] === 'tuition') {
                    $generation->generateForStudent(
                        $student,
                        $academicYear,
                        (int) $validated['semester'],
                        $validated['due_date'] ?? null,
                        $request->user()->id,
                        (bool) $validated['issue_immediately'],
                    );
                } else {
                    $generation->createCustomInvoice(
                        $student,
                        $this->cleanItems($request->input('items', [])),
                        $validated['invoice_type'],
                        $validated['due_date'],
                        $academicYear,
                        filled($validated['semester'] ?? null) ? (int) $validated['semester'] : null,
                        ($validated['notes'] ?? null) ?: null,
                        $request->user()->id,
                        (bool) $validated['issue_immediately'],
                    );
                }

                $created++;
            } catch (Throwable $exception) {
                $errors[] = ($student->nim ?? $student->id).': '.$exception->getMessage();
            }
        }

        if ($created > 0 && empty($errors)) {
            return redirect()->route('admin.financial.student-invoices.index')
                ->with('success', $created.' invoice tagihan berhasil diterbitkan.');
        }

        $message = $created > 0 ? $created.' invoice terbit. ' : '';
        $message .= implode(' ', array_slice($errors, 0, 5));

        return redirect()->route('admin.financial.student-invoices.index')
            ->with($created > 0 ? 'success' : 'error', $message);
    }

    public function edit(Request $request, StudentInvoice $studentInvoice): Response
    {
        abort_unless($studentInvoice->isEditable(), 403);

        $studentInvoice->load(['items', 'studentProfile.user']);

        return Inertia::render('Admin/Financial/StudentInvoice/Edit', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Ubah Tagihan'),
            'invoice' => [
                'id' => $studentInvoice->id,
                'number' => $studentInvoice->invoice_number,
                'student' => $studentInvoice->studentProfile?->user?->name,
                'nim' => $studentInvoice->studentProfile?->nim,
            ],
            'form' => [
                'academic_year_id' => $studentInvoice->academic_year_id ? (string) $studentInvoice->academic_year_id : '',
                'semester' => $studentInvoice->semester !== null ? (string) $studentInvoice->semester : '',
                'invoice_type' => $studentInvoice->invoice_type,
                'due_date' => $studentInvoice->due_date?->format('Y-m-d'),
                'notes' => $studentInvoice->notes,
            ],
            'items' => $studentInvoice->items->sortBy('sort_order')->values()->map(fn ($item) => [
                'item_type' => in_array($item->item_type, ['discount', 'adjustment', 'penalty'], true) ? $item->item_type : 'fee',
                'description' => $item->description,
                'amount' => (string) abs((float) $item->amount),
            ])->all() ?: [['item_type' => 'fee', 'description' => '', 'amount' => '']],
            'years' => $this->yearOptions(),
            'invoiceTypes' => $this->invoiceTypeOptions(),
            'urls' => [
                'index' => route('admin.financial.student-invoices.index'),
                'show' => route('admin.financial.student-invoices.show', $studentInvoice),
                'submit' => route('admin.financial.student-invoices.update', $studentInvoice),
            ],
        ]);
    }

    public function update(Request $request, StudentInvoice $studentInvoice)
    {
        abort_unless($studentInvoice->fresh()->isEditable(), 403);

        $validated = $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'invoice_type' => ['required', Rule::in(array_keys(config('financial.invoice_types', [])))],
            'due_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:fee,discount,adjustment,penalty',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
        ]);

        $studentInvoice->update([
            'academic_year_id' => filled($validated['academic_year_id'] ?? null) ? $validated['academic_year_id'] : null,
            'semester' => filled($validated['semester'] ?? null) ? (int) $validated['semester'] : null,
            'invoice_type' => $validated['invoice_type'],
            'due_date' => $validated['due_date'],
            'notes' => ($validated['notes'] ?? null) ?: null,
            'updated_by' => $request->user()->id,
        ]);

        $studentInvoice->items()->delete();

        foreach (array_values($validated['items']) as $index => $item) {
            $amount = (float) $item['amount'];

            $studentInvoice->items()->create([
                'item_type' => $item['item_type'],
                'description' => $item['description'],
                'amount' => $item['item_type'] === 'discount' ? -abs($amount) : $amount,
                'sort_order' => $index + 1,
            ]);
        }

        $this->statuses->refresh($studentInvoice);

        return redirect()->route('admin.financial.student-invoices.show', $studentInvoice)
            ->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function issue(Request $request, StudentInvoice $studentInvoice)
    {
        try {
            $this->publishing->issue($studentInvoice, $request->user()->id);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Tagihan berhasil diterbitkan ke mahasiswa.');
    }

    public function cancel(Request $request, StudentInvoice $studentInvoice)
    {
        $studentInvoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Tagihan dibatalkan dan tidak dihitung sebagai tunggakan.');
    }

    public function bulkCancel(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_invoices,id',
        ]);

        StudentInvoice::whereIn('id', $validated['ids'])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->each(fn ($invoice) => $invoice->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]));

        return back()->with('success', count($validated['ids']).' tagihan dibatalkan.');
    }

    public function adjust(Request $request, StudentInvoice $studentInvoice)
    {
        $validated = $request->validate([
            'adjustment_type' => ['required', Rule::in(array_keys($this->adjustments->types()))],
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $this->adjustments->apply(
                invoice: $studentInvoice,
                type: $validated['adjustment_type'],
                amount: (float) $validated['amount'],
                reason: $validated['reason'] ?: null,
                createdBy: $request->user()->id,
            );
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Penyesuaian tagihan berhasil diterapkan.');
    }

    public function applyScholarship(Request $request, StudentInvoice $studentInvoice, ScholarshipApplicationService $scholarships)
    {
        $validated = $request->validate([
            'scholarship_assignment_id' => 'required|exists:student_scholarships,id',
        ]);

        try {
            $assignment = StudentScholarship::with('scholarship')->findOrFail($validated['scholarship_assignment_id']);
            $scholarships->applyAssignmentToInvoice($assignment, $studentInvoice, $request->user()->id);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Alokasi beasiswa diterapkan ke tagihan ini.');
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = StudentProfile::query()
            ->with(['user:id,first_name,last_name', 'studyProgram:id,name'])
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query
                ->where('nim', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('nim')
            ->limit(25)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => ($s->nim ?? '-').' - '.$s->user?->name.' ('.$s->studyProgram?->name.')',
            ])
            ->all();

        return response()->json(['options' => $students]);
    }

    public function pdf(StudentInvoice $studentInvoice)
    {
        $studentInvoice->load([
            'studentProfile.user', 'studentProfile.studyProgram', 'academicYear',
            'items', 'adjustments', 'payments', 'issuedBy',
        ]);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.financial.student-invoice', [
            'invoice' => $studentInvoice,
        ])->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$studentInvoice->invoice_number.'.pdf"',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudentInvoice::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nomor', 'Mahasiswa', 'NIM', 'Prodi', 'Tahun', 'Semester', 'Jenis', 'Total', 'Terbayar', 'Sisa', 'Status', 'Jatuh Tempo']], null, 'A1');

        foreach ($rows as $i => $invoice) {
            $sheet->fromArray([
                $invoice->invoice_number,
                $invoice->studentProfile?->user?->name,
                $invoice->studentProfile?->nim,
                $invoice->studentProfile?->studyProgram?->name,
                $invoice->academicYear?->name,
                $invoice->semester,
                $invoice->invoice_type,
                (float) $invoice->total_amount,
                (float) $invoice->paid_amount,
                (float) $invoice->outstanding_amount,
                $invoice->status,
                $invoice->due_date?->format('Y-m-d'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'student-invoices-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function rowPayload(StudentInvoice $invoice, int $no): array
    {
        return [
            'id' => $invoice->id,
            'no' => $no,
            'number' => $invoice->invoice_number,
            'type' => $invoice->invoice_type,
            'typeText' => $this->typeLabel($invoice->invoice_type),
            'student' => $invoice->studentProfile?->user?->name ?? '-',
            'nim' => $invoice->studentProfile?->nim,
            'program' => $invoice->studentProfile?->studyProgram?->name,
            'year' => $invoice->academicYear?->name,
            'semester' => $invoice->semester,
            'total' => $this->money($invoice->total_amount),
            'outstanding' => $this->money($invoice->outstanding_amount),
            'status' => $invoice->status,
            'statusTone' => $this->statusTone($invoice->status),
            'statusText' => $this->statusText($invoice->status),
            'dueDate' => $invoice->due_date?->format('d M Y'),
            'isEditable' => $invoice->isEditable(),
            'showUrl' => route('admin.financial.student-invoices.show', $invoice),
            'editUrl' => route('admin.financial.student-invoices.edit', $invoice),
            'issueUrl' => route('admin.financial.student-invoices.issue', $invoice),
            'cancelUrl' => route('admin.financial.student-invoices.cancel', $invoice),
            'pdfUrl' => route('admin.financial.student-invoices.pdf', $invoice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(StudentInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'type' => $invoice->invoice_type,
            'typeText' => $this->typeLabel($invoice->invoice_type),
            'student' => $invoice->studentProfile?->user?->name ?? '-',
            'nim' => $invoice->studentProfile?->nim,
            'program' => $invoice->studentProfile?->studyProgram?->name,
            'year' => $invoice->academicYear?->name,
            'semester' => $invoice->semester,
            'status' => $invoice->status,
            'statusTone' => $this->statusTone($invoice->status),
            'statusText' => $this->statusText($invoice->status),
            'total' => $this->money($invoice->total_amount),
            'paid' => $this->money($invoice->paid_amount),
            'outstanding' => $this->money($invoice->outstanding_amount),
            'dueDate' => $invoice->due_date?->format('d M Y'),
            'issuedAt' => $invoice->issued_at?->format('d M Y H:i'),
            'issuedBy' => $invoice->issuedBy?->name,
            'cancelledAt' => $invoice->cancelled_at?->format('d M Y H:i'),
            'cancelledBy' => $invoice->cancelledBy?->name,
            'notes' => $invoice->notes,
            'isEditable' => $invoice->isEditable(),
            'items' => $invoice->items->sortBy('sort_order')->values()->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->item_type,
                'description' => $item->description,
                'amount' => $this->money($item->amount),
                'rawAmount' => (float) $item->amount,
            ])->all(),
            'adjustments' => $invoice->adjustments->sortByDesc('created_at')->values()->map(fn ($adj) => [
                'id' => $adj->id,
                'type' => $adj->adjustment_type,
                'amount' => $this->money($adj->amount),
                'reason' => $adj->reason,
                'by' => $adj->createdBy?->name,
                'at' => $adj->created_at?->format('d M Y H:i'),
            ])->all(),
            'payments' => $invoice->payments->sortByDesc('created_at')->values()->map(fn ($pay) => [
                'id' => $pay->id,
                'number' => $pay->payment_number,
                'amount' => $this->money($pay->amount),
                'method' => $pay->payment_method,
                'status' => $pay->status,
                'at' => $pay->paid_at?->format('d M Y H:i') ?? $pay->created_at?->format('d M Y H:i'),
                'verifiedBy' => $pay->verifiedBy?->name,
            ])->all(),
            'installments' => $invoice->installments->sortBy('due_date')->values()->map(fn ($inst) => [
                'id' => $inst->id,
                'amount' => $this->money($inst->amount),
                'dueDate' => $inst->due_date?->format('d M Y'),
                'status' => $inst->status,
            ])->all(),
            'installmentRequests' => $invoice->installmentRequests->sortByDesc('created_at')->values()->map(fn ($req) => [
                'id' => $req->id,
                'status' => $req->status,
                'reviewedBy' => $req->reviewedBy?->name,
                'at' => $req->created_at?->format('d M Y H:i'),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRules(Request $request): array
    {
        $isTuition = ($request->input('invoice_kind') ?? 'tuition') === 'tuition';
        $invoiceType = $isTuition ? 'tuition' : ($request->input('invoice_type') ?? 'custom');
        $academicYearRule = in_array($invoiceType, ['tuition', 'registration', 'exam'], true)
            ? 'required|exists:academic_years,id'
            : 'nullable|exists:academic_years,id';

        $rules = [
            'invoice_kind' => 'required|in:tuition,custom',
            'generation_mode' => 'required|in:single,selected_students,active_students',
            'student_profile_id' => 'required_if:generation_mode,single|nullable|exists:student_profiles,id',
            'student_profile_ids' => 'required_if:generation_mode,selected_students|nullable|array',
            'student_profile_ids.*' => 'exists:student_profiles,id',
            'academic_year_id' => $academicYearRule,
            'semester' => 'required_if:invoice_kind,tuition|nullable|integer|min:1|max:14',
            'due_date' => 'required_if:invoice_kind,custom|nullable|date',
            'invoice_type' => ['required', Rule::in(array_keys(config('financial.invoice_types', [])))],
            'notes' => 'nullable|string|max:1000',
            'issue_immediately' => 'required|boolean',
        ];

        if (! $isTuition) {
            $rules += [
                'items' => 'required|array|min:1',
                'items.*.item_type' => 'required|in:fee,discount,adjustment,penalty',
                'items.*.description' => 'required|string|max:255',
                'items.*.amount' => 'required|numeric|min:0.01',
            ];
        }

        return $rules;
    }

    /**
     * @return \Illuminate\Support\Collection<int, StudentProfile>
     */
    private function studentsForGeneration(array $validated, array $selectedIds)
    {
        if ($validated['generation_mode'] === 'single') {
            return StudentProfile::query()
                ->whereKey($validated['student_profile_id'])
                ->get();
        }

        if ($validated['generation_mode'] === 'selected_students') {
            return StudentProfile::query()
                ->where('is_active', true)
                ->whereKey($selectedIds)
                ->get();
        }

        return StudentProfile::query()
            ->where('is_active', true)
            ->when($validated['semester'] ?? null, fn ($query, $semester) => $query->where('current_semester', (int) $semester))
            ->when($validated['academic_year_id'] ?? null, function ($query, $academicYearId) use ($validated) {
                $query->whereHas('registrations', function ($query) use ($academicYearId, $validated) {
                    $query->where('academic_year_id', $academicYearId)
                        ->when($validated['semester'] ?? null, fn ($query, $semester) => $query->where('semester_no', $semester))
                        ->where('registration_status', 'Approved')
                        ->where('is_active', true);
                });
            })
            ->get();
    }

    /**
     * @return array<int, array{item_type: string, description: string, amount: float}>
     */
    private function cleanItems(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => [
                'item_type' => $item['item_type'] ?? 'fee',
                'description' => $item['description'] ?? '',
                'amount' => (float) ($item['amount'] ?? 0),
            ])
            ->filter(fn ($item) => filled($item['description']) && $item['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function scholarshipOptions(StudentInvoice $invoice): array
    {
        return StudentScholarship::query()
            ->with('scholarship')
            ->where('student_profile_id', $invoice->student_profile_id)
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereNull('academic_year_id')
                ->orWhere('academic_year_id', $invoice->academic_year_id))
            ->where(fn ($query) => $query
                ->whereNull('semester')
                ->orWhere('semester', $invoice->semester))
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'label' => $assignment->scholarship?->name.' - '.($assignment->academicYear?->name ?? 'Semua Tahun').' / '.($assignment->semester ? 'Semester '.$assignment->semester : 'Semua Semester'),
            ])
            ->all();
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'tuition' => 'SPP / Kuliah',
            'registration' => 'Pendaftaran',
            'exam' => 'Ujian Akhir',
            default => ucfirst(str_replace('_', ' ', (string) $type)),
        };
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'paid' => 'green',
            'partially_paid' => 'blue',
            'overdue' => 'red',
            'cancelled' => 'gray',
            'issued' => '',
            default => 'amber',
        };
    }

    private function statusText(?string $status): string
    {
        return match ($status) {
            'paid' => 'Lunas',
            'partially_paid' => 'Cicilan',
            'overdue' => 'Jatuh Tempo',
            'cancelled' => 'Dibatalkan',
            'issued' => 'Aktif',
            'draft' => 'Draft',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function invoiceTypeOptions(): array
    {
        return collect(config('financial.invoice_types', []))
            ->map(fn ($label, $value) => ['id' => $value, 'name' => $label])
            ->values()
            ->all();
    }
}
