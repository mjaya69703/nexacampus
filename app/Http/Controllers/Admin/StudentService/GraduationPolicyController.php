<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationPolicy;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD aturan yudisium — satu policy aktif per scope (global / prodi).
 */
class GraduationPolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'study_program_id' => 'nullable|integer',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,minimum_semester,minimum_passed_credits,minimum_gpa,is_active',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = GraduationPolicy::query()->with('studyProgram');

        if (filled($validated['q'] ?? null)) {
            $query->where('name', 'like', '%'.$validated['q'].'%');
        }

        if (filled($validated['study_program_id'] ?? null)) {
            $query->where('study_program_id', $validated['study_program_id']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'is_active';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction)->orderBy('name');

        $perPage = (int) ($validated['perPage'] ?? 10);
        $policies = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/GraduationPolicies/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Aturan Yudisium'),
            'can' => [
                'create' => ActivePermission::check('graduation-policy.create'),
                'update' => ActivePermission::check('graduation-policy.update'),
            ],
            'stats' => [
                'total' => GraduationPolicy::count(),
                'active' => GraduationPolicy::where('is_active', true)->count(),
                'global' => GraduationPolicy::whereNull('study_program_id')->count(),
                'program' => GraduationPolicy::whereNotNull('study_program_id')->count(),
            ],
            'data' => [
                'rows' => collect($policies->items())->values()->map(fn (GraduationPolicy $policy, int $i) => [
                    'id' => $policy->id,
                    'no' => ($policies->firstItem() ?? 0) + $i,
                    'name' => $policy->name,
                    'scope' => $policy->studyProgram?->name ?? 'Global',
                    'minimumSemester' => (int) $policy->minimum_semester,
                    'minimumCredits' => (int) $policy->minimum_passed_credits,
                    'minimumGpa' => number_format((float) $policy->minimum_gpa, 2),
                    'rules' => collect([
                        $policy->require_active_status ? 'Status aktif' : null,
                        $policy->require_no_financial_hold ? 'Bebas hold' : null,
                        $policy->require_no_incomplete_grade ? 'Tanpa nilai incomplete' : null,
                        $policy->require_open_yudisium_period ? 'Periode terbuka' : null,
                    ])->filter()->implode(', ') ?: '-',
                    'isActive' => (bool) $policy->is_active,
                    'editUrl' => route('admin.student-services.graduation-policies.edit', $policy->id),
                    'toggleUrl' => route('admin.student-services.graduation-policies.toggle', $policy->id),
                ])->all(),
                'currentPage' => $policies->currentPage(),
                'lastPage' => $policies->lastPage(),
                'perPage' => $policies->perPage(),
                'total' => $policies->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.graduation-policies.index'),
                'create' => route('admin.student-services.graduation-policies.create'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StudentServices/GraduationPolicies/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Tambah Aturan Yudisium'),
            'mode' => 'create',
            'policy' => [
                'name' => '',
                'study_program_id' => '',
                'minimum_semester' => 8,
                'minimum_passed_credits' => 144,
                'minimum_gpa' => 2.00,
                'require_active_status' => true,
                'require_no_financial_hold' => true,
                'require_no_incomplete_grade' => true,
                'require_open_yudisium_period' => true,
                'is_active' => true,
                'description' => '',
            ],
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.graduation-policies.index'),
                'submit' => route('admin.student-services.graduation-policies.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $this->ensureUniqueActiveScope($validated['study_program_id'] ?: null, (bool) $validated['is_active']);

        GraduationPolicy::create([
            ...$validated,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-services.graduation-policies.index')
            ->with('success', 'Aturan yudisium berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $graduationPolicy = GraduationPolicy::findOrFail($id);

        return Inertia::render('Admin/StudentServices/GraduationPolicies/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Edit Aturan Yudisium'),
            'mode' => 'edit',
            'policy' => [
                'id' => $graduationPolicy->id,
                'name' => $graduationPolicy->name,
                'study_program_id' => $graduationPolicy->study_program_id ?? '',
                'minimum_semester' => (int) $graduationPolicy->minimum_semester,
                'minimum_passed_credits' => (int) $graduationPolicy->minimum_passed_credits,
                'minimum_gpa' => (float) $graduationPolicy->minimum_gpa,
                'require_active_status' => (bool) $graduationPolicy->require_active_status,
                'require_no_financial_hold' => (bool) $graduationPolicy->require_no_financial_hold,
                'require_no_incomplete_grade' => (bool) $graduationPolicy->require_no_incomplete_grade,
                'require_open_yudisium_period' => (bool) $graduationPolicy->require_open_yudisium_period,
                'is_active' => (bool) $graduationPolicy->is_active,
                'description' => $graduationPolicy->description ?? '',
            ],
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.graduation-policies.index'),
                'submit' => route('admin.student-services.graduation-policies.update', $graduationPolicy->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $graduationPolicy = GraduationPolicy::findOrFail($id);
        $validated = $this->validatePayload($request);
        $this->ensureUniqueActiveScope($validated['study_program_id'] ?: null, (bool) $validated['is_active'], $graduationPolicy->id);

        $graduationPolicy->update([
            ...$validated,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-services.graduation-policies.index')
            ->with('success', 'Aturan yudisium berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $graduationPolicy = GraduationPolicy::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        if ((bool) $validated['is_active']) {
            $this->ensureUniqueActiveScope($graduationPolicy->study_program_id, true, $graduationPolicy->id);
        }

        $graduationPolicy->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status aturan yudisium berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'study_program_id' => ['nullable', Rule::exists('study_programs', 'id')],
            'minimum_semester' => ['required', 'integer', 'min:1', 'max:20'],
            'minimum_passed_credits' => ['required', 'integer', 'min:0', 'max:300'],
            'minimum_gpa' => ['required', 'numeric', 'min:0', 'max:4'],
            'require_active_status' => ['boolean'],
            'require_no_financial_hold' => ['boolean'],
            'require_no_incomplete_grade' => ['boolean'],
            'require_open_yudisium_period' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach (['require_active_status', 'require_no_financial_hold', 'require_no_incomplete_grade', 'require_open_yudisium_period', 'is_active'] as $flag) {
            $validated[$flag] = (bool) ($validated[$flag] ?? false);
        }

        return $validated;
    }

    private function ensureUniqueActiveScope(?int $studyProgramId, bool $isActive, ?int $ignoreId = null): void
    {
        if (! $isActive) {
            return;
        }

        $exists = GraduationPolicy::query()
            ->where('is_active', true)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->when($studyProgramId, fn ($query) => $query->where('study_program_id', $studyProgramId), fn ($query) => $query->whereNull('study_program_id'))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'study_program_id' => 'Sudah ada policy aktif untuk scope ini.',
            ]);
        }
    }
}
