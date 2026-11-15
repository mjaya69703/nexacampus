<?php

namespace App\Http\Controllers\Admin\Alumni;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniProfile;
use App\Models\StudentService\GraduationBatch;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Profil Alumni — kit CRUD shared (Inertia React).
 *
 * Koreksi dari Blade lama:
 * - statistik "Bekerja" memakai nilai enum `working` (dulu `employed`, selalu 0);
 * - fakultas & program studi wajib (kolom DB NOT NULL) dan harus konsisten;
 * - kolom opsional bisa dikosongkan saat edit (dulu nilai kosong diabaikan);
 * - file foto dibersihkan saat diganti/dihapus permanen (dulu bocor);
 * - konversi yudisium tersedia dari halaman daftar.
 */
class AlumniProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $this->validateIndex($request);
        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query = $this->filteredQuery($validated, applySort: false);
        $this->applySort($query, $sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $profiles = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Alumni/Profile/Index', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Data Alumni'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('alumni-profile.create'),
                'update' => ActivePermission::check('alumni-profile.update'),
                'delete' => ActivePermission::check('alumni-profile.delete'),
                'restore' => ActivePermission::any(['alumni-profile.update', 'alumni-profile.delete']),
                'toggle' => ActivePermission::check('alumni-profile.update'),
                'view' => ActivePermission::check('alumni-profile.view'),
                'convert' => ! $isTrash && ActivePermission::check('alumni-profile.create'),
            ],
            'stats' => [
                'total' => AlumniProfile::count(),
                'active' => AlumniProfile::where('is_active', true)->count(),
                'working' => AlumniProfile::where('employment_status', EmploymentStatus::Working->value)->count(),
                'entrepreneur' => AlumniProfile::where('employment_status', EmploymentStatus::Entrepreneur->value)->count(),
                'trashed' => AlumniProfile::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($profiles->items())->values()->map(fn (AlumniProfile $profile, int $i) => [
                    'id' => $profile->id,
                    'no' => ($profiles->firstItem() ?? 0) + $i,
                    'nim' => $profile->nim,
                    'fullName' => $profile->full_name,
                    'email' => $profile->email,
                    'phone' => $profile->phone,
                    'photoUrl' => $this->publicUrl($profile->photo_path),
                    'studyProgram' => $profile->studyProgram?->name ?? '-',
                    'faculty' => $profile->faculty?->name ?? '-',
                    'graduationYear' => (int) $profile->graduation_year,
                    'graduationDate' => $profile->graduation_date?->format('d M Y'),
                    'gpa' => $profile->gpa !== null ? (float) $profile->gpa : null,
                    'employmentStatus' => $profile->employment_status,
                    'employmentStatusLabel' => EmploymentStatus::tryFrom($profile->employment_status)?->label() ?? $profile->employment_status,
                    'employerName' => $profile->employer_name,
                    'jobTitle' => $profile->job_title,
                    'currentCity' => $profile->current_city,
                    'completeness' => $profile->profileCompleteness(),
                    'isActive' => (bool) $profile->is_active,
                    'createdAt' => $profile->created_at?->format('d M Y H:i'),
                    'showUrl' => $isTrash ? null : route('admin.alumni.alumni-profiles.show', $profile),
                    'editUrl' => $isTrash ? null : route('admin.alumni.alumni-profiles.edit', $profile),
                    'deleteUrl' => route('admin.alumni.alumni-profiles.destroy', $profile),
                    'restoreUrl' => route('admin.alumni.alumni-profiles.restore', $profile->id),
                    'forceUrl' => route('admin.alumni.alumni-profiles.force-destroy', $profile->id),
                    'toggleUrl' => route('admin.alumni.alumni-profiles.toggle', $profile),
                ])->all(),
                'currentPage' => $profiles->currentPage(),
                'lastPage' => $profiles->lastPage(),
                'perPage' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'faculty_id' => $validated['faculty_id'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'graduation_year' => $validated['graduation_year'] ?? '',
                'employment_status' => $validated['employment_status'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'facultyOptions' => $this->facultyOptions(),
            'studyProgramOptions' => $this->studyProgramOptions(),
            'yearOptions' => $this->yearOptions(),
            'employmentStatusOptions' => $this->employmentStatusOptions(),
            'batchOptions' => $this->graduationBatchOptions(),
            'urls' => [
                'index' => route('admin.alumni.alumni-profiles.index'),
                'create' => route('admin.alumni.alumni-profiles.create'),
                'export' => route('admin.alumni.alumni-profiles.export'),
                'bulkDestroy' => route('admin.alumni.alumni-profiles.bulk-destroy'),
                'bulkRestore' => route('admin.alumni.alumni-profiles.bulk-restore'),
                'bulkForceDestroy' => route('admin.alumni.alumni-profiles.bulk-force-destroy'),
                'batchConvert' => route('admin.alumni.alumni-profiles.batch-convert'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Alumni/Profile/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Tambah Data Alumni'),
            'mode' => 'create',
            'profile' => $this->defaultProfile(),
            'facultyOptions' => $this->facultyOptions(),
            'studyProgramOptions' => $this->studyProgramOptions(),
            'employmentStatusOptions' => $this->employmentStatusOptions(),
            'urls' => [
                'index' => route('admin.alumni.alumni-profiles.index'),
                'submit' => route('admin.alumni.alumni-profiles.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateProfile($request);
        $this->ensureProgramMatchesFaculty($validated);

        $data = [
            ...$this->profilePayload($validated),
            'created_by' => $request->user()->id,
        ];

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('alumni/photos', 'public');
        }

        $profile = AlumniProfile::create($data);

        return redirect()->route('admin.alumni.alumni-profiles.index')
            ->with('success', 'Profil alumni "'.$profile->full_name.'" berhasil ditambahkan.');
    }

    public function edit(Request $request, AlumniProfile $profile): Response
    {
        return Inertia::render('Admin/Alumni/Profile/Form', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Edit Data Alumni'),
            'mode' => 'edit',
            'profile' => [
                'id' => $profile->id,
                ...$this->defaultProfile(),
                'nim' => $profile->nim,
                'full_name' => $profile->full_name,
                'graduation_date' => $profile->graduation_date?->toDateString() ?? '',
                'graduation_year' => (int) $profile->graduation_year,
                'faculty_id' => $profile->faculty_id,
                'study_program_id' => $profile->study_program_id,
                'gpa' => $profile->gpa !== null ? (float) $profile->gpa : '',
                'birth_date' => $profile->birth_date?->toDateString() ?? '',
                'gender' => $profile->gender ?? '',
                'email' => $profile->email,
                'phone' => $profile->phone ?? '',
                'address' => $profile->address ?? '',
                'current_city' => $profile->current_city ?? '',
                'current_province' => $profile->current_province ?? '',
                'employment_status' => $profile->employment_status,
                'employer_name' => $profile->employer_name ?? '',
                'job_title' => $profile->job_title ?? '',
                'job_industry' => $profile->job_industry ?? '',
                'linkedin_url' => $profile->linkedin_url ?? '',
                'is_active' => (bool) $profile->is_active,
                'photo_url' => $this->publicUrl($profile->photo_path),
            ],
            'facultyOptions' => $this->facultyOptions(),
            'studyProgramOptions' => $this->studyProgramOptions(),
            'employmentStatusOptions' => $this->employmentStatusOptions(),
            'urls' => [
                'index' => route('admin.alumni.alumni-profiles.index'),
                'submit' => route('admin.alumni.alumni-profiles.update', $profile),
            ],
        ]);
    }

    public function update(Request $request, AlumniProfile $profile)
    {
        $validated = $this->validateProfile($request, $profile);
        $this->ensureProgramMatchesFaculty($validated);

        $data = [
            ...$this->profilePayload($validated),
            'updated_by' => $request->user()->id,
        ];

        if (($validated['remove_photo'] ?? false) && $profile->photo_path) {
            $this->deletePhoto($profile->photo_path);
            $data['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($profile->photo_path) {
                $this->deletePhoto($profile->photo_path);
            }

            $data['photo_path'] = $request->file('photo')->store('alumni/photos', 'public');
        }

        $profile->update($data);

        return redirect()->route('admin.alumni.alumni-profiles.index')
            ->with('success', 'Profil alumni "'.$profile->full_name.'" berhasil diperbarui.');
    }

    public function show(Request $request, AlumniProfile $profile): Response
    {
        $profile->loadMissing(['studyProgram:id,name', 'faculty:id,name', 'user:id,first_name,last_name,email', 'createdByUser:id,first_name,last_name', 'updatedByUser:id,first_name,last_name']);

        return Inertia::render('Admin/Alumni/Profile/Show', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Detail Profil Alumni'),
            'can' => [
                'update' => ActivePermission::check('alumni-profile.update'),
                'delete' => ActivePermission::check('alumni-profile.delete'),
            ],
            'profile' => [
                'id' => $profile->id,
                'nim' => $profile->nim,
                'fullName' => $profile->full_name,
                'email' => $profile->email,
                'phone' => $profile->phone,
                'gender' => $profile->gender,
                'genderLabel' => match ($profile->gender) {
                    'L' => 'Laki-laki',
                    'P' => 'Perempuan',
                    default => null,
                },
                'birthDate' => $profile->birth_date?->format('d M Y'),
                'graduationDate' => $profile->graduation_date?->format('d M Y'),
                'graduationYear' => (int) $profile->graduation_year,
                'gpa' => $profile->gpa !== null ? (float) $profile->gpa : null,
                'faculty' => $profile->faculty?->name,
                'studyProgram' => $profile->studyProgram?->name,
                'address' => $profile->address,
                'currentCity' => $profile->current_city,
                'currentProvince' => $profile->current_province,
                'employmentStatus' => $profile->employment_status,
                'employmentStatusLabel' => EmploymentStatus::tryFrom($profile->employment_status)?->label() ?? $profile->employment_status,
                'employerName' => $profile->employer_name,
                'jobTitle' => $profile->job_title,
                'jobIndustry' => $profile->job_industry,
                'linkedinUrl' => $profile->linkedin_url,
                'photoUrl' => $this->publicUrl($profile->photo_path),
                'isActive' => (bool) $profile->is_active,
                'completeness' => $profile->profileCompleteness(),
                'hasAccount' => (bool) $profile->user,
                'accountName' => $profile->user?->name,
                'accountEmail' => $profile->user?->email,
                'createdAt' => $profile->created_at?->format('d M Y H:i'),
                'updatedAt' => $profile->updated_at?->format('d M Y H:i'),
                'createdBy' => $profile->createdByUser?->name,
                'updatedBy' => $profile->updatedByUser?->name,
            ],
            'urls' => [
                'index' => route('admin.alumni.alumni-profiles.index'),
                'edit' => ActivePermission::check('alumni-profile.update')
                    ? route('admin.alumni.alumni-profiles.edit', $profile)
                    : null,
                'destroy' => route('admin.alumni.alumni-profiles.destroy', $profile),
            ],
        ]);
    }

    public function toggle(Request $request, AlumniProfile $profile)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $profile->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Status keanggotaan alumni berhasil diperbarui.');
    }

    public function destroy(Request $request, AlumniProfile $profile)
    {
        $name = $profile->full_name;
        $profile->update(['updated_by' => $request->user()->id]);
        $profile->delete();

        return redirect()->route('admin.alumni.alumni-profiles.index')
            ->with('success', 'Profil alumni "'.$name.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:alumni_profiles,id',
        ]);

        $profiles = AlumniProfile::whereIn('id', $validated['ids'])->get();

        foreach ($profiles as $profile) {
            $profile->update(['updated_by' => $request->user()->id]);
            $profile->delete();
        }

        return back()->with('success', $profiles->count().' profil alumni berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $profile = AlumniProfile::withTrashed()->findOrFail($id);
        $profile->restore();

        return back()->with('success', 'Profil alumni "'.$profile->full_name.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $profile = AlumniProfile::withTrashed()->findOrFail($id);
        $name = $profile->full_name;

        $this->deletePhoto($profile->photo_path);
        $profile->forceDelete();

        return back()->with('success', 'Profil alumni "'.$name.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:alumni_profiles,id',
        ]);

        $count = 0;

        foreach (AlumniProfile::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $profile) {
            $profile->restore();
            $count++;
        }

        return back()->with('success', $count.' profil alumni berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:alumni_profiles,id',
        ]);

        $count = 0;

        foreach (AlumniProfile::withTrashed()->whereIn('id', $validated['ids'])->get() as $profile) {
            $this->deletePhoto($profile->photo_path);
            $profile->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' profil alumni dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = $this->filteredQuery($request->all(), applySort: false);

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['NIM', 'Nama', 'Email', 'Telepon', 'Fakultas', 'Program Studi', 'Tahun Lulus', 'Tanggal Lulus', 'IPK', 'Status Kerja', 'Perusahaan', 'Jabatan', 'Kota', 'Provinsi', 'Aktif', 'Dibuat'],
        ], null, 'A1');

        foreach ($rows as $i => $profile) {
            $sheet->fromArray([
                $profile->nim,
                $profile->full_name,
                $profile->email,
                $profile->phone,
                $profile->faculty?->name,
                $profile->studyProgram?->name,
                (int) $profile->graduation_year,
                $profile->graduation_date?->format('Y-m-d'),
                $profile->gpa !== null ? (float) $profile->gpa : null,
                EmploymentStatus::tryFrom($profile->employment_status)?->label() ?? $profile->employment_status,
                $profile->employer_name,
                $profile->job_title,
                $profile->current_city,
                $profile->current_province,
                $profile->is_active ? 'Ya' : 'Tidak',
                $profile->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'data-alumni-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIndex(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:100',
            'faculty_id' => 'nullable|integer|exists:faculties,id',
            'study_program_id' => 'nullable|integer|exists:study_programs,id',
            'graduation_year' => 'nullable|integer|min:1990|max:2100',
            'employment_status' => ['nullable', Rule::in(array_keys(EmploymentStatus::options()))],
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,nim,full_name,study_program,graduation_year,graduation_date,gpa,employment_status,current_city,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function filteredQuery(array $validated, bool $applySort = true): Builder
    {
        $isTrash = ($validated['mode'] ?? 'all') === 'trash';

        $query = AlumniProfile::query()
            ->with(['studyProgram:id,name,code', 'faculty:id,name']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('nim', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('employer_name', 'like', "%{$q}%")
                    ->orWhere('current_city', 'like', "%{$q}%")
                    ->orWhereHas('studyProgram', fn (Builder $program) => $program
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%"))
                    ->orWhereHas('faculty', fn (Builder $faculty) => $faculty->where('name', 'like', "%{$q}%"));
            });
        }

        foreach (['faculty_id', 'study_program_id', 'graduation_year', 'employment_status'] as $field) {
            if (filled($validated[$field] ?? null)) {
                $query->where($field, $validated[$field]);
            }
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        if ($applySort) {
            $this->applySort($query, $validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc');
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($sort === 'study_program') {
            $query->orderBy(
                StudyProgram::query()->select('name')->whereColumn('study_programs.id', 'alumni_profiles.study_program_id'),
                $direction,
            );

            return;
        }

        $query->orderBy($sort, $direction);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProfile(Request $request, ?AlumniProfile $profile = null): array
    {
        return $request->validate([
            'nim' => [
                'required', 'string', 'max:50',
                Rule::unique('alumni_profiles', 'nim')->ignore($profile?->id),
            ],
            'full_name' => 'required|string|max:255',
            'graduation_date' => 'required|date',
            'graduation_year' => 'required|integer|min:1990|max:2100',
            'faculty_id' => 'required|integer|exists:faculties,id',
            'study_program_id' => 'required|integer|exists:study_programs,id',
            'gpa' => 'nullable|numeric|min:0|max:4',
            'birth_date' => 'nullable|date|before_or_equal:today',
            'gender' => 'nullable|in:L,P',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:2000',
            'current_city' => 'nullable|string|max:255',
            'current_province' => 'nullable|string|max:255',
            'employment_status' => ['required', Rule::in(array_keys(EmploymentStatus::options()))],
            'employer_name' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'job_industry' => 'nullable|string|max:255',
            'linkedin_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_photo' => 'boolean',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureProgramMatchesFaculty(array $validated): void
    {
        $facultyId = StudyProgram::whereKey($validated['study_program_id'])->value('faculty_id');

        if ((int) $facultyId !== (int) $validated['faculty_id']) {
            throw ValidationException::withMessages([
                'study_program_id' => 'Program studi yang dipilih tidak berada di bawah fakultas tersebut.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function profilePayload(array $validated): array
    {
        return [
            'nim' => $validated['nim'],
            'full_name' => $validated['full_name'],
            'graduation_date' => $validated['graduation_date'],
            'graduation_year' => $validated['graduation_year'],
            'faculty_id' => $validated['faculty_id'],
            'study_program_id' => $validated['study_program_id'],
            'gpa' => $validated['gpa'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'current_city' => $validated['current_city'] ?? null,
            'current_province' => $validated['current_province'] ?? null,
            'employment_status' => $validated['employment_status'],
            'employer_name' => $validated['employer_name'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'job_industry' => $validated['job_industry'] ?? null,
            'linkedin_url' => $validated['linkedin_url'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultProfile(): array
    {
        return [
            'nim' => '',
            'full_name' => '',
            'graduation_date' => now()->toDateString(),
            'graduation_year' => (int) now()->year,
            'faculty_id' => '',
            'study_program_id' => '',
            'gpa' => '',
            'birth_date' => '',
            'gender' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'current_city' => '',
            'current_province' => '',
            'employment_status' => EmploymentStatus::Unemployed->value,
            'employer_name' => '',
            'job_title' => '',
            'job_industry' => '',
            'linkedin_url' => '',
            'is_active' => true,
            'photo_url' => null,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function facultyOptions(): array
    {
        return Faculty::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'code' => $faculty->code,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, facultyId: int}>
     */
    private function studyProgramOptions(): array
    {
        return StudyProgram::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'faculty_id'])
            ->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'facultyId' => $program->faculty_id,
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        return AlumniProfile::query()
            ->whereNotNull('graduation_year')
            ->distinct()
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function employmentStatusOptions(): array
    {
        return EmploymentStatus::options();
    }

    /**
     * @return array<int, array{id: int, label: string, count: int}>
     */
    private function graduationBatchOptions(): array
    {
        return GraduationBatch::query()
            ->withCount(['applications as finalized_count' => fn (Builder $query) => $query->where('status', 'finalized')])
            ->whereHas('applications', fn (Builder $query) => $query->where('status', 'finalized'))
            ->orderByDesc('yudisium_date')
            ->get(['id', 'name', 'code', 'yudisium_date'])
            ->map(fn (GraduationBatch $batch) => [
                'id' => $batch->id,
                'label' => $batch->name.' ('.$batch->code.')',
                'count' => (int) $batch->finalized_count,
            ])
            ->all();
    }

    private function deletePhoto(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }
}
