<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentCreditTransaction;
use App\Support\ActivePermission;
use App\Support\Financial\StudentCreditService;
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
 * Saldo deposit mahasiswa — pantau + catat refund/adjustment (paritas Blade).
 *
 * Saldo bertambah otomatis dari kelebihan bayar; admin mencatat refund
 * keluar dan koreksi manual dari halaman ini.
 */
class StudentCreditController extends Controller
{
    public function __construct(
        protected StudentCreditService $credits = new StudentCreditService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'program' => 'nullable|integer|exists:study_programs,id',
            'sort' => 'nullable|in:id,balance,updated_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentCreditBalance::query()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name',
                'studentProfile:id,nim,user_id,study_program_id',
            ])
            ->where('balance', '!=', 0);

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

        if (filled($validated['program'] ?? null)) {
            $query->whereHas('studentProfile', fn ($s) => $s->where('study_program_id', $validated['program']));
        }

        $sort = $validated['sort'] ?? 'balance';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $balances = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Financial/StudentCredit/Index', [
            'shell' => ShellProps::make($request->user(), 'Keuangan', 'Saldo Deposit'),
            'can' => [
                'record' => ActivePermission::check('student-credit.create'),
            ],
            'stats' => [
                'students' => StudentCreditBalance::where('balance', '>', 0)->count(),
                'balance' => (float) StudentCreditBalance::sum('balance'),
                'overpayment' => (float) StudentCreditTransaction::where('transaction_type', 'overpayment')->sum('amount'),
                'refund' => abs((float) StudentCreditTransaction::where('transaction_type', 'refund')->sum('amount')),
            ],
            'data' => [
                'rows' => collect($balances->items())->values()->map(fn ($b, $i) => [
                    'id' => $b->id,
                    'no' => ($balances->firstItem() ?? 0) + $i,
                    'student' => $b->studentProfile?->user?->name ?? '-',
                    'nim' => $b->studentProfile?->nim,
                    'program' => $b->studentProfile?->studyProgram?->name,
                    'balance' => $this->money($b->balance),
                    'at' => $b->updated_at?->format('d M Y H:i'),
                ])->all(),
                'currentPage' => $balances->currentPage(),
                'lastPage' => $balances->lastPage(),
                'perPage' => $balances->perPage(),
                'total' => $balances->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'program' => $validated['program'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'programOptions' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'urls' => [
                'index' => route('admin.financial.student-credits.index'),
                'record' => route('admin.financial.student-credits.record'),
                'export' => route('admin.financial.student-credits.export'),
                'searchStudents' => route('admin.financial.student-credits.search-students'),
            ],
        ]);
    }

    public function record(Request $request)
    {
        $validated = $request->validate([
            'student_profile_ids' => 'required|array|min:1',
            'student_profile_ids.*' => 'exists:student_profiles,id',
            'transaction_type' => ['required', Rule::in(['refund', 'manual_adjustment'])],
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $created = 0;
        $errors = [];

        foreach ($validated['student_profile_ids'] as $studentProfileId) {
            $studentProfile = StudentProfile::findOrFail($studentProfileId);

            try {
                if ($validated['transaction_type'] === 'refund') {
                    $this->credits->refund($studentProfile, (float) $validated['amount'], $request->user()->id, $validated['notes'] ?? null);
                } else {
                    $this->credits->manualAdjustment($studentProfile, (float) $validated['amount'], $request->user()->id, $validated['notes'] ?? null);
                }

                $created++;
            } catch (Throwable $exception) {
                $errors[] = ($studentProfile->nim ?: $studentProfile->id).': '.$exception->getMessage();
            }
        }

        if ($created > 0 && empty($errors)) {
            return back()->with('success', $created.' transaksi deposit berhasil dicatat.');
        }

        $message = $created > 0 ? $created.' transaksi tersimpan. ' : '';
        $message .= implode(' ', array_slice($errors, 0, 5));

        return back()->with($created > 0 ? 'success' : 'error', $message);
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $balances = StudentCreditBalance::query()->pluck('balance', 'student_profile_id');

        $students = StudentProfile::query()
            ->with(['user:id,first_name,last_name'])
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
                'label' => ($s->nim ?: '-').' - '.$s->user?->name.' (Saldo: '.$this->money($balances[$s->id] ?? 0).')',
            ])
            ->all();

        return response()->json(['options' => $students]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudentCreditBalance::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram'])
            ->where('balance', '!=', 0);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('balance')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Prodi', 'Saldo', 'Diperbarui']], null, 'A1');

        foreach ($rows as $i => $balance) {
            $sheet->fromArray([
                $balance->studentProfile?->user?->name,
                $balance->studentProfile?->nim,
                $balance->studentProfile?->studyProgram?->name,
                (float) $balance->balance,
                $balance->updated_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'student-credits-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
