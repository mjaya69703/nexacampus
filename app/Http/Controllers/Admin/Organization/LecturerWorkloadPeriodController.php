<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD periode Beban Kerja Dosen (BKD) — paritas halaman Livewire lama,
 * termasuk detail laporan yang masuk dan penjagaan hapus periode.
 */
class LecturerWorkloadPeriodController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draf (Belum Dibuka)',
        'open' => 'Dibuka (Masa Pengajuan)',
        'review' => 'Masa Review & Asesmen',
        'closed' => 'Ditutup / Selesai',
    ];

    private const STATUS_LABELS = [
        'draft' => 'Draf',
        'open' => 'Dibuka',
        'review' => 'Review',
        'closed' => 'Ditutup',
    ];

    private const SUBMISSION_STATUS_LABELS = [
        'draft' => 'Draf',
        'submitted' => 'Diajukan',
        'in_approval' => 'Dalam Persetujuan',
        'approved' => 'Disetujui',
        'revision' => 'Revisi',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:draft,open,review,closed',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'sort' => 'nullable|in:name,code,status,starts_at,ends_at,submissions_count,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = LecturerWorkloadPeriod::query()
            ->with(['academicYear', 'academicPeriod'])
            ->withCount('submissions');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%")
                    ->orWhereHas('academicYear', fn ($year) => $year
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%"))
                    ->orWhereHas('academicPeriod', fn ($period) => $period
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['academic_year_id'] ?? null)) {
            $query->where('academic_year_id', (int) $validated['academic_year_id']);
        }

        $sort = $validated['sort'] ?? 'starts_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'starts_at') {
            $query->orderByDesc('starts_at');
        }

        $query->orderByDesc('id');

        $perPage = (int) ($validated['perPage'] ?? 10);
        $periods = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/LecturerWorkloadPeriods/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Periode BKD'),
            'can' => [
                'create' => ActivePermission::check('lecturer-workload-period.create'),
                'update' => ActivePermission::check('lecturer-workload-period.update'),
                'delete' => ActivePermission::check('lecturer-workload-period.delete'),
                'view' => ActivePermission::check('lecturer-workload-period.view'),
            ],
            'stats' => [
                'total' => LecturerWorkloadPeriod::count(),
                'open' => LecturerWorkloadPeriod::where('status', 'open')->count(),
                'review' => LecturerWorkloadPeriod::where('status', 'review')->count(),
                'closed' => LecturerWorkloadPeriod::where('status', 'closed')->count(),
            ],
            'data' => [
                'rows' => collect($periods->items())->values()->map(fn (LecturerWorkloadPeriod $period, int $i) => [
                    'id' => $period->id,
                    'no' => ($periods->firstItem() ?? 0) + $i,
                    'name' => $period->name,
                    'code' => $period->code,
                    'academicYear' => $period->academicYear?->name ?? '-',
                    'academicPeriod' => $period->academicPeriod?->name,
                    'periodRange' => ($period->starts_at?->format('d M Y') ?? '-').' s.d. '.($period->ends_at?->format('d M Y') ?? '-'),
                    'sksRange' => $this->sksRange($period),
                    'minimumSks' => (float) $period->minimum_sks,
                    'maximumSks' => (float) $period->maximum_sks,
                    'status' => $period->status,
                    'statusLabel' => self::STATUS_LABELS[$period->status] ?? ucfirst((string) $period->status),
                    'submissionsCount' => (int) $period->submissions_count,
                    'showUrl' => route('admin.organization.lecturer-workload-periods.show', $period->id),
                    'editUrl' => route('admin.organization.lecturer-workload-periods.edit', $period->id),
                    'deleteUrl' => route('admin.organization.lecturer-workload-periods.destroy', $period->id),
                ])->all(),
                'currentPage' => $periods->currentPage(),
                'lastPage' => $periods->lastPage(),
                'perPage' => $periods->perPage(),
                'total' => $periods->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'academic_year_id' => (string) ($validated['academic_year_id'] ?? ''),
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'statuses' => $this->optionList(self::STATUSES),
            'academicYears' => $this->academicYearOptions(),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-periods.index'),
                'create' => route('admin.organization.lecturer-workload-periods.create'),
                'bulkDestroy' => route('admin.organization.lecturer-workload-periods.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/LecturerWorkloadPeriods/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Periode BKD'),
            'mode' => 'create',
            'period' => [
                'academic_year_id' => '',
                'academic_period_id' => '',
                'name' => '',
                'code' => '',
                'starts_at' => '',
                'ends_at' => '',
                'status' => 'draft',
                'minimum_sks' => 12,
                'maximum_sks' => 16,
                'notes' => '',
            ],
            'statuses' => $this->optionList(self::STATUSES),
            'academicYears' => $this->academicYearOptions(),
            'academicPeriods' => $this->academicPeriodOptions(),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-periods.index'),
                'submit' => route('admin.organization.lecturer-workload-periods.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        LecturerWorkloadPeriod::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.lecturer-workload-periods.index')
            ->with('success', 'Periode BKD berhasil dibuat.');
    }

    public function show(Request $request, int $id): Response
    {
        $period = LecturerWorkloadPeriod::query()
            ->with(['academicYear', 'academicPeriod', 'submissions.owner'])
            ->withCount('submissions')
            ->findOrFail($id);

        $submissions = $period->submissions->sortByDesc('id')->values();
        $averageSks = $submissions->avg('total_sks') ?: 0;

        return Inertia::render('Admin/Organization/LecturerWorkloadPeriods/Show', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Detail Periode BKD'),
            'can' => [
                'update' => ActivePermission::check('lecturer-workload-period.update'),
            ],
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'code' => $period->code,
                'status' => $period->status,
                'statusLabel' => self::STATUS_LABELS[$period->status] ?? ucfirst((string) $period->status),
                'academicYear' => $period->academicYear?->name ?? '-',
                'academicPeriod' => $period->academicPeriod?->name ?? '-',
                'startsAt' => $period->starts_at?->format('d M Y') ?? '-',
                'endsAt' => $period->ends_at?->format('d M Y') ?? '-',
                'sksRange' => $this->sksRange($period),
                'notes' => $period->notes,
                'submissionsCount' => (int) $period->submissions_count,
                'approvedCount' => $submissions->where('status', 'approved')->count(),
                'averageSks' => round((float) $averageSks, 1),
            ],
            'submissions' => $submissions->map(fn ($submission) => [
                'id' => $submission->id,
                'ownerName' => $submission->owner?->name ?? '-',
                'ownerEmail' => $submission->owner?->email,
                'status' => $submission->status,
                'statusLabel' => self::SUBMISSION_STATUS_LABELS[$submission->status] ?? ucfirst(str_replace('_', ' ', (string) $submission->status)),
                'teachingSks' => (float) $submission->teaching_sks,
                'structuralSks' => (float) $submission->structural_sks,
                'tridharmaSks' => (float) $submission->tridharma_sks,
                'totalSks' => (float) $submission->total_sks,
                'showUrl' => route('admin.organization.lecturer-workload-submissions.show', $submission->id),
            ])->all(),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-periods.index'),
                'edit' => route('admin.organization.lecturer-workload-periods.edit', $period->id),
            ],
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $period = LecturerWorkloadPeriod::findOrFail($id);

        return Inertia::render('Admin/Organization/LecturerWorkloadPeriods/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Periode BKD'),
            'mode' => 'edit',
            'period' => [
                'id' => $period->id,
                'academic_year_id' => (string) ($period->academic_year_id ?? ''),
                'academic_period_id' => (string) ($period->academic_period_id ?? ''),
                'name' => $period->name,
                'code' => $period->code,
                'starts_at' => $period->starts_at?->toDateString() ?? '',
                'ends_at' => $period->ends_at?->toDateString() ?? '',
                'status' => $period->status,
                'minimum_sks' => (float) $period->minimum_sks,
                'maximum_sks' => (float) $period->maximum_sks,
                'notes' => $period->notes ?? '',
            ],
            'statuses' => $this->optionList(self::STATUSES),
            'academicYears' => $this->academicYearOptions(),
            'academicPeriods' => $this->academicPeriodOptions(),
            'urls' => [
                'index' => route('admin.organization.lecturer-workload-periods.index'),
                'show' => route('admin.organization.lecturer-workload-periods.show', $period->id),
                'submit' => route('admin.organization.lecturer-workload-periods.update', $period->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $period = LecturerWorkloadPeriod::findOrFail($id);
        $validated = $this->validatePayload($request, $period);

        $period->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.lecturer-workload-periods.index')
            ->with('success', 'Periode BKD berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $period = LecturerWorkloadPeriod::findOrFail($id);

        if ($period->submissions()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus periode yang sudah memiliki data pengajuan BKD dosen.');
        }

        $period->update(['deleted_by' => $request->user()->id]);
        $period->delete();

        return redirect()->route('admin.organization.lecturer-workload-periods.index')
            ->with('success', 'Periode BKD "'.$period->name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:lecturer_workload_periods,id',
        ]);

        $periods = LecturerWorkloadPeriod::whereIn('id', $validated['ids'])->get();

        foreach ($periods as $period) {
            $period->update(['deleted_by' => $request->user()->id]);
            $period->delete();
        }

        return back()->with('success', $periods->count().' periode BKD berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?LecturerWorkloadPeriod $period = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
            'academic_year_id' => filled($request->input('academic_year_id')) ? $request->input('academic_year_id') : null,
            'academic_period_id' => filled($request->input('academic_period_id')) ? $request->input('academic_period_id') : null,
        ]);

        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('lecturer_workload_periods', 'code')->ignore($period?->id)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'minimum_sks' => ['required', 'numeric', 'min:0'],
            'maximum_sks' => ['required', 'numeric', 'gte:minimum_sks'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['academic_year_id'] = $validated['academic_year_id'] ? (int) $validated['academic_year_id'] : null;
        $validated['academic_period_id'] = $validated['academic_period_id'] ? (int) $validated['academic_period_id'] : null;
        $validated['starts_at'] = ($validated['starts_at'] ?? null) ?: null;
        $validated['ends_at'] = ($validated['ends_at'] ?? null) ?: null;
        $validated['minimum_sks'] = (float) $validated['minimum_sks'];
        $validated['maximum_sks'] = (float) $validated['maximum_sks'];
        $validated['notes'] = ($validated['notes'] ?? null) ?: null;

        return $validated;
    }

    private function sksRange(LecturerWorkloadPeriod $period): string
    {
        return $this->formatSks((float) $period->minimum_sks).' - '.$this->formatSks((float) $period->maximum_sks).' SKS';
    }

    private function formatSks(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : number_format($value, 2, ',', '.');
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function academicYearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => [
                'id' => (string) $year->id,
                'label' => $year->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function academicPeriodOptions(): array
    {
        return AcademicPeriod::query()
            ->orderByDesc('start_at')
            ->get(['id', 'name', 'type'])
            ->map(fn (AcademicPeriod $period) => [
                'id' => (string) $period->id,
                'label' => $period->name.' ('.strtoupper((string) $period->type).')',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return Collection::make($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
