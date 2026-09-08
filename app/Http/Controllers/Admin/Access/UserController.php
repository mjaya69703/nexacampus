<?php

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Access\Role;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Pengguna — menu terakhir area access (pola kit CRUD shared).
 *
 * Paritas form Blade lama: tab biodata/keamanan + sub-form profil
 * student/lecturer kondisional (muncul bila role student/lecturer
 * dipilih), upload foto, password wajib saat tambah / opsional saat
 * ubah, sync role transaksional, guard akun-sendiri (hapus, nonaktif,
 * bulk). Tanpa hapus permanen: sesuai sistem lama, user hanya
 * soft-delete + pulihkan agar profil terkait tidak yatim.
 */
class UserController extends Controller
{
    private const IMPORT_REQUIRED = ['first_name', 'last_name', 'username', 'email', 'phone', 'password'];

    private const IMPORT_ENUMS = [
        'religion' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'],
        'blood_type' => ['A', 'B', 'AB', 'O'],
        'citizenship' => ['WNI', 'WNA'],
        'gender' => ['Laki-laki', 'Perempuan'],
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'role' => 'nullable|integer|exists:roles,id',
            'gender' => 'nullable|string|max:20',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,first_name,username,email,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = User::query()->with('roles:id,name');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['role'] ?? null)) {
            $query->whereHas('roles', fn ($r) => $r->where('roles.id', $validated['role']));
        }

        if (filled($validated['gender'] ?? null)) {
            $query->where('gender', $validated['gender']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $users = $query->paginate($perPage)->withQueryString();
        $authId = $request->user()->id;

        return Inertia::render('Admin/Access/User/Index', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Daftar Pengguna'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('user.create'),
                'update' => ActivePermission::check('user.update'),
                'delete' => ActivePermission::check('user.delete'),
                'restore' => ActivePermission::any(['user.update', 'user.delete']),
                'toggle' => ActivePermission::check('user.update'),
            ],
            'stats' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'withRole' => User::whereHas('roles')->count(),
                'trashed' => User::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($users->items())->values()->map(fn ($u, $i) => [
                    'id' => $u->id,
                    'no' => ($users->firstItem() ?? 0) + $i,
                    'photo' => $u->photo,
                    'name' => $u->name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'roles' => $u->roles->pluck('name')->all(),
                    'gender' => $u->gender,
                    'isActive' => (bool) $u->is_active,
                    'isSelf' => $u->id === $authId,
                    'createdAt' => $u->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.access.users.edit', $u->id),
                    'deleteUrl' => route('admin.access.users.destroy', $u->id),
                    'restoreUrl' => route('admin.access.users.restore', $u->id),
                    'forceUrl' => route('admin.access.users.force-destroy', $u->id),
                    'toggleUrl' => route('admin.access.users.toggle', $u->id),
                ])->all(),
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'role' => $validated['role'] ?? '',
                'gender' => $validated['gender'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'roleOptions' => Role::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.access.users.index'),
                'create' => route('admin.access.users.create'),
                'export' => route('admin.access.users.export'),
                'bulkDestroy' => route('admin.access.users.bulk-destroy'),
                'bulkRestore' => route('admin.access.users.bulk-restore'),
                'bulkForceDestroy' => route('admin.access.users.bulk-force-destroy'),
                'importTemplate' => route('admin.access.users.import-template'),
                'importSubmit' => route('admin.access.users.import'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Access/User/Form', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Tambah Pengguna'),
            'mode' => 'create',
            'user' => [
                'first_name' => '', 'last_name' => '', 'username' => '', 'email' => '',
                'phone' => '', 'instagram' => '', 'facebook' => '', 'linkedin' => '',
                'identity_number' => '', 'religion' => '', 'blood_type' => '', 'citizenship' => '',
                'gender' => '', 'height' => '', 'weight' => '', 'place_of_birth' => '',
                'date_of_birth' => '', 'is_active' => true, 'fst_setup' => false, 'tfa_setup' => false,
                'role_ids' => [],
            ],
            'student' => null,
            'lecturer' => null,
            'roles' => $this->roleOptions(),
            'studentRoleId' => Role::where('name', 'student')->value('id'),
            'lecturerRoleId' => Role::where('name', 'lecturer')->value('id'),
            'faculties' => Faculty::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'studyPrograms' => StudyProgram::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'urls' => [
                'index' => route('admin.access.users.index'),
                'submit' => route('admin.access.users.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'username' => 'required|string|max:255|unique:users,username',
            'phone' => 'required|string|max:20|unique:users,phone',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8|same:password',
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'identity_number' => 'nullable|string|max:255|unique:users,identity_number',
            'religion' => 'nullable|in:Islam,Kristen,Katolik,Hindu,Buddha,Khonghucu',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'citizenship' => 'nullable|in:WNI,WNA',
            'gender' => 'nullable|in:Laki-laki,Perempuan',
            'height' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'place_of_birth' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'fst_setup' => 'boolean',
            'tfa_setup' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $photo = 'default.jpg';

            if ($request->hasFile('photo')) {
                $filename = 'profile_'.time().'.'.$request->file('photo')->getClientOriginalExtension();
                $request->file('photo')->storeAs('images/profile', $filename, 'public');
                $photo = $filename;
            }

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'username' => $validated['username'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'instagram' => $validated['instagram'] ?? null,
                'facebook' => $validated['facebook'] ?? null,
                'linkedin' => $validated['linkedin'] ?? null,
                'identity_number' => $validated['identity_number'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'blood_type' => $validated['blood_type'] ?? null,
                'citizenship' => $validated['citizenship'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'height' => $validated['height'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'place_of_birth' => $validated['place_of_birth'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'code' => uniqid(),
                'photo' => $photo,
                'is_active' => $validated['is_active'] ?? true,
                'fst_setup' => $validated['fst_setup'] ?? false,
                'tfa_setup' => $validated['tfa_setup'] ?? false,
            ]);

            if (! empty($validated['role_ids'])) {
                $user->syncRoles(Role::whereIn('id', $validated['role_ids'])->get());
            }

            return redirect()->route('admin.access.users.index')
                ->with('success', 'User berhasil dibuat!');
        });
    }

    public function edit(Request $request, User $user): Response
    {
        $user->load(['studentProfile', 'lecturerProfile']);

        return Inertia::render('Admin/Access/User/Form', [
            'shell' => ShellProps::make($request->user(), 'Manajemen Akses', 'Edit Pengguna'),
            'mode' => 'edit',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name, 'last_name' => $user->last_name,
                'username' => $user->username, 'email' => $user->email, 'phone' => $user->phone,
                'instagram' => $user->instagram, 'facebook' => $user->facebook, 'linkedin' => $user->linkedin,
                'identity_number' => $user->identity_number, 'religion' => $user->religion,
                'blood_type' => $user->blood_type, 'citizenship' => $user->citizenship,
                'gender' => $user->gender, 'height' => $user->height, 'weight' => $user->weight,
                'place_of_birth' => $user->place_of_birth,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'photo_url' => $user->photo,
                'is_active' => (bool) $user->is_active,
                'fst_setup' => (bool) $user->fst_setup,
                'tfa_setup' => (bool) $user->tfa_setup,
                'is_self' => $user->id === $request->user()->id,
                'role_ids' => $user->roles()->pluck('roles.id')->all(),
            ],
            'student' => $user->studentProfile ? [
                'study_program_id' => $user->studentProfile->study_program_id,
                'entry_academic_year_id' => $user->studentProfile->entry_academic_year_id,
                'nim' => $user->studentProfile->nim,
                'entry_year' => $user->studentProfile->entry_year,
                'academic_status' => $user->studentProfile->academic_status,
                'entry_date' => $user->studentProfile->entry_date?->format('Y-m-d'),
                'graduation_date' => $user->studentProfile->graduation_date?->format('Y-m-d'),
                'current_semester' => $user->studentProfile->current_semester,
                'is_active' => (bool) $user->studentProfile->is_active,
                'desc' => $user->studentProfile->desc,
            ] : null,
            'lecturer' => $user->lecturerProfile ? [
                'faculty_id' => $user->lecturerProfile->faculty_id,
                'study_program_id' => $user->lecturerProfile->study_program_id,
                'nidn' => $user->lecturerProfile->nidn,
                'nidk' => $user->lecturerProfile->nidk,
                'nip' => $user->lecturerProfile->nip,
                'employment_status' => $user->lecturerProfile->employment_status,
                'join_date' => $user->lecturerProfile->join_date?->format('Y-m-d'),
                'is_active' => (bool) $user->lecturerProfile->is_active,
                'desc' => $user->lecturerProfile->desc,
            ] : null,
            'roles' => $this->roleOptions(),
            'studentRoleId' => Role::where('name', 'student')->value('id'),
            'lecturerRoleId' => Role::where('name', 'lecturer')->value('id'),
            'faculties' => Faculty::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'studyPrograms' => StudyProgram::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'urls' => [
                'index' => route('admin.access.users.index'),
                'submit' => route('admin.access.users.update', $user),
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'phone' => 'required|string|max:20|unique:users,phone,'.$user->id,
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'identity_number' => 'nullable|string|max:255|unique:users,identity_number,'.$user->id,
            'religion' => 'nullable|in:Islam,Kristen,Katolik,Hindu,Buddha,Khonghucu',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'citizenship' => 'nullable|in:WNI,WNA',
            'gender' => 'nullable|in:Laki-laki,Perempuan',
            'height' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'place_of_birth' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'new_password' => 'nullable|string|min:8|confirmed',
            'new_password_confirmation' => 'nullable|string|min:8|same:new_password',
            'is_active' => 'nullable|boolean',
            'fst_setup' => 'boolean',
            'tfa_setup' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'student' => 'nullable|array',
            'student.study_program_id' => 'nullable|exists:study_programs,id',
            'student.entry_academic_year_id' => 'nullable|exists:academic_years,id',
            'student.nim' => 'nullable|string|max:255|unique:student_profiles,nim,'.($user->studentProfile?->id ?? 'NULL'),
            'student.entry_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'student.academic_status' => 'nullable|in:Aktif,Cuti,Lulus,Drop Out,Nonaktif,Keluar',
            'student.entry_date' => 'nullable|date',
            'student.graduation_date' => 'nullable|date|after:student.entry_date',
            'student.current_semester' => 'nullable|integer|min:1|max:14',
            'student.is_active' => 'nullable|boolean',
            'student.desc' => 'nullable|string',
            'lecturer' => 'nullable|array',
            'lecturer.faculty_id' => 'nullable|exists:faculties,id',
            'lecturer.study_program_id' => 'nullable|exists:study_programs,id',
            'lecturer.nidn' => 'nullable|string|max:255|unique:lecturer_profiles,nidn,'.($user->lecturerProfile?->id ?? 'NULL'),
            'lecturer.nidk' => 'nullable|string|max:255|unique:lecturer_profiles,nidk,'.($user->lecturerProfile?->id ?? 'NULL'),
            'lecturer.nip' => 'nullable|string|max:255|unique:lecturer_profiles,nip,'.($user->lecturerProfile?->id ?? 'NULL'),
            'lecturer.employment_status' => 'nullable|in:Tetap,Kontrak,Tidak Tetap,Tamu',
            'lecturer.join_date' => 'nullable|date',
            'lecturer.is_active' => 'nullable|boolean',
            'lecturer.desc' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request, $user) {
            if ($request->hasFile('photo')) {
                $filename = 'profile_'.$user->id.'_'.time().'.'.$request->file('photo')->getClientOriginalExtension();
                $request->file('photo')->storeAs('images/profile', $filename, 'public');

                $oldPhoto = $user->getRawOriginal('photo');

                if ($oldPhoto && $oldPhoto !== 'default.jpg') {
                    Storage::disk('public')->delete('images/profile/'.$oldPhoto);
                }

                $user->photo = $filename;
            }

            $user->first_name = $validated['first_name'];
            $user->last_name = $validated['last_name'];
            $user->username = $validated['username'];
            $user->phone = $validated['phone'];
            $user->email = $validated['email'];
            $user->instagram = $validated['instagram'] ?? null;
            $user->facebook = $validated['facebook'] ?? null;
            $user->linkedin = $validated['linkedin'] ?? null;
            $user->identity_number = $validated['identity_number'] ?? null;
            $user->religion = $validated['religion'] ?? null;
            $user->blood_type = $validated['blood_type'] ?? null;
            $user->citizenship = $validated['citizenship'] ?? null;
            $user->gender = $validated['gender'] ?? null;
            $user->height = $validated['height'] ?? null;
            $user->weight = $validated['weight'] ?? null;
            $user->place_of_birth = $validated['place_of_birth'] ?? null;
            $user->date_of_birth = $validated['date_of_birth'] ?? null;
            $user->is_active = $validated['is_active'] ?? true;
            $user->fst_setup = $validated['fst_setup'] ?? false;
            $user->tfa_setup = $validated['tfa_setup'] ?? false;

            if (! empty($validated['new_password'])) {
                $user->password = Hash::make($validated['new_password']);
            }

            $user->save();

            $user->syncRoles(Role::whereIn('id', $validated['role_ids'] ?? [])->get());

            $selectedIds = collect($validated['role_ids'] ?? [])->map(fn ($id) => (int) $id);
            $studentRoleId = Role::where('name', 'student')->value('id');
            $lecturerRoleId = Role::where('name', 'lecturer')->value('id');
            $isStudent = $studentRoleId !== null && $selectedIds->contains((int) $studentRoleId);
            $isLecturer = $lecturerRoleId !== null && $selectedIds->contains((int) $lecturerRoleId);

            $this->syncProfile($user, 'student', $validated['student'] ?? [], $isStudent);
            $this->syncProfile($user, 'lecturer', $validated['lecturer'] ?? [], $isLecturer);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return redirect()->route('admin.access.users.index')
                ->with('success', 'Profil berhasil diperbarui!');
        });
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun yang sedang login!');
        }

        $validated = $request->validate(['is_active' => 'required|boolean']);
        $user->update(['is_active' => $validated['is_active']]);

        return back()->with('success', 'Status user berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak bisa menghapus diri sendiri!');
        }

        $name = $user->first_name;
        $user->delete();

        return redirect()->route('admin.access.users.index')
            ->with('success', 'User "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
        ]);

        $authId = $request->user()->id;
        $ids = collect($validated['ids'])->reject(fn ($id) => (int) $id === $authId);
        $skippedSelf = count($validated['ids']) !== $ids->count();

        User::whereIn('id', $ids)->delete();

        if ($skippedSelf && $ids->isNotEmpty()) {
            return back()->with('warning', $ids->count().' user dihapus. Akun yang sedang login tidak ikut dihapus.');
        }

        if ($skippedSelf) {
            return back()->with('error', 'Akun yang sedang login tidak bisa dihapus lewat bulk action.');
        }

        return back()->with('success', $ids->count().' user berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return back()->with('success', 'User "'.$user->first_name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(Request $request, int $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak bisa menghapus permanen akun sendiri!');
        }

        $name = $user->first_name;
        $photo = $user->getRawOriginal('photo');
        $user->forceDelete();

        if ($photo && $photo !== 'default.jpg') {
            Storage::disk('public')->delete('images/profile/'.$photo);
        }

        return back()->with('success', 'User "'.$name.'" dihapus permanen beserta profil terkaitnya.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
        ]);

        $count = 0;

        foreach (User::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $user) {
            $user->restore();
            $count++;
        }

        return back()->with('success', $count.' user berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
        ]);

        $authId = $request->user()->id;
        $ids = collect($validated['ids'])->reject(fn ($id) => (int) $id === $authId);
        $skippedSelf = count($validated['ids']) !== $ids->count();

        $count = 0;

        foreach (User::withTrashed()->whereIn('id', $ids)->get() as $user) {
            $photo = $user->getRawOriginal('photo');
            $user->forceDelete();

            if ($photo && $photo !== 'default.jpg') {
                Storage::disk('public')->delete('images/profile/'.$photo);
            }

            $count++;
        }

        if ($skippedSelf && $count > 0) {
            return back()->with('warning', $count.' user dihapus permanen. Akun yang sedang login tidak ikut dihapus.');
        }

        if ($skippedSelf) {
            return back()->with('error', 'Akun yang sedang login tidak bisa dihapus permanen.');
        }

        return back()->with('success', $count.' user dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = User::query()->with('roles:id,name');

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(fn ($sub) => $sub
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($request->query('role'))) {
            $query->whereHas('roles', fn ($r) => $r->where('roles.id', $request->query('role')));
        }

        $rows = $query->orderBy('first_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nama', 'Username', 'Email', 'No. HP', 'Role', 'Jenis Kelamin', 'Status', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $user) {
            $sheet->fromArray([
                $user->name,
                $user->username,
                $user->email,
                $user->phone,
                $user->roles->pluck('name')->join(', '),
                $user->gender,
                $user->is_active ? 'Aktif' : 'Nonaktif',
                $user->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'users-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'first_name', 'last_name', 'username', 'email', 'phone', 'password',
            'role_names', 'identity_number', 'gender', 'religion', 'blood_type',
            'citizenship', 'height', 'weight', 'place_of_birth', 'date_of_birth',
            'instagram', 'facebook', 'linkedin', 'is_active',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->fromArray([[
            'Budi', 'Santoso', 'budi.santoso', 'budi@kampus.ac.id', '081234567890',
            'rahasia123', 'petugas', '', 'Laki-laki', 'Islam', '', 'WNI',
            '', '', '', '', '', '', '', '1',
        ]], null, 'A2');
        $sheet->getStyle('A1:T1')->getFont()->setBold(true);

        foreach (range('A', 'T') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-users.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable) {
            return back()->with('error', 'File tidak bisa dibaca. Gunakan template yang disediakan.');
        }

        $rows = $spreadsheet->getActiveSheet()->toArray();
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows ? array_shift($rows) : []);

        $missing = array_diff(self::IMPORT_REQUIRED, $header);

        if (! empty($missing)) {
            return back()->with('error', 'Header wajib hilang: '.implode(', ', $missing).'. Unduh template terbaru.');
        }

        $rows = array_values(array_filter(
            $rows,
            fn ($row) => collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        ));

        if (empty($rows)) {
            return back()->with('error', 'File tidak berisi data.');
        }

        if (count($rows) > 500) {
            return back()->with('error', 'Maksimal 500 baris per import.');
        }

        $roleMap = Role::pluck('id', 'name')->all();
        $seen = ['username' => [], 'email' => [], 'phone' => [], 'identity_number' => []];
        $valid = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $data = array_combine(
                $header,
                array_pad(array_slice($row, 0, count($header)), count($header), null)
            );
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
            $rowErrors = [];

            foreach (self::IMPORT_REQUIRED as $column) {
                if (($data[$column] ?? null) === null || $data[$column] === '') {
                    $rowErrors[] = "{$column} wajib diisi";
                }
            }

            if (isset($data['password']) && strlen((string) $data['password']) < 8) {
                $rowErrors[] = 'password minimal 8 karakter';
            }

            if (! empty($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'email tidak valid';
            }

            foreach (['username', 'email', 'phone', 'identity_number'] as $column) {
                $value = $data[$column] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                if (in_array($value, $seen[$column], true)) {
                    $rowErrors[] = "{$column} duplikat di dalam file";
                    continue;
                }

                if (User::where($column, $value)->exists()) {
                    $rowErrors[] = "{$column} sudah terdaftar";
                    continue;
                }

                $seen[$column][] = $value;
            }

            $roleIds = [];

            if (! empty($data['role_names'])) {
                foreach (explode(',', (string) $data['role_names']) as $name) {
                    $name = trim($name);

                    if ($name === '') {
                        continue;
                    }

                    if (! isset($roleMap[$name])) {
                        $rowErrors[] = "role \"{$name}\" tidak ditemukan";
                    } else {
                        $roleIds[] = $roleMap[$name];
                    }
                }
            }

            foreach (self::IMPORT_ENUMS as $column => $allowed) {
                if (! empty($data[$column]) && ! in_array($data[$column], $allowed, true)) {
                    $rowErrors[] = "{$column} tidak valid";
                }
            }

            $birthDate = $this->parseImportDate($data['date_of_birth'] ?? null);

            if (! empty($data['date_of_birth']) && $birthDate === null) {
                $rowErrors[] = 'date_of_birth tidak valid (gunakan Y-m-d)';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $valid[] = ['data' => $data, 'birthDate' => $birthDate, 'roleIds' => array_values(array_unique($roleIds))];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.access.users.index')->with('import_result', [
                'success' => false,
                'created' => 0,
                'rejected' => count($errors),
                'errors' => $errors,
            ]);
        }

        DB::transaction(function () use ($valid) {
            foreach ($valid as $item) {
                $data = $item['data'];

                $user = User::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'username' => $data['username'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'password' => Hash::make((string) $data['password']),
                    'instagram' => $data['instagram'] ?? null,
                    'facebook' => $data['facebook'] ?? null,
                    'linkedin' => $data['linkedin'] ?? null,
                    'identity_number' => $data['identity_number'] ?? null,
                    'religion' => $data['religion'] ?? null,
                    'blood_type' => $data['blood_type'] ?? null,
                    'citizenship' => $data['citizenship'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'height' => $data['height'] ?? null,
                    'weight' => $data['weight'] ?? null,
                    'place_of_birth' => $data['place_of_birth'] ?? null,
                    'date_of_birth' => $item['birthDate'],
                    'code' => uniqid(),
                    'photo' => 'default.jpg',
                    'is_active' => $this->parseImportBool($data['is_active'] ?? null, true),
                    'fst_setup' => false,
                    'tfa_setup' => false,
                ]);

                if (! empty($item['roleIds'])) {
                    $user->syncRoles(Role::whereIn('id', $item['roleIds'])->get());
                }
            }
        });

        return redirect()->route('admin.access.users.index')
            ->with('success', count($valid).' user berhasil diimpor.')
            ->with('import_result', [
                'success' => true,
                'created' => count($valid),
                'rejected' => 0,
                'errors' => [],
            ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])
            ->all();
    }

    private function parseImportDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }

    private function syncProfile(User $user, string $type, array $form, bool $roleSelected): void
    {
        if (! $roleSelected) {
            return;
        }

        if (empty(array_filter($form, fn ($value) => $value !== null && $value !== ''))) {
            return;
        }

        $relation = $type === 'student' ? 'studentProfile' : 'lecturerProfile';
        $profile = $user->$relation;

        if ($profile) {
            $profile->update(array_merge($form, ['updated_by' => auth()->id()]));
        } else {
            $user->$relation()->create(array_merge($form, ['created_by' => auth()->id()]));
        }
    }
}
