<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\GraduationBatch;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentService\GraduationApplicationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * CRUD batch yudisium + bulk finalize pengajuan approved.
 */
class GraduationBatchController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'academic_period_id' => 'nullable|integer',
            'study_program_id' => 'nullable|integer',
            'status' => 'nullable|in:draft,open,review,finalized,cancelled',
            'sort' => 'nullable|in:name,code,yudisium_date,status,applications_count',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = GraduationBatch::query()
            ->with(['academicPeriod.academicYear', 'studyProgram'])
            ->withCount([
                'applications',
                'applications as approved_count' => fn ($sub) => $sub->where('status', 'approved'),
                'applications as finalized_count' => fn ($sub) => $sub->where('status', 'finalized'),
            ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['academic_period_id'] ?? null)) {
            $query->where('academic_period_id', $validated['academic_period_id']);
        }

        if (filled($validated['study_program_id'] ?? null)) {
            $query->where('study_program_id', $validated['study_program_id']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $batches = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/GraduationBatches/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Batch Yudisium'),
            'can' => [
                'create' => ActivePermission::check('graduation-batch.create'),
                'update' => ActivePermission::check('graduation-batch.update'),
                'view' => ActivePermission::check('graduation-batch.view'),
            ],
            'stats' => [
                'total' => GraduationBatch::count(),
                'open' => GraduationBatch::where('status', 'open')->count(),
                'review' => GraduationBatch::where('status', 'review')->count(),
                'finalized' => GraduationBatch::where('status', 'finalized')->count(),
            ],
            'data' => [
                'rows' => collect($batches->items())->values()->map(fn (GraduationBatch $batch, int $i) => [
                    'id' => $batch->id,
                    'no' => ($batches->firstItem() ?? 0) + $i,
                    'name' => $batch->name,
                    'code' => $batch->code,
                    'period' => $batch->academicPeriod?->name ?? '-',
                    'scope' => $batch->studyProgram?->name ?? 'Semua Program Studi',
                    'yudisiumDate' => $batch->yudisium_date?->format('d M Y') ?? '-',
                    'status' => $batch->status,
                    'applicationsCount' => (int) $batch->applications_count,
                    'approvedCount' => (int) $batch->approved_count,
                    'finalizedCount' => (int) $batch->finalized_count,
                    'showUrl' => route('admin.student-services.graduation-batches.show', $batch->id),
                    'editUrl' => route('admin.student-services.graduation-batches.edit', $batch->id),
                ])->all(),
                'currentPage' => $batches->currentPage(),
                'lastPage' => $batches->lastPage(),
                'perPage' => $batches->perPage(),
                'total' => $batches->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'academic_period_id' => $validated['academic_period_id'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'academicPeriods' => AcademicPeriod::query()->with('academicYear')->where('type', 'Yudisium')->orderByDesc('start_at')->get()
                ->map(fn (AcademicPeriod $period) => [
                    'id' => $period->id,
                    'name' => $period->name.' - '.($period->academicYear?->name ?? '-'),
                ])->all(),
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.graduation-batches.index'),
                'create' => route('admin.student-services.graduation-batches.create'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StudentServices/GraduationBatches/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Buat Batch Yudisium'),
            'mode' => 'create',
            'batch' => [
                'academic_period_id' => '',
                'study_program_id' => '',
                'name' => '',
                'code' => '',
                'yudisium_date' => '',
                'sk_number' => '',
                'sk_date' => '',
                'status' => 'draft',
                'notes' => '',
            ],
            ...$this->formOptions(),
            'urls' => [
                'index' => route('admin.student-services.graduation-batches.index'),
                'submit' => route('admin.student-services.graduation-batches.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        GraduationBatch::create($validated);

        return redirect()->route('admin.student-services.graduation-batches.index')
            ->with('success', 'Batch yudisium berhasil dibuat.');
    }

    public function show(Request $request, int $id): Response
    {
        $graduationBatch = GraduationBatch::findOrFail($id);
        $graduationBatch->load([
            'academicPeriod.academicYear',
            'studyProgram',
            'applications.studentProfile.user',
            'applications.studentProfile.studyProgram',
        ])->loadCount([
            'applications',
            'applications as approved_count' => fn ($query) => $query->where('status', 'approved'),
            'applications as finalized_count' => fn ($query) => $query->where('status', 'finalized'),
        ]);

        return Inertia::render('Admin/StudentServices/GraduationBatches/Show', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Detail Batch Yudisium'),
            'can' => [
                'update' => ActivePermission::check('graduation-batch.update'),
                'viewApplication' => ActivePermission::check('graduation-application.view'),
            ],
            'batch' => [
                'id' => $graduationBatch->id,
                'name' => $graduationBatch->name,
                'code' => $graduationBatch->code,
                'period' => $graduationBatch->academicPeriod?->name ?? '-',
                'academicYear' => $graduationBatch->academicPeriod?->academicYear?->name ?? '-',
                'scope' => $graduationBatch->studyProgram?->name ?? 'Semua Program Studi',
                'yudisiumDate' => $graduationBatch->yudisium_date?->format('d M Y') ?? null,
                'skNumber' => $graduationBatch->sk_number,
                'skDate' => $graduationBatch->sk_date?->format('d M Y'),
                'status' => $graduationBatch->status,
                'notes' => $graduationBatch->notes,
                'applicationsCount' => (int) $graduationBatch->applications_count,
                'approvedCount' => (int) $graduationBatch->approved_count,
                'finalizedCount' => (int) $graduationBatch->finalized_count,
                'applications' => $graduationBatch->applications
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (GraduationApplication $application) => [
                        'id' => $application->id,
                        'number' => $application->application_number,
                        'student' => $application->studentProfile?->user?->name ?? '-',
                        'nim' => $application->studentProfile?->nim ?? '-',
                        'studyProgram' => $application->studentProfile?->studyProgram?->name ?? '-',
                        'status' => $application->status,
                        'submittedAt' => $application->created_at?->format('d M Y H:i'),
                        'showUrl' => route('admin.student-services.graduation-applications.show', $application->id),
                    ])->all(),
            ],
            'urls' => [
                'index' => route('admin.student-services.graduation-batches.index'),
                'edit' => route('admin.student-services.graduation-batches.edit', $graduationBatch->id),
                'bulkFinalize' => route('admin.student-services.graduation-batches.bulk-finalize', $graduationBatch->id),
            ],
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $graduationBatch = GraduationBatch::findOrFail($id);

        return Inertia::render('Admin/StudentServices/GraduationBatches/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Edit Batch Yudisium'),
            'mode' => 'edit',
            'batch' => [
                'id' => $graduationBatch->id,
                'academic_period_id' => $graduationBatch->academic_period_id,
                'study_program_id' => $graduationBatch->study_program_id ?? '',
                'name' => $graduationBatch->name,
                'code' => $graduationBatch->code,
                'yudisium_date' => $graduationBatch->yudisium_date?->format('Y-m-d') ?? '',
                'sk_number' => $graduationBatch->sk_number ?? '',
                'sk_date' => $graduationBatch->sk_date?->format('Y-m-d') ?? '',
                'status' => $graduationBatch->status,
                'notes' => $graduationBatch->notes ?? '',
            ],
            ...$this->formOptions(),
            'urls' => [
                'index' => route('admin.student-services.graduation-batches.index'),
                'submit' => route('admin.student-services.graduation-batches.update', $graduationBatch->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $graduationBatch = GraduationBatch::findOrFail($id);
        $validated = $this->validatePayload($request, $graduationBatch);

        $graduationBatch->update($validated);

        return redirect()->route('admin.student-services.graduation-batches.show', $graduationBatch->id)
            ->with('success', 'Batch yudisium berhasil diperbarui.');
    }

    public function bulkFinalize(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-batch.update'), 403);

        $graduationBatch = GraduationBatch::findOrFail($id);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $count = app(GraduationApplicationService::class)
                ->finalizeApprovedBatch($graduationBatch, $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', $count.' pengajuan approved berhasil difinalisasi.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?GraduationBatch $batch = null): array
    {
        $validated = $request->validate([
            'academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('graduation_batches', 'code')->ignore($batch?->id)],
            'yudisium_date' => ['nullable', 'date'],
            'sk_number' => ['nullable', 'string', 'max:255'],
            'sk_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,open,review,finalized,cancelled'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $validated['study_program_id'] = $validated['study_program_id'] ?: null;
        $validated['yudisium_date'] = $validated['yudisium_date'] ?: null;
        $validated['sk_number'] = $validated['sk_number'] ?: null;
        $validated['sk_date'] = $validated['sk_date'] ?: null;
        $validated['notes'] = $validated['notes'] ?: null;

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'academicPeriods' => AcademicPeriod::query()->with('academicYear')->where('type', 'Yudisium')->orderByDesc('start_at')->get()
                ->map(fn (AcademicPeriod $period) => [
                    'id' => $period->id,
                    'name' => $period->name.' - '.($period->academicYear?->name ?? '-'),
                ])->all(),
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'statuses' => [
                ['id' => 'draft', 'name' => 'Draf'],
                ['id' => 'open', 'name' => 'Dibuka'],
                ['id' => 'review', 'name' => 'Review'],
                ['id' => 'finalized', 'name' => 'Final'],
                ['id' => 'cancelled', 'name' => 'Dibatalkan'],
            ],
        ];
    }
}
