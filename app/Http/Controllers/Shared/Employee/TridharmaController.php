<?php

namespace App\Http\Controllers\Shared\Employee;

use App\Http\Controllers\Controller;
use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\TridharmaRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TridharmaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSelfService($request);

        $records = TridharmaRecord::query()
            ->withCount(['milestones', 'outputs', 'attachments'])
            ->with(['milestones:id,tridharma_record_id,progress_percentage'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function ($record) use ($request) {
                return [
                'id' => $record->id,
                'type' => $record->type,
                'typeLabel' => $this->typeLabel($record->type),
                'title' => $record->title,
                'scheme' => $record->scheme,
                'fundingSource' => $record->funding_source,
                'fundingAmount' => (float) $record->funding_amount,
                'fundingLabel' => 'Rp '.number_format((float) $record->funding_amount, 0, ',', '.'),
                'startLabel' => $record->starts_at?->format('d M Y'),
                'status' => $record->status,
                'progress' => $record->milestones->isEmpty() ? 0 : (int) round($record->milestones->avg('progress_percentage')),
                'milestonesCount' => $record->milestones_count,
                'outputsCount' => $record->outputs_count,
                'attachmentsCount' => $record->attachments_count,
                'showUrl' => route($this->routePrefix($request).'tridharma.show', $record),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Shared/Employee/Tridharma/Index', [
            'shell' => ShellProps::make($request->user(), 'Tridharma', 'Tridharma Saya'),
            'records' => $records,
            'createUrl' => route($this->routePrefix($request).'tridharma.create'),
            'stats' => [
                'total' => count($records),
                'approved' => collect($records)->whereIn('status', ['approved', 'active', 'completed'])->count(),
                'draft' => collect($records)->where('status', 'draft')->count(),
                'outputs' => collect($records)->sum('outputsCount'),
                'funding' => collect($records)->sum('fundingAmount'),
                'fundingLabel' => 'Rp '.number_format(collect($records)->sum('fundingAmount'), 0, ',', '.'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeSelfService($request);
        $user = $request->user();

        return Inertia::render('Shared/Employee/Tridharma/Create', [
            'shell' => ShellProps::make($user, 'Tridharma', 'Tambah Tridharma'),
            'leader' => ['name' => $user->name, 'email' => $user->email],
            'indexUrl' => route($this->routePrefix($request).'tridharma.index'),
            'basePath' => $this->basePath($request),
            'searchUsersUrl' => route($this->routePrefix($request).'users.search'),
        ]);
    }

    public function store(Request $request, TridharmaRecordService $service): RedirectResponse
    {
        $this->authorizeSelfService($request);

        $validated = $request->validate([
            'type' => ['required', 'in:research,community_service,publication'],
            'title' => ['required', 'string', 'max:255'],
            'scheme' => ['nullable', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'funding_amount' => ['nullable', 'numeric', 'min:0'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'proposal' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192'],
            'team' => ['nullable', 'array'],
            'team.*.kind' => ['required_with:team', 'in:internal,external'],
            'team.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'team.*.name' => ['nullable', 'string', 'max:255'],
            'team.*.email' => ['nullable', 'email', 'max:255'],
            'team.*.institution' => ['nullable', 'string', 'max:255'],
            'team.*.role' => ['nullable', 'string', 'max:100'],
            'action' => ['required', 'in:draft,submit'],
        ]);

        $record = DB::transaction(function () use ($request, $validated, $service) {
            $user = $request->user()->load(['lecturerProfile', 'employeeProfile']);

            $record = TridharmaRecord::create([
                'user_id' => $user->id,
                'lecturer_profile_id' => $user->lecturerProfile?->id,
                'employee_profile_id' => $user->employeeProfile?->id,
                'type' => $validated['type'],
                'title' => $validated['title'],
                'scheme' => $validated['scheme'] ?? null,
                'abstract' => $validated['abstract'] ?? null,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'funding_amount' => $validated['funding_amount'] ?? 0,
                'funding_source' => $validated['funding_source'] ?? null,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $record->members()->create([
                'user_id' => $user->id,
                'member_name' => null,
                'institution' => null,
                'email' => null,
                'role' => 'leader',
                'is_external' => false,
                'sort_order' => 1,
            ]);

            $sort = 2;
            foreach ($validated['team'] ?? [] as $member) {
                if (($member['kind'] ?? null) === 'internal' && ! empty($member['user_id']) && (int) $member['user_id'] !== (int) $user->id) {
                    $record->members()->create([
                        'user_id' => (int) $member['user_id'],
                        'member_name' => null,
                        'institution' => $member['institution'] ?? null,
                        'email' => null,
                        'role' => $member['role'] ?? 'member',
                        'is_external' => false,
                        'sort_order' => $sort++,
                    ]);
                } elseif (($member['kind'] ?? null) === 'external' && ! empty($member['name'])) {
                    $record->members()->create([
                        'user_id' => null,
                        'member_name' => $member['name'],
                        'institution' => $member['institution'] ?? null,
                        'email' => $member['email'] ?? null,
                        'role' => $member['role'] ?? 'member',
                        'is_external' => true,
                        'sort_order' => $sort++,
                    ]);
                }
            }

            if ($request->hasFile('proposal')) {
                $service->storeAttachment($record, $request->file('proposal'), 'proposal', $user);
            }

            if ($validated['action'] === 'submit') {
                $admin = User::query()->where('email', 'superuser@example.com')->first() ?? $user;
                $service->ensureDefaultApprovalTemplate($admin);
                $service->submitForApproval($record, $user);
            }

            return $record->fresh();
        });

        $message = $validated['action'] === 'submit' ? 'Proposal Tridharma berhasil diajukan.' : 'Draft Tridharma berhasil dibuat.';

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', $message);
    }

    public function show(Request $request, TridharmaRecord $record): Response
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);

        $record->load(['members.user', 'milestones', 'outputs.attachments', 'attachments', 'approvalRequest.steps.actedBy']);

        return Inertia::render('Shared/Employee/Tridharma/Show', [
            'shell' => ShellProps::make($request->user(), 'Tridharma', 'Detail Tridharma'),
            'record' => $this->shapeRecord($request, $record),
            'indexUrl' => route($this->routePrefix($request).'tridharma.index'),
            'basePath' => $this->basePath($request),
            'searchUsersUrl' => route($this->routePrefix($request).'users.search'),
        ]);
    }

    public function submitApproval(Request $request, TridharmaRecord $record, TridharmaRecordService $service): RedirectResponse
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);

        $admin = User::query()->where('email', 'superuser@example.com')->first() ?? $request->user();
        $service->ensureDefaultApprovalTemplate($admin);
        $service->submitForApproval($record, $request->user());

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', 'Proposal diajukan ke approval.');
    }

    public function storeMilestone(Request $request, TridharmaRecord $record): RedirectResponse
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'completion_notes' => ['nullable', 'string'],
        ]);

        $progress = (int) $data['progress_percentage'];
        $status = $this->milestoneStatus($progress);

        $record->milestones()->create($data + [
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
            'completed_by' => $status === 'completed' ? $request->user()->id : null,
            'sort_order' => $record->milestones()->count() + 1,
        ]);

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', 'Milestone berhasil ditambahkan.');
    }

    public function updateMilestone(Request $request, TridharmaRecord $record, int $milestoneId): RedirectResponse
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);
        abort_unless($this->canUpdateProgress($record), 403);

        $milestone = $record->milestones()->whereKey($milestoneId)->firstOrFail();
        abort_if($this->isMilestoneLocked($milestone), 403);

        $data = $request->validate([
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'completion_notes' => ['nullable', 'string'],
        ]);

        $progress = (int) $data['progress_percentage'];
        $status = $this->milestoneStatus($progress);

        $milestone->update([
            'progress_percentage' => $progress,
            'status' => $status,
            'completion_notes' => $data['completion_notes'] ?? null,
            'completed_at' => $status === 'completed' ? now() : null,
            'completed_by' => $status === 'completed' ? $request->user()->id : null,
        ]);

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', 'Progress milestone berhasil diperbarui.');
    }

    public function storeOutput(Request $request, TridharmaRecord $record, TridharmaRecordService $service): RedirectResponse
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);

        $data = $request->validate([
            'output_id' => ['nullable', 'integer'],
            'output_type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'indexing' => ['nullable', 'string', 'max:255'],
            'doi' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'in:draft,submitted,published,accepted'],
            'published_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx,csv,zip', 'max:8192'],
        ]);

        if (! empty($data['output_id'])) {
            $output = $record->outputs()->whereKey($data['output_id'])->firstOrFail();
            abort_if($this->isOutputLocked($output), 403);
            abort_unless($this->canUpdateProgress($record), 403);
            $output->update(collect($data)->except(['output_id', 'document'])->all());
            $message = 'Luaran berhasil diperbarui.';
        } else {
            $output = $record->outputs()->create(collect($data)->except(['output_id', 'document'])->all());
            $message = 'Luaran berhasil ditambahkan.';
        }

        if ($request->hasFile('document')) {
            $service->storeAttachment($record, $request->file('document'), 'output', $request->user(), $output);
        }

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', $message);
    }

    public function storeMember(Request $request, TridharmaRecord $record): RedirectResponse
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);
        abort_unless($this->canManageTeam($request, $record), 403);

        $kind = $request->input('kind', 'external');

        if ($kind === 'internal') {
            $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

            if (! $record->members->contains(fn ($member) => (int) $member->user_id === (int) $data['user_id'])) {
                User::query()->where('is_active', true)->findOrFail($data['user_id']);
                $record->members()->create([
                    'user_id' => (int) $data['user_id'],
                    'role' => 'member',
                    'is_external' => false,
                    'sort_order' => $record->members()->count() + 1,
                ]);
            }

            return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', 'Anggota internal ditambahkan.');
        }

        $data = $request->validate([
            'member_name' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', 'string', 'max:100'],
        ]);

        $record->members()->create([
            'member_name' => $data['member_name'],
            'institution' => $data['institution'] ?? null,
            'email' => $data['email'] ?? null,
            'role' => $data['role'],
            'is_external' => true,
            'sort_order' => $record->members()->count() + 1,
        ]);

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', 'Anggota eksternal ditambahkan.');
    }

    public function destroyMember(Request $request, TridharmaRecord $record, int $memberId): RedirectResponse
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);
        abort_unless($this->canManageTeam($request, $record), 403);

        $member = $record->members()->whereKey($memberId)->firstOrFail();

        if ($member->role === 'leader' || (int) $member->user_id === (int) $request->user()->id) {
            return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('error', 'Ketua tim atau diri sendiri tidak bisa dihapus.');
        }

        $member->delete();

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', 'Anggota tim dihapus.');
    }

    public function storeAttachment(Request $request, TridharmaRecord $record, TridharmaRecordService $service): RedirectResponse
    {
        $this->authorizeSelfService($request);
        abort_unless($record->userCanAccess($request->user()), 403);

        $request->validate(['document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,xlsx', 'max:8192']]);
        $service->storeAttachment($record, $request->file('document'), 'evidence', $request->user());

        return redirect()->route($this->routePrefix($request).'tridharma.show', $record)->with('success', 'Dokumen pendukung diunggah.');
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $this->authorizeSelfService($request);

        $query = trim((string) $request->input('q', ''));
        $exclude = array_filter(array_map('intval', (array) $request->input('exclude', [])));

        if (mb_strlen($query) < 2) {
            return response()->json(['users' => []]);
        }

        $search = '%'.$query.'%';

        $users = User::query()
            ->where('is_active', true)
            ->when($exclude !== [], fn ($builder) => $builder->whereNotIn('id', $exclude))
            ->where(fn ($builder) => $builder
                ->where('first_name', 'like', $search)
                ->orWhere('last_name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('username', 'like', $search)
                ->orWhere('code', 'like', $search)
                ->orWhere('identity_number', 'like', $search))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'email', 'username', 'code'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'code' => $user->code,
            ])
            ->values()
            ->all();

        return response()->json(['users' => $users]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeRecord(Request $request, TridharmaRecord $record): array
    {
        $user = $request->user();
        $canManageTeam = $this->canManageTeam($request, $record);
        $canUpdateProgress = $this->canUpdateProgress($record);

        return [
            'id' => $record->id,
            'type' => $record->type,
            'typeLabel' => $this->typeLabel($record->type),
            'title' => $record->title,
            'scheme' => $record->scheme,
            'abstract' => $record->abstract,
            'startsLabel' => $record->starts_at?->format('d M Y'),
            'endsLabel' => $record->ends_at?->format('d M Y'),
            'fundingLabel' => 'Rp '.number_format((float) $record->funding_amount, 0, ',', '.'),
            'fundingSource' => $record->funding_source,
            'status' => $record->status,
            'statusLabel' => $this->statusLabel($record->status),
            'progress' => $record->milestones->isEmpty() ? 0 : (int) round($record->milestones->avg('progress_percentage')),
            'canManageTeam' => $canManageTeam,
            'canUpdateProgress' => $canUpdateProgress,
            'canSubmit' => (int) $record->user_id === (int) $user->id && in_array($record->status, ['draft', 'rejected'], true),
            'members' => $record->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->user?->name ?? $member->member_name ?? '-',
                'detail' => $member->institution ?? $member->user?->email ?? $member->email ?? '-',
                'role' => $member->role,
                'roleLabel' => str_replace('_', ' ', (string) $member->role),
                'isExternal' => (bool) $member->is_external,
                'isLeader' => $member->role === 'leader',
                'removable' => $canManageTeam && $member->role !== 'leader' && (int) $member->user_id !== (int) $user->id,
            ])->values()->all(),
            'milestones' => $record->milestones->map(fn ($milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'dueLabel' => $milestone->due_date?->format('d M Y'),
                'progress' => (int) $milestone->progress_percentage,
                'status' => $milestone->status,
                'statusLabel' => $this->statusLabel($milestone->status ?? $this->milestoneStatus((int) $milestone->progress_percentage)),
                'notes' => $milestone->completion_notes,
                'locked' => $this->isMilestoneLocked($milestone),
                'editable' => $canUpdateProgress && ! $this->isMilestoneLocked($milestone),
            ])->values()->all(),
            'outputs' => $record->outputs->map(fn ($output) => [
                'id' => $output->id,
                'type' => $output->output_type,
                'title' => $output->title,
                'publisher' => $output->publisher,
                'indexing' => $output->indexing,
                'doi' => $output->doi,
                'url' => $output->url,
                'publishedLabel' => $output->published_at?->format('d M Y'),
                'status' => $output->status,
                'statusLabel' => $this->outputStatusLabel($output->status),
                'notes' => $output->notes,
                'locked' => $this->isOutputLocked($output),
                'editable' => $canUpdateProgress && ! $this->isOutputLocked($output),
                'attachments' => $output->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'fileName' => $attachment->file_name,
                    'previewUrl' => route('tridharma.attachments.preview', $attachment),
                ])->values()->all(),
            ])->values()->all(),
            'attachments' => $record->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'fileName' => $attachment->file_name,
                'documentType' => $attachment->document_type,
                'previewUrl' => route('tridharma.attachments.preview', $attachment),
            ])->values()->all(),
            'approvalSteps' => $record->approvalRequest?->steps->map(fn ($step) => [
                'id' => $step->id,
                'name' => $step->name ?? 'Tahap approval',
                'status' => $step->status,
                'statusLabel' => $this->statusLabel($step->status ?? 'pending'),
                'actorName' => $step->actedBy?->name,
                'actedLabel' => $step->acted_at?->format('d M Y H:i'),
                'notes' => $step->notes,
            ])->values()->all() ?? [],
        ];
    }

    private function authorizeSelfService(Request $request): void
    {
        if ($request->routeIs('lecturer.*')) {
            return;
        }

        abort_unless((bool) $request->user()?->employeeProfile?->is_active, 403);
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('lecturer.*') ? 'lecturer.' : 'employee.';
    }

    private function basePath(Request $request): string
    {
        return $request->routeIs('lecturer.*') ? '/lecturer/tridharma' : '/employee/tridharma';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'research' => 'Penelitian',
            'community_service' => 'Pengabdian',
            'publication' => 'Publikasi',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'rejected' => 'Ditolak',
            'archived' => 'Diarsipkan',
            'pending' => 'Belum Mulai',
            'in_progress' => 'Berjalan',
            'blocked' => 'Terkendala',
            'published' => 'Final/Terbit',
            'accepted' => 'Diterima',
            default => ucwords(str_replace('_', ' ', (string) $status)),
        };
    }

    private function outputStatusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Rencana',
            'submitted' => 'Diajukan',
            'accepted' => 'Diterima',
            'published' => 'Final/Terbit',
            default => $this->statusLabel($status),
        };
    }

    private function milestoneStatus(int $progress): string
    {
        return match (true) {
            $progress >= 100 => 'completed',
            $progress > 0 => 'in_progress',
            default => 'pending',
        };
    }

    private function isMilestoneLocked($milestone): bool
    {
        return (int) $milestone->progress_percentage >= 100 || $milestone->status === 'completed';
    }

    private function isOutputLocked($output): bool
    {
        return $output->status === 'published';
    }

    private function canUpdateProgress(TridharmaRecord $record): bool
    {
        return in_array($record->status, ['approved', 'active', 'completed'], true);
    }

    private function canManageTeam(Request $request, TridharmaRecord $record): bool
    {
        return (int) $record->user_id === (int) $request->user()->id
            && in_array($record->status, ['draft', 'rejected'], true);
    }
}
