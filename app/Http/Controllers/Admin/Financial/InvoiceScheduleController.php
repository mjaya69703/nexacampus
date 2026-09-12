<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\InvoiceSchedule;
use App\Support\ActivePermission;
use App\Support\Financial\InvoiceScheduleService;
use App\Support\Inertia\ShellProps;
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
 * CRUD jadwal penerbitan tagihan — memakai kit CRUD shared.
 *
 * Paritas Blade lama: kind tuition/custom, target active/selected/single,
 * item komponen untuk custom, edit terkunci kecuali pending/failed
 * (failed kembali pending), eksekusi manual + hasil run.
 */
class InvoiceScheduleController extends Controller
{
    public const KINDS = ['tuition', 'custom'];
    public const MODES = ['active_students', 'selected_students', 'single'];
    public const STATUSES = ['pending', 'running', 'completed', 'failed', 'cancelled'];
    public const ITEM_TYPES = ['fee', 'discount', 'adjustment', 'penalty'];

    public function __construct(
        protected InvoiceScheduleService $schedules = new InvoiceScheduleService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'kind' => 'nullable|string|max:20',
            'mode' => 'nullable|string|max:30',
            'status' => 'nullable|string|max:20',
            'active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,created_at,publish_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = InvoiceSchedule::query()
            ->with(['academicYear:id,name'])
            ->withCount('invoices as invoices_count');

        if (filled($validated['q'] ?? null)) {
            $query->where('name', 'like', '%'.$validated['q'].'%');
        }

        foreach (['invoice_kind' => 'kind', 'generation_mode' => 'mode', 'status' => 'status'] as $column => $key) {
            if (filled($validated[$key] ?? null)) {
                $query->where($column, $validated[$key]);
            }
        }

        if (isset($validated['active']) && $validated['active'] !== '') {
            $query->where('is_active', (bool) $validated['active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $schedules = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/InvoiceSchedule/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Jadwal Penerbitan Tagihan'),
            'can' => [
                'create' => ActivePermission::check('invoice-schedule.create'),
                'update' => ActivePermission::check('invoice-schedule.update'),
                'delete' => ActivePermission::check('invoice-schedule.delete'),
                'run' => ActivePermission::check('invoice-schedule.update'),
            ],
            'stats' => [
                'total' => InvoiceSchedule::count(),
                'pending' => InvoiceSchedule::where('status', 'pending')->count(),
                'completed' => InvoiceSchedule::where('status', 'completed')->count(),
            ],
            'data' => [
                'rows' => collect($schedules->items())->values()->map(fn ($s, $i) => [
                    'id' => $s->id,
                    'no' => ($schedules->firstItem() ?? 0) + $i,
                    'name' => $s->name,
                    'kind' => $s->invoice_kind,
                    'kindText' => $this->kindLabel($s->invoice_kind),
                    'mode' => $s->generation_mode,
                    'modeText' => $this->modeLabel($s->generation_mode),
                    'year' => $s->academicYear?->name,
                    'semester' => $s->semester,
                    'publishAt' => $s->publish_at?->format('d M Y H:i'),
                    'status' => $s->status,
                    'statusTone' => $this->statusTone($s->status),
                    'statusText' => $this->statusText($s->status),
                    'isActive' => (bool) $s->is_active,
                    'created' => (int) $s->created_count,
                    'invoices' => (int) $s->invoices_count,
                    'canRun' => $s->status === 'pending',
                    'editUrl' => route('admin.financial.invoice-schedules.edit', $s->id),
                    'deleteUrl' => route('admin.financial.invoice-schedules.destroy', $s->id),
                    'runUrl' => route('admin.financial.invoice-schedules.run', $s->id),
                ])->all(),
                'currentPage' => $schedules->currentPage(),
                'lastPage' => $schedules->lastPage(),
                'perPage' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'kind' => $validated['kind'] ?? '',
                'mode' => $validated['mode'] ?? '',
                'status' => $validated['status'] ?? '',
                'active' => $validated['active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'kinds' => self::KINDS,
            'modes' => self::MODES,
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.financial.invoice-schedules.index'),
                'create' => route('admin.financial.invoice-schedules.create'),
                'store' => route('admin.financial.invoice-schedules.store'),
                'export' => route('admin.financial.invoice-schedules.export'),
                'exportPdf' => route('admin.financial.exports.pdf', ['resource' => 'invoice-schedules']),
                'bulkDestroy' => route('admin.financial.invoice-schedules.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Financial/InvoiceSchedule/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Buat Jadwal Tagihan'),
            'mode' => 'create',
            'schedule' => $this->blankForm(),
            'items' => [['item_type' => 'fee', 'description' => '', 'amount' => '']],
            'selectedStudents' => [],
            'years' => $this->yearOptions(),
            'invoiceTypes' => $this->invoiceTypeOptions(),
            'kinds' => self::KINDS,
            'modes' => self::MODES,
            'itemTypes' => self::ITEM_TYPES,
            'urls' => [
                'index' => route('admin.financial.invoice-schedules.index'),
                'submit' => route('admin.financial.invoice-schedules.store'),
                'searchStudents' => route('admin.financial.invoice-schedules.search-students'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));

        InvoiceSchedule::create([
            'name' => $validated['name'],
            'invoice_kind' => $validated['invoice_kind'],
            'generation_mode' => $validated['generation_mode'],
            'student_profile_id' => $validated['generation_mode'] === 'single' ? $validated['student_profile_id'] : null,
            'student_profile_ids' => $validated['generation_mode'] === 'selected_students'
                ? array_values($validated['student_profile_ids'] ?? []) : null,
            'academic_year_id' => filled($validated['academic_year_id'] ?? null) ? $validated['academic_year_id'] : null,
            'semester' => filled($validated['semester'] ?? null) ? (int) $validated['semester'] : null,
            'invoice_type' => $validated['invoice_type'],
            'items' => $validated['invoice_kind'] === 'custom' ? $this->cleanItems($validated['items'] ?? []) : null,
            'due_date' => $validated['due_date'],
            'publish_at' => $validated['publish_at'],
            'issue_immediately' => (bool) $validated['issue_immediately'],
            'status' => 'pending',
            'is_active' => (bool) $validated['is_active'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.financial.invoice-schedules.index')
            ->with('success', 'Jadwal penerbitan tagihan berhasil dibuat.');
    }

    public function edit(Request $request, InvoiceSchedule $invoiceSchedule): Response
    {
        $locked = ! in_array($invoiceSchedule->status, ['pending', 'failed'], true);

        $selectedStudents = [];
        if ($invoiceSchedule->generation_mode === 'single' && $invoiceSchedule->student_profile_id) {
            $student = StudentProfile::with(['user', 'studyProgram'])->find($invoiceSchedule->student_profile_id);
            if ($student) {
                $selectedStudents[] = $this->studentOption($student);
            }
        } elseif ($invoiceSchedule->generation_mode === 'selected_students' && $invoiceSchedule->student_profile_ids) {
            $selectedStudents = StudentProfile::with(['user', 'studyProgram'])
                ->whereKey((array) $invoiceSchedule->student_profile_ids)
                ->get()->map(fn ($s) => $this->studentOption($s))->all();
        }

        return Inertia::render('Admin/Financial/InvoiceSchedule/Form', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Ubah Jadwal Tagihan'),
            'mode' => 'edit',
            'locked' => $locked,
            'status' => $invoiceSchedule->status,
            'lastResult' => $invoiceSchedule->last_result,
            'schedule' => [
                'name' => $invoiceSchedule->name,
                'invoice_kind' => $invoiceSchedule->invoice_kind,
                'generation_mode' => $invoiceSchedule->generation_mode,
                'student_profile_id' => $invoiceSchedule->student_profile_id ? (string) $invoiceSchedule->student_profile_id : '',
                'academic_year_id' => $invoiceSchedule->academic_year_id ? (string) $invoiceSchedule->academic_year_id : '',
                'semester' => $invoiceSchedule->semester !== null ? (string) $invoiceSchedule->semester : '',
                'invoice_type' => $invoiceSchedule->invoice_type,
                'publish_at' => $invoiceSchedule->publish_at?->format('Y-m-d\TH:i'),
                'due_date' => $invoiceSchedule->due_date?->format('Y-m-d'),
                'issue_immediately' => (bool) $invoiceSchedule->issue_immediately,
                'is_active' => (bool) $invoiceSchedule->is_active,
                'notes' => $invoiceSchedule->notes,
            ],
            'items' => collect($invoiceSchedule->items ?? [])->map(fn ($item) => [
                'item_type' => $item['item_type'] ?? 'fee',
                'description' => $item['description'] ?? '',
                'amount' => isset($item['amount']) ? (string) $item['amount'] : '',
            ])->values()->all() ?: [['item_type' => 'fee', 'description' => '', 'amount' => '']],
            'selectedStudents' => $selectedStudents,
            'years' => $this->yearOptions(),
            'invoiceTypes' => $this->invoiceTypeOptions(),
            'kinds' => self::KINDS,
            'modes' => self::MODES,
            'itemTypes' => self::ITEM_TYPES,
            'urls' => [
                'index' => route('admin.financial.invoice-schedules.index'),
                'submit' => route('admin.financial.invoice-schedules.update', $invoiceSchedule),
                'searchStudents' => route('admin.financial.invoice-schedules.search-students'),
            ],
        ]);
    }

    public function update(Request $request, InvoiceSchedule $invoiceSchedule)
    {
        if (! in_array($invoiceSchedule->status, ['pending', 'failed'], true)) {
            return back()->with('error', 'Jadwal yang sudah completed/running/cancelled tidak dapat diubah lagi.');
        }

        $validated = $request->validate($this->rules($request));

        $invoiceSchedule->update([
            'name' => $validated['name'],
            'invoice_kind' => $validated['invoice_kind'],
            'generation_mode' => $validated['generation_mode'],
            'student_profile_id' => $validated['generation_mode'] === 'single' ? $validated['student_profile_id'] : null,
            'student_profile_ids' => $validated['generation_mode'] === 'selected_students'
                ? array_values($validated['student_profile_ids'] ?? []) : null,
            'academic_year_id' => filled($validated['academic_year_id'] ?? null) ? $validated['academic_year_id'] : null,
            'semester' => filled($validated['semester'] ?? null) ? (int) $validated['semester'] : null,
            'invoice_type' => $validated['invoice_type'],
            'items' => $validated['invoice_kind'] === 'custom' ? $this->cleanItems($validated['items'] ?? []) : null,
            'due_date' => $validated['due_date'],
            'publish_at' => $validated['publish_at'],
            'issue_immediately' => (bool) $validated['issue_immediately'],
            'status' => $invoiceSchedule->status === 'failed' ? 'pending' : $invoiceSchedule->status,
            'is_active' => (bool) $validated['is_active'],
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.financial.invoice-schedules.index')
            ->with('success', 'Jadwal penerbitan tagihan berhasil diperbarui.');
    }

    public function run(Request $request, InvoiceSchedule $invoiceSchedule)
    {
        try {
            $result = $this->schedules->run($invoiceSchedule->fresh());
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success',
            "Eksekusi selesai: {$result['created']} terbit, {$result['skipped']} dilewati.");
    }

    public function destroy(InvoiceSchedule $invoiceSchedule)
    {
        $invoiceSchedule->delete();

        return back()->with('success', 'Jadwal dipindahkan ke sampah.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:invoice_schedules,id',
        ]);

        InvoiceSchedule::whereIn('id', $validated['ids'])->get()->each->delete();

        return back()->with('success', count($validated['ids']).' jadwal dipindahkan ke sampah.');
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
            ->map(fn ($s) => $this->studentOption($s))
            ->all();

        return response()->json(['options' => $students]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = InvoiceSchedule::query()->with(['academicYear'])->withCount('invoices as invoices_count');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Jenis', 'Target', 'Tahun', 'Semester', 'Publish', 'Status', 'Aktif', 'Terbit', 'Invoice']], null, 'A1');

        foreach ($rows as $i => $schedule) {
            $sheet->fromArray([
                $schedule->name,
                $schedule->invoice_kind,
                $schedule->generation_mode,
                $schedule->academicYear?->name,
                $schedule->semester,
                $schedule->publish_at?->format('Y-m-d H:i:s'),
                $schedule->status,
                $schedule->is_active ? 'Ya' : 'Tidak',
                (int) $schedule->created_count,
                (int) $schedule->invoices_count,
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'invoice-schedules-'.now()->format('Ymd-His').'.'.$format;
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
    private function rules(Request $request): array
    {
        $isTuition = ($request->input('invoice_kind') ?? 'tuition') === 'tuition';

        $rules = [
            'name' => 'required|string|max:255',
            'invoice_kind' => ['required', Rule::in(self::KINDS)],
            'generation_mode' => ['required', Rule::in(self::MODES)],
            'student_profile_id' => 'required_if:generation_mode,single|nullable|exists:student_profiles,id',
            'student_profile_ids' => 'required_if:generation_mode,selected_students|nullable|array',
            'student_profile_ids.*' => 'exists:student_profiles,id',
            'academic_year_id' => [$isTuition ? 'required' : 'nullable', 'exists:academic_years,id'],
            'semester' => [$isTuition ? 'required' : 'nullable', 'integer', 'min:1', 'max:14'],
            'invoice_type' => ['required', Rule::in(array_keys(config('financial.invoice_types', [])))],
            'publish_at' => 'required|date',
            'due_date' => 'required|date|after_or_equal:today',
            'issue_immediately' => 'required|boolean',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ];

        if (! $isTuition) {
            $rules += [
                'items' => 'required|array|min:1',
                'items.*.item_type' => ['required', Rule::in(self::ITEM_TYPES)],
                'items.*.description' => 'required|string|max:255',
                'items.*.amount' => 'required|numeric|min:1',
            ];
        }

        return $rules;
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
     * @return array{id: int, label: string}
     */
    private function studentOption(StudentProfile $student): array
    {
        return [
            'id' => $student->id,
            'label' => ($student->nim ?? '-').' - '.$student->user?->name.' ('.$student->studyProgram?->name.')',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'name' => '',
            'invoice_kind' => 'tuition',
            'generation_mode' => 'active_students',
            'student_profile_id' => '',
            'academic_year_id' => '',
            'semester' => '1',
            'invoice_type' => 'tuition',
            'publish_at' => '',
            'due_date' => '',
            'issue_immediately' => true,
            'is_active' => true,
            'notes' => '',
        ];
    }

    private function kindLabel(?string $kind): string
    {
        return match ($kind) {
            'tuition' => 'SPP / Uang Kuliah',
            'custom' => 'Kustom / Non-SPP',
            default => str((string) $kind)->title()->toString(),
        };
    }

    private function modeLabel(?string $mode): string
    {
        return match ($mode) {
            'active_students' => 'Semua Aktif',
            'selected_students' => 'Terpilih',
            'single' => 'Satu Mahasiswa',
            default => str((string) $mode)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'completed' => 'green',
            'running' => 'blue',
            'failed' => 'red',
            'cancelled' => 'gray',
            default => 'amber',
        };
    }

    private function statusText(?string $status): string
    {
        return match ($status) {
            'completed' => 'Selesai Terbit',
            'running' => 'Sedang Diproses',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
            'pending' => 'Menunggu Jadwal',
            default => str((string) $status)->title()->toString(),
        };
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
