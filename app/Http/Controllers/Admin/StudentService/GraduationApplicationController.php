<?php

namespace App\Http\Controllers\Admin\StudentService;

use App\Http\Controllers\Controller;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\GraduationDocument;
use App\Models\StudentService\GraduationDocumentRequirement;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentService\GraduationApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Monitoring & workflow pengajuan yudisium.
 */
class GraduationApplicationController extends Controller
{
    /**
     * Label checklist review yang dipakai halaman lama.
     *
     * @var array<string, string>
     */
    private const CHECKLIST_LABELS = [
        'transcript_checked' => 'Transkrip sudah dicek',
        'final_project_checked' => 'Skripsi/TA sudah dicek',
        'library_clearance' => 'Bebas pustaka',
        'lab_clearance' => 'Bebas lab',
        'document_complete' => 'Dokumen lengkap',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'graduation_batch_id' => 'nullable|integer',
            'study_program_id' => 'nullable|integer',
            'status' => 'nullable|in:submitted,in_approval,under_review,revision_requested,approved,rejected,finalized,cancelled',
            'sort' => 'nullable|in:application_number,created_at,status,graduation_period',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = GraduationApplication::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'graduationBatch']);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('application_number', 'like', "%{$q}%")
                    ->orWhere('thesis_title', 'like', "%{$q}%")
                    ->orWhereHas('studentProfile', fn ($student) => $student->where('nim', 'like', "%{$q}%"))
                    ->orWhereHas('studentProfile.user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['graduation_batch_id'] ?? null)) {
            $query->where('graduation_batch_id', $validated['graduation_batch_id']);
        }

        if (filled($validated['study_program_id'] ?? null)) {
            $query->whereHas('studentProfile', fn ($student) => $student->where('study_program_id', $validated['study_program_id']));
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $applications = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/StudentServices/GraduationApplications/Index', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Pengajuan Yudisium'),
            'can' => [
                'view' => ActivePermission::check('graduation-application.view'),
                'update' => ActivePermission::check('graduation-application.update'),
            ],
            'stats' => [
                'total' => GraduationApplication::count(),
                'waiting' => GraduationApplication::whereIn('status', ['submitted', 'in_approval', 'under_review'])->count(),
                'approved' => GraduationApplication::where('status', 'approved')->count(),
                'finalized' => GraduationApplication::where('status', 'finalized')->count(),
            ],
            'data' => [
                'rows' => collect($applications->items())->values()->map(fn (GraduationApplication $application, int $i) => [
                    'id' => $application->id,
                    'no' => ($applications->firstItem() ?? 0) + $i,
                    'number' => $application->application_number,
                    'student' => $application->studentProfile?->user?->name ?? '-',
                    'nim' => $application->studentProfile?->nim ?? '-',
                    'studyProgram' => $application->studentProfile?->studyProgram?->name ?? '-',
                    'batch' => $application->graduationBatch?->name ?? '-',
                    'graduationPeriod' => $application->graduation_period,
                    'status' => $application->status,
                    'submittedAt' => $application->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.student-services.graduation-applications.show', $application->id),
                ])->all(),
                'currentPage' => $applications->currentPage(),
                'lastPage' => $applications->lastPage(),
                'perPage' => $applications->perPage(),
                'total' => $applications->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'graduation_batch_id' => $validated['graduation_batch_id'] ?? '',
                'study_program_id' => $validated['study_program_id'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'batches' => \App\Models\StudentService\GraduationBatch::query()->orderByDesc('id')->get(['id', 'name'])->all(),
            'studyPrograms' => \App\Models\Academic\StudyProgram::query()->orderBy('name')->get(['id', 'name'])->all(),
            'statuses' => $this->statusOptions(),
            'urls' => [
                'index' => route('admin.student-services.graduation-applications.index'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $application = GraduationApplication::query()
            ->with([
                'studentProfile.user',
                'studentProfile.studyProgram.faculty',
                'histories.changedBy',
                'reviewedBy',
                'approvedBy',
                'finalizedBy',
                'academicPeriod',
                'graduationBatch',
                'approvalRequest',
                'documents.requirement',
                'documents.verifiedBy',
            ])
            ->findOrFail($id);

        $service = app(GraduationApplicationService::class);
        $checklist = $this->normalizeChecklist(
            $application->admin_checklist ?: $service->defaultChecklist(),
        );

        $checklistUsers = $this->checklistUserNames($checklist);
        $documents = $application->documents
            ->sortBy(fn (GraduationDocument $document) => $document->requirement?->sort_order ?? 999)
            ->values();

        $requiredDocumentsVerified = $this->requiredDocumentsVerified($application);
        $checklistComplete = collect($checklist)->every(fn (array $item) => $item['checked']);
        $snapshot = $application->eligibility_snapshot ?? [];
        $checks = collect($snapshot['checks'] ?? [])
            ->map(fn (array $check, string $key) => [
                'key' => $key,
                'label' => $check['label'] ?? $key,
                'required' => (bool) ($check['required'] ?? true),
                'passed' => (bool) ($check['passed'] ?? false),
                'actual' => is_scalar($check['actual'] ?? null) ? (string) $check['actual'] : '-',
                'expected' => is_scalar($check['expected'] ?? null) ? (string) $check['expected'] : '-',
                'message' => $check['message'] ?? null,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/StudentServices/GraduationApplications/Show', [
            'shell' => ShellProps::make($request->user(), 'Layanan Mahasiswa', 'Detail Pengajuan Yudisium'),
            'can' => [
                'update' => ActivePermission::check('graduation-application.update'),
            ],
            'application' => [
                'id' => $application->id,
                'number' => $application->application_number,
                'status' => $application->status,
                'student' => $application->studentProfile?->user?->name ?? '-',
                'nim' => $application->studentProfile?->nim ?? '-',
                'studyProgram' => $application->studentProfile?->studyProgram?->name ?? '-',
                'faculty' => $application->studentProfile?->studyProgram?->faculty?->name ?? '-',
                'academicPeriod' => $application->academicPeriod?->name ?? '-',
                'batch' => $application->graduationBatch?->name ?? '-',
                'batchDate' => $application->graduationBatch?->yudisium_date?->format('Y-m-d'),
                'graduationPeriod' => $application->graduation_period,
                'thesisTitle' => $application->thesis_title,
                'reason' => $application->reason,
                'studentNotes' => $application->student_notes,
                'adminNotes' => $application->admin_notes,
                'attachmentUrl' => $application->attachment_path
                    ? Storage::disk('public')->url($application->attachment_path)
                    : null,
                'attachmentName' => $application->attachment_name,
                'submittedAt' => $application->created_at?->format('d M Y H:i'),
                'updatedAt' => $application->updated_at?->format('d M Y H:i'),
                'reviewedBy' => $application->reviewedBy?->name,
                'reviewedAt' => $application->reviewed_at?->format('d M Y H:i'),
                'approvedBy' => $application->approvedBy?->name,
                'approvedAt' => $application->approved_at?->format('d M Y H:i'),
                'finalizedBy' => $application->finalizedBy?->name,
                'finalizedAt' => $application->finalized_at?->format('d M Y H:i'),
                'graduationDate' => $application->graduation_date?->format('Y-m-d')
                    ?? $application->graduationBatch?->yudisium_date?->format('Y-m-d'),
                'approval' => $application->approvalRequest ? [
                    'status' => $application->approvalRequest->status,
                    'waitingMessage' => $application->approvalRequest->waitingMessage(),
                ] : null,
                'histories' => $application->histories
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn ($history) => [
                        'id' => $history->id,
                        'from' => $history->from_status,
                        'to' => $history->to_status,
                        'notes' => $history->notes,
                        'changedBy' => $history->changedBy?->name,
                        'changedAt' => $history->created_at?->format('d M Y H:i'),
                    ])->all(),
            ],
            'checklist' => collect($checklist)->map(fn (array $item, string $key) => [
                'key' => $key,
                'label' => self::CHECKLIST_LABELS[$key] ?? $key,
                'checked' => $item['checked'],
                'notes' => $item['notes'],
                'checkedBy' => $item['checked_by'] ? ($checklistUsers[$item['checked_by']] ?? 'User #'.$item['checked_by']) : null,
                'checkedAt' => $item['checked_at'],
            ])->values()->all(),
            'checklistComplete' => $checklistComplete,
            'requiredDocumentsVerified' => $requiredDocumentsVerified,
            'requiredDocumentCount' => $this->requiredDocumentCount($application),
            'documents' => $documents->map(fn (GraduationDocument $document) => [
                'id' => $document->id,
                'requirement' => $document->requirement?->label ?? $document->document_type,
                'documentType' => $document->document_type,
                'fileName' => $document->file_name,
                'fileSize' => $document->file_size,
                'status' => $document->verification_status,
                'notes' => $document->verification_notes,
                'verifiedBy' => $document->verifiedBy?->name,
                'verifiedAt' => $document->verified_at?->format('d M Y H:i'),
                'previewUrl' => route('admin.student-services.graduation-documents.preview', ['document' => $document->id]),
            ])->all(),
            'eligibility' => [
                'policyName' => $snapshot['policy_name'] ?? null,
                'policyScope' => $snapshot['policy_scope'] ?? null,
                'checks' => $checks,
                'rows' => collect($snapshot)
                    ->reject(fn ($value, $key) => is_array($value) || in_array($key, ['policy_id'], true))
                    ->map(fn ($value, $key) => [
                        'key' => $key,
                        'label' => str($key)->replace('_', ' ')->title()->toString(),
                        'value' => is_bool($value) ? ($value ? 'Ya' : 'Tidak') : (is_null($value) ? '-' : (string) $value),
                    ])
                    ->values()
                    ->all(),
            ],
            'nextAction' => $this->nextAction($application, $checklistComplete, $requiredDocumentsVerified),
            'urls' => [
                'index' => route('admin.student-services.graduation-applications.index'),
                'markUnderReview' => route('admin.student-services.graduation-applications.mark-under-review', $application->id),
                'saveChecklist' => route('admin.student-services.graduation-applications.save-checklist', $application->id),
                'verifyDocument' => route('admin.student-services.graduation-applications.verify-document', $application->id),
                'approve' => route('admin.student-services.graduation-applications.approve', $application->id),
                'requestRevision' => route('admin.student-services.graduation-applications.request-revision', $application->id),
                'reject' => route('admin.student-services.graduation-applications.reject', $application->id),
                'finalize' => route('admin.student-services.graduation-applications.finalize', $application->id),
            ],
        ]);
    }

    public function markUnderReview(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        $application = GraduationApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(GraduationApplicationService::class)
                ->setStatus($application, 'under_review', $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan yudisium ditandai sedang direview.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function saveChecklist(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        $application = GraduationApplication::findOrFail($id);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
            'checklist' => ['required', 'array'],
            'checklist.*.checked' => ['boolean'],
            'checklist.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(GraduationApplicationService::class)->saveChecklist(
                $application,
                $validated['checklist'],
                $validated['notes'] ?? null,
                $request->user()->id,
            );

            return back()->with('success', 'Checklist review berhasil disimpan.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function verifyDocument(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        $application = GraduationApplication::findOrFail($id);
        $validated = $request->validate([
            'document_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document = $application->documents()->whereKey($validated['document_id'])->firstOrFail();

        try {
            app(GraduationApplicationService::class)->verifyDocument(
                $document,
                $validated['status'],
                $validated['notes'] ?? null,
                $request->user()->id,
            );

            return back()->with('success', 'Status dokumen yudisium berhasil diperbarui.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approve(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        $application = GraduationApplication::findOrFail($id);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.checked' => ['boolean'],
            'checklist.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $checklist = $validated['checklist']
            ?? $application->admin_checklist
            ?? app(GraduationApplicationService::class)->defaultChecklist();

        try {
            app(GraduationApplicationService::class)->approve(
                $application,
                $checklist,
                $validated['notes'] ?? null,
                $request->user()->id,
            );

            return back()->with('success', 'Pengajuan yudisium berhasil disetujui.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function requestRevision(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        $application = GraduationApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(GraduationApplicationService::class)
                ->setStatus($application, 'revision_requested', $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Permintaan perbaikan dikirim ke mahasiswa.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        $application = GraduationApplication::findOrFail($id);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:3000']]);

        try {
            app(GraduationApplicationService::class)
                ->reject($application, $validated['notes'] ?? null, $request->user()->id);

            return back()->with('success', 'Pengajuan yudisium ditolak.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function finalize(Request $request, int $id)
    {
        abort_unless(ActivePermission::check('graduation-application.update'), 403);

        $application = GraduationApplication::findOrFail($id);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
            'graduation_date' => ['nullable', 'date'],
        ]);

        try {
            app(GraduationApplicationService::class)->finalize(
                $application,
                $validated['graduation_date'] ?? null,
                $validated['notes'] ?? null,
                $request->user()->id,
            );

            return back()->with('success', 'Yudisium berhasil difinalisasi. Status mahasiswa menjadi Lulus.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $checklist
     * @return array<string, array{checked: bool, notes: string|null, checked_by: int|null, checked_at: string|null}>
     */
    private function normalizeChecklist(array $checklist): array
    {
        return collect(self::CHECKLIST_LABELS)
            ->mapWithKeys(function (string $label, string $key) use ($checklist) {
                $item = $checklist[$key] ?? [];

                return [$key => [
                    'checked' => is_array($item) ? (bool) ($item['checked'] ?? false) : (bool) $item,
                    'notes' => is_array($item) ? (($item['notes'] ?? null) ?: null) : null,
                    'checked_by' => is_array($item) ? ($item['checked_by'] ?? null) : null,
                    'checked_at' => is_array($item) && ! empty($item['checked_at'])
                        ? \Illuminate\Support\Carbon::parse($item['checked_at'])->format('d M Y H:i')
                        : null,
                ]];
            })
            ->all();
    }

    /**
     * @param  array<string, array{checked: bool, notes: string|null, checked_by: int|null, checked_at: string|null}>  $checklist
     * @return array<int, string>
     */
    private function checklistUserNames(array $checklist): array
    {
        $ids = collect($checklist)->pluck('checked_by')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (User $user) => [$user->id => trim($user->first_name.' '.$user->last_name)])
            ->all();
    }

    private function requiredDocumentsVerified(GraduationApplication $application): bool
    {
        $application->loadMissing(['studentProfile', 'documents']);
        $documentsByRequirement = $application->documents->keyBy('graduation_document_requirement_id');

        return app(GraduationApplicationService::class)
            ->documentRequirements($application->studentProfile)
            ->filter(fn (GraduationDocumentRequirement $requirement) => $requirement->is_required)
            ->every(function (GraduationDocumentRequirement $requirement) use ($documentsByRequirement) {
                $document = $documentsByRequirement->get($requirement->id);

                return $document && $document->verification_status === 'verified';
            });
    }

    private function requiredDocumentCount(GraduationApplication $application): int
    {
        $application->loadMissing('studentProfile');

        return app(GraduationApplicationService::class)
            ->documentRequirements($application->studentProfile)
            ->filter(fn (GraduationDocumentRequirement $requirement) => $requirement->is_required)
            ->count();
    }

    /**
     * @return array{tone: string, title: string, body: string}
     */
    private function nextAction(GraduationApplication $application, bool $checklistComplete, bool $documentsVerified): array
    {
        return match ($application->status) {
            'submitted' => [
                'tone' => 'info',
                'title' => 'Mulai review pengajuan',
                'body' => 'Tandai sedang direview atau mulai centang checklist. Mencentang checklist otomatis menyimpan progres dan mengubah status menjadi Sedang Ditinjau.',
            ],
            'under_review' => ! $checklistComplete ? [
                'tone' => 'warning',
                'title' => 'Lengkapi checklist review',
                'body' => 'Tombol setujui aktif setelah semua checklist yudisium selesai. Isi catatan per item bila ada temuan untuk audit internal.',
            ] : (! $documentsVerified ? [
                'tone' => 'warning',
                'title' => 'Verifikasi dokumen wajib',
                'body' => 'Checklist sudah lengkap, tetapi dokumen wajib yudisium masih ada yang belum terverifikasi atau ditolak.',
            ] : [
                'tone' => 'success',
                'title' => 'Pengajuan siap disetujui',
                'body' => 'Semua checklist dan dokumen wajib sudah lengkap. Klik Setujui jika hasil review akademik sudah sesuai.',
            ]),
            'revision_requested' => [
                'tone' => 'warning',
                'title' => 'Menunggu perbaikan mahasiswa',
                'body' => 'Mahasiswa perlu memperbaiki pengajuan sesuai catatan admin. Setelah dikirim ulang, review bisa dilanjutkan.',
            ],
            'approved' => ! $application->graduationBatch?->yudisium_date ? [
                'tone' => 'danger',
                'title' => 'Tanggal yudisium batch belum diisi',
                'body' => 'Finalisasi belum bisa dilakukan sampai batch yudisium memiliki tanggal resmi.',
            ] : [
                'tone' => 'success',
                'title' => 'Siap finalisasi kelulusan',
                'body' => 'Finalisasi akan mengubah status mahasiswa menjadi Lulus dan menonaktifkan profil mahasiswa.',
            ],
            'finalized' => [
                'tone' => 'success',
                'title' => 'Yudisium sudah final',
                'body' => 'Pengajuan sudah difinalisasi dan tidak bisa direvisi lewat workflow normal.',
            ],
            'rejected' => [
                'tone' => 'danger',
                'title' => 'Pengajuan ditolak',
                'body' => 'Pengajuan sudah ditolak. Mahasiswa perlu membuat pengajuan baru jika kampus mengizinkan.',
            ],
            default => [
                'tone' => 'info',
                'title' => 'Cek status pengajuan',
                'body' => 'Lihat detail status, checklist, dan riwayat untuk menentukan aksi berikutnya.',
            ],
        };
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['id' => 'submitted', 'name' => 'Diajukan'],
            ['id' => 'in_approval', 'name' => 'Menunggu Persetujuan'],
            ['id' => 'under_review', 'name' => 'Sedang Ditinjau'],
            ['id' => 'revision_requested', 'name' => 'Perlu Perbaikan'],
            ['id' => 'approved', 'name' => 'Disetujui'],
            ['id' => 'rejected', 'name' => 'Ditolak'],
            ['id' => 'finalized', 'name' => 'Selesai'],
            ['id' => 'cancelled', 'name' => 'Dibatalkan'],
        ];
    }
}
