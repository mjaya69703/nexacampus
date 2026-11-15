<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionConversionService;
use App\Support\Admission\AdmissionStatusService;
use App\Support\Admission\NimGenerationService;
use App\Support\Inertia\ShellProps;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Pendaftar PMB — daftar, detail review, verifikasi dokumen & konversi.
 *
 * Paritas Blade lama: filter berjenjang (periode/fakultas/prodi/kelas/status),
 * export XLSX/CSV/PDF, bulk convert menjadi mahasiswa, serta trash/restore.
 */
class AdmissionApplicationController extends Controller
{
    public const STATUSES = [
        'submitted' => 'Submitted (Terkirim)',
        'under_review' => 'Under Review (Proses Seleksi)',
        'accepted' => 'Accepted (Diterima)',
        'rejected' => 'Rejected (Ditolak)',
        'waitlisted' => 'Waitlisted (Cadangan)',
    ];

    public const CLASS_TYPES = [
        'regular' => 'Reguler Pagi',
        'evening' => 'Kelas Malam',
        'weekend' => 'Kelas Akhir Pekan (Weekend)',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'admission_period_id' => 'nullable|integer|exists:admission_periods,id',
            'faculty_id' => 'nullable|integer|exists:faculties,id',
            'study_program_id' => 'nullable|integer|exists:study_programs,id',
            'class_type' => ['nullable', Rule::in(array_keys(self::CLASS_TYPES))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'sort' => 'nullable|in:id,application_number,full_name,email,phone,class_type,status,final_score,submitted_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = AdmissionApplication::query()
            ->with(['period:id,name,code', 'faculty:id,name', 'studyProgram:id,name,code'])
            ->withCount(['documents', 'scores', 'examParticipants']);

        if ($isTrash) {
            $query->onlyTrashed();
        }

        $this->applyFilters($query, $validated);

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $applications = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Admission/Application/Index', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Pendaftar PMB'),
            'can' => [
                'view' => ActivePermission::check('admission-application.view'),
                'update' => ActivePermission::check('admission-application.update'),
                'delete' => ActivePermission::check('admission-application.delete'),
                'restore' => ActivePermission::any(['admission-application.update', 'admission-application.delete']),
                'convert' => ! $isTrash && ActivePermission::check('admission-application.update'),
            ],
            'stats' => [
                'total' => AdmissionApplication::count(),
                'underReview' => AdmissionApplication::where('status', 'under_review')->count(),
                'accepted' => AdmissionApplication::where('status', 'accepted')->count(),
                'converted' => AdmissionApplication::whereNotNull('converted_at')->count(),
                'trashed' => AdmissionApplication::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($applications->items())->values()->map(fn (AdmissionApplication $application, $i) => [
                    'id' => $application->id,
                    'no' => ($applications->firstItem() ?? 0) + $i,
                    'applicationNumber' => $application->application_number,
                    'fullName' => $application->full_name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'period' => $application->period?->name,
                    'faculty' => $application->faculty?->name,
                    'studyProgram' => $application->studyProgram?->name,
                    'classType' => $application->class_type,
                    'classTypeLabel' => self::CLASS_TYPES[$application->class_type] ?? ucfirst((string) $application->class_type),
                    'status' => $application->status,
                    'statusLabel' => self::STATUSES[$application->status] ?? ucfirst((string) $application->status),
                    'documentsCount' => (int) $application->documents_count,
                    'scoresCount' => (int) $application->scores_count,
                    'examCount' => (int) $application->exam_participants_count,
                    'finalScore' => $application->final_score,
                    'convertedAt' => $application->converted_at?->format('d M Y H:i'),
                    'submittedAt' => $application->submitted_at?->format('d M Y H:i'),
                    'createdAt' => $application->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.admission.admission-applications.show', $application),
                    'deleteUrl' => route('admin.admission.admission-applications.destroy', $application),
                    'restoreUrl' => route('admin.admission.admission-applications.restore', $application->id),
                    'forceUrl' => route('admin.admission.admission-applications.force-destroy', $application->id),
                ])->all(),
                'currentPage' => $applications->currentPage(),
                'lastPage' => $applications->lastPage(),
                'perPage' => $applications->perPage(),
                'total' => $applications->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'admission_period_id' => $validated['admission_period_id'] ?? '',
                'faculty_id' => $validated['faculty_id'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'class_type' => $validated['class_type'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'options' => [
                'periods' => $this->periodOptions(),
                'faculties' => $this->facultyOptions(),
                'studyPrograms' => $this->studyProgramOptions(),
                'statuses' => $this->statusOptions(),
                'classTypes' => $this->classTypeOptions(),
            ],
            'urls' => [
                'index' => route('admin.admission.admission-applications.index'),
                'export' => route('admin.admission.admission-applications.export'),
                'bulkDestroy' => route('admin.admission.admission-applications.bulk-destroy'),
                'bulkRestore' => route('admin.admission.admission-applications.bulk-restore'),
                'bulkForceDestroy' => route('admin.admission.admission-applications.bulk-force-destroy'),
                'bulkConvert' => route('admin.admission.admission-applications.bulk-convert'),
            ],
        ]);
    }

    public function show(Request $request, AdmissionApplication $application): Response
    {
        $application->load([
            'period.academicYear',
            'faculty:id,name',
            'studyProgram:id,name,code',
            'documents.requirement',
            'documents.verifiedBy',
            'examParticipants.schedule',
            'scores.schedule',
            'scores.scorer',
            'statusHistories.changedBy',
            'reviewedBy',
            'user.studentProfile',
        ]);

        return Inertia::render('Admin/Admission/Application/Show', [
            'shell' => ShellProps::make($request->user(), 'Admission', 'Detail Pendaftar'),
            'can' => [
                'update' => ActivePermission::check('admission-application.update'),
                'convert' => ActivePermission::check('admission-application.update'),
                'delete' => ActivePermission::check('admission-application.delete'),
            ],
            'application' => [
                'id' => $application->id,
                'applicationNumber' => $application->application_number,
                'fullName' => $application->full_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'birthDate' => $application->birth_date?->format('d F Y'),
                'gender' => $application->gender,
                'genderLabel' => $application->gender === 'male' ? 'Laki-laki' : ($application->gender === 'female' ? 'Perempuan' : ucfirst((string) $application->gender)),
                'address' => $application->address,
                'emergencyContactName' => $application->emergency_contact_name,
                'emergencyContactPhone' => $application->emergency_contact_phone,
                'highSchoolName' => $application->high_school_name,
                'highSchoolMajor' => $application->high_school_major,
                'highSchoolGraduationYear' => $application->high_school_graduation_year,
                'period' => $application->period?->name,
                'academicYear' => $application->period?->academicYear?->name,
                'faculty' => $application->faculty?->name,
                'studyProgram' => $application->studyProgram?->name,
                'classType' => self::CLASS_TYPES[$application->class_type] ?? ucfirst((string) $application->class_type),
                'status' => $application->status,
                'statusLabel' => self::STATUSES[$application->status] ?? ucfirst((string) $application->status),
                'finalScore' => $application->final_score,
                'reviewNotes' => $application->review_notes,
                'submittedAt' => $application->submitted_at?->format('d M Y H:i'),
                'reviewedAt' => $application->reviewed_at?->format('d M Y H:i'),
                'reviewedBy' => $application->reviewedBy?->name,
                'acceptedAt' => $application->accepted_at?->format('d M Y H:i'),
                'convertedAt' => $application->converted_at?->format('d F Y, H:i'),
                'nim' => $application->user?->studentProfile?->nim,
                'portalUrl' => route('root.admission.portal', [
                    'applicationNumber' => $application->application_number,
                    'token' => $application->access_token,
                ]),
                'acceptanceLetterUrl' => $application->converted_at
                    ? route('admin.admission.applications.acceptance-letter', ['application' => $application->id])
                    : null,
                'documents' => $application->documents->map(function (AdmissionDocument $document) use ($application) {
                    $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));

                    return [
                        'id' => $document->id,
                        'label' => $document->requirement?->label ?? $document->document_type,
                        'fileName' => $document->file_name,
                        'fileSizeKb' => round($document->file_size / 1024, 1),
                        'verificationStatus' => $document->verification_status,
                        'verificationNotes' => $document->verification_notes,
                        'verifiedBy' => $document->verifiedBy?->name,
                        'verifiedAt' => $document->verified_at?->format('d M Y H:i'),
                        'previewUrl' => route('admin.admission.documents.preview', ['document' => $document->id]),
                        'verifyUrl' => route('admin.admission.admission-applications.documents.verify', [$application, $document]),
                        'isImage' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                        'isPdf' => $extension === 'pdf',
                    ];
                })->values()->all(),
                'examParticipants' => $application->examParticipants->map(fn ($participant) => [
                    'id' => $participant->id,
                    'title' => $participant->schedule?->title,
                    'examType' => $participant->schedule?->exam_type,
                    'examDate' => $participant->schedule?->exam_date?->format('d M Y'),
                    'examTime' => $participant->schedule?->exam_time?->format('H:i'),
                    'attendanceStatus' => $participant->attendance_status,
                ])->values()->all(),
                'scores' => $application->scores->map(fn ($score) => [
                    'id' => $score->id,
                    'scoreType' => $score->score_type,
                    'schedule' => $score->schedule?->title,
                    'score' => $score->score,
                    'weight' => $score->weight,
                    'scorer' => $score->scorer?->name,
                    'notes' => $score->notes,
                ])->values()->all(),
                'histories' => $application->statusHistories->sortByDesc('created_at')->values()->map(fn ($history) => [
                    'id' => $history->id,
                    'fromStatus' => $history->from_status,
                    'toStatus' => $history->to_status,
                    'notes' => $history->notes,
                    'changedBy' => $history->changedBy?->name ?? 'Sistem Otomatis',
                    'createdAt' => $history->created_at?->format('d F Y, H:i'),
                ])->values()->all(),
                'review' => [
                    'status' => $application->status,
                    'review_notes' => $application->review_notes ?? '',
                    'final_score' => $application->final_score,
                ],
            ],
            'nimPreview' => $this->nimPreview($application),
            'statusOptions' => $this->statusOptions(),
            'documentStatuses' => ['verified', 'rejected', 'pending'],
            'urls' => [
                'index' => route('admin.admission.admission-applications.index'),
                'updateReview' => route('admin.admission.admission-applications.update-review', $application),
                'convert' => route('admin.admission.admission-applications.convert', $application),
                'delete' => route('admin.admission.admission-applications.destroy', $application),
            ],
        ]);
    }

    public function updateReview(Request $request, AdmissionApplication $application, AdmissionStatusService $statusService)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'review_notes' => 'nullable|string',
            'final_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $application->update([
            'final_score' => $validated['final_score'] ?? null,
            'review_notes' => $validated['review_notes'] ?: null,
            'updated_by' => $request->user()->id,
        ]);

        if ($validated['status'] !== $application->status) {
            $statusService->change(
                $application,
                $validated['status'],
                $validated['review_notes'] ?: null,
                $request->user()->id,
            );
        }

        return back()->with('success', 'Review aplikasi pendaftaran berhasil diperbarui.');
    }

    public function verifyDocument(Request $request, AdmissionApplication $application, AdmissionDocument $document)
    {
        $validated = $request->validate([
            'status' => 'required|in:verified,rejected,pending',
            'notes' => 'nullable|string',
        ]);

        abort_unless((int) $document->admission_application_id === (int) $application->id, 404);

        $document->update([
            'verification_status' => $validated['status'],
            'verification_notes' => $validated['notes'] ?? null,
            'verified_by' => $validated['status'] === 'pending' ? null : $request->user()->id,
            'verified_at' => $validated['status'] === 'pending' ? null : now(),
        ]);

        return back()->with('success', 'Status verifikasi dokumen berhasil diperbarui.');
    }

    public function convert(Request $request, AdmissionApplication $application, AdmissionConversionService $conversionService)
    {
        try {
            $studentProfile = $conversionService->convert($application, $request->user()->id);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Calon mahasiswa berhasil dikonversi menjadi mahasiswa aktif dengan NIM '.$studentProfile->nim.'.',
        );
    }

    public function bulkConvert(Request $request, AdmissionConversionService $conversionService)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:admission_applications,id',
        ]);

        $converted = 0;
        $errors = [];

        foreach (AdmissionApplication::whereIn('id', $validated['ids'])->get() as $application) {
            try {
                $conversionService->convert($application, $request->user()->id);
                $converted++;
            } catch (Throwable $exception) {
                $errors[] = $application->application_number.': '.$exception->getMessage();
            }
        }

        $response = back();

        if ($converted > 0) {
            $response->with('success', $converted.' applicant berhasil dikonversi menjadi student.');
        }

        if ($errors) {
            $response->with('error', implode(' ', $errors));
        }

        return $response;
    }

    public function destroy(AdmissionApplication $application)
    {
        $application->update(['deleted_by' => auth()->id()]);
        $application->delete();

        return redirect()->route('admin.admission.admission-applications.index')
            ->with('success', 'Aplikasi pendaftaran berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $application = AdmissionApplication::withTrashed()->findOrFail($id);
        $application->update(['deleted_by' => null]);
        $application->restore();

        return back()->with('success', 'Aplikasi pendaftaran berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $application = AdmissionApplication::withTrashed()->findOrFail($id);
        $number = $application->application_number;
        $application->forceDelete();

        return back()->with('success', 'Aplikasi '.$number.' dihapus permanen.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_applications,id',
        ]);

        $count = 0;

        foreach (AdmissionApplication::whereIn('id', $validated['ids'])->get() as $application) {
            $application->update(['deleted_by' => $request->user()->id]);
            $application->delete();
            $count++;
        }

        return back()->with('success', $count.' aplikasi dipindah ke sampah.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_applications,id',
        ]);

        $count = 0;

        foreach (AdmissionApplication::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $application) {
            $application->update(['deleted_by' => null]);
            $application->restore();
            $count++;
        }

        return back()->with('success', $count.' aplikasi berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:admission_applications,id',
        ]);

        $count = 0;

        foreach (AdmissionApplication::withTrashed()->whereIn('id', $validated['ids'])->get() as $application) {
            $application->forceDelete();
            $count++;
        }

        return back()->with('success', $count.' aplikasi dihapus permanen.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf']), 404);

        $query = AdmissionApplication::query()
            ->with(['period:id,name', 'faculty:id,name', 'studyProgram:id,name'])
            ->withCount(['documents', 'scores']);

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        $this->applyFilters($query, [
            'q' => $request->query('q'),
            'admission_period_id' => $request->query('admission_period_id'),
            'faculty_id' => $request->query('faculty_id'),
            'study_program_id' => $request->query('study_program_id'),
            'class_type' => $request->query('class_type'),
            'status' => $request->query('status'),
        ]);

        $applications = $query->orderByDesc('created_at')->get();

        $headers = [
            'No. Pendaftaran', 'Nama Pendaftar', 'Email', 'No. HP / WA', 'Gelombang',
            'Fakultas', 'Program Studi', 'Kelas', 'Status', 'Skor Akhir',
            'Dokumen', 'Nilai Ujian', 'Tanggal Daftar',
        ];

        $rows = $applications->map(fn (AdmissionApplication $application) => [
            $application->application_number,
            $application->full_name,
            $application->email,
            $application->phone,
            $application->period?->name,
            $application->faculty?->name,
            $application->studyProgram?->name,
            self::CLASS_TYPES[$application->class_type] ?? $application->class_type,
            self::STATUSES[$application->status] ?? $application->status,
            $application->final_score,
            (int) $application->documents_count,
            (int) $application->scores_count,
            $application->submitted_at?->format('Y-m-d H:i:s'),
        ])->all();

        if ($format === 'pdf') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $options = new Options;
                $options->set('defaultFont', 'DejaVu Sans');
                $options->set('isRemoteEnabled', false);

                $dompdf = new Dompdf($options);
                $dompdf->loadHtml(view('exports.admission-applications-pdf', [
                    'headers' => $headers,
                    'rows' => $rows,
                    'generatedAt' => now(),
                ])->render());
                $dompdf->setPaper('a4', 'landscape');
                $dompdf->render();

                echo $dompdf->output();
            }, 'admission-applications-'.now()->format('Ymd-His').'.pdf', ['Content-Type' => 'application/pdf']);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');

        foreach ($rows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.($i + 2));
        }

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'admission-applications-'.now()->format('Ymd-His').'.'.$format;
        $mime = $format === 'csv'
            ? 'text/csv'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $mime]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<AdmissionApplication>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (filled($filters['q'] ?? null)) {
            $q = $filters['q'];
            $query->where(fn ($sub) => $sub
                ->where('application_number', 'like', "%{$q}%")
                ->orWhere('full_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhereHas('period', fn ($period) => $period
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%"))
                ->orWhereHas('studyProgram', fn ($program) => $program
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")));
        }

        foreach (['admission_period_id', 'faculty_id', 'study_program_id', 'class_type', 'status'] as $filter) {
            if (filled($filters[$filter] ?? null)) {
                $query->where($filter, $filters[$filter]);
            }
        }
    }

    private function nimPreview(AdmissionApplication $application): ?string
    {
        if ($application->status !== 'accepted' || $application->converted_at) {
            return null;
        }

        try {
            return app(NimGenerationService::class)->preview($application);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function periodOptions(): array
    {
        return AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn (AdmissionPeriod $period) => [
                'id' => $period->id,
                'label' => $period->name.' ('.$period->code.')',
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function facultyOptions(): array
    {
        return Faculty::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Faculty $faculty) => ['id' => $faculty->id, 'label' => $faculty->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function studyProgramOptions(): array
    {
        return StudyProgram::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'label' => $program->name.' ('.$program->code.')',
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(self::STATUSES)
            ->map(fn (string $label, string $value) => ['id' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function classTypeOptions(): array
    {
        return collect(self::CLASS_TYPES)
            ->map(fn (string $label, string $value) => ['id' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
