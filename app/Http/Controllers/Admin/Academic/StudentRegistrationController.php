<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Registrasi Mahasiswa + approval (paritas Blade lama).
 *
 * Approval Approved mendorong semester_no ke profil mahasiswa
 * (kecuali Cuti) — ini yang membuka jalan KRS, invoice, dan jadwal.
 */
class StudentRegistrationController extends Controller
{
    public const ACADEMIC_STATUSES = ['Aktif', 'Cuti', 'Nonaktif', 'Lulus', 'Drop Out', 'Keluar'];
    public const REGISTRATION_STATUSES = ['Draft', 'Submitted', 'Approved', 'Rejected', 'Cancelled'];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'nim' => 'nullable|string|max:50',
            'year' => 'nullable|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'semester' => 'nullable|integer|min:1|max:14',
            'registration_status' => 'nullable|string|max:20',
            'academic_status' => 'nullable|string|max:20',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = StudentRegistration::query()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name',
                'studentProfile:id,nim,user_id,study_program_id',
                'academicYear:id,name',
                'approvedBy:id,first_name,last_name',
            ]);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('studentProfile.user', fn ($u) => $u
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        if (filled($validated['nim'] ?? null)) {
            $query->whereHas('studentProfile', fn ($s) => $s->where('nim', 'like', '%'.$validated['nim'].'%'));
        }

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['program'] ?? null)) {
            $query->whereHas('studentProfile', fn ($s) => $s->where('study_program_id', $validated['program']));
        }

        if (filled($validated['semester'] ?? null)) {
            $query->where('semester_no', $validated['semester']);
        }

        if (filled($validated['registration_status'] ?? null)) {
            $query->where('registration_status', $validated['registration_status']);
        }

        if (filled($validated['academic_status'] ?? null)) {
            $query->where('academic_status', $validated['academic_status']);
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $registrations = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/StudentRegistration/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Registrasi Mahasiswa'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('student-registration.create'),
                'update' => ActivePermission::check('student-registration.update'),
                'delete' => ActivePermission::check('student-registration.delete'),
                'view' => ActivePermission::check('student-registration.view'),
                'restore' => ActivePermission::any(['student-registration.update', 'student-registration.delete']),
                'toggle' => ActivePermission::check('student-registration.update'),
                'approve' => ActivePermission::check('student-registration.update'),
            ],
            'stats' => [
                'total' => StudentRegistration::count(),
                'active' => StudentRegistration::where('is_active', true)->count(),
                'approved' => StudentRegistration::where('registration_status', 'Approved')->count(),
                'pending' => StudentRegistration::whereIn('registration_status', ['Draft', 'Submitted'])->count(),
                'trashed' => StudentRegistration::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($registrations->items())->values()->map(fn ($r, $i) => [
                    'id' => $r->id,
                    'no' => ($registrations->firstItem() ?? 0) + $i,
                    'student' => $r->studentProfile?->user?->name ?? '-',
                    'nim' => $r->studentProfile?->nim,
                    'program' => $r->studentProfile?->studyProgram?->name,
                    'year' => $r->academicYear?->name,
                    'semester' => $r->semester_no,
                    'registrationStatus' => $r->registration_status,
                    'registrationTone' => $this->statusTone($r->registration_status),
                    'academicStatus' => $r->academic_status,
                    'isActive' => (bool) $r->is_active,
                    'createdAt' => $r->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.academic.student-registrations.show', $r->id),
                    'editUrl' => $isTrash ? null : route('admin.academic.student-registrations.edit', $r->id),
                    'deleteUrl' => route('admin.academic.student-registrations.destroy', $r->id),
                    'restoreUrl' => route('admin.academic.student-registrations.restore', $r->id),
                    'forceUrl' => route('admin.academic.student-registrations.force-destroy', $r->id),
                    'toggleUrl' => route('admin.academic.student-registrations.toggle', $r->id),
                    'approveUrl' => route('admin.academic.student-registrations.approve', $r->id),
                ])->all(),
                'currentPage' => $registrations->currentPage(),
                'lastPage' => $registrations->lastPage(),
                'perPage' => $registrations->perPage(),
                'total' => $registrations->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'nim' => $validated['nim'] ?? '',
                'year' => $validated['year'] ?? '',
                'program' => $validated['program'] ?? '',
                'semester' => $validated['semester'] ?? '',
                'registration_status' => $validated['registration_status'] ?? '',
                'academic_status' => $validated['academic_status'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'yearOptions' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->all(),
            'programOptions' => \App\Models\Academic\StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'registrationStatuses' => self::REGISTRATION_STATUSES,
            'academicStatuses' => self::ACADEMIC_STATUSES,
            'urls' => [
                'index' => route('admin.academic.student-registrations.index'),
                'create' => route('admin.academic.student-registrations.create'),
                'export' => route('admin.academic.student-registrations.export'),
                'exportPdf' => route('admin.academic.exports.pdf', ['resource' => 'student-registrations']),
                'importTemplate' => route('admin.academic.import-template', ['resource' => 'student-registrations']),
                'bulkDestroy' => route('admin.academic.student-registrations.bulk-destroy'),
                'bulkRestore' => route('admin.academic.student-registrations.bulk-restore'),
                'bulkForceDestroy' => route('admin.academic.student-registrations.bulk-force-destroy'),
                'searchStudents' => route('admin.academic.student-registrations.search-students'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/StudentRegistration/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Registrasi'),
            'mode' => 'create',
            'registration' => [
                'student_profile_id' => '', 'academic_year_id' => '', 'semester_no' => '',
                'academic_status' => 'Aktif', 'registration_status' => 'Draft',
                'notes' => '', 'is_active' => true,
            ],
            'years' => $this->yearOptions(),
            'academicStatuses' => self::ACADEMIC_STATUSES,
            'registrationStatuses' => self::REGISTRATION_STATUSES,
            'urls' => [
                'index' => route('admin.academic.student-registrations.index'),
                'submit' => route('admin.academic.student-registrations.store'),
                'searchStudents' => route('admin.academic.student-registrations.search-students'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_profile_id' => [
                'required', 'exists:student_profiles,id',
                Rule::unique('student_registrations', 'student_profile_id')
                    ->where('academic_year_id', $request->input('academic_year_id'))
                    ->whereNull('deleted_at'),
            ],
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_no' => 'nullable|integer|min:1|max:14',
            'academic_status' => 'required|in:'.implode(',', self::ACADEMIC_STATUSES),
            'registration_status' => 'required|in:'.implode(',', self::REGISTRATION_STATUSES),
            'notes' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $registration = StudentRegistration::create([
            'student_profile_id' => $validated['student_profile_id'],
            'academic_year_id' => $validated['academic_year_id'],
            'semester_no' => $validated['semester_no'] ?? null,
            'academic_status' => $validated['academic_status'],
            'registration_status' => $validated['registration_status'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.student-registrations.edit', $registration)
            ->with('success', 'Registrasi berhasil dibuat. Tinjau dan setujui bila sudah sesuai.');
    }

    public function show(Request $request, int $id): Response
    {
        $registration = StudentRegistration::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear', 'approvedBy'])
            ->findOrFail($id);

        return Inertia::render('Admin/Academic/StudentRegistration/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail Registrasi'),
            'registration' => $this->detailPayload($registration),
            'canUpdate' => ActivePermission::check('student-registration.update'),
            'urls' => [
                'index' => route('admin.academic.student-registrations.index'),
                'edit' => route('admin.academic.student-registrations.edit', $registration),
                'approve' => route('admin.academic.student-registrations.approve', $registration),
            ],
        ]);
    }

    public function edit(Request $request, StudentRegistration $studentRegistration): Response
    {
        $studentRegistration->load(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear', 'approvedBy']);

        return Inertia::render('Admin/Academic/StudentRegistration/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Registrasi'),
            'mode' => 'edit',
            'registration' => [
                'id' => $studentRegistration->id,
                'student_profile_id' => $studentRegistration->student_profile_id,
                'student_label' => trim(($studentRegistration->studentProfile?->nim ? $studentRegistration->studentProfile->nim.' - ' : '').($studentRegistration->studentProfile?->user?->name ?? '')),
                'academic_year_id' => $studentRegistration->academic_year_id,
                'semester_no' => $studentRegistration->semester_no ?? '',
                'academic_status' => $studentRegistration->academic_status,
                'registration_status' => $studentRegistration->registration_status,
                'notes' => $studentRegistration->notes ?? '',
                'is_active' => (bool) $studentRegistration->is_active,
                'submitted_at' => $studentRegistration->submitted_at?->format('d M Y H:i'),
                'approved_at' => $studentRegistration->approved_at?->format('d M Y H:i'),
                'approved_by' => $studentRegistration->approvedBy?->name,
            ],
            'years' => $this->yearOptions(),
            'academicStatuses' => self::ACADEMIC_STATUSES,
            'registrationStatuses' => self::REGISTRATION_STATUSES,
            'urls' => [
                'index' => route('admin.academic.student-registrations.index'),
                'submit' => route('admin.academic.student-registrations.update', $studentRegistration),
                'show' => route('admin.academic.student-registrations.show', $studentRegistration),
                'approve' => route('admin.academic.student-registrations.approve', $studentRegistration),
                'searchStudents' => route('admin.academic.student-registrations.search-students'),
            ],
        ]);
    }

    public function update(Request $request, StudentRegistration $studentRegistration)
    {
        $validated = $request->validate([
            'semester_no' => 'nullable|integer|min:1|max:14',
            'academic_status' => 'required|in:'.implode(',', self::ACADEMIC_STATUSES),
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $studentRegistration->update([
            'semester_no' => $validated['semester_no'] ?? null,
            'academic_status' => $validated['academic_status'],
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.student-registrations.index')
            ->with('success', 'Registrasi berhasil diperbarui.');
    }

    /**
     * Approval registrasi (Approved/Rejected + catatan opsional).
     */
    public function approve(Request $request, StudentRegistration $studentRegistration)
    {
        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $request, $studentRegistration) {
            $updateData = [
                'registration_status' => $validated['status'],
                'updated_by' => $request->user()->id,
            ];

            if ($validated['status'] === 'Approved') {
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = $request->user()->id;

                if ($studentRegistration->academic_status !== 'Cuti'
                    && $studentRegistration->semester_no !== null
                    && $studentRegistration->studentProfile) {
                    $studentRegistration->studentProfile->update([
                        'current_semester' => $studentRegistration->semester_no,
                        'updated_by' => $request->user()->id,
                    ]);
                }
            }

            if (! empty($validated['notes'])) {
                $updateData['notes'] = $validated['notes'];
            }

            $studentRegistration->update($updateData);
        });

        return back()->with(
            'success',
            $validated['status'] === 'Approved' ? 'Registrasi berhasil disetujui!' : 'Registrasi berhasil ditolak!'
        );
    }

    public function toggle(Request $request, StudentRegistration $studentRegistration)
    {
        $validated = $request->validate(['is_active' => 'required|boolean']);

        $studentRegistration->update(['is_active' => $validated['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Status registrasi berhasil diperbarui.');
    }

    public function destroy(StudentRegistration $studentRegistration)
    {
        $studentRegistration->delete();

        return redirect()->route('admin.academic.student-registrations.index')
            ->with('success', 'Registrasi berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_registrations,id',
        ]);

        $count = StudentRegistration::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' registrasi berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $registration = StudentRegistration::withTrashed()->findOrFail($id);
        $registration->restore();

        return back()->with('success', 'Registrasi berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $registration = StudentRegistration::withTrashed()->findOrFail($id);
        $registration->forceDelete();

        return back()->with('success', 'Registrasi dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_registrations,id',
        ]);

        $count = 0;

        foreach (StudentRegistration::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $registration) {
            $registration->restore();
            $count++;
        }

        return back()->with('success', $count.' registrasi berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:student_registrations,id',
        ]);

        $count = 0;

        foreach (StudentRegistration::withTrashed()->whereIn('id', $validated['ids'])->get() as $registration) {
            $registration->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' registrasi dihapus permanen.');
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = StudentProfile::query()
            ->with(['user:id,first_name,last_name', 'studyProgram:id,name'])
            ->when($q !== '', fn ($query) => $query
                ->where('nim', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")))
            ->orderBy('nim')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => trim(($s->nim ? $s->nim.' - ' : '').($s->user?->name ?? '').($s->studyProgram ? ' ('.$s->studyProgram->name.')' : '')),
            ])
            ->all();

        return response()->json(['options' => $students]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = StudentRegistration::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicYear']);

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $rows = $query->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Mahasiswa', 'NIM', 'Prodi', 'Tahun', 'Semester', 'Status Registrasi', 'Status Akademik', 'Aktif', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $registration) {
            $sheet->fromArray([
                $registration->studentProfile?->user?->name,
                $registration->studentProfile?->nim,
                $registration->studentProfile?->studyProgram?->name,
                $registration->academicYear?->name,
                $registration->semester_no,
                $registration->registration_status,
                $registration->academic_status,
                $registration->is_active ? 'Ya' : 'Tidak',
                $registration->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'student-registrations-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function detailPayload(StudentRegistration $registration): array
    {
        $statusTone = match ($registration->registration_status) {
            'Approved' => 'green',
            'Rejected' => 'red',
            'Submitted' => 'amber',
            'Cancelled' => 'gray',
            default => '',
        };

        return [
            'id' => $registration->id,
            'student' => $registration->studentProfile?->user?->name ?? '-',
            'nim' => $registration->studentProfile?->nim,
            'program' => $registration->studentProfile?->studyProgram?->name,
            'year' => $registration->academicYear?->name,
            'semester' => $registration->semester_no,
            'registrationStatus' => $registration->registration_status,
            'registrationTone' => $statusTone,
            'academicStatus' => $registration->academic_status,
            'isActive' => (bool) $registration->is_active,
            'notes' => $registration->notes,
            'submittedAt' => $registration->submitted_at?->format('d M Y H:i'),
            'approvedAt' => $registration->approved_at?->format('d M Y H:i'),
            'approvedBy' => $registration->approvedBy?->name,
            'createdAt' => $registration->created_at?->format('d M Y H:i'),
            'canApprove' => in_array($registration->registration_status, ['Draft', 'Submitted'], true),
        ];
    }

    private function statusTone(?string $status): string
    {
        return match ($status) {
            'Approved' => 'green',
            'Rejected' => 'red',
            'Submitted' => 'amber',
            'Cancelled' => 'gray',
            default => '',
        };
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])
            ->all();
    }
}
