<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Admission\AdmissionDocumentRequirement;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Periode & Gelombang PMB — memakai kit CRUD shared.
 *
 * Paritas Blade lama: kode unik, tahun akademik tersinkron dari AcademicYear,
 * status aktif + publikasi terpisah, dan repeater syarat dokumen.
 * Hapus ditolak bila periode sudah memiliki pendaftar.
 */
class AdmissionPeriodController extends Controller
{
    private const SAFE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'is_active' => 'nullable|in:0,1',
            'is_published' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,name,code,wave,opens_at,closes_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = AdmissionPeriod::query()
            ->with('academicYear:id,name')
            ->withCount(['applications', 'documentRequirements']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['academic_year_id'] ?? null)) {
            $query->where('academic_year_id', $validated['academic_year_id']);
        }

        foreach (['is_active', 'is_published'] as $status) {
            if (isset($validated[$status]) && $validated[$status] !== '') {
                $query->where($status, (bool) $validated[$status]);
            }
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $periods = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Admission/Period/Index', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Periode PMB'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('admission-period.create'),
                'update' => ActivePermission::check('admission-period.update'),
                'delete' => ActivePermission::check('admission-period.delete'),
                'restore' => ActivePermission::any(['admission-period.update', 'admission-period.delete']),
                'toggle' => ActivePermission::check('admission-period.update'),
            ],
            'stats' => [
                'total' => AdmissionPeriod::count(),
                'active' => AdmissionPeriod::where('is_active', true)->count(),
                'published' => AdmissionPeriod::where('is_published', true)->count(),
                'trashed' => AdmissionPeriod::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($periods->items())->values()->map(fn ($period, $i) => [
                    'id' => $period->id,
                    'no' => ($periods->firstItem() ?? 0) + $i,
                    'name' => $period->name,
                    'code' => $period->code,
                    'academicYear' => $period->academicYear?->name ?? (string) $period->academic_year,
                    'wave' => (int) $period->wave,
                    'opensAt' => $period->opens_at?->format('d M Y'),
                    'closesAt' => $period->closes_at?->format('d M Y'),
                    'applicationsCount' => (int) $period->applications_count,
                    'requirementsCount' => (int) $period->document_requirements_count,
                    'isActive' => (bool) $period->is_active,
                    'isPublished' => (bool) $period->is_published,
                    'createdAt' => $period->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.admission.admission-periods.edit', $period),
                    'deleteUrl' => route('admin.admission.admission-periods.destroy', $period),
                    'restoreUrl' => route('admin.admission.admission-periods.restore', $period->id),
                    'forceUrl' => route('admin.admission.admission-periods.force-destroy', $period->id),
                    'toggleUrl' => route('admin.admission.admission-periods.toggle', $period),
                ])->all(),
                'currentPage' => $periods->currentPage(),
                'lastPage' => $periods->lastPage(),
                'perPage' => $periods->perPage(),
                'total' => $periods->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'academic_year_id' => $validated['academic_year_id'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'is_published' => $validated['is_published'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'yearOptions' => $this->yearOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-periods.index'),
                'create' => route('admin.admission.admission-periods.create'),
                'export' => route('admin.admission.admission-periods.export'),
                'bulkDestroy' => route('admin.admission.admission-periods.bulk-destroy'),
                'bulkRestore' => route('admin.admission.admission-periods.bulk-restore'),
                'bulkForceDestroy' => route('admin.admission.admission-periods.bulk-force-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $year = (int) now()->year;

        return Inertia::render('Admin/Admission/Period/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Tambah Periode PMB'),
            'mode' => 'create',
            'period' => [
                'name' => 'PMB '.$year.' - Gelombang 1',
                'code' => 'ADM'.$year.'W1',
                'academic_year_id' => '',
                'wave' => 1,
                'opens_at' => now()->toDateString(),
                'closes_at' => now()->addMonth()->toDateString(),
                'is_active' => true,
                'is_published' => false,
                'description' => '',
                'document_requirements' => $this->defaultDocumentRequirements(),
            ],
            'years' => $this->yearOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-periods.index'),
                'submit' => route('admin.admission.admission-periods.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $period = DB::transaction(function () use ($validated, $request) {
            $period = AdmissionPeriod::create([
                ...$this->periodPayload($validated),
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['document_requirements'] ?? [] as $index => $requirement) {
                $period->documentRequirements()->create([
                    ...$this->requirementPayload($requirement),
                    'sort_order' => $index + 1,
                ]);
            }

            return $period;
        });

        return redirect()->route('admin.admission.admission-periods.index')
            ->with('success', 'Periode "'.$period->name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, AdmissionPeriod $period): Response
    {
        $period->load(['documentRequirements' => fn ($query) => $query->orderBy('sort_order')]);

        return Inertia::render('Admin/Admission/Period/Form', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Edit Periode PMB'),
            'mode' => 'edit',
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'code' => $period->code,
                'academic_year_id' => $period->academic_year_id ?? '',
                'wave' => (int) $period->wave,
                'opens_at' => $period->opens_at?->toDateString(),
                'closes_at' => $period->closes_at?->toDateString(),
                'is_active' => (bool) $period->is_active,
                'is_published' => (bool) $period->is_published,
                'description' => $period->description ?? '',
                'document_requirements' => $period->documentRequirements->map(fn ($requirement) => [
                    'id' => $requirement->id,
                    'document_type' => $requirement->document_type,
                    'label' => $requirement->label,
                    'is_required' => (bool) $requirement->is_required,
                    'allowed_extensions' => $requirement->allowed_extensions ?? 'pdf,jpg,jpeg,png',
                    'max_size_kb' => (int) ($requirement->max_size_kb ?? 2048),
                ])->values()->all(),
            ],
            'years' => $this->yearOptions(),
            'urls' => [
                'index' => route('admin.admission.admission-periods.index'),
                'submit' => route('admin.admission.admission-periods.update', $period),
            ],
        ]);
    }

    public function update(Request $request, AdmissionPeriod $period)
    {
        $validated = $request->validate($this->rules($period));

        DB::transaction(function () use ($validated, $request, $period) {
            $period->update([
                ...$this->periodPayload($validated),
                'updated_by' => $request->user()->id,
            ]);

            $keptIds = [];

            foreach ($validated['document_requirements'] ?? [] as $index => $requirement) {
                $id = $requirement['id'] ?? null;

                if ($id) {
                    $period->documentRequirements()
                        ->whereKey($id)
                        ->update([
                            ...$this->requirementPayload($requirement),
                            'sort_order' => $index + 1,
                        ]);
                    $keptIds[] = (int) $id;
                } else {
                    $created = $period->documentRequirements()->create([
                        ...$this->requirementPayload($requirement),
                        'sort_order' => $index + 1,
                    ]);
                    $keptIds[] = $created->id;
                }
            }

            $period->documentRequirements()
                ->whereNotIn('id', $keptIds)
                ->delete();
        });

        return redirect()->route('admin.admission.admission-periods.index')
            ->with('success', 'Periode "'.$period->name.'" berhasil diperbarui.');
    }

    public function toggle(Request $request, AdmissionPeriod $period)
    {
        $validated = $request->validate([
            'field' => ['required', Rule::in(['is_active', 'is_published'])],
            'value' => 'required|boolean',
        ]);

        $period->update([
            $validated['field'] => (bool) $validated['value'],
            'updated_by' => $request->user()->id,
        ]);

        $label = $validated['field'] === 'is_active' ? 'Status aktif' : 'Status publikasi';

        return back()->with('success', $label.' periode berhasil diperbarui.');
    }

    public function destroy(AdmissionPeriod $period)
    {
        if ($period->applications()->exists()) {
            return back()->with('error', 'Periode "'.$period->name.'" sudah memiliki pendaftar dan tidak bisa dihapus.');
        }

        $period->update(['deleted_by' => auth()->id()]);
        $period->delete();

        return redirect()->route('admin.admission.admission-periods.index')
            ->with('success', 'Periode "'.$period->name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_periods,id',
        ]);

        $periods = AdmissionPeriod::whereIn('id', $validated['ids'])->get();
        $deleted = 0;
        $skipped = 0;

        foreach ($periods as $period) {
            if ($period->applications()->exists()) {
                $skipped++;

                continue;
            }

            $period->update(['deleted_by' => $request->user()->id]);
            $period->delete();
            $deleted++;
        }

        $response = back()->with('success', $deleted.' periode berhasil dihapus.');

        if ($skipped > 0) {
            $response->with('warning', $skipped.' periode dilewati karena sudah memiliki pendaftar.');
        }

        return $response;
    }

    public function restore(int $id)
    {
        $period = AdmissionPeriod::withTrashed()->findOrFail($id);
        $period->update(['deleted_by' => null]);
        $period->restore();

        return back()->with('success', 'Periode "'.$period->name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $period = AdmissionPeriod::withTrashed()->findOrFail($id);
        $name = $period->name;
        $period->forceDelete();

        return back()->with('success', 'Periode "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_periods,id',
        ]);

        $count = 0;

        foreach (AdmissionPeriod::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $period) {
            $period->update(['deleted_by' => null]);
            $period->restore();
            $count++;
        }

        return back()->with('success', $count.' periode berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_periods,id',
        ]);

        $count = 0;

        foreach (AdmissionPeriod::withTrashed()->whereIn('id', $validated['ids'])->get() as $period) {
            $period->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' periode dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = AdmissionPeriod::query()
            ->with('academicYear:id,name')
            ->withCount(['applications', 'documentRequirements']);

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($request->query('academic_year_id'))) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        foreach (['is_active', 'is_published'] as $status) {
            if (filled($request->query($status))) {
                $query->where($status, (bool) $request->query($status));
            }
        }

        $rows = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama Periode', 'Kode', 'Tahun Akademik', 'Gelombang', 'Buka', 'Tutup', 'Pendaftar', 'Syarat Dokumen', 'Aktif', 'Publikasi', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $period) {
            $sheet->fromArray([
                $period->name,
                $period->code,
                $period->academicYear?->name ?? (string) $period->academic_year,
                (int) $period->wave,
                $period->opens_at?->format('Y-m-d'),
                $period->closes_at?->format('Y-m-d'),
                (int) $period->applications_count,
                (int) $period->document_requirements_count,
                $period->is_active ? 'Ya' : 'Tidak',
                $period->is_published ? 'Ya' : 'Tidak',
                $period->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'periode-pmb-'.now()->format('Ymd-His').'.'.$format;
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
    private function rules(?AdmissionPeriod $period = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('admission_periods', 'code')->ignore($period?->id),
            ],
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'wave' => 'required|integer|min:1|max:20',
            'opens_at' => 'required|date',
            'closes_at' => 'required|date|after_or_equal:opens_at',
            'is_active' => 'boolean',
            'is_published' => 'boolean',
            'description' => 'nullable|string',
            'document_requirements' => 'array',
            'document_requirements.*.id' => [
                'nullable', 'integer',
                Rule::exists('admission_document_requirements', 'id')
                    ->where('admission_period_id', $period?->id),
            ],
            'document_requirements.*.document_type' => 'required|string|max:80|distinct',
            'document_requirements.*.label' => 'required|string|max:255',
            'document_requirements.*.is_required' => 'boolean',
            'document_requirements.*.allowed_extensions' => 'nullable|string|max:255',
            'document_requirements.*.max_size_kb' => 'nullable|integer|min:1|max:10240',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function periodPayload(array $validated): array
    {
        $academicYear = AcademicYear::find($validated['academic_year_id']);

        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'academic_year_id' => $validated['academic_year_id'],
            'academic_year' => (int) ($academicYear?->start_date?->format('Y') ?? now()->year),
            'wave' => $validated['wave'],
            'opens_at' => $validated['opens_at'],
            'closes_at' => $validated['closes_at'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $requirement
     * @return array<string, mixed>
     */
    private function requirementPayload(array $requirement): array
    {
        return [
            'document_type' => $requirement['document_type'],
            'label' => $requirement['label'],
            'is_required' => (bool) ($requirement['is_required'] ?? true),
            'allowed_extensions' => $this->sanitizeAllowedExtensions($requirement['allowed_extensions'] ?? null),
            'max_size_kb' => $requirement['max_size_kb'] ?? 2048,
        ];
    }

    private function sanitizeAllowedExtensions(?string $extensions): string
    {
        $allowed = collect(explode(',', $extensions ?: 'pdf,jpg,jpeg,png'))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->intersect(self::SAFE_EXTENSIONS)
            ->values()
            ->all();

        return implode(',', $allowed ?: ['pdf', 'jpg', 'jpeg', 'png']);
    }

    /**
     * @return array<int, array{id: int, name: string, year: int}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date'])
            ->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
                'year' => (int) ($year->start_date?->format('Y') ?? now()->year),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultDocumentRequirements(): array
    {
        return [
            ['document_type' => 'id_card', 'label' => 'Kartu Identitas (KTP / SIM / Kartu Pelajar)', 'is_required' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_kb' => 2048],
            ['document_type' => 'photo', 'label' => 'Pasfoto Resmi (Latar Merah / Biru)', 'is_required' => true, 'allowed_extensions' => 'jpg,jpeg,png', 'max_size_kb' => 1024],
            ['document_type' => 'high_school_certificate', 'label' => 'Ijazah SMA / SMK / Sederajat atau SKL', 'is_required' => true, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_kb' => 4096],
            ['document_type' => 'report_card', 'label' => 'Transkrip Nilai / Rapor Semester 1-5', 'is_required' => false, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_kb' => 4096],
            ['document_type' => 'payment_proof', 'label' => 'Bukti Pembayaran Pendaftaran', 'is_required' => false, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_size_kb' => 2048],
        ];
    }
}
