<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\LecturerPerformanceRubric;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD rubrik performa dosen — paritas halaman Livewire lama.
 */
class LecturerPerformanceRubricController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:code,name,minimum_responses,target_workload_sks,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = LecturerPerformanceRubric::query();

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('notes', 'like', "%{$q}%"));
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'is_active';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction)->orderByDesc('updated_at');

        $perPage = (int) ($validated['perPage'] ?? 10);
        $rubrics = $query->paginate($perPage)->withQueryString();
        $active = LecturerPerformanceRubric::where('is_active', true)->latest('updated_at')->first();

        return Inertia::render('Admin/Organization/LecturerPerformanceRubrics/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Rubrik Performa'),
            'can' => [
                'create' => ActivePermission::check('lecturer-performance-rubric.create'),
                'update' => ActivePermission::check('lecturer-performance-rubric.update'),
                'delete' => ActivePermission::check('lecturer-performance-rubric.delete'),
            ],
            'stats' => [
                'total' => LecturerPerformanceRubric::count(),
                'activeCode' => $active?->code ?? '-',
                'activeName' => $active?->name ?? 'Belum Atur',
                'targetSks' => (float) ($active?->target_workload_sks ?? 12),
            ],
            'data' => [
                'rows' => collect($rubrics->items())->values()->map(fn (LecturerPerformanceRubric $rubric, int $i) => [
                    'id' => $rubric->id,
                    'no' => ($rubrics->firstItem() ?? 0) + $i,
                    'code' => $rubric->code,
                    'name' => $rubric->name,
                    'edomWeight' => (float) $rubric->edom_weight,
                    'teachingWeight' => (float) $rubric->teaching_weight,
                    'attendanceWeight' => (float) $rubric->attendance_weight,
                    'workloadWeight' => (float) $rubric->workload_weight,
                    'totalWeight' => $rubric->totalWeight(),
                    'minimumResponses' => (int) $rubric->minimum_responses,
                    'targetWorkloadSks' => (float) $rubric->target_workload_sks,
                    'isActive' => (bool) $rubric->is_active,
                    'notes' => $rubric->notes,
                    'editUrl' => route('admin.organization.lecturer-performance-rubrics.edit', $rubric->id),
                    'toggleUrl' => route('admin.organization.lecturer-performance-rubrics.toggle', $rubric->id),
                    'deleteUrl' => route('admin.organization.lecturer-performance-rubrics.destroy', $rubric->id),
                ])->all(),
                'currentPage' => $rubrics->currentPage(),
                'lastPage' => $rubrics->lastPage(),
                'perPage' => $rubrics->perPage(),
                'total' => $rubrics->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.organization.lecturer-performance-rubrics.index'),
                'create' => route('admin.organization.lecturer-performance-rubrics.create'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/LecturerPerformanceRubrics/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Rubrik Performa'),
            'mode' => 'create',
            'rubric' => [
                'code' => '',
                'name' => '',
                'edom_weight' => 40,
                'teaching_weight' => 30,
                'attendance_weight' => 20,
                'workload_weight' => 10,
                'minimum_responses' => 3,
                'target_workload_sks' => 12,
                'is_active' => true,
                'notes' => '',
            ],
            'urls' => [
                'index' => route('admin.organization.lecturer-performance-rubrics.index'),
                'submit' => route('admin.organization.lecturer-performance-rubrics.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        if ($validated['is_active']) {
            LecturerPerformanceRubric::query()->update(['is_active' => false]);
        }

        LecturerPerformanceRubric::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.lecturer-performance-rubrics.index')
            ->with('success', 'Rubrik performa berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $rubric = LecturerPerformanceRubric::findOrFail($id);

        return Inertia::render('Admin/Organization/LecturerPerformanceRubrics/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Rubrik Performa'),
            'mode' => 'edit',
            'rubric' => [
                'id' => $rubric->id,
                'code' => $rubric->code,
                'name' => $rubric->name,
                'edom_weight' => (float) $rubric->edom_weight,
                'teaching_weight' => (float) $rubric->teaching_weight,
                'attendance_weight' => (float) $rubric->attendance_weight,
                'workload_weight' => (float) $rubric->workload_weight,
                'minimum_responses' => (int) $rubric->minimum_responses,
                'target_workload_sks' => (float) $rubric->target_workload_sks,
                'is_active' => (bool) $rubric->is_active,
                'notes' => $rubric->notes ?? '',
            ],
            'urls' => [
                'index' => route('admin.organization.lecturer-performance-rubrics.index'),
                'submit' => route('admin.organization.lecturer-performance-rubrics.update', $rubric->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $rubric = LecturerPerformanceRubric::findOrFail($id);
        $validated = $this->validatePayload($request, $rubric);

        if ($validated['is_active']) {
            LecturerPerformanceRubric::query()->whereKeyNot($rubric->id)->update(['is_active' => false]);
        }

        $rubric->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.organization.lecturer-performance-rubrics.index')
            ->with('success', 'Rubrik performa berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $rubric = LecturerPerformanceRubric::findOrFail($id);
        $validated = $request->validate(['is_active' => 'boolean']);
        $isActive = (bool) ($validated['is_active'] ?? true);

        if ($isActive) {
            LecturerPerformanceRubric::query()->whereKeyNot($rubric->id)->update(['is_active' => false]);
        }

        $rubric->update([
            'is_active' => $isActive,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $isActive
            ? 'Rubrik aktif berhasil diperbarui.'
            : 'Rubrik berhasil dinonaktifkan.');
    }

    public function destroy(Request $request, int $id)
    {
        $rubric = LecturerPerformanceRubric::findOrFail($id);

        if ($rubric->is_active) {
            return back()->with('error', 'Tidak dapat menghapus rubrik yang sedang aktif digunakan.');
        }

        $rubric->delete();

        return redirect()->route('admin.organization.lecturer-performance-rubrics.index')
            ->with('success', 'Rubrik berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?LecturerPerformanceRubric $rubric = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('lecturer_performance_rubrics', 'code')->ignore($rubric?->id)],
            'name' => ['required', 'string', 'max:255'],
            'edom_weight' => ['required', 'numeric', 'min:0'],
            'teaching_weight' => ['required', 'numeric', 'min:0'],
            'attendance_weight' => ['required', 'numeric', 'min:0'],
            'workload_weight' => ['required', 'numeric', 'min:0'],
            'minimum_responses' => ['required', 'integer', 'min:1'],
            'target_workload_sks' => ['required', 'numeric', 'min:1'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach (['edom_weight', 'teaching_weight', 'attendance_weight', 'workload_weight', 'target_workload_sks'] as $field) {
            $validated[$field] = (float) $validated[$field];
        }

        $validated['minimum_responses'] = (int) $validated['minimum_responses'];
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['notes'] = ($validated['notes'] ?? null) ?: null;

        return $validated;
    }
}
