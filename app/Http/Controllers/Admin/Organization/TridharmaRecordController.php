<?php

namespace App\Http\Controllers\Admin\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\TridharmaAttachment;
use App\Models\Organization\TridharmaBudget;
use App\Models\Organization\TridharmaMember;
use App\Models\Organization\TridharmaMilestone;
use App\Models\Organization\TridharmaOutput;
use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\TridharmaRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * CRUD portofolio Tridharma (penelitian, pengabdian, publikasi) — paritas
 * halaman Livewire lama: daftar, formulir, detail lengkap dengan milestone,
 * anggaran, luaran, tim, lampiran, verifikasi, dan alur persetujuan.
 */
class TridharmaRecordController extends Controller
{
    private const TYPES = [
        'research' => 'Penelitian',
        'community_service' => 'Pengabdian Kepada Masyarakat',
        'publication' => 'Publikasi Ilmiah',
        'intellectual_property' => 'HKI / Paten',
    ];

    private const FORM_TYPES = [
        'research' => 'Penelitian (Research & Studi)',
        'community_service' => 'Pengabdian Kepada Masyarakat (Abdimas / KKN)',
        'publication' => 'Publikasi Ilmiah / Luaran / HKI',
    ];

    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Diajukan',
        'in_approval' => 'Menunggu Approval',
        'approved' => 'Disetujui',
        'active' => 'Aktif',
        'completed' => 'Selesai',
        'rejected' => 'Ditolak',
        'archived' => 'Diarsipkan',
    ];

    private const CREATE_STATUSES = [
        'draft' => 'Draft (Persiapan)',
        'approved' => 'Disetujui (Approved)',
        'active' => 'Sedang Berjalan (Active)',
        'completed' => 'Selesai (Completed)',
        'archived' => 'Diarsipkan (Archived)',
    ];

    private const EDIT_STATUSES = [
        'draft' => 'Draft (Persiapan)',
        'in_approval' => 'Menunggu Approval',
        'approved' => 'Disetujui (Approved)',
        'active' => 'Sedang Berjalan (Active)',
        'completed' => 'Selesai (Completed)',
        'rejected' => 'Ditolak / Revisi',
        'archived' => 'Diarsipkan (Archived)',
    ];

    private const MEMBER_ROLES = [
        'leader' => 'Ketua Peneliti/Pengabdi',
        'member' => 'Anggota Tim',
        'partner' => 'Mitra Kerjasama',
        'student_collaborator' => 'Kolaborator Mahasiswa',
    ];

    private const MILESTONE_STATUSES = [
        'pending' => 'Belum Mulai',
        'in_progress' => 'Berjalan',
        'completed' => 'Selesai',
        'blocked' => 'Terkendala',
    ];

    private const OUTPUT_TYPES = [
        'article' => 'Artikel',
        'report' => 'Laporan',
        'hki' => 'HKI / Paten',
        'dataset' => 'Dataset',
        'module' => 'Modul Ajar',
    ];

    private const OUTPUT_STATUSES = [
        'draft' => 'Rencana',
        'submitted' => 'Diajukan',
        'accepted' => 'Diterima',
        'published' => 'Final/Terbit',
    ];

    private const DOCUMENT_TYPES = [
        'proposal' => 'Dokumen Proposal',
        'report' => 'Laporan Akhir',
        'evidence' => 'Bukti Kegiatan (Evidence)',
        'output' => 'Bukti Luaran (Publikasi/HKI)',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'is_verified' => 'nullable|in:0,1',
            'sort' => 'nullable|in:title,type,funding_amount,outputs_count,attachments_count,status,is_verified,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = TridharmaRecord::query()
            ->with('owner')
            ->withCount(['milestones', 'outputs', 'attachments'])
            ->withCount(['milestones as completed_milestones_count' => fn ($milestones) => $milestones->where('status', 'completed')]);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($sub) use ($term) {
                $sub->where('title', 'like', $term)
                    ->orWhere('scheme', 'like', $term)
                    ->orWhere('funding_source', 'like', $term)
                    ->orWhereHas('owner', function ($owner) use ($term) {
                        $owner->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('username', 'like', $term)
                            ->orWhere('code', 'like', $term)
                            ->orWhere('identity_number', 'like', $term);
                    });
            });
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['is_verified']) && $validated['is_verified'] !== '') {
            $query->where('is_verified', (bool) $validated['is_verified']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        if ($sort !== 'created_at') {
            $query->orderByDesc('created_at');
        }

        $perPage = (int) ($validated['perPage'] ?? 10);
        $records = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Organization/TridharmaRecords/Index', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tridharma'),
            'can' => [
                'create' => ActivePermission::check('tridharma-record.create'),
                'update' => ActivePermission::check('tridharma-record.update'),
                'delete' => ActivePermission::check('tridharma-record.delete'),
                'view' => ActivePermission::check('tridharma-record.view'),
            ],
            'stats' => [
                'total' => TridharmaRecord::count(),
                'inApproval' => TridharmaRecord::whereIn('status', ['in_approval', 'submitted'])->count(),
                'verified' => TridharmaRecord::where('is_verified', true)->count(),
                'completed' => TridharmaRecord::where('status', 'completed')->count(),
            ],
            'data' => [
                'rows' => collect($records->items())->values()->map(fn (TridharmaRecord $record, int $i) => [
                    'id' => $record->id,
                    'no' => ($records->firstItem() ?? 0) + $i,
                    'ownerName' => $record->owner?->name ?? '-',
                    'ownerEmail' => $record->owner?->email,
                    'type' => $record->type,
                    'typeLabel' => self::TYPES[$record->type] ?? ucwords(str_replace('_', ' ', (string) $record->type)),
                    'title' => $record->title,
                    'scheme' => $record->scheme,
                    'fundingAmount' => (float) $record->funding_amount,
                    'fundingLabel' => 'Rp '.number_format((float) $record->funding_amount, 0, ',', '.'),
                    'milestonesCount' => (int) $record->milestones_count,
                    'completedMilestones' => (int) $record->completed_milestones_count,
                    'outputsCount' => (int) $record->outputs_count,
                    'attachmentsCount' => (int) $record->attachments_count,
                    'status' => $record->status,
                    'statusLabel' => self::STATUSES[$record->status] ?? ucwords(str_replace('_', ' ', (string) $record->status)),
                    'isVerified' => (bool) $record->is_verified,
                    'createdAt' => $record->created_at?->format('d M Y H:i'),
                    'showUrl' => route('admin.organization.tridharma-records.show', $record->id),
                    'editUrl' => route('admin.organization.tridharma-records.edit', $record->id),
                    'deleteUrl' => route('admin.organization.tridharma-records.destroy', $record->id),
                ])->all(),
                'currentPage' => $records->currentPage(),
                'lastPage' => $records->lastPage(),
                'perPage' => $records->perPage(),
                'total' => $records->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'type' => $validated['type'] ?? '',
                'status' => $validated['status'] ?? '',
                'is_verified' => $validated['is_verified'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'types' => $this->optionList(self::TYPES),
            'statuses' => $this->optionList(self::STATUSES),
            'urls' => [
                'index' => route('admin.organization.tridharma-records.index'),
                'create' => route('admin.organization.tridharma-records.create'),
                'bulkDestroy' => route('admin.organization.tridharma-records.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Organization/TridharmaRecords/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Tambah Tridharma'),
            'mode' => 'create',
            'record' => [
                'type' => 'research',
                'title' => '',
                'scheme' => '',
                'abstract' => '',
                'starts_at' => '',
                'ends_at' => '',
                'status' => 'draft',
                'funding_amount' => '',
                'funding_source' => '',
                'admin_notes' => '',
            ],
            'owner' => null,
            'attachments' => [],
            'types' => $this->optionList(self::FORM_TYPES),
            'statuses' => $this->optionList(self::CREATE_STATUSES),
            'urls' => [
                'index' => route('admin.organization.tridharma-records.index'),
                'submit' => route('admin.organization.tridharma-records.store'),
                'searchUsers' => route('admin.organization.tridharma-records.search-users'),
            ],
        ]);
    }

    public function store(Request $request, TridharmaRecordService $service)
    {
        $validated = $this->validatePayload($request, true);

        $record = DB::transaction(function () use ($request, $validated, $service) {
            $owner = User::with(['lecturerProfile', 'employeeProfile'])->findOrFail($validated['user_id']);
            $status = $validated['status'];
            $approved = in_array($status, ['approved', 'active', 'completed'], true);

            $record = TridharmaRecord::create([
                'user_id' => $owner->id,
                'lecturer_profile_id' => $owner->lecturerProfile?->id,
                'employee_profile_id' => $owner->employeeProfile?->id,
                'type' => $validated['type'],
                'title' => $validated['title'],
                'scheme' => $validated['scheme'],
                'abstract' => $validated['abstract'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'status' => $status,
                'funding_amount' => $validated['funding_amount'],
                'funding_source' => $validated['funding_source'],
                'admin_notes' => $validated['admin_notes'],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'approved_by' => $approved ? $request->user()->id : null,
                'approved_at' => $approved ? now() : null,
                'completed_by' => $status === 'completed' ? $request->user()->id : null,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);

            if ($request->hasFile('attachment')) {
                $service->storeAttachment($record, $request->file('attachment'), 'proposal', $request->user());
            }

            return $record;
        });

        return redirect()->route('admin.organization.tridharma-records.index')
            ->with('success', 'Record Tridharma berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $record = TridharmaRecord::with(['owner', 'attachments'])->findOrFail($id);

        return Inertia::render('Admin/Organization/TridharmaRecords/Form', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Edit Tridharma'),
            'mode' => 'edit',
            'record' => [
                'id' => $record->id,
                'type' => $record->type,
                'title' => $record->title,
                'scheme' => $record->scheme ?? '',
                'abstract' => $record->abstract ?? '',
                'starts_at' => $record->starts_at?->format('Y-m-d') ?? '',
                'ends_at' => $record->ends_at?->format('Y-m-d') ?? '',
                'status' => $record->status,
                'funding_amount' => $record->funding_amount === null ? '' : (float) $record->funding_amount,
                'funding_source' => $record->funding_source ?? '',
                'admin_notes' => $record->admin_notes ?? '',
            ],
            'owner' => $record->owner ? [
                'id' => $record->owner->id,
                'name' => $record->owner->name,
                'email' => $record->owner->email,
                'username' => $record->owner->username,
                'code' => $record->owner->code,
            ] : null,
            'attachments' => $record->attachments->map(fn (TridharmaAttachment $attachment) => [
                'id' => $attachment->id,
                'fileName' => $attachment->file_name,
                'documentType' => $attachment->document_type,
                'fileSize' => (int) $attachment->file_size,
                'previewUrl' => route('admin.organization.tridharma-records.attachments.preview', $attachment->id),
            ])->values()->all(),
            'types' => $this->optionList(self::FORM_TYPES),
            'statuses' => $this->optionList(self::EDIT_STATUSES),
            'urls' => [
                'index' => route('admin.organization.tridharma-records.index'),
                'submit' => route('admin.organization.tridharma-records.update', $record->id),
                'show' => route('admin.organization.tridharma-records.show', $record->id),
            ],
        ]);
    }

    public function update(Request $request, int $id, TridharmaRecordService $service)
    {
        $record = TridharmaRecord::findOrFail($id);
        $validated = $this->validatePayload($request, false);

        DB::transaction(function () use ($request, $record, $validated, $service) {
            $status = $validated['status'];
            $payload = [
                'type' => $validated['type'],
                'title' => $validated['title'],
                'scheme' => $validated['scheme'],
                'abstract' => $validated['abstract'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'status' => $status,
                'funding_amount' => $validated['funding_amount'],
                'funding_source' => $validated['funding_source'],
                'admin_notes' => $validated['admin_notes'],
                'updated_by' => $request->user()->id,
            ];

            if (in_array($status, ['approved', 'active', 'completed'], true) && ! $record->approved_at) {
                $payload['approved_by'] = $request->user()->id;
                $payload['approved_at'] = now();
            }

            if ($status === 'completed' && ! $record->completed_at) {
                $payload['completed_by'] = $request->user()->id;
                $payload['completed_at'] = now();
            }

            $record->update($payload);

            if ($request->hasFile('attachment')) {
                $service->storeAttachment($record, $request->file('attachment'), 'evidence', $request->user());
            }
        });

        return redirect()->route('admin.organization.tridharma-records.show', $record->id)
            ->with('success', 'Record Tridharma berhasil diperbarui.');
    }

    public function show(Request $request, int $id): Response
    {
        $record = TridharmaRecord::query()
            ->with([
                'owner',
                'members.user',
                'milestones',
                'budgets',
                'outputs',
                'attachments',
                'approvalRequest.steps.actedBy',
            ])
            ->findOrFail($id);

        $canUpdate = ActivePermission::check('tridharma-record.update');
        $canVerify = ActivePermission::check('tridharma-record.verify');
        $canComplete = ActivePermission::check('tridharma-record.complete');
        $memberUserIds = $record->members->pluck('user_id')->filter()->values()->all();

        return Inertia::render('Admin/Organization/TridharmaRecords/Show', [
            'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Detail Tridharma'),
            'can' => [
                'update' => $canUpdate,
                'delete' => ActivePermission::check('tridharma-record.delete'),
                'verify' => $canVerify,
                'complete' => $canComplete,
                'approve' => ActivePermission::check('tridharma-record.approve'),
            ],
            'record' => [
                'id' => $record->id,
                'ownerName' => $record->owner?->name ?? '-',
                'ownerEmail' => $record->owner?->email,
                'type' => $record->type,
                'typeLabel' => self::TYPES[$record->type] ?? ucwords(str_replace('_', ' ', (string) $record->type)),
                'title' => $record->title,
                'scheme' => $record->scheme,
                'abstract' => $record->abstract,
                'startsAt' => $record->starts_at?->format('d M Y'),
                'endsAt' => $record->ends_at?->format('d M Y'),
                'fundingAmount' => (float) $record->funding_amount,
                'fundingLabel' => 'Rp '.number_format((float) $record->funding_amount, 0, ',', '.'),
                'fundingSource' => $record->funding_source ?: 'Mandiri/Internal',
                'status' => $record->status,
                'statusLabel' => self::STATUSES[$record->status] ?? ucwords(str_replace('_', ' ', (string) $record->status)),
                'isVerified' => (bool) $record->is_verified,
                'verifierName' => $record->verifier?->name,
                'verifiedAt' => $record->verified_at?->format('d M Y H:i'),
                'verificationNotes' => $record->verification_notes,
                'adminNotes' => $record->admin_notes,
                'createdAt' => $record->created_at?->format('d M Y H:i'),
                'canSubmit' => $canUpdate && in_array($record->status, ['draft', 'rejected'], true),
                'canVerify' => $canVerify && ! $record->is_verified,
                'canComplete' => $canComplete && ! in_array($record->status, ['completed', 'archived'], true),
                'canArchive' => $canUpdate && $record->status !== 'archived',
            ],
            'members' => $record->members->map(fn (TridharmaMember $member) => [
                'id' => $member->id,
                'name' => $member->user?->name ?? $member->member_name ?? '-',
                'detail' => $member->institution ?: ($member->user?->email ?? $member->email ?? '-'),
                'role' => $member->role,
                'roleLabel' => self::MEMBER_ROLES[$member->role] ?? ucwords(str_replace('_', ' ', (string) $member->role)),
                'isLeader' => $member->role === 'leader',
                'isExternal' => (bool) $member->is_external,
            ])->values()->all(),
            'milestones' => $record->milestones->map(fn (TridharmaMilestone $milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'dueDate' => $milestone->due_date?->format('d M Y'),
                'progress' => (int) $milestone->progress_percentage,
                'status' => $milestone->status,
                'statusLabel' => self::MILESTONE_STATUSES[$milestone->status] ?? ucwords(str_replace('_', ' ', (string) $milestone->status)),
                'notes' => $milestone->completion_notes,
            ])->values()->all(),
            'budgets' => $record->budgets->map(fn (TridharmaBudget $budget) => [
                'id' => $budget->id,
                'category' => $budget->category,
                'description' => $budget->description,
                'plannedAmount' => (float) $budget->planned_amount,
                'realizedAmount' => (float) $budget->realized_amount,
                'plannedLabel' => 'Rp '.number_format((float) $budget->planned_amount, 0, ',', '.'),
                'realizedLabel' => 'Rp '.number_format((float) $budget->realized_amount, 0, ',', '.'),
                'notes' => $budget->notes,
            ])->values()->all(),
            'outputs' => $record->outputs->map(fn (TridharmaOutput $output) => [
                'id' => $output->id,
                'outputType' => $output->output_type,
                'typeLabel' => self::OUTPUT_TYPES[$output->output_type] ?? ucwords(str_replace('_', ' ', (string) $output->output_type)),
                'title' => $output->title,
                'publisher' => $output->publisher,
                'indexing' => $output->indexing,
                'doi' => $output->doi,
                'url' => $output->url,
                'publishedAt' => $output->published_at?->format('d M Y'),
                'status' => $output->status,
                'statusLabel' => self::OUTPUT_STATUSES[$output->status] ?? ucwords(str_replace('_', ' ', (string) $output->status)),
                'notes' => $output->notes,
            ])->values()->all(),
            'attachments' => $record->attachments->map(fn (TridharmaAttachment $attachment) => [
                'id' => $attachment->id,
                'fileName' => $attachment->file_name,
                'documentType' => $attachment->document_type,
                'documentTypeLabel' => self::DOCUMENT_TYPES[$attachment->document_type] ?? ucwords(str_replace('_', ' ', (string) $attachment->document_type)),
                'fileSize' => (int) $attachment->file_size,
                'uploadedBy' => $attachment->uploadedBy?->name,
                'previewUrl' => route('admin.organization.tridharma-records.attachments.preview', $attachment->id),
            ])->values()->all(),
            'approvalSteps' => $record->approvalRequest?->steps->map(fn ($step) => [
                'id' => $step->id,
                'name' => $step->name ?? 'Tahap approval',
                'status' => $step->status,
                'statusLabel' => self::STATUSES[$step->status] ?? ucwords(str_replace('_', ' ', (string) $step->status)),
                'actorName' => $step->actedBy?->name,
                'actedAt' => $step->acted_at?->format('d M Y H:i'),
                'notes' => $step->notes,
            ])->values()->all() ?? [],
            'options' => [
                'memberRoles' => $this->optionList(self::MEMBER_ROLES),
                'milestoneStatuses' => $this->optionList(self::MILESTONE_STATUSES),
                'outputTypes' => $this->optionList(self::OUTPUT_TYPES),
                'outputStatuses' => $this->optionList(self::OUTPUT_STATUSES),
                'documentTypes' => $this->optionList(self::DOCUMENT_TYPES),
            ],
            'memberUserIds' => $memberUserIds,
            'urls' => [
                'index' => route('admin.organization.tridharma-records.index'),
                'edit' => route('admin.organization.tridharma-records.edit', $record->id),
                'submitApproval' => route('admin.organization.tridharma-records.submit-approval', $record->id),
                'verify' => route('admin.organization.tridharma-records.verify', $record->id),
                'complete' => route('admin.organization.tridharma-records.complete', $record->id),
                'archive' => route('admin.organization.tridharma-records.archive', $record->id),
                'addMember' => route('admin.organization.tridharma-records.members.store', $record->id),
                'addMilestone' => route('admin.organization.tridharma-records.milestones.store', $record->id),
                'addBudget' => route('admin.organization.tridharma-records.budgets.store', $record->id),
                'addOutput' => route('admin.organization.tridharma-records.outputs.store', $record->id),
                'uploadAttachment' => route('admin.organization.tridharma-records.attachments.store', $record->id),
                'searchUsers' => route('admin.organization.tridharma-records.search-users'),
            ],
        ]);
    }

    public function submitApproval(Request $request, int $id, TridharmaRecordService $service)
    {
        $record = TridharmaRecord::findOrFail($id);

        if (! in_array($record->status, ['draft', 'rejected'], true)) {
            return back()->with('error', 'Proposal Tridharma hanya dapat diajukan dari status draft atau ditolak.');
        }

        try {
            $service->ensureDefaultApprovalTemplate($request->user());
            $service->submitForApproval($record, $request->user());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Proposal Tridharma diajukan ke approval.');
    }

    public function verify(Request $request, int $id)
    {
        $validated = $request->validate([
            'verification_notes' => 'nullable|string|max:2000',
        ]);

        $record = TridharmaRecord::findOrFail($id);

        $record->update([
            'is_verified' => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_notes' => ($validated['verification_notes'] ?? null) ?: null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Record Tridharma berhasil diverifikasi.');
    }

    public function complete(Request $request, int $id)
    {
        $record = TridharmaRecord::findOrFail($id);

        $record->update([
            'status' => 'completed',
            'completed_by' => $request->user()->id,
            'completed_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Record Tridharma ditandai selesai.');
    }

    public function archive(Request $request, int $id)
    {
        $record = TridharmaRecord::findOrFail($id);

        $record->update([
            'status' => 'archived',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Record Tridharma diarsipkan.');
    }

    public function storeMember(Request $request, int $id)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'member_name' => 'nullable|string|max:255',
            'institution' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'role' => ['required', Rule::in(array_keys(self::MEMBER_ROLES))],
        ]);

        if (blank($validated['user_id'] ?? null) && blank($validated['member_name'] ?? null)) {
            return back()->withErrors(['member_name' => 'Pilih user internal atau isi nama anggota eksternal.']);
        }

        $record = TridharmaRecord::findOrFail($id);
        $isExternal = blank($validated['user_id'] ?? null);

        $record->members()->create([
            'user_id' => $isExternal ? null : (int) $validated['user_id'],
            'member_name' => $isExternal ? $validated['member_name'] : null,
            'institution' => ($validated['institution'] ?? null) ?: null,
            'email' => $isExternal ? (($validated['email'] ?? null) ?: null) : null,
            'role' => $validated['role'],
            'is_external' => $isExternal,
            'sort_order' => $record->members()->count() + 1,
        ]);

        return back()->with('success', 'Anggota tim berhasil ditambahkan.');
    }

    public function storeMilestone(Request $request, int $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'nullable|date',
            'status' => ['required', Rule::in(array_keys(self::MILESTONE_STATUSES))],
            'progress_percentage' => 'required|integer|min:0|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $record = TridharmaRecord::findOrFail($id);

        $record->milestones()->create([
            'title' => $validated['title'],
            'due_date' => ($validated['due_date'] ?? null) ?: null,
            'status' => $validated['status'],
            'progress_percentage' => (int) $validated['progress_percentage'],
            'description' => ($validated['description'] ?? null) ?: null,
            'sort_order' => $record->milestones()->count() + 1,
        ]);

        return back()->with('success', 'Milestone berhasil ditambahkan.');
    }

    public function storeBudget(Request $request, int $id)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'planned_amount' => 'required|numeric|min:0',
            'realized_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $record = TridharmaRecord::findOrFail($id);

        $record->budgets()->create([
            'category' => $validated['category'],
            'description' => ($validated['description'] ?? null) ?: null,
            'planned_amount' => (float) $validated['planned_amount'],
            'realized_amount' => (float) $validated['realized_amount'],
            'notes' => ($validated['notes'] ?? null) ?: null,
        ]);

        return back()->with('success', 'Rincian anggaran berhasil ditambahkan.');
    }

    public function storeOutput(Request $request, int $id)
    {
        $validated = $request->validate([
            'output_type' => ['required', Rule::in(array_keys(self::OUTPUT_TYPES))],
            'title' => 'required|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'indexing' => 'nullable|string|max:255',
            'doi' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:255',
            'published_at' => 'nullable|date',
            'status' => ['required', Rule::in(array_keys(self::OUTPUT_STATUSES))],
            'notes' => 'nullable|string|max:2000',
        ]);

        $record = TridharmaRecord::findOrFail($id);

        $record->outputs()->create([
            'output_type' => $validated['output_type'],
            'title' => $validated['title'],
            'publisher' => ($validated['publisher'] ?? null) ?: null,
            'indexing' => ($validated['indexing'] ?? null) ?: null,
            'doi' => ($validated['doi'] ?? null) ?: null,
            'url' => ($validated['url'] ?? null) ?: null,
            'published_at' => ($validated['published_at'] ?? null) ?: null,
            'status' => $validated['status'],
            'notes' => ($validated['notes'] ?? null) ?: null,
        ]);

        return back()->with('success', 'Luaran ilmiah berhasil ditambahkan.');
    }

    public function storeAttachment(Request $request, int $id, TridharmaRecordService $service)
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'attachment' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx|max:8192',
        ]);

        $record = TridharmaRecord::findOrFail($id);
        $service->storeAttachment($record, $request->file('attachment'), $validated['document_type'], $request->user());

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    public function destroy(Request $request, int $id)
    {
        $record = TridharmaRecord::findOrFail($id);
        $record->delete();

        return redirect()->route('admin.organization.tridharma-records.index')
            ->with('success', 'Record Tridharma berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:tridharma_records,id',
        ]);

        $records = TridharmaRecord::whereIn('id', $validated['ids'])->get();

        foreach ($records as $record) {
            $record->delete();
        }

        return back()->with('success', $records->count().' record Tridharma berhasil dihapus.');
    }

    /**
     * Pencarian pengguna untuk picker owner maupun anggota tim internal.
     */
    public function searchUsers(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'context' => 'nullable|in:owner,member',
            'exclude' => 'nullable|string|max:1000',
        ]);

        $context = $validated['context'] ?? 'owner';
        $query = trim((string) ($validated['q'] ?? ''));
        $exclude = collect(explode(',', (string) ($validated['exclude'] ?? '')))
            ->map(fn (string $value) => (int) trim($value))
            ->filter()
            ->values()
            ->all();

        if ($context === 'member' && mb_strlen($query) < 2) {
            return response()->json(['options' => []]);
        }

        $search = '%'.$query.'%';

        $options = User::query()
            ->where('is_active', true)
            ->when($context === 'owner', fn ($builder) => $builder->where(function ($sub) {
                $sub->whereHas('lecturerProfile')
                    ->orWhereHas('employeeProfile', fn ($employee) => $employee->where('is_active', true));
            }))
            ->when($exclude !== [], fn ($builder) => $builder->whereNotIn('id', $exclude))
            ->when(filled($query), function ($builder) use ($search) {
                $builder->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhere('identity_number', 'like', $search);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name.' ('.($user->email ?? $user->username ?? '-').')',
                'meta' => [
                    'email' => $user->email,
                    'username' => $user->username,
                    'code' => $user->code,
                ],
            ])
            ->values()
            ->all();

        return response()->json(['options' => $options]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $withOwner): array
    {
        $rules = [
            'type' => ['required', Rule::in(array_keys(self::FORM_TYPES))],
            'title' => 'required|string|max:255',
            'scheme' => 'nullable|string|max:255',
            'abstract' => 'nullable|string|max:10000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'status' => ['required', Rule::in(array_keys($withOwner ? self::CREATE_STATUSES : self::EDIT_STATUSES))],
            'funding_amount' => 'nullable|numeric|min:0',
            'funding_source' => 'nullable|string|max:255',
            'admin_notes' => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx|max:8192',
        ];

        if ($withOwner) {
            $rules['user_id'] = 'required|integer|exists:users,id';
        }

        $validated = $request->validate($rules);

        $validated['scheme'] = ($validated['scheme'] ?? null) ?: null;
        $validated['abstract'] = ($validated['abstract'] ?? null) ?: null;
        $validated['starts_at'] = ($validated['starts_at'] ?? null) ?: null;
        $validated['ends_at'] = ($validated['ends_at'] ?? null) ?: null;
        $validated['funding_amount'] = filled($validated['funding_amount'] ?? null) ? (float) $validated['funding_amount'] : 0;
        $validated['funding_source'] = ($validated['funding_source'] ?? null) ?: null;
        $validated['admin_notes'] = ($validated['admin_notes'] ?? null) ?: null;

        if ($withOwner) {
            $validated['user_id'] = (int) $validated['user_id'];
        }

        return $validated;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{id: string, label: string}>
     */
    private function optionList(array $options): array
    {
        return Collection::make($options)
            ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
