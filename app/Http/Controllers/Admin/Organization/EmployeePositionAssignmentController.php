<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\EmployeePositionAssignment;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\EmployeePositionAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD penugasan jabatan pegawai — paritas halaman Livewire lama termasuk
 * scope fakultas/prodi/unit kerja dan sinkronisasi keanggotaan unit kerja.
 */
class EmployeePositionAssignmentController extends Controller
{
    private const SCOPE_LABELS = [
        'none' => 'Tanpa Scope (Global)',
        'faculty' => 'Fakultas',
        'study_program' => 'Program Studi',
        'work_unit' => 'Unit Kerja',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_primary' => 'nullable|in:0,1',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:starts_at,ends_at,is_primary,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = EmployeePositionAssignment::query()
            ->with(['employeeProfile.user', 'position', 'faculty', 'studyProgram', 'workUnit']);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->whereHas('employeeProfile.user', function ($user) use ($term) {
                    $user->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('identity_number', 'like', $term);
                })
                    ->orWhereHas('employeeProfile', fn ($profile) => $profile->where('employee_number', 'like', $term))
                    ->orWhereHas('position', function ($position) use ($term) {
                        $position->where('name', 'like', $term)->orWhere('code', 'like', $term);
                    })
                    ->orWhereHas('faculty', fn ($faculty) => $faculty->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('studyProgram', fn ($program) => $program->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('workUnit', fn ($unit) => $unit->where('name', 'like', $term)->orWhere('code', 'like', $term));
            });
        }

        if (isset($validated['is_primary']) && $validated['is_primary'] !== '') {
            $query->where('is_primary', (bool) $validated['is_primary']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $assignments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/EmployeePositionAssignments/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Penugasan Jabatan'),
            'can' => [
                'create' => ActivePermission::check('employee-position-assignment.create'),
                'update' => ActivePermission::check('employee-position-assignment.update'),
                'delete' => ActivePermission::check('employee-position-assignment.delete'),
            ],
            'stats' => [
                'total' => EmployeePositionAssignment::count(),
                'active' => EmployeePositionAssignment::where('is_active', true)->count(),
                'primary' => EmployeePositionAssignment::where('is_primary', true)->count(),
                'scoped' => EmployeePositionAssignment::query()
                    ->where(function ($query) {
                        $query->whereNotNull('faculty_id')
                            ->orWhereNotNull('study_program_id')
                            ->orWhereNotNull('work_unit_id');
                    })
                    ->count(),
            ],
            'data' => [
                'rows' => collect($assignments->items())->values()->map(fn (EmployeePositionAssignment $assignment, int $i) => [
                    'id' => $assignment->id,
                    'no' => ($assignments->firstItem() ?? 0) + $i,
                    'employeeName' => $assignment->employeeProfile?->user?->name ?? '-',
                    'employeeNumber' => $assignment->employeeProfile?->employee_number,
                    'positionName' => $assignment->position?->name ?? '-',
                    'positionCode' => $assignment->position?->code,
                    'scopeType' => $assignment->position?->scope_type ?? 'none',
                    'scopeTypeLabel' => self::SCOPE_LABELS[$assignment->position?->scope_type ?? 'none'] ?? 'Tanpa Scope (Global)',
                    'scopeName' => $assignment->faculty?->name ?? $assignment->studyProgram?->name ?? $assignment->workUnit?->name,
                    'startsAt' => $assignment->starts_at?->format('d M Y'),
                    'endsAt' => $assignment->ends_at?->format('d M Y'),
                    'isPrimary' => (bool) $assignment->is_primary,
                    'isActive' => (bool) $assignment->is_active,
                    'createdAt' => $assignment->created_at?->format('d M Y'),
                    'editUrl' => route('admin.organization.employee-position-assignments.edit', $assignment->id),
                    'toggleUrl' => route('admin.organization.employee-position-assignments.toggle', $assignment->id),
                    'deleteUrl' => route('admin.organization.employee-position-assignments.destroy', $assignment->id),
                ])->all(),
                'currentPage' => $assignments->currentPage(),
                'lastPage' => $assignments->lastPage(),
                'perPage' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_primary' => $validated['is_primary'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'urls' => [
                'index' => route('admin.organization.employee-position-assignments.index'),
                'create' => route('admin.organization.employee-position-assignments.create'),
                'bulkDestroy' => route('admin.organization.employee-position-assignments.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/EmployeePositionAssignments/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Penugasan Jabatan'),
            'mode' => 'create',
            'assignment' => [
                'employee' => null,
                'organizational_position_id' => '',
                'faculty_id' => '',
                'study_program_id' => '',
                'work_unit_id' => '',
                'starts_at' => now()->toDateString(),
                'ends_at' => '',
                'is_primary' => false,
                'is_active' => true,
                'notes' => '',
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.organization.employee-position-assignments.index'),
                'submit' => route('admin.organization.employee-position-assignments.store'),
                'searchEmployees' => route('admin.organization.employee-position-assignments.search-employees'),
            ],
        ]);
    }

