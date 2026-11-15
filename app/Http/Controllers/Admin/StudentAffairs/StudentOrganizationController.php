<?php

namespace App\Http\Controllers\Admin\StudentAffairs;

use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentOrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => ['nullable', Rule::enum(OrganizationType::class)],
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,type,is_active,founded_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentOrganization::query()
            ->with([
                'faculty:id,name,short_name',
                'studyProgram:id,name',
                'advisor:id,first_name,last_name',
            ])
            ->withCount(['members', 'activeMembers']);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $rows = $query->paginate((int) ($validated['perPage'] ?? 10))->withQueryString();

        return Inertia::render('Admin/StudentAffairs/Organizations/Index', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Organisasi Mahasiswa'),
            'can' => [
                'create' => ActivePermission::check('student-organization.create'),
                'update' => ActivePermission::check('student-organization.update'),
                'delete' => ActivePermission::check('student-organization.delete'),
            ],
            'stats' => [
                'total' => StudentOrganization::count(),
                'active' => StudentOrganization::where('is_active', true)->count(),
                'members' => StudentOrganizationMember::where('is_active', true)->count(),
                'achievements' => StudentAchievement::count(),
            ],
            'data' => [
                'rows' => collect($rows->items())->values()->map(fn (StudentOrganization $row, int $i) => [
                    'id' => $row->id,
                    'no' => ($rows->firstItem() ?? 0) + $i,
                    'name' => $row->name,
                    'type' => $row->type->value,
                    'typeLabel' => $row->type->label(),
                    'typeShort' => $row->type->shortLabel(),
                    'typeTone' => $row->type->tone(),
                    'faculty' => $row->faculty?->short_name ?? $row->faculty?->name,
                    'studyProgram' => $row->studyProgram?->name,
                    'advisor' => $row->advisor?->name,
                    'membersCount' => (int) $row->members_count,
                    'activeMembersCount' => (int) $row->active_members_count,
                    'foundedAt' => $row->founded_at?->format('d M Y'),
                    'isActive' => (bool) $row->is_active,
                    'logoUrl' => $row->logo_path ? Storage::disk('public')->url($row->logo_path) : null,
                    'showUrl' => route('admin.student-affairs.organizations.show', $row->id),
                    'editUrl' => route('admin.student-affairs.organizations.edit', $row->id),
                    'deleteUrl' => route('admin.student-affairs.organizations.destroy', $row->id),
                ])->all(),
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => (int) ($validated['perPage'] ?? 10),
            ],
            'options' => [
                'types' => OrganizationType::options(),
            ],
            'urls' => [
                'index' => route('admin.student-affairs.organizations.index'),
                'create' => route('admin.student-affairs.organizations.create'),
                'bulkDestroy' => route('admin.student-affairs.organizations.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StudentAffairs/Organizations/Form', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Tambah Organisasi Mahasiswa'),
            'mode' => 'create',
            'organization' => [
                'id' => null,
                'name' => '',
                'type' => OrganizationType::BEM->value,
                'faculty_id' => null,
                'study_program_id' => null,
                'advisor' => null,
                'description' => '',
                'founded_at' => '',
                'contact_email' => '',
                'contact_phone' => '',
                'instagram_url' => '',
                'is_active' => true,
                'logo_url' => null,
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.student-affairs.organizations.index'),
                'submit' => route('admin.student-affairs.organizations.store'),
                'searchLecturers' => route('admin.student-affairs.organizations.search-lecturers'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $logoPath = $this->storeLogo($request);

        StudentOrganization::create([
            ...$validated,
            'logo_path' => $logoPath,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-affairs.organizations.index')
            ->with('success', 'Organisasi mahasiswa berhasil ditambahkan.');
    }

    public function edit(Request $request, int $id): Response
    {
        $organization = StudentOrganization::with('advisor:id,first_name,last_name')->findOrFail($id);

        return Inertia::render('Admin/StudentAffairs/Organizations/Form', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Edit Organisasi Mahasiswa'),
            'mode' => 'edit',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type->value,
                'faculty_id' => $organization->faculty_id,
                'study_program_id' => $organization->study_program_id,
                'advisor' => $organization->advisor ? [
                    'id' => $organization->advisor->id,
                    'label' => $organization->advisor->name,
                ] : null,
                'description' => (string) $organization->description,
                'founded_at' => $organization->founded_at?->format('Y-m-d') ?? '',
                'contact_email' => (string) $organization->contact_email,
                'contact_phone' => (string) $organization->contact_phone,
                'instagram_url' => (string) $organization->instagram_url,
                'is_active' => (bool) $organization->is_active,
                'logo_url' => $organization->logo_path ? Storage::disk('public')->url($organization->logo_path) : null,
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.student-affairs.organizations.index'),
                'submit' => route('admin.student-affairs.organizations.update', $organization->id),
                'searchLecturers' => route('admin.student-affairs.organizations.search-lecturers'),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $organization = StudentOrganization::findOrFail($id);
        $validated = $this->validatePayload($request);
        $logoPath = $this->storeLogo($request, $organization);

        $organization->update([
            ...$validated,
            'logo_path' => $logoPath,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.student-affairs.organizations.index')
            ->with('success', 'Organisasi mahasiswa berhasil diperbarui.');
    }

    public function show(Request $request, int $id): Response
    {
        $organization = StudentOrganization::with([
            'faculty:id,name,short_name',
            'studyProgram:id,name',
            'advisor:id,first_name,last_name',
        ])->withCount(['members', 'activeMembers'])->findOrFail($id);

        $members = StudentOrganizationMember::with([
            'user:id,first_name,last_name,username',
            'user.studentProfile:id,user_id,nim,study_program_id',
            'user.studentProfile.studyProgram:id,name',
        ])
            ->where('student_organization_id', $organization->id)
            ->orderByDesc('is_active')
            ->orderBy('position')
            ->orderBy('period', 'desc')
            ->get()
            ->map(fn (StudentOrganizationMember $member) => [
                'id' => $member->id,
                'userId' => $member->user_id,
                'name' => $member->user?->name ?? 'Pengguna dihapus',
                'nim' => $member->user?->studentProfile?->nim,
                'program' => $member->user?->studentProfile?->studyProgram?->name,
                'position' => $member->position->value,
                'positionLabel' => $member->position->label(),
                'positionTone' => $member->position->tone(),
                'period' => $member->period,
                'joinedAt' => $member->joined_at?->format('d M Y'),
                'leftAt' => $member->left_at?->format('d M Y'),
                'joinedAtIso' => $member->joined_at?->format('Y-m-d'),
                'leftAtIso' => $member->left_at?->format('Y-m-d'),
                'isActive' => (bool) $member->is_active,
                'notes' => $member->notes,
                'updateUrl' => route('admin.student-affairs.organizations.members.update', [$organization->id, $member->id]),
                'deleteUrl' => route('admin.student-affairs.organizations.members.destroy', [$organization->id, $member->id]),
            ]);

        $achievements = StudentAchievement::with('user:id,first_name,last_name')
            ->where('student_organization_id', $organization->id)
            ->orderByDesc('achieved_at')
            ->limit(30)
            ->get()
            ->map(fn (StudentAchievement $achievement) => ['id' => $achievement->id,
                'title' => $achievement->title,
                'student' => $achievement->user?->name ?? '-',
                'categoryLabel' => $achievement->category->label(),
                'levelLabel' => $achievement->level->label(),
                'rankLabel' => $achievement->rank->label(),
                'achievedAt' => $achievement->achieved_at?->format('d M Y'),
                'status' => $achievement->status->value,
                'statusLabel' => $achievement->status->label(),
                'statusTone' => $achievement->status->tone(),
                'showUrl' => route('admin.student-affairs.achievements.show', $achievement->id),
            ]);

        return Inertia::render('Admin/StudentAffairs/Organizations/Show', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', $organization->name),
            'can' => [
                'update' => ActivePermission::check('student-organization.update'),
                'delete' => ActivePermission::check('student-organization.delete'),
                'manageMembers' => ActivePermission::check('student-organization.update'),
                'createAchievement' => ActivePermission::check('student-achievement.create'),
            ],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type->value,
                'typeLabel' => $organization->type->label(),
                'typeShort' => $organization->type->shortLabel(),
                'typeTone' => $organization->type->tone(),
                'faculty' => $organization->faculty?->name,
                'studyProgram' => $organization->studyProgram?->name,
                'advisor' => $organization->advisor?->name,
                'description' => $organization->description,
                'foundedAt' => $organization->founded_at?->format('d M Y'),
                'contactEmail' => $organization->contact_email,
                'contactPhone' => $organization->contact_phone,
                'instagramUrl' => $organization->instagram_url,
                'isActive' => (bool) $organization->is_active,
                'logoUrl' => $organization->logo_path ? Storage::disk('public')->url($organization->logo_path) : null,
            ],
            'stats' => [
                'members' => (int) $organization->members_count,
                'activeMembers' => (int) $organization->active_members_count,
                'achievements' => StudentAchievement::where('student_organization_id', $organization->id)->count(),
            ],
            'members' => $members,
            'achievements' => $achievements,
            'options' => [
                'positions' => MemberPosition::options(),
            ],
            'urls' => [
                'index' => route('admin.student-affairs.organizations.index'),
                'edit' => route('admin.student-affairs.organizations.edit', $organization->id),
                'delete' => route('admin.student-affairs.organizations.destroy', $organization->id),
                'memberStore' => route('admin.student-affairs.organizations.members.store', $organization->id),
                'searchStudents' => route('admin.student-affairs.organizations.search-students'),
                'achievementCreate' => route('admin.student-affairs.achievements.create', ['organization_id' => $organization->id]),
            ],
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $organization = StudentOrganization::withCount('achievements')->findOrFail($id);

        if ($organization->achievements_count > 0) {
            return back()->with('error', 'Organisasi tidak dapat dihapus karena masih memiliki data prestasi mahasiswa.');
        }

        $organization->update(['deleted_by' => $request->user()->id]);
        $organization->delete();

        return redirect()->route('admin.student-affairs.organizations.index')
            ->with('success', 'Organisasi mahasiswa berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:student_organizations,id',
        ]);

        $deleted = 0;
        $skipped = 0;

        foreach (StudentOrganization::withCount('achievements')->whereIn('id', $validated['ids'])->get() as $organization) {
            if ($organization->achievements_count > 0) {
                $skipped++;

                continue;
            }

            $organization->update(['deleted_by' => $request->user()->id]);
            $organization->delete();
            $deleted++;
        }

        $message = "{$deleted} organisasi dihapus.";
        if ($skipped > 0) {
            $message .= " {$skipped} organisasi dilewati karena masih memiliki prestasi.";
        }

        return redirect()->route('admin.student-affairs.organizations.index')
            ->with($skipped > 0 ? 'warning' : 'success', $message);
    }

    public function storeMember(Request $request, int $id)
    {
        $organization = StudentOrganization::findOrFail($id);
        $validated = $this->validateMemberPayload($request);

        $this->ensureStudent($validated['user_id']);

        $duplicate = StudentOrganizationMember::where('student_organization_id', $organization->id)
            ->where('user_id', $validated['user_id'])
            ->where('period', $validated['period'])
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['user_id' => 'Mahasiswa ini sudah terdaftar pada organisasi dan periode tersebut.'])->withInput();
        }

        StudentOrganizationMember::create([
            ...$validated,
            'student_organization_id' => $organization->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Anggota organisasi berhasil ditambahkan.');
    }

    public function updateMember(Request $request, int $id, int $memberId)
    {
        $organization = StudentOrganization::findOrFail($id);
        $member = StudentOrganizationMember::where('student_organization_id', $organization->id)->findOrFail($memberId);
        $validated = $this->validateMemberPayload($request);

        $this->ensureStudent($validated['user_id']);

        $duplicate = StudentOrganizationMember::where('student_organization_id', $organization->id)
            ->where('user_id', $validated['user_id'])
            ->where('period', $validated['period'])
            ->whereKeyNot($member->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['user_id' => 'Mahasiswa ini sudah terdaftar pada organisasi dan periode tersebut.'])->withInput();
        }

        $member->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Data anggota organisasi berhasil diperbarui.');
    }

    public function destroyMember(Request $request, int $id, int $memberId)
    {
        $organization = StudentOrganization::findOrFail($id);
        $member = StudentOrganizationMember::where('student_organization_id', $organization->id)->findOrFail($memberId);

        $member->update(['deleted_by' => $request->user()->id]);
        $member->delete();

        return back()->with('success', 'Anggota organisasi berhasil dihapus.');
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = User::query()
            ->with([
                'studentProfile:id,user_id,nim,study_program_id',
                'studentProfile.studyProgram:id,name',
            ])
            ->where('is_active', true)
            ->whereHas('studentProfile')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%")
                        ->orWhereHas('studentProfile', fn ($profile) => $profile->where('nim', 'like', "%{$q}%"));
                });
            })
            ->orderBy('first_name')
            ->limit(15)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name.' · '.($user->studentProfile?->nim ?? '-'),
                'meta' => [
                    'nim' => $user->studentProfile?->nim,
                    'program' => $user->studentProfile?->studyProgram?->name,
                ],
            ]);

        return response()->json(['options' => $students]);
    }

    public function searchLecturers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $lecturers = User::query()
            ->with('lecturerProfile:id,user_id,nidn')
            ->where('is_active', true)
            ->whereHas('lecturerProfile')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%")
                        ->orWhereHas('lecturerProfile', fn ($profile) => $profile->where('nidn', 'like', "%{$q}%"));
                });
            })
            ->orderBy('first_name')
            ->limit(15)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name.' · '.($user->lecturerProfile?->nidn ?? 'Dosen'),
                'meta' => ['nidn' => $user->lecturerProfile?->nidn],
            ]);

        return response()->json(['options' => $lecturers]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'advisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'founded_at' => ['nullable', 'date'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($validated['logo']);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMemberPayload(Request $request): array
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', Rule::enum(MemberPosition::class)],
            'period' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'joined_at' => ['nullable', 'date'],
            'left_at' => ['nullable', 'date', 'after_or_equal:joined_at'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    private function ensureStudent(int $userId): void
    {
        $user = User::with('studentProfile')->findOrFail($userId);

        if (! $user->studentProfile) {
            throw ValidationException::withMessages([
                'user_id' => 'Pengguna yang dipilih bukan mahasiswa aktif.',
            ]);
        }
    }

    private function storeLogo(Request $request, ?StudentOrganization $organization = null): ?string
    {
        if (! $request->hasFile('logo')) {
            return $organization?->logo_path;
        }

        $path = $request->file('logo')->store('student-affairs/organizations', 'public');

        if ($organization?->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => OrganizationType::options(),
            'faculties' => Faculty::query()->orderBy('name')->get(['id', 'name'])->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
            ]),
            'studyPrograms' => StudyProgram::query()->orderBy('name')->get(['id', 'name', 'faculty_id'])->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'facultyId' => $program->faculty_id,
            ]),
        ];
    }
}
