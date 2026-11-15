<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\AdmissionQuota;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Kuota Penerimaan PMB — memakai kit CRUD shared.
 *
 * Kuota bersifat opsional per fakultas/program studi/kelas: baris tanpa
 * fakultas berarti berlaku untuk seluruh fakultas (lihat AdmissionSelectionService).
 * Hitungan "diterima" disegarkan dari data pendaftar setiap kali halaman dibuka.
 */
class AdmissionQuotaController extends Controller
{
    public const CLASS_TYPES = [
        'regular' => 'Reguler Pagi',
        'evening' => 'Kelas Malam',
        'weekend' => 'Kelas Akhir Pekan (Weekend)',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'admission_period_id' => 'nullable|integer|exists:admission_periods,id',
            'faculty_id' => 'nullable|integer|exists:faculties,id',
            'study_program_id' => 'nullable|integer|exists:study_programs,id',
            'class_type' => ['nullable', Rule::in(array_keys(self::CLASS_TYPES))],
            'sort' => 'nullable|in:id,quota,accepted_count,class_type,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        app(AdmissionSelectionService::class)->refreshAcceptedCounts();

        $query = AdmissionQuota::query()
            ->with(['period:id,name,code', 'faculty:id,name', 'studyProgram:id,name']);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->whereHas('period', fn ($period) => $period
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%"))
                ->orWhereHas('faculty', fn ($faculty) => $faculty->where('name', 'like', "%{$q}%"))
                ->orWhereHas('studyProgram', fn ($program) => $program->where('name', 'like', "%{$q}%")));
        }

