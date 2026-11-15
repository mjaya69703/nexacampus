<?php

namespace App\Http\Controllers\Admin\StudentAffairs;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Http\Controllers\Controller;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentAchievementController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => ['nullable', Rule::enum(AchievementCategory::class)],
            'level' => ['nullable', Rule::enum(AchievementLevel::class)],
            'status' => ['nullable', Rule::enum(AchievementStatus::class)],
            'is_featured' => 'nullable|in:0,1',
            'sort' => 'nullable|in:title,category,level,rank,achieved_at,status,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentAchievement::query()
            ->with([
                'user:id,first_name,last_name',
                'user.studentProfile:id,user_id,nim,study_program_id',
                'user.studentProfile.studyProgram:id,name',
                'organization:id,name',
            ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")
                ->orWhere('organizer', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($user) => $user->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%"))
                ->orWhereHas('user.studentProfile', fn ($profile) => $profile->where('nim', 'like', "%{$q}%")));
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category', $validated['category']);
        }

        if (filled($validated['level'] ?? null)) {
            $query->where('level', $validated['level']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['is_featured']) && $validated['is_featured'] !== '') {
            $query->where('is_featured', (bool) $validated['is_featured']);
        }

        $sort = $validated['sort'] ?? 'achieved_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $rows = $query->paginate((int) ($validated['perPage'] ?? 10))->withQueryString();

        return Inertia::render('Admin/StudentAffairs/Achievements/Index', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Prestasi Mahasiswa'),
            'can' => [
                'create' => ActivePermission::check('student-achievement.create'),
                'update' => ActivePermission::check('student-achievement.update'),
                'delete' => ActivePermission::check('student-achievement.delete'),
                'verify' => ActivePermission::check('student-achievement.verify'),
            ],
            'stats' => [
                'total' => StudentAchievement::count(),
                'pending' => StudentAchievement::where('status', AchievementStatus::PENDING->value)->count(),
                'verified' => StudentAchievement::where('status', AchievementStatus::VERIFIED->value)->count(),
                'featured' => StudentAchievement::where('is_featured', true)->count(),
            ],
            'data' => [
                'rows' => collect($rows->items())->values()->map(fn (StudentAchievement $row, int $i) => [
                    'id' => $row->id,
                    'no' => ($rows->firstItem() ?? 0) + $i,
                    'title' => $row->title,
                    'student' => $row->user?->name ?? '-',
                    'nim' => $row->user?->studentProfile?->nim,
                    'program' => $row->user?->studentProfile?->studyProgram?->name,
                    'organization' => $row->organization?->name,
                    'category' => $row->category->value,
                    'categoryLabel' => $row->category->label(),
                    'categoryTone' => $row->category->tone(),
                    'level' => $row->level->value,
                    'levelLabel' => $row->level->label(),
                    'levelTone' => $row->level->tone(),
                    'rankLabel' => $row->rank->label(),
                    'achievedAt' => $row->achieved_at?->format('d M Y'),
                    'status' => $row->status->value,
                    'statusLabel' => $row->status->label(),
                    'statusTone' => $row->status->tone(),
                    'isFeatured' => (bool) $row->is_featured,
                    'hasCertificate' => filled($row->certificate_path),
                    'certificateUrl' => $row->certificate_path
                        ? route('admin.student-affairs.achievements.certificate', $row->id)
                        : null,
                    'showUrl' => route('admin.student-affairs.achievements.show', $row->id),
                    'editUrl' => route('admin.student-affairs.achievements.edit', $row->id),
                    'deleteUrl' => route('admin.student-affairs.achievements.destroy', $row->id),
                ])->all(),
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'level' => $validated['level'] ?? '',
                'status' => $validated['status'] ?? '',
                'is_featured' => $validated['is_featured'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => (int) ($validated['perPage'] ?? 10),
            ],
            'options' => [
                'categories' => AchievementCategory::options(),
                'levels' => AchievementLevel::options(),
                'statuses' => AchievementStatus::options(),
            ],
            'urls' => [
                'index' => route('admin.student-affairs.achievements.index'),
                'create' => route('admin.student-affairs.achievements.create'),
                'bulkDestroy' => route('admin.student-affairs.achievements.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedOrganization = $request->integer('organization_id');

        return Inertia::render('Admin/StudentAffairs/Achievements/Form', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Tambah Prestasi Mahasiswa'),
            'mode' => 'create',
            'achievement' => [
                'id' => null,
                'student' => null,
                'student_organization_id' => $selectedOrganization > 0 ? $selectedOrganization : null,
                'title' => '',
                'category' => AchievementCategory::ACADEMIC->value,
                'level' => AchievementLevel::LOCAL->value,
                'rank' => AchievementRank::WINNER->value,
                'achieved_at' => now()->format('Y-m-d'),
                'organizer' => '',
                'description' => '',
                'status' => AchievementStatus::PENDING->value,
                'is_featured' => false,
                'rejection_reason' => '',
                'certificate_url' => null,
                'certificate_name' => null,
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.student-affairs.achievements.index'),
                'submit' => route('admin.student-affairs.achievements.store'),
                'searchStudents' => route('admin.student-affairs.achievements.search-students'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $this->ensureStudent($validated['user_id']);

        $achievement = StudentAchievement::create([
            ...$this->applyVerificationState($validated, $request->user()->id),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->storeCertificate($request, $achievement);

        return redirect()->route('admin.student-affairs.achievements.index')
            ->with('success', 'Prestasi mahasiswa berhasil ditambahkan.');
    }

    public function edit(Request $request, int $id): Response
    {
        $achievement = StudentAchievement::with([
            'user:id,first_name,last_name',
            'user.studentProfile:id,user_id,nim',
        ])->findOrFail($id);

        return Inertia::render('Admin/StudentAffairs/Achievements/Form', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Edit Prestasi Mahasiswa'),
            'mode' => 'edit',
            'achievement' => [
                'id' => $achievement->id,
                'student' => $achievement->user ? [
                    'id' => $achievement->user->id,
                    'label' => $achievement->user->name.' · '.($achievement->user->studentProfile?->nim ?? '-'),
                ] : null,
                'student_organization_id' => $achievement->student_organization_id,
                'title' => $achievement->title,
                'category' => $achievement->category->value,
                'level' => $achievement->level->value,
                'rank' => $achievement->rank->value,
                'achieved_at' => $achievement->achieved_at?->format('Y-m-d') ?? '',
                'organizer' => (string) $achievement->organizer,
                'description' => (string) $achievement->description,
                'status' => $achievement->status->value,
                'is_featured' => (bool) $achievement->is_featured,
                'rejection_reason' => (string) $achievement->rejection_reason,
                'certificate_url' => $achievement->certificate_path
                    ? route('admin.student-affairs.achievements.certificate', $achievement->id)
                    : null,
                'certificate_name' => $achievement->certificate_original_name,
            ],
            'options' => $this->formOptions(),
            'urls' => [
                'index' => route('admin.student-affairs.achievements.index'),
                'submit' => route('admin.student-affairs.achievements.update', $achievement->id),
                'searchStudents' => route('admin.student-affairs.achievements.search-students'),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $achievement = StudentAchievement::findOrFail($id);
        $validated = $this->validatePayload($request);
        $this->ensureStudent($validated['user_id']);
        $removeCertificate = (bool) ($validated['remove_certificate'] ?? false);

        $achievement->update([
            ...$this->applyVerificationState($validated, $request->user()->id),
            'updated_by' => $request->user()->id,
        ]);

        if ($removeCertificate && ! $request->hasFile('certificate')) {
            $this->deleteCertificate($achievement);
        }

        $this->storeCertificate($request, $achievement);

        return redirect()->route('admin.student-affairs.achievements.index')
            ->with('success', 'Prestasi mahasiswa berhasil diperbarui.');
    }

    public function show(Request $request, int $id): Response
    {
        $achievement = StudentAchievement::with([
            'user:id,first_name,last_name',
            'user.studentProfile:id,user_id,nim,study_program_id',
            'user.studentProfile.studyProgram:id,name',
            'organization:id,name',
            'verifier:id,first_name,last_name',
        ])->findOrFail($id);

        return Inertia::render('Admin/StudentAffairs/Achievements/Show', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', $achievement->title),
            'can' => [
                'update' => ActivePermission::check('student-achievement.update'),
                'delete' => ActivePermission::check('student-achievement.delete'),
                'verify' => ActivePermission::check('student-achievement.verify'),
            ],
            'achievement' => [
                'id' => $achievement->id,
                'title' => $achievement->title,
                'category' => $achievement->category->value,
                'categoryLabel' => $achievement->category->label(),
                'categoryTone' => $achievement->category->tone(),
                'level' => $achievement->level->value,
                'levelLabel' => $achievement->level->label(),
                'levelTone' => $achievement->level->tone(),
                'rank' => $achievement->rank->value,
                'rankLabel' => $achievement->rank->label(),
                'rankTone' => $achievement->rank->tone(),
                'achievedAt' => $achievement->achieved_at?->format('d M Y'),
                'organizer' => $achievement->organizer,
                'description' => $achievement->description,
                'status' => $achievement->status->value,
                'statusLabel' => $achievement->status->label(),
                'statusTone' => $achievement->status->tone(),
                'isFeatured' => (bool) $achievement->is_featured,
                'rejectionReason' => $achievement->rejection_reason,
                'verifiedBy' => $achievement->verifier?->name,
                'verifiedAt' => $achievement->verified_at?->format('d M Y H:i'),
                'certificateUrl' => $achievement->certificate_path
                    ? route('admin.student-affairs.achievements.certificate', $achievement->id)
                    : null,
                'certificateName' => $achievement->certificate_original_name,
                'createdAt' => $achievement->created_at?->format('d M Y H:i'),
                'updatedAt' => $achievement->updated_at?->format('d M Y H:i'),
                'student' => [
                    'id' => $achievement->user?->id,
                    'name' => $achievement->user?->name ?? '-',
                    'nim' => $achievement->user?->studentProfile?->nim,
                    'program' => $achievement->user?->studentProfile?->studyProgram?->name,
                ],
                'organization' => $achievement->organization ? [
                    'id' => $achievement->organization->id,
                    'name' => $achievement->organization->name,
                ] : null,
            ],
            'urls' => [
                'index' => route('admin.student-affairs.achievements.index'),
                'edit' => route('admin.student-affairs.achievements.edit', $achievement->id),
                'delete' => route('admin.student-affairs.achievements.destroy', $achievement->id),
                'verify' => route('admin.student-affairs.achievements.verify', $achievement->id),
                'reject' => route('admin.student-affairs.achievements.reject', $achievement->id),
                'feature' => route('admin.student-affairs.achievements.feature', $achievement->id),
                'certificate' => $achievement->certificate_path
                    ? route('admin.student-affairs.achievements.certificate', $achievement->id)
                    : null,
            ],
        ]);
    }

    public function verify(Request $request, int $id)
    {
        $achievement = StudentAchievement::findOrFail($id);

        $achievement->update([
            'status' => AchievementStatus::VERIFIED->value,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Prestasi berhasil diverifikasi.');
    }

    public function reject(Request $request, int $id)
    {
        $achievement = StudentAchievement::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $achievement->update([
            'status' => AchievementStatus::REJECTED->value,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
            'is_featured' => false,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Prestasi ditolak dengan alasan yang dicatat.');
    }

    public function toggleFeatured(Request $request, int $id)
    {
        $achievement = StudentAchievement::findOrFail($id);

        if ($achievement->status !== AchievementStatus::VERIFIED) {
            return back()->with('error', 'Hanya prestasi terverifikasi yang dapat ditampilkan di sorotan publik.');
        }

        $achievement->update([
            'is_featured' => ! $achievement->is_featured,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $achievement->is_featured
            ? 'Prestasi ditambahkan ke sorotan publik.'
            : 'Prestasi dikeluarkan dari sorotan publik.');
    }

    public function downloadCertificate(Request $request, int $id)
    {
        $achievement = StudentAchievement::findOrFail($id);

        if (blank($achievement->certificate_path) || ! Storage::disk('public')->exists($achievement->certificate_path)) {
            abort(404);
        }

        return Storage::disk('public')->download(
            $achievement->certificate_path,
            $achievement->certificate_original_name ?: 'sertifikat-prestasi'
        );
    }

    public function destroy(Request $request, int $id)
    {
        $achievement = StudentAchievement::findOrFail($id);

        $this->deleteCertificate($achievement);
        $achievement->update(['deleted_by' => $request->user()->id]);
        $achievement->delete();

        return redirect()->route('admin.student-affairs.achievements.index')
            ->with('success', 'Prestasi mahasiswa berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:student_achievements,id',
        ]);

        $achievements = StudentAchievement::whereIn('id', $validated['ids'])->get();

        foreach ($achievements as $achievement) {
            $this->deleteCertificate($achievement);
            $achievement->update(['deleted_by' => $request->user()->id]);
            $achievement->delete();
        }

        return redirect()->route('admin.student-affairs.achievements.index')
            ->with('success', $achievements->count().' prestasi mahasiswa berhasil dihapus.');
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

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'student_organization_id' => ['nullable', 'integer', 'exists:student_organizations,id'],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(AchievementCategory::class)],
            'level' => ['required', Rule::enum(AchievementLevel::class)],
            'rank' => ['required', Rule::enum(AchievementRank::class)],
            'achieved_at' => ['required', 'date', 'before_or_equal:today'],
            'organizer' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::enum(AchievementStatus::class)],
            'is_featured' => ['boolean'],
            'rejection_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,rejected'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'remove_certificate' => ['boolean'],
        ]);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyVerificationState(array $validated, int $userId): array
    {
        $status = AchievementStatus::from($validated['status']);

        $validated['status'] = $status->value;
        $validated['is_featured'] = $status === AchievementStatus::VERIFIED && (bool) ($validated['is_featured'] ?? false);
        unset($validated['certificate'], $validated['remove_certificate']);

        if ($status === AchievementStatus::VERIFIED) {
            $validated['verified_by'] = $userId;
            $validated['verified_at'] = now();
            $validated['rejection_reason'] = null;
        } elseif ($status === AchievementStatus::REJECTED) {
            $validated['verified_by'] = $userId;
            $validated['verified_at'] = now();
        } else {
            $validated['verified_by'] = null;
            $validated['verified_at'] = null;
            $validated['rejection_reason'] = null;
        }

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

    private function storeCertificate(Request $request, StudentAchievement $achievement): void
    {
        if (! $request->hasFile('certificate')) {
            return;
        }

        $file = $request->file('certificate');
        $path = $file->store('student-affairs/achievements/'.$achievement->id, 'public');

        if ($achievement->certificate_path) {
            Storage::disk('public')->delete($achievement->certificate_path);
        }

        $achievement->update([
            'certificate_path' => $path,
            'certificate_original_name' => $file->getClientOriginalName(),
        ]);
    }

    private function deleteCertificate(StudentAchievement $achievement): void
    {
        if (filled($achievement->certificate_path)) {
            Storage::disk('public')->delete($achievement->certificate_path);
        }

        $achievement->update([
            'certificate_path' => null,
            'certificate_original_name' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => AchievementCategory::options(),
            'levels' => AchievementLevel::options(),
            'ranks' => AchievementRank::options(),
            'statuses' => AchievementStatus::options(),
            'organizations' => StudentOrganization::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (StudentOrganization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ]),
        ];
    }
}
