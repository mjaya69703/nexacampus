<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Organization\WorkUnit;
use App\Models\StudentService\StudentComplaintCategory;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD kategori pengaduan mahasiswa — SLA & unit kerja default.
 */
class StudentComplaintCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'default_work_unit_id' => 'nullable|integer',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,default_sla_hours,complaints_count,is_active',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentComplaintCategory::query()
            ->with('defaultWorkUnit')
            ->withCount('complaints');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
        }

        if (filled($validated['default_work_unit_id'] ?? null)) {
            $query->where('default_work_unit_id', $validated['default_work_unit_id']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $categories = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/ComplaintCategories/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Kategori Pengaduan'),
            'can' => [
                'create' => ActivePermission::check('student-complaint-category.create'),
                'update' => ActivePermission::check('student-complaint-category.update'),
                'delete' => ActivePermission::check('student-complaint-category.delete'),
            ],
            'stats' => [
                'total' => StudentComplaintCategory::count(),
                'active' => StudentComplaintCategory::where('is_active', true)->count(),
                'tickets' => \App\Models\StudentService\StudentComplaint::count(),
                'avgSla' => (int) round((float) StudentComplaintCategory::avg('default_sla_hours')),
            ],
            'data' => [
                'rows' => collect($categories->items())->values()->map(fn (StudentComplaintCategory $category, int $i) => [
                    'id' => $category->id,
                    'no' => ($categories->firstItem() ?? 0) + $i,
                    'name' => $category->name,
                    'code' => $category->code,
                    'workUnit' => $category->defaultWorkUnit?->name ?? '-',
                    'slaHours' => (int) $category->default_sla_hours,
                    'complaintsCount' => (int) $category->complaints_count,
                    'isActive' => (bool) $category->is_active,
                    'editUrl' => route('admin.student-services.complaint-categories.edit', $category->id),
                    'toggleUrl' => route('admin.student-services.complaint-categories.toggle', $category->id),
                    'deleteUrl' => route('admin.student-services.complaint-categories.destroy', $category->id),
                ])->all(),
                'currentPage' => $categories->currentPage(),
                'lastPage' => $categories->lastPage(),
                'perPage' => $categories->perPage(),
                'total' => $categories->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'default_work_unit_id' => $validated['default_work_unit_id'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'workUnits' => WorkUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.complaint-categories.index'),
                'create' => route('admin.student-services.complaint-categories.create'),
                'bulkDestroy' => route('admin.student-services.complaint-categories.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StudentServices/ComplaintCategories/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Tambah Kategori Pengaduan'),
            'mode' => 'create',
            'category' => [
                'name' => '',
                'code' => '',
                'default_work_unit_id' => '',
                'description' => '',
                'default_sla_hours' => 48,
                'is_active' => true,
            ],
            'workUnits' => WorkUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.complaint-categories.index'),
                'submit' => route('admin.student-services.complaint-categories.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        StudentComplaintCategory::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'default_work_unit_id' => $validated['default_work_unit_id'] ?: null,
        ]);

        return redirect()->route('admin.student-services.complaint-categories.index')
            ->with('success', 'Kategori pengaduan berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $studentComplaintCategory = StudentComplaintCategory::findOrFail($id);

        return Inertia::render('Admin/StudentServices/ComplaintCategories/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Edit Kategori Pengaduan'),
            'mode' => 'edit',
            'category' => [
                'id' => $studentComplaintCategory->id,
                'name' => $studentComplaintCategory->name,
                'code' => $studentComplaintCategory->code,
                'default_work_unit_id' => $studentComplaintCategory->default_work_unit_id ?? '',
                'description' => $studentComplaintCategory->description ?? '',
                'default_sla_hours' => (int) $studentComplaintCategory->default_sla_hours,
                'is_active' => (bool) $studentComplaintCategory->is_active,
            ],
            'workUnits' => WorkUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.complaint-categories.index'),
                'submit' => route('admin.student-services.complaint-categories.update', $studentComplaintCategory->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $studentComplaintCategory = StudentComplaintCategory::findOrFail($id);
        $validated = $this->validatePayload($request, $studentComplaintCategory);

        $studentComplaintCategory->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'default_work_unit_id' => $validated['default_work_unit_id'] ?: null,
        ]);

        return redirect()->route('admin.student-services.complaint-categories.index')
            ->with('success', 'Kategori pengaduan berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $studentComplaintCategory = StudentComplaintCategory::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $studentComplaintCategory->update(['is_active' => (bool) $validated['is_active']]);

        return back()->with('success', 'Status kategori pengaduan berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $studentComplaintCategory = StudentComplaintCategory::findOrFail($id);
        $name = $studentComplaintCategory->name;
        $studentComplaintCategory->delete();

        return redirect()->route('admin.student-services.complaint-categories.index')
            ->with('success', 'Kategori "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_complaint_categories,id',
        ]);

        $count = StudentComplaintCategory::whereIn('id', $validated['ids'])->count();
        StudentComplaintCategory::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' kategori pengaduan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?StudentComplaintCategory $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', Rule::unique('student_complaint_categories', 'code')->ignore($category?->id)],
            'default_work_unit_id' => ['nullable', 'exists:work_units,id'],
            'description' => ['nullable', 'string'],
            'default_sla_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