        foreach (['admission_period_id', 'faculty_id', 'study_program_id', 'class_type'] as $filter) {
            if (filled($validated[$filter] ?? null)) {
                $query->where($filter, $validated[$filter]);
            }
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $quotas = $query->paginate($perPage)->withQueryString();

        $totals = AdmissionQuota::query()
            ->selectRaw('COALESCE(SUM(quota), 0) as total_quota, COALESCE(SUM(accepted_count), 0) as total_accepted')
            ->first();

        return Inertia::render('Admin/Admission/Quota/Index', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Kuota PMB'),
            'can' => [
                'create' => ActivePermission::check('admission-quota.create'),
                'update' => ActivePermission::check('admission-quota.update'),
                'delete' => ActivePermission::check('admission-quota.delete'),
            ],
            'stats' => [
                'total' => AdmissionQuota::count(),
                'quota' => (int) ($totals->total_quota ?? 0),
                'accepted' => (int) ($totals->total_accepted ?? 0),
                'remaining' => max(0, (int) ($totals->total_quota ?? 0) - (int) ($totals->total_accepted ?? 0)),
            ],
            'data' => [
                'rows' => collect($quotas->items())->values()->map(fn ($quota, $i) => [
                    'id' => $quota->id,
                    'no' => ($quotas->firstItem() ?? 0) + $i,
                    'period' => $quota->period?->name,
                    'faculty' => $quota->faculty?->name ?? 'Semua Fakultas',
                    'studyProgram' => $quota->studyProgram?->name ?? 'Semua Program Studi',
                    'classType' => $quota->class_type,
                    'classTypeLabel' => $quota->class_type
                        ? (self::CLASS_TYPES[$quota->class_type] ?? ucfirst($quota->class_type))
                        : 'Semua Kelas',
                    'quota' => (int) $quota->quota,
                    'acceptedCount' => (int) $quota->accepted_count,
                    'remaining' => max(0, (int) $quota->quota - (int) $quota->accepted_count),
                    'usage' => $quota->quota > 0
                        ? (int) round(($quota->accepted_count / $quota->quota) * 100)
                        : 0,
                    'createdAt' => $quota->created_at?->format('d M Y H:i'),
                    'editUrl' => route('admin.admission.admission-quotas.edit', $quota),
                    'deleteUrl' => route('admin.admission.admission-quotas.destroy', $quota),
                ])->all(),
                'currentPage' => $quotas->currentPage(),
                'lastPage' => $quotas->lastPage(),
                'perPage' => $quotas->perPage(),
                'total' => $quotas->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'admission_period_id' => $validated['admission_period_id'] ?? '',
                'faculty_id' => $validated['faculty_id'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'class_type' => $validated['class_type'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'periodOptions' => $this->periodOptions(),
            'facultyOptions' => $this->facultyOptions(),
            'programOptions' => $this->programOptions(),
            'classTypeOptions' => $this->classTypeOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-quotas.index'),
                'create' => route('admin.admission.admission-quotas.create'),
                'export' => route('admin.admission.admission-quotas.export'),
                'bulkDestroy' => route('admin.admission.admission-quotas.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Admission/Quota/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Tambah Kuota PMB'),
            'mode' => 'create',
            'quota' => [
                'admission_period_id' => '',
                'faculty_id' => '',
                'study_program_id' => '',
                'class_type' => '',
                'quota' => 1,
            ],
            'periodOptions' => $this->periodOptions(),
            'facultyOptions' => $this->facultyOptions(),
            'programOptions' => $this->programOptions(),
            'classTypeOptions' => $this->classTypeOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-quotas.index'),
                'submit' => route('admin.admission.admission-quotas.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $quota = AdmissionQuota::create([
            ...$this->payload($validated),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.admission.admission-quotas.index')
            ->with('success', 'Kuota "'.$quota->period?->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, AdmissionQuota $quota): Response
    {
        $quota->load(['period:id,name,code']);

        return Inertia::render('Admin/Admission/Quota/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Edit Kuota PMB'),
            'mode' => 'edit',
            'quota' => [
                'id' => $quota->id,
                'admission_period_id' => $quota->admission_period_id,
                'faculty_id' => $quota->faculty_id ?? '',
                'study_program_id' => $quota->study_program_id ?? '',
                'class_type' => $quota->class_type ?? '',
                'quota' => (int) $quota->quota,
            ],
            'periodOptions' => $this->periodOptions(),
            'facultyOptions' => $this->facultyOptions(),
            'programOptions' => $this->programOptions(),
            'classTypeOptions' => $this->classTypeOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-quotas.index'),
                'submit' => route('admin.admission.admission-quotas.update', $quota),
            ],
        ]);
    }

    public function update(Request $request, AdmissionQuota $quota)
    {
        $validated = $request->validate($this->rules());

        $quota->update([
            ...$this->payload($validated),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.admission.admission-quotas.index')
            ->with('success', 'Kuota berhasil diperbarui.');
    }

    public function destroy(AdmissionQuota $quota)
    {
        $quota->delete();

        return redirect()->route('admin.admission.admission-quotas.index')
            ->with('success', 'Kuota penerimaan berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_quotas,id',
        ]);

        $count = AdmissionQuota::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' kuota berhasil dihapus.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = AdmissionQuota::query()
            ->with(['period:id,name,code', 'faculty:id,name', 'studyProgram:id,name']);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->whereHas('period', fn ($period) => $period
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%"))
                ->orWhereHas('faculty', fn ($faculty) => $faculty->where('name', 'like', "%{$q}%"))
                ->orWhereHas('studyProgram', fn ($program) => $program->where('name', 'like', "%{$q}%")));
        }

        foreach (['admission_period_id', 'faculty_id', 'study_program_id', 'class_type'] as $filter) {
            if (filled($request->query($filter))) {
                $query->where($filter, $request->query($filter));
            }
        }

        $rows = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Periode', 'Fakultas', 'Program Studi', 'Kelas', 'Kuota', 'Diterima', 'Sisa', 'Terisi (%)', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $quota) {
            $sheet->fromArray([
                $quota->period?->name,
                $quota->faculty?->name ?? 'Semua Fakultas',
                $quota->studyProgram?->name ?? 'Semua Program Studi',
                $quota->class_type ? (self::CLASS_TYPES[$quota->class_type] ?? ucfirst($quota->class_type)) : 'Semua Kelas',
                (int) $quota->quota,
                (int) $quota->accepted_count,
                max(0, (int) $quota->quota - (int) $quota->accepted_count),
                $quota->quota > 0 ? (int) round(($quota->accepted_count / $quota->quota) * 100) : 0,
                $quota->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'kuota-pmb-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'admission_period_id' => 'required|integer|exists:admission_periods,id',
            'faculty_id' => 'nullable|integer|exists:faculties,id',
            'study_program_id' => 'nullable|integer|exists:study_programs,id',
            'class_type' => ['nullable', Rule::in(array_keys(self::CLASS_TYPES))],
            'quota' => 'required|integer|min:1',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'admission_period_id' => $validated['admission_period_id'],
            'faculty_id' => $validated['faculty_id'] ?: null,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'class_type' => $validated['class_type'] ?: null,
            'quota' => $validated['quota'],
        ];
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function periodOptions(): array
    {
        return AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (AdmissionPeriod $period) => [
                'id' => $period->id,
                'label' => $period->name.' ('.$period->code.')',
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function facultyOptions(): array
    {
        return Faculty::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'label' => $faculty->name.' ('.$faculty->code.')',
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, facultyId: int, label: string}>
     */
    private function programOptions(): array
    {
        return StudyProgram::query()
            ->orderBy('name')
            ->get(['id', 'faculty_id', 'name', 'code'])
            ->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'facultyId' => $program->faculty_id,
                'label' => $program->name.' ('.$program->code.')',
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function classTypeOptions(): array
    {
        return collect(self::CLASS_TYPES)
            ->map(fn (string $label, string $value) => ['id' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