    public function store(Request $request, EmployeePositionAssignmentService $service)
    {
        $validated = $this->validatePayload($request);
        $payload = $this->payloadForScope($validated);

        $service->create(array_merge($payload, [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return redirect()->route('admin.organization.employee-position-assignments.index')
            ->with('success', 'Penugasan jabatan berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $assignment = EmployeePositionAssignment::with(['employeeProfile.user', 'position'])->findOrFail($id);

        return Inertia::render('Admin/Organization/EmployeePositionAssignments/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Penugasan Jabatan'),
            'mode' => 'edit',
            'assignment' => [
                'id' => $assignment->id,
                'employee' => [
                    'id' => $assignment->employeeProfile?->id,
                    'name' => $assignment->employeeProfile?->user?->name ?? '-',
                    'email' => $assignment->employeeProfile?->user?->email,
                    'employee_number' => $assignment->employeeProfile?->employee_number,
                ],
                'organizational_position_id' => (string) $assignment->organizational_position_id,
                'faculty_id' => $assignment->faculty_id ? (string) $assignment->faculty_id : '',
                'study_program_id' => $assignment->study_program_id ? (string) $assignment->study_program_id : '',
                'work_unit_id' => $assignment->work_unit_id ? (string) $assignment->work_unit_id : '',
                'starts_at' => $assignment->starts_at?->format('Y-m-d') ?? '',
                'ends_at' => $assignment->ends_at?->format('Y-m-d') ?? '',
                'is_primary' => (bool) $assignment->is_primary,
                'is_active' => (bool) $assignment->is_active,
                'notes' => $assignment->notes ?? '',
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.organization.employee-position-assignments.index'),
                'submit' => route('admin.organization.employee-position-assignments.update', $assignment->id),
                'searchEmployees' => route('admin.organization.employee-position-assignments.search-employees'),
            ],
        ]);
    }

    public function update(Request $request, int $id, EmployeePositionAssignmentService $service)
    {
        $assignment = EmployeePositionAssignment::findOrFail($id);
        $validated = $this->validatePayload($request, $assignment);
        $payload = $this->payloadForScope($validated);

        $service->update($assignment, array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]));

        return redirect()->route('admin.organization.employee-position-assignments.index')
            ->with('success', 'Penugasan jabatan berhasil diperbarui.');
    }

    public function toggle(Request $request, int $id, EmployeePositionAssignmentService $service)
    {
        $validated = $request->validate([
            'field' => ['required', Rule::in(['is_active', 'is_primary'])],
            'value' => ['required', 'boolean'],
        ]);

        $assignment = EmployeePositionAssignment::findOrFail($id);

        $assignment->update([
            $validated['field'] => (bool) $validated['value'],
            'updated_by' => $request->user()->id,
        ]);

        if ($validated['field'] === 'is_active') {
            $service->syncWorkUnitMembership($assignment->refresh());
        }

        return back()->with('success', 'Penugasan jabatan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $assignment = EmployeePositionAssignment::findOrFail($id);

        $assignment->update(['deleted_by' => $request->user()->id]);
        $assignment->delete();

        return redirect()->route('admin.organization.employee-position-assignments.index')
            ->with('success', 'Penugasan jabatan berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:employee_position_assignments,id',
        ]);

        $assignments = EmployeePositionAssignment::whereIn('id', $validated['ids'])->get();

        foreach ($assignments as $assignment) {
            $assignment->update(['deleted_by' => $request->user()->id]);
            $assignment->delete();
        }

        return back()->with('success', $assignments->count().' penugasan jabatan berhasil dihapus.');
    }

