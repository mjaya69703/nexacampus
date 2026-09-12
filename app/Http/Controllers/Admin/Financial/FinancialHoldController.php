<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialHold;
use App\Support\ActivePermission;
use App\Support\Financial\FinancialClearanceService;
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
 * Blokir akademik — tinjau + lepas/tangguhkan (paritas Blade).
 *
 * Hold dibuat otomatis oleh evaluasi clearance; admin hanya menangani
 * pelepasan manual dan dispensasi sementara.
 */
class FinancialHoldController extends Controller
{
    public const STATUSES = ['active', 'waived', 'released'];

    public function __construct(
        protected FinancialClearanceService $clearance = new FinancialClearanceService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'number' => 'nullable|string|max:100',
            'hold_type' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
            'sort' => 'nullable|in:id,created_at,blocked_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = FinancialHold::query()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile:id,nim,user_id',
                'invoice:id,invoice_number,invoice_type',
                'policy:id,mode',
            ]);

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

        if (filled($validated['number'] ?? null)) {
            $query->whereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', '%'.$validated['number'].'%'));
        }

        if (filled($validated['hold_type'] ?? null)) {
            $query->where('hold_type', $validated['hold_type']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $holds = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/FinancialHold/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Blokir Akademik'),
            'can' => [
                'view' => ActivePermission::check('financial-hold.view'),
                'handle' => ActivePermission::check('financial-hold.update'),
            ],
            'stats' => [
                'total' => FinancialHold::count(),
                'active' => FinancialHold::where('status', 'active')->count(),
                'waived' => FinancialHold::where('status', 'waived')->count(),
            ],
            'data' => [
                'rows' => collect($holds->items())->values()->map(fn ($h, $i) => [
                    'id' => $h->id,
                    'no' => ($holds->firstItem() ?? 0) + $i,
                    'student' => $h->studentProfile?->user?->name ?? '-',
                    'nim' => $h->studentProfile?->nim,
                    'invoice' => $h->invoice?->invoice_number ?? '-',
                    'invoiceType' => $h->invoice?->invoice_type,
                    'holdType' => $h->hold_type,
                    'holdText' => $this->holdLabel($h->hold_type),
                    'blockedAt' => $h->blocked_at?->format('d M Y'),
                    'waivedUntil' => $h->waived_until?->format('d M Y'),
                    'status' => $h->status,
                    'statusTone' => $this->statusTone($h),
                    'statusText' => $this->statusText($h),
                    'showUrl' => route('admin.financial.financial-holds.show', $h->id),
                ])->all(),
                'currentPage' => $holds->currentPage(),
                'lastPage' => $holds->lastPage(),
                'perPage' => $holds->perPage(),
                'total' => $holds->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'number' => $validated['number'] ?? '',
                'hold_type' => $validated['hold_type'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'holdTypes' => $this->holdTypeOptions(),
            'statuses' => self::STATUSES,
            'urls' => [
                'index' => route('admin.financial.financial-holds.index'),
                'export' => route('admin.financial.financial-holds.export'),
                'exportPdf' => route('admin.financial.exports.pdf', ['resource' => 'financial-holds']),
            ],
        ]);
    }

    public function show(Request $request, FinancialHold $financialHold): Response
    {
        $financialHold->load([
            'studentProfile.user',
            'studentProfile.studyProgram',
            'invoice',
            'policy',
            'releasedBy',
        ]);

        return Inertia::render('Admin/Financial/FinancialHold/Show', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Detail Blokir'),
            'hold' => [
                'id' => $financialHold->id,
                'student' => $financialHold->studentProfile?->user?->name ?? '-',
                'nim' => $financialHold->studentProfile?->nim,
                'program' => $financialHold->studentProfile?->studyProgram?->name,
                'holdType' => $financialHold->hold_type,
                'holdText' => $this->holdLabel($financialHold->hold_type),
                'invoice' => $financialHold->invoice?->invoice_number,
                'invoiceType' => $financialHold->invoice?->invoice_type,
                'invoiceUrl' => $financialHold->invoice
                    ? route('admin.financial.student-invoices.show', $financialHold->invoice)
                    : null,
                'outstanding' => $financialHold->invoice
                    ? $this->money($financialHold->invoice->outstanding_amount)
                    : null,
                'reason' => $financialHold->reason,
                'status' => $financialHold->status,
                'statusTone' => $this->statusTone($financialHold),
                'statusText' => $this->statusText($financialHold),
                'blockedAt' => $financialHold->blocked_at?->format('d M Y H:i'),
                'waivedUntil' => $financialHold->waived_until?->format('Y-m-d'),
                'releasedAt' => $financialHold->released_at?->format('d M Y H:i'),
                'releasedBy' => $financialHold->releasedBy?->name,
                'releaseNotes' => $financialHold->release_notes,
                'policyMode' => $financialHold->policy?->mode,
                'isHandleable' => in_array($financialHold->status, ['active', 'waived'], true),
            ],
            'can' => [
                'handle' => ActivePermission::check('financial-hold.update'),
            ],
            'urls' => [
                'index' => route('admin.financial.financial-holds.index'),
                'release' => route('admin.financial.financial-holds.release', $financialHold),
                'waive' => route('admin.financial.financial-holds.waive', $financialHold),
            ],
        ]);
    }

    public function release(Request $request, FinancialHold $financialHold)
    {
        $validated = $request->validate([
            'release_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->clearance->release($financialHold, $request->user()->id, $validated['release_notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Blokir akademik dilepas.');
    }

    public function waive(Request $request, FinancialHold $financialHold)
    {
        $validated = $request->validate([
            'waived_until' => 'required|date|after_or_equal:today',
            'release_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->clearance->waive($financialHold, $validated['waived_until'], $request->user()->id, $validated['release_notes'] ?? null);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Dispensasi sementara diberikan.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = FinancialHold::query()
            ->with(['studentProfile.user', 'invoice']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Invoice', 'Target', 'Status', 'Diblokir', 'Ditangguhkan s/d', 'Dilepas']], null, 'A1');

        foreach ($rows as $i => $hold) {
            $sheet->fromArray([
                $hold->studentProfile?->user?->name,
                $hold->studentProfile?->nim,
                $hold->invoice?->invoice_number,
                $hold->hold_type,
                $hold->status,
                $hold->blocked_at?->format('Y-m-d H:i:s'),
                $hold->waived_until?->format('Y-m-d'),
                $hold->released_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'financial-holds-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function holdLabel(?string $type): string
    {
        return config('financial.hold_targets.'.$type.'.label', str((string) $type)->replace('_', ' ')->title()->toString());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function holdTypeOptions(): array
    {
        return collect(config('financial.hold_targets', []))
            ->map(fn ($target, $key) => [
                'id' => $key,
                'name' => $target['label'] ?? str($key)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }

    private function statusTone(FinancialHold $hold): string
    {
        return match ($hold->status) {
            'active' => $hold->isBlocking() ? 'red' : 'amber',
            'waived' => 'blue',
            'released' => 'green',
            default => 'gray',
        };
    }

    private function statusText(FinancialHold $hold): string
    {
        return match ($hold->status) {
            'active' => $hold->isBlocking() ? 'Diblokir' : 'Peringatan',
            'waived' => 'Ditangguhkan',
            'released' => 'Dilepas',
            default => ucfirst((string) $hold->status),
        };
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
