<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\EdomPeriod;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD periode EDOM — paritas halaman Livewire lama.
 */
class EdomPeriodController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draf',
        'open' => 'Dibuka',
        'closed' => 'Ditutup',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:draft,open,closed',
            'academic_year_id' => 'nullable|integer',
            'sort' => 'nullable|in:name,code,starts_at,ends_at,status,minimum_responses,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = EdomPeriod::query()->with(['academicYear', 'academicPeriod'])->withCount('responses');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhereHas('academicYear', fn ($year) => $year->where('name', 'like', "%{$q}%")));
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['academic_year_id'] ?? null)) {
            $query->where('academic_year_id', $validated['academic_year_id']);
        }

        $sort = $validated['sort'] ?? 'starts_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $periods = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/EdomPeriods/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Periode EDOM'),
            'can' => [
                'create' => ActivePermission::check('edom-period.create'),
                'update' => ActivePermission::check('edom-period.update'),
                'delete' => ActivePermission::check('edom-period.delete'),
            ],
            'stats' => [
                'total' => EdomPeriod::count(),
                'open' => EdomPeriod::where('status', 'open')->count(),
                'closed' => EdomPeriod::where('status', 'closed')->count(),
                'avgMin' => round((float) (EdomPeriod::avg('minimum_responses') ?: 0), 1),
            ],
            'data' => [
                'rows' => collect($periods->items())->values()->map(fn (EdomPeriod $period, int $i) => [
                    'id' => $period->id,
                    'no' => ($periods->firstItem() ?? 0) + $i,
                    'name' => $period->name,
                    'code' => $period->code,
                    'academicYearName' => $period->academicYear?->name ?? '-',
                    'academicPeriodName' => $period->academicPeriod?->name ?? '-',
                    'startsAt' => $period->starts_at?->format('d M Y') ?? '-',
                    'endsAt' => $period->ends_at?->format('d M Y') ?? '-',
                    'minimumResponses' => (int) $period->minimum_responses,
                    'status' => $period->status,
                    'statusLabel' => self::STATUSES[$period->status] ?? $period->status,
                    'responsesCount' => (int) $period->responses_count,
                    'editUrl' => route('admin.organization.edom-periods.edit', $period->id),
                    'deleteUrl' => route('admin.organization.edom-periods.destroy', $period->id),
                ])->all(),
                'currentPage' => $periods->currentPage(),
                'lastPage' => $periods->lastPage(),
                'perPage' => $periods->perPage(),
                'total' => $periods->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'academic_year_id' => $validated['academic_year_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'academicYears' => $this->academicYears(),
            'statuses' => $this->optionList(self::STATUSES),
            'urls' => [
                'index' => route('admin.organization.edom-periods.index'),
                'create' => route('admin.organization.edom-periods.create'),
                'bulkDestroy' => route('admin.organization.edom-periods.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/EdomPeriods/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Periode EDOM'),
            'mode' => 'create',
            'period' => [
                'name' => '',
                'code' => '',
                'academic_year_id' => '',
                'academic_period_id' => '',
                'starts_at' => '',
                'ends_at' => '',
                'status' => 'draft',
                'minimum_responses' => 3,
                'notes' => '',
            ],
            'academicYears' => $this->academicYears(),
            'academicPeriods' => $this->academicPeriods(),
            'statuses' => $this->optionList(self::STATUSES),
            'urls' => [
                'index' => route('admin.organization.edom-periods.index'),
                'submit' => route('admin.organization.edom-periods.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        EdomPeriod::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.edom-periods.index')
            ->with('success', 'Periode EDOM berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $period = EdomPeriod::findOrFail($id);

        return Inertia::render('Admin/Organization/EdomPeriods/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Periode EDOM'),
            'mode' => 'edit',
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'code' => $period->code,
                'academic_year_id' => $period->academic_year_id ?? '',
                'academic_period_id' => $period->academic_period_id ?? '',
                'starts_at' => $period->starts_at?->format('Y-m-d') ?? '',
                'ends_at' => $period->ends_at?->format('Y-m-d') ?? '',
                'status' => $period->status,
                'minimum_responses' => (int) $period->minimum_responses,
                'notes' => $period->notes ?? '',
            ],
            'academicYears' => $this->academicYears(),
            'academicPeriods' => $this->academicPeriods(),
            'statuses' => $this->optionList(self::STATUSES),
            'urls' => [
                'index' => route('admin.organization.edom-periods.index'),
                'submit' => route('admin.organization.edom-periods.update', $period->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $period = EdomPeriod::findOrFail($id);
        $validated = $this->validatePayload($request, $period);

        $period->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.edom-periods.index')
            ->with('success', 'Periode EDOM berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $period = EdomPeriod::findOrFail($id);

        if ($period->responses()->exists()) {
            return back()->with('error', 'Periode EDOM ini tidak dapat dihapus karena sudah memiliki data respons dari mahasiswa.');
        }

        $period->update(['deleted_by' => $request->user()->id]);
        $period->delete();

        return redirect()->route('admin.organization.edom-periods.index')
            ->with('success', 'Periode EDOM "'.$period->name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:edom_periods,id',
        ]);

        $periods = EdomPeriod::whereIn('id', $validated['ids'])->get();

        foreach ($periods as $period) {
            $period->update(['deleted_by' => $request->user()->id]);
            $period->delete();
        }

        return back()->with('success', $periods->count().' periode EDOM berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?EdomPeriod $period = null): array
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'academic_period_id' => ['nullable', 'integer', 'exists:academic_periods,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('edom_periods', 'code')->ignore($period?->id)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'minimum_responses' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['academic_year_id'] = ($validated['academic_year_id'] ?? null) ?: null;
        $validated['academic_period_id'] = ($validated['academic_period_id'] ?? null) ?: null;
        $validated['starts_at'] = ($validated['starts_at'] ?? null) ?: null;
        $validated['ends_at'] = ($validated['ends_at'] ?? null) ?: null;
        $validated['notes'] = ($validated['notes'] ?? null) ?: null;

        return $validated;
    }

    /**
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function academicYears(): array
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
                'code' => $year->code,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, type: string}>
     */
    private function academicPeriods(): array
    {
        return AcademicPeriod::query()
            ->orderByDesc('start_at')
            ->get(['id', 'name', 'type'])
            ->map(fn (AcademicPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'type' => $period->type,
            ])
            ->all();
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