    /**
     * Endpoint pencarian pegawai aktif untuk picker penugasan.
     */
    public function searchEmployees(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'exclude' => 'nullable|string|max:2000',
        ]);

        $exclude = collect(explode(',', (string) ($validated['exclude'] ?? '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $q = $validated['q'] ?? '';

        $options = EmployeeProfile::query()
            ->with(['user', 'primaryWorkUnit'])
            ->where('is_active', true)
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->when(filled($q), function ($query) use ($q) {
                $search = '%'.$q.'%';

                $query->where(function ($sub) use ($search) {
                    $sub->where('employee_number', 'like', $search)
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search)
                                ->orWhere('email', 'like', $search)
                                ->orWhere('username', 'like', $search)
                                ->orWhere('code', 'like', $search)
                                ->orWhere('identity_number', 'like', $search);
                        });
                });
            })
            ->limit(12)
            ->get()
            ->map(fn (EmployeeProfile $profile) => [
                'id' => $profile->id,
                'label' => $profile->user?->name ?? '-',
                'meta' => [
                    'email' => $profile->user?->email,
                    'employee_number' => $profile->employee_number,
                    'work_unit' => $profile->primaryWorkUnit?->name,
                ],
            ])
            ->values()
            ->all();

        return response()->json(['options' => $options]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?EmployeePositionAssignment $assignment = null): array
    {
        return $request->validate([
            'employee_profile_id' => ['required', 'integer', 'exists:employee_profiles,id'],
            'organizational_position_id' => ['required', 'integer', 'exists:organizational_positions,id'],
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'work_unit_id' => ['nullable', 'integer', 'exists:work_units,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * Bangun payload sesuai scope jabatan (kolom scope lain dikosongkan).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadForScope(array $validated): array
    {
        $position = OrganizationalPosition::findOrFail($validated['organizational_position_id']);
        $errors = [];

        if ($position->scope_type === 'faculty' && blank($validated['faculty_id'] ?? null)) {
            $errors['faculty_id'] = 'Scope fakultas wajib dipilih untuk jabatan ini.';
        }

        if ($position->scope_type === 'study_program' && blank($validated['study_program_id'] ?? null)) {
            $errors['study_program_id'] = 'Scope program studi wajib dipilih untuk jabatan ini.';
        }

        if ($position->scope_type === 'work_unit' && blank($validated['work_unit_id'] ?? null)) {
            $errors['work_unit_id'] = 'Scope unit kerja wajib dipilih untuk jabatan ini.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'employee_profile_id' => (int) $validated['employee_profile_id'],
            'organizational_position_id' => $position->id,
            'faculty_id' => $position->scope_type === 'faculty' ? ($validated['faculty_id'] ?: null) : null,
            'study_program_id' => $position->scope_type === 'study_program' ? ($validated['study_program_id'] ?: null) : null,
            'work_unit_id' => $position->scope_type === 'work_unit' ? ($validated['work_unit_id'] ?: null) : null,
            'starts_at' => ($validated['starts_at'] ?? null) ?: null,
            'ends_at' => ($validated['ends_at'] ?? null) ?: null,
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'notes' => ($validated['notes'] ?? null) ?: null,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function formOptions(): array
    {
        return [
            'positions' => OrganizationalPosition::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'scope_type'])
                ->map(fn (OrganizationalPosition $position) => [
                    'id' => (string) $position->id,
                    'label' => $position->name.($position->code ? ' ('.$position->code.')' : ''),
                    'scopeType' => $position->scope_type,
                    'scopeLabel' => self::SCOPE_LABELS[$position->scope_type] ?? ucwords(str_replace('_', ' ', (string) $position->scope_type)),
                ])
                ->values()
                ->all(),
            'faculties' => Faculty::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Faculty $faculty) => [
                    'id' => (string) $faculty->id,
                    'label' => $faculty->name.($faculty->code ? ' ('.$faculty->code.')' : ''),
                ])
                ->values()
                ->all(),
            'studyPrograms' => StudyProgram::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (StudyProgram $program) => [
                    'id' => (string) $program->id,
                    'label' => $program->name.($program->code ? ' ('.$program->code.')' : ''),
                ])
                ->values()
                ->all(),
            'workUnits' => WorkUnit::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (WorkUnit $unit) => [
                    'id' => (string) $unit->id,
                    'label' => $unit->name.($unit->code ? ' ('.$unit->code.')' : ''),
                ])
                ->values()
                ->all(),
        ];
    }
}
