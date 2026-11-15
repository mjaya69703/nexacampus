<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudyProgram;
use App\Models\StudentService\GraduationDocumentRequirement;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD syarat dokumen yudisium — scope global atau per program studi.
 */
class GraduationDocumentRequirementController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'study_program_id' => 'nullable|integer',
            'is_required' => 'nullable|in:0,1',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:label,document_type,sort_order,max_size_kb,is_active',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = GraduationDocumentRequirement::query()->with('studyProgram');

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('label', 'like', "%{$q}%")->orWhere('document_type', 'like', "%{$q}%"));
        }

        if (filled($validated['study_program_id'] ?? null)) {
            $query->where('study_program_id', $validated['study_program_id']);
        }

        if (isset($validated['is_required']) && $validated['is_required'] !== '') {
            $query->where('is_required', (bool) $validated['is_required']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'sort_order';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction)->orderBy('label');

        $perPage = (int) ($validated['perPage'] ?? 10);
        $requirements = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/GraduationDocumentRequirements/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Dokumen Yudisium'),
            'can' => [
                'create' => ActivePermission::check('graduation-document-requirement.create'),
                'update' => ActivePermission::check('graduation-document-requirement.update'),
            ],
            'stats' => [
                'total' => GraduationDocumentRequirement::count(),
                'required' => GraduationDocumentRequirement::where('is_required', true)->count(),
                'active' => GraduationDocumentRequirement::where('is_active', true)->count(),
                'global' => GraduationDocumentRequirement::whereNull('study_program_id')->count(),
            ],
            'data' => [
                'rows' => collect($requirements->items())->values()->map(fn (GraduationDocumentRequirement $requirement, int $i) => [
                    'id' => $requirement->id,
                    'no' => ($requirements->firstItem() ?? 0) + $i,
                    'label' => $requirement->label,
                    'documentType' => $requirement->document_type,
                    'scope' => $requirement->studyProgram?->name ?? 'Global',
                    'isRequired' => (bool) $requirement->is_required,
                    'allowedExtensions' => $requirement->allowed_extensions ?: 'pdf,jpg,jpeg,png',
                    'maxSizeKb' => (int) $requirement->max_size_kb,
                    'sortOrder' => (int) $requirement->sort_order,
                    'isActive' => (bool) $requirement->is_active,
                    'editUrl' => route('admin.student-services.graduation-document-requirements.edit', $requirement->id),
                    'toggleUrl' => route('admin.student-services.graduation-document-requirements.toggle', $requirement->id),
                ])->all(),
                'currentPage' => $requirements->currentPage(),
                'lastPage' => $requirements->lastPage(),
                'perPage' => $requirements->perPage(),
                'total' => $requirements->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'is_required' => $validated['is_required'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.graduation-document-requirements.index'),
                'create' => route('admin.student-services.graduation-document-requirements.create'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StudentServices/GraduationDocumentRequirements/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Tambah Dokumen Yudisium'),
            'mode' => 'create',
            'requirement' => [
                'study_program_id' => '',
                'document_type' => '',
                'label' => '',
                'is_required' => true,
                'allowed_extensions' => 'pdf,jpg,jpeg,png',
                'max_size_kb' => 5120,
                'sort_order' => 0,
                'is_active' => true,
                'description' => '',
            ],
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.graduation-document-requirements.index'),
                'submit' => route('admin.student-services.graduation-document-requirements.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        GraduationDocumentRequirement::create([
            ...$validated,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'allowed_extensions' => $this->sanitizeExtensions($validated['allowed_extensions'] ?? null),
            'description' => $validated['description'] ?: null,
        ]);

        return redirect()->route('admin.student-services.graduation-document-requirements.index')
            ->with('success', 'Syarat dokumen yudisium berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $graduationDocumentRequirement = GraduationDocumentRequirement::findOrFail($id);

        return Inertia::render('Admin/StudentServices/GraduationDocumentRequirements/Form', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Edit Dokumen Yudisium'),
            'mode' => 'edit',
            'requirement' => [
                'id' => $graduationDocumentRequirement->id,
                'study_program_id' => $graduationDocumentRequirement->study_program_id ?? '',
                'document_type' => $graduationDocumentRequirement->document_type,
                'label' => $graduationDocumentRequirement->label,
                'is_required' => (bool) $graduationDocumentRequirement->is_required,
                'allowed_extensions' => $graduationDocumentRequirement->allowed_extensions ?? '',
                'max_size_kb' => (int) $graduationDocumentRequirement->max_size_kb,
                'sort_order' => (int) $graduationDocumentRequirement->sort_order,
                'is_active' => (bool) $graduationDocumentRequirement->is_active,
                'description' => $graduationDocumentRequirement->description ?? '',
            ],
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'urls' => [
                'index' => route('admin.student-services.graduation-document-requirements.index'),
                'submit' => route('admin.student-services.graduation-document-requirements.update', $graduationDocumentRequirement->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $graduationDocumentRequirement = GraduationDocumentRequirement::findOrFail($id);
        $validated = $this->validatePayload($request, $graduationDocumentRequirement);

        $graduationDocumentRequirement->update([
            ...$validated,
            'study_program_id' => $validated['study_program_id'] ?: null,
            'allowed_extensions' => $this->sanitizeExtensions($validated['allowed_extensions'] ?? null),
            'description' => $validated['description'] ?: null,
        ]);

        return redirect()->route('admin.student-services.graduation-document-requirements.index')
            ->with('success', 'Syarat dokumen yudisium berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id)
    {
        $graduationDocumentRequirement = GraduationDocumentRequirement::findOrFail($id);
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $graduationDocumentRequirement->update(['is_active' => (bool) $validated['is_active']]);

        return back()->with('success', 'Status syarat dokumen berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?GraduationDocumentRequirement $requirement = null): array
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'document_type' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('graduation_document_requirements', 'document_type')
                    ->ignore($requirement?->id)
                    ->where(fn ($query) => $query->where('study_program_id', $request->input('study_program_id') ?: null)),
            ],
            'label' => ['required', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'allowed_extensions' => ['nullable', 'string', 'max:255'],
            'max_size_kb' => ['nullable', 'integer', 'min:1', 'max:51200'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['is_required'] = (bool) ($validated['is_required'] ?? false);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    private function sanitizeExtensions(?string $value): string
    {
        $extensions = collect(explode(',', (string) $value))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter(fn (string $extension) => in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true))
            ->unique()
            ->values();

        return $extensions->isEmpty() ? 'pdf,jpg,jpeg,png' : $extensions->implode(',');
    }
}
