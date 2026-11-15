<?php

namespace App\Http\Controllers\Admin\Publication;

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Http\Controllers\Controller;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\Faculty;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Publication\Announcement;
use App\Models\Publication\AnnouncementRead;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Pengumuman — memakai kit CRUD shared (pola menu academic).
 *
 * Khusus menu ini: permission announcement.publish mengawal aksi
 * publish/unpublish (massal maupun satuan), pin dikawal update,
 * target penerima dipilih via endpoint search-targets (tanpa full-load
 * seperti Blade lama), lampiran tunggal 5MB dikelola di store/update/
 * destroy, dan scheduled_at dihormati scopePublished().
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'target_type' => 'nullable|string',
            'priority' => 'nullable|string',
            'is_published' => 'nullable|in:0,1',
            'is_pinned' => 'nullable|in:0,1',
            'sort' => 'nullable|in:id,title,published_at,created_at',
            'direction' => 'nullable|in:asc,desc',
            'mode' => 'nullable|in:all,trash',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        foreach (['target_type' => AnnouncementTargetType::class, 'priority' => AnnouncementPriority::class] as $field => $enum) {
            if (filled($validated[$field] ?? null)) {
                $request->validate([$field => Rule::enum($enum)]);
            }
        }

        $isTrash = ($validated['mode'] ?? 'all') === 'trash';
        $query = Announcement::query()
            ->with('creator:id,first_name,last_name')
            ->withCount('reads');

        if ($isTrash) {
            $query->onlyTrashed();
        }

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhereHas('creator', fn ($c) => $c
                    ->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")));
        }

        foreach (['target_type', 'priority'] as $field) {
            if (filled($validated[$field] ?? null)) {
                $query->where($field, $validated[$field]);
            }
        }

        foreach (['is_published', 'is_pinned'] as $field) {
            if (isset($validated[$field]) && $validated[$field] !== '') {
                $query->where($field, (bool) $validated[$field]);
            }
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderByDesc('is_pinned')->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $announcements = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Publication/Announcement/Index', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Daftar Pengumuman'),
            'can' => [
                'create' => ! $isTrash && ActivePermission::check('announcement.create'),
                'update' => ActivePermission::check('announcement.update'),
                'delete' => ActivePermission::check('announcement.delete'),
                'restore' => ActivePermission::any(['announcement.update', 'announcement.delete']),
                'toggle' => ActivePermission::check('announcement.update'),
                'publish' => ActivePermission::check('announcement.publish'),
                'view' => ActivePermission::check('announcement.view'),
            ],
            'stats' => [
                'total' => Announcement::count(),
                'published' => Announcement::where('is_published', true)->count(),
                'draft' => Announcement::where('is_published', false)->count(),
                'pinned' => Announcement::where('is_pinned', true)->count(),
                'reads' => AnnouncementRead::count(),
                'trashed' => Announcement::onlyTrashed()->count(),
            ],
            'data' => [
                'rows' => collect($announcements->items())->values()->map(fn ($a, $i) => [
                    'id' => $a->id,
                    'no' => ($announcements->firstItem() ?? 0) + $i,
                    'title' => $a->title,
                    'targetType' => $a->target_type instanceof AnnouncementTargetType ? $a->target_type->value : (string) $a->target_type,
                    'targetLabel' => $a->target_type instanceof AnnouncementTargetType ? $a->target_type->label() : (string) $a->target_type,
                    'priority' => $a->priority instanceof AnnouncementPriority ? $a->priority->value : (string) $a->priority,
                    'priorityLabel' => $a->priority instanceof AnnouncementPriority ? $a->priority->label() : (string) $a->priority,
                    'isPinned' => (bool) $a->is_pinned,
                    'isPublished' => (bool) $a->is_published,
                    'isScheduled' => (bool) ($a->is_published && $a->scheduled_at && $a->scheduled_at->isFuture()),
                    'publishedAt' => $a->published_at?->format('d M Y H:i'),
                    'creatorName' => $a->creator?->name ?? '-',
                    'readsCount' => (int) $a->reads_count,
                    'hasAttachment' => (bool) $a->attachment_path,
                    'createdAt' => $a->created_at?->format('d M Y H:i'),
                    'editUrl' => $isTrash ? null : route('admin.publication.announcements.edit', $a->id),
                    'showUrl' => route('admin.publication.announcements.show', $a->id),
                    'deleteUrl' => route('admin.publication.announcements.destroy', $a->id),
                    'restoreUrl' => route('admin.publication.announcements.restore', $a->id),
                    'forceUrl' => route('admin.publication.announcements.force-destroy', $a->id),
                    'togglePinUrl' => route('admin.publication.announcements.toggle-pin', $a->id),
                    'publishUrl' => route('admin.publication.announcements.publish', $a->id),
                    'unpublishUrl' => route('admin.publication.announcements.unpublish', $a->id),
                ])->all(),
                'currentPage' => $announcements->currentPage(),
                'lastPage' => $announcements->lastPage(),
                'perPage' => $announcements->perPage(),
                'total' => $announcements->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'target_type' => $validated['target_type'] ?? '',
                'priority' => $validated['priority'] ?? '',
                'is_published' => $validated['is_published'] ?? '',
                'is_pinned' => $validated['is_pinned'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'mode' => $isTrash ? 'trash' : 'all',
                'perPage' => $perPage,
            ],
            'targetTypeOptions' => collect(AnnouncementTargetType::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'priorityOptions' => collect(AnnouncementPriority::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'importResult' => $request->session()->get('import_result'),
            'urls' => [
                'index' => route('admin.publication.announcements.index'),
                'create' => route('admin.publication.announcements.create'),
                'export' => route('admin.publication.announcements.export'),
                'exportPdf' => route('admin.publication.exports.pdf', ['resource' => 'announcements']),
                'importTemplate' => route('admin.publication.announcements.import-template'),
                'importSubmit' => route('admin.publication.announcements.import'),
                'bulkDestroy' => route('admin.publication.announcements.bulk-destroy'),
                'bulkRestore' => route('admin.publication.announcements.bulk-restore'),
                'bulkForceDestroy' => route('admin.publication.announcements.bulk-force-destroy'),
                'bulkPublish' => route('admin.publication.announcements.bulk-publish'),
                'searchTargets' => url('/admin/publication/announcements/search-targets'),
            ],
        ]);
    }

    public function show(Request $request, Announcement $announcement): Response
    {
        $announcement->loadMissing('creator:id,first_name,last_name')->loadCount('reads');

        return Inertia::render('Admin/Publication/Announcement/Show', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Detail Pengumuman'),
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'targetType' => $announcement->target_type instanceof AnnouncementTargetType ? $announcement->target_type->value : (string) $announcement->target_type,
                'targetLabel' => $announcement->target_type instanceof AnnouncementTargetType ? $announcement->target_type->label() : (string) $announcement->target_type,
                'targetDetail' => $this->resolveTargetLabel($announcement->target_type, $announcement->target_id),
                'priority' => $announcement->priority instanceof AnnouncementPriority ? $announcement->priority->value : (string) $announcement->priority,
                'priorityLabel' => $announcement->priority instanceof AnnouncementPriority ? $announcement->priority->label() : (string) $announcement->priority,
                'isPinned' => (bool) $announcement->is_pinned,
                'isPublished' => (bool) $announcement->is_published,
                'isScheduled' => (bool) ($announcement->is_published && $announcement->scheduled_at && $announcement->scheduled_at->isFuture()),
                'publishedAt' => $announcement->published_at?->format('d M Y H:i'),
                'scheduledAt' => $announcement->scheduled_at?->format('d M Y H:i'),
                'attachmentName' => $announcement->attachment_name,
                'attachmentType' => $announcement->attachment_type,
                'attachmentSize' => $announcement->formatted_attachment_size,
                'attachmentUrl' => $this->publicUrl($announcement->attachment_path),
                'readsCount' => (int) $announcement->reads_count,
                'creatorName' => $announcement->creator?->name ?? '-',
                'createdAt' => $announcement->created_at?->format('d M Y H:i'),
                'updatedAt' => $announcement->updated_at?->format('d M Y H:i'),
            ],
            'can' => [
                'update' => ActivePermission::check('announcement.update'),
                'delete' => ActivePermission::check('announcement.delete'),
                'publish' => ActivePermission::check('announcement.publish'),
            ],
            'urls' => [
                'index' => route('admin.publication.announcements.index'),
                'edit' => route('admin.publication.announcements.edit', $announcement),
                'publish' => route('admin.publication.announcements.publish', $announcement),
                'unpublish' => route('admin.publication.announcements.unpublish', $announcement),
                'destroy' => route('admin.publication.announcements.destroy', $announcement),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Publication/Announcement/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Buat Pengumuman'),
            'mode' => 'create',
            'announcement' => [
                'title' => '', 'content' => '',
                'target_type' => AnnouncementTargetType::GLOBAL->value, 'target_id' => null,
                'target_label' => null,
                'priority' => AnnouncementPriority::NORMAL->value,
                'is_pinned' => false, 'is_published' => false,
                'published_at' => now()->format('Y-m-d\TH:i'), 'scheduled_at' => '',
                'attachment_name' => null,
            ],
            'targetTypeOptions' => collect(AnnouncementTargetType::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'priorityOptions' => collect(AnnouncementPriority::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'urls' => [
                'index' => route('admin.publication.announcements.index'),
                'submit' => route('admin.publication.announcements.store'),
                'searchTargets' => url('/admin/publication/announcements/search-targets'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_type' => ['required', Rule::enum(AnnouncementTargetType::class)],
            'target_id' => 'nullable|integer',
            'priority' => ['required', Rule::enum(AnnouncementPriority::class)],
            'is_pinned' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ]);

        $targetType = $validated['target_type'] instanceof AnnouncementTargetType
            ? $validated['target_type']
            : AnnouncementTargetType::from($validated['target_type']);

        if ($targetType->requiresTargetId() && empty($validated['target_id'])) {
            return back()->withErrors(['target_id' => 'Target harus dipilih untuk tipe ini.'])->withInput();
        }

        if (! empty($validated['target_id']) && ! $this->targetExists($targetType, (int) $validated['target_id'])) {
            return back()->withErrors(['target_id' => 'Target yang dipilih tidak ditemukan.'])->withInput();
        }

        $data = [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'target_type' => $targetType->value,
            'target_id' => $targetType->requiresTargetId() ? ($validated['target_id'] ?? null) : null,
            'created_by' => $request->user()->id,
            'priority' => $validated['priority'] instanceof AnnouncementPriority ? $validated['priority']->value : $validated['priority'],
            'is_pinned' => $validated['is_pinned'] ?? false,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? ($validated['published_at'] ?? now()) : null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('announcements/'.now()->format('Y/m'), 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_type'] = $file->getClientOriginalExtension();
            $data['attachment_size'] = $file->getSize();
        }

        $announcement = Announcement::create($data);

        return redirect()->route('admin.publication.announcements.index')
            ->with('success', 'Pengumuman "'.$announcement->title.'" berhasil dibuat.');
    }

    public function edit(Request $request, Announcement $announcement): Response
    {
        $targetType = $announcement->target_type instanceof AnnouncementTargetType
            ? $announcement->target_type
            : AnnouncementTargetType::from((string) $announcement->target_type);

        return Inertia::render('Admin/Publication/Announcement/Form', [
            'shell' => ShellProps::make($request->user(), 'Publikasi', 'Edit Pengumuman'),
            'mode' => 'edit',
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'target_type' => $targetType->value,
                'target_id' => $announcement->target_id,
                'target_label' => $this->resolveTargetLabel($targetType, $announcement->target_id),
                'priority' => $announcement->priority instanceof AnnouncementPriority ? $announcement->priority->value : (string) $announcement->priority,
                'is_pinned' => (bool) $announcement->is_pinned,
                'is_published' => (bool) $announcement->is_published,
                'published_at' => $announcement->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'),
                'scheduled_at' => $announcement->scheduled_at?->format('Y-m-d\TH:i') ?? '',
                'attachment_name' => $announcement->attachment_name,
                'attachment_size' => $announcement->formatted_attachment_size,
            ],
            'targetTypeOptions' => collect(AnnouncementTargetType::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'priorityOptions' => collect(AnnouncementPriority::cases())
                ->map(fn ($c) => ['id' => $c->value, 'name' => $c->label()])->all(),
            'urls' => [
                'index' => route('admin.publication.announcements.index'),
                'submit' => route('admin.publication.announcements.update', $announcement),
                'searchTargets' => url('/admin/publication/announcements/search-targets'),
            ],
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_type' => ['required', Rule::enum(AnnouncementTargetType::class)],
            'target_id' => 'nullable|integer',
            'priority' => ['required', Rule::enum(AnnouncementPriority::class)],
            'is_pinned' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'remove_attachment' => 'boolean',
        ]);

        $targetType = $validated['target_type'] instanceof AnnouncementTargetType
            ? $validated['target_type']
            : AnnouncementTargetType::from($validated['target_type']);

        if ($targetType->requiresTargetId() && empty($validated['target_id'])) {
            return back()->withErrors(['target_id' => 'Target harus dipilih untuk tipe ini.'])->withInput();
        }

        if (! empty($validated['target_id']) && ! $this->targetExists($targetType, (int) $validated['target_id'])) {
            return back()->withErrors(['target_id' => 'Target yang dipilih tidak ditemukan.'])->withInput();
        }

        $data = [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'target_type' => $targetType->value,
            'target_id' => $targetType->requiresTargetId() ? ($validated['target_id'] ?? null) : null,
            'priority' => $validated['priority'] instanceof AnnouncementPriority ? $validated['priority']->value : $validated['priority'],
            'is_pinned' => $validated['is_pinned'] ?? false,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? ($validated['published_at'] ?? now()) : null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'updated_by' => $request->user()->id,
        ];

        if ($validated['remove_attachment'] ?? false) {
            $this->deleteAttachment($announcement);
            $data['attachment_path'] = null;
            $data['attachment_name'] = null;
            $data['attachment_type'] = null;
            $data['attachment_size'] = null;
        }

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($announcement);
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('announcements/'.now()->format('Y/m'), 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_type'] = $file->getClientOriginalExtension();
            $data['attachment_size'] = $file->getSize();
        }

        $announcement->update($data);

        return redirect()->route('admin.publication.announcements.index')
            ->with('success', 'Pengumuman "'.$announcement->title.'" berhasil diperbarui.');
    }

    public function togglePin(Request $request, Announcement $announcement)
    {
        $validated = $request->validate(['is_pinned' => 'required|boolean']);

        $announcement->update(['is_pinned' => $validated['is_pinned'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Pin pengumuman berhasil diperbarui.');
    }

    public function publish(Request $request, Announcement $announcement)
    {
        $announcement->update([
            'is_published' => true,
            'published_at' => $announcement->published_at ?? now(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Pengumuman "'.$announcement->title.'" dipublish.');
    }

    public function unpublish(Request $request, Announcement $announcement)
    {
        $announcement->update(['is_published' => false, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Publish pengumuman "'.$announcement->title.'" dibatalkan (kembali ke draft).');
    }

    public function bulkPublish(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:announcements,id',
        ]);

        $published = 0;
        $skipped = 0;

        foreach (Announcement::whereIn('id', $validated['ids'])->get() as $announcement) {
            if ($announcement->is_published) {
                $skipped++;
                continue;
            }

            $announcement->update([
                'is_published' => true,
                'published_at' => $announcement->published_at ?? now(),
                'updated_by' => $request->user()->id,
            ]);
            $published++;
        }

        $message = $published > 0 ? "{$published} pengumuman dipublish." : 'Tidak ada pengumuman yang dipublish.';
        if ($skipped > 0) {
            $message .= " {$skipped} dilewati (sudah terbit).";
        }

        return back()->with($published > 0 ? 'success' : 'error', $message);
    }

    public function destroy(Announcement $announcement)
    {
        $this->deleteAttachment($announcement);

        $title = $announcement->title;
        $announcement->delete();

        return redirect()->route('admin.publication.announcements.index')
            ->with('success', 'Pengumuman "'.$title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:announcements,id',
        ]);

        $announcements = Announcement::whereIn('id', $validated['ids'])->get();

        foreach ($announcements as $announcement) {
            $this->deleteAttachment($announcement);
        }

        $count = $announcements->count();
        Announcement::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count.' pengumuman berhasil dihapus.');
    }

    public function restore(int $id)
    {
        $announcement = Announcement::withTrashed()->findOrFail($id);
        $announcement->restore();

        return back()->with('success', 'Pengumuman "'.$announcement->title.'" berhasil dipulihkan.');
    }

    public function forceDestroy(int $id)
    {
        $announcement = Announcement::withTrashed()->findOrFail($id);

        $this->deleteAttachment($announcement);

        $title = $announcement->title;
        $announcement->forceDelete();

        return back()->with('success', 'Pengumuman "'.$title.'" dihapus permanen.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:announcements,id',
        ]);

        $count = 0;

        foreach (Announcement::onlyTrashed()->whereIn('id', $validated['ids'])->get() as $announcement) {
            $announcement->restore();
            $count++;
        }

        return back()->with('success', $count.' pengumuman berhasil dipulihkan.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:announcements,id',
        ]);

        $announcements = Announcement::withTrashed()->whereIn('id', $validated['ids'])->get();

        foreach ($announcements as $announcement) {
            $this->deleteAttachment($announcement);
            $announcement->forceDelete();
        }

        return back()->with('success', $announcements->count().' pengumuman dihapus permanen.');
    }

    public function searchTargets(Request $request, string $type)
    {
        $targetType = AnnouncementTargetType::tryFrom($type);
        abort_unless($targetType && $targetType !== AnnouncementTargetType::GLOBAL, 404);

        $validated = $request->validate(['q' => 'nullable|string|max:100']);

        $q = $validated['q'] ?? '';

        $type = $targetType;

        $options = match ($type) {
            AnnouncementTargetType::FACULTY => Faculty::query()
                ->when(filled($q), fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->orderBy('name')->limit(20)->get(['id', 'name'])
                ->map(fn ($f) => ['id' => $f->id, 'label' => $f->name])->all(),
            AnnouncementTargetType::STUDY_PROGRAM => StudyProgram::query()
                ->with('faculty:id,name')
                ->when(filled($q), fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->orderBy('name')->limit(20)->get(['id', 'name', 'faculty_id'])
                ->map(fn ($sp) => ['id' => $sp->id, 'label' => $sp->name.($sp->faculty ? ' ('.$sp->faculty->name.')' : '')])->all(),
            AnnouncementTargetType::COURSE_OFFERING => CourseOffering::query()
                ->with(['course:id,code,name', 'academicYear:id,name'])
                ->when(filled($q), fn ($query) => $query->whereHas('course', fn ($c) => $c
                    ->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn ($co) => ['id' => $co->id, 'label' => ($co->course?->code ?? '-').' - '.($co->course?->name ?? '-').($co->academicYear ? ' ('.$co->academicYear->name.')' : '')])->all(),
            AnnouncementTargetType::LECTURER => LecturerProfile::query()
                ->with('user:id,first_name,last_name')
                ->when(filled($q), fn ($query) => $query->whereHas('user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")))
                ->orderByDesc('id')->limit(20)->get()
                ->filter(fn ($p) => $p->user)
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->user->name])->values()->all(),
            AnnouncementTargetType::STUDENT => StudentProfile::query()
                ->with('user:id,first_name,last_name')
                ->when(filled($q), fn ($query) => $query
                    ->where('nim', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")))
                ->orderByDesc('id')->limit(20)->get(['id', 'nim', 'user_id'])
                ->filter(fn ($p) => $p->user)
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->user->name.($p->nim ? ' ('.$p->nim.')' : '')])->values()->all(),
            AnnouncementTargetType::GLOBAL => [],
        };

        return response()->json(['options' => $options]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx']), 404);

        $query = Announcement::query()->withCount('reads');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->query('ids')));
        }

        if ($request->query('mode') === 'trash') {
            $query->onlyTrashed();
        }

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where('title', 'like', "%{$q}%");
        }

        foreach (['target_type', 'priority'] as $field) {
            if (filled($request->query($field))) {
                $query->where($field, $request->query($field));
            }
        }

        foreach (['is_published', 'is_pinned'] as $field) {
            if ($request->query($field) !== null && $request->query($field) !== '') {
                $query->where($field, (bool) $request->query($field));
            }
        }

        $rows = $query->orderByDesc('is_pinned')->orderByDesc('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Judul', 'Target', 'Prioritas', 'Pin', 'Status', 'Dibaca', 'Lampiran', 'Dibuat']], null, 'A1');

        foreach ($rows as $i => $announcement) {
            $sheet->fromArray([
                $announcement->title,
                $announcement->target_type instanceof AnnouncementTargetType ? $announcement->target_type->label() : (string) $announcement->target_type,
                $announcement->priority instanceof AnnouncementPriority ? $announcement->priority->label() : (string) $announcement->priority,
                $announcement->is_pinned ? 'Ya' : 'Tidak',
                $announcement->is_published ? 'Published' : 'Draft',
                (int) $announcement->reads_count,
                $announcement->attachment_name ?? '-',
                $announcement->created_at?->format('Y-m-d H:i:s'),
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'announcements-'.now()->format('Ymd-His').'.'.$format;
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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['title', 'content', 'target_type', 'target_id', 'priority', 'is_published']], null, 'A1');
        $sheet->fromArray([['Libur Semester', 'Perkuliahan libur mulai...', 'global', '', 'normal', '1']], null, 'A2');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'template-import-announcements.xlsx',
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
        $missing = array_diff(['title', 'content', 'target_type'], $header);

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

        $allowedTargets = collect(AnnouncementTargetType::cases())->map(fn ($c) => $c->value)->all();
        $allowedPriorities = collect(AnnouncementPriority::cases())->map(fn ($c) => $c->value)->all();
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

            if (($data['title'] ?? null) === null || $data['title'] === '') {
                $rowErrors[] = 'title wajib diisi';
            } elseif (strlen((string) $data['title']) > 255) {
                $rowErrors[] = 'title maksimal 255 karakter';
            }

            if (($data['content'] ?? null) === null || $data['content'] === '') {
                $rowErrors[] = 'content wajib diisi';
            }

            $targetType = null;

            if (($data['target_type'] ?? null) === null || $data['target_type'] === '') {
                $rowErrors[] = 'target_type wajib diisi';
            } elseif (! in_array($data['target_type'], $allowedTargets, true)) {
                $rowErrors[] = 'target_type tidak valid ('.implode('/', $allowedTargets).')';
            } else {
                $targetType = AnnouncementTargetType::from($data['target_type']);
            }

            $targetId = isset($data['target_id']) && $data['target_id'] !== '' ? (int) $data['target_id'] : null;

            if ($targetType && $targetType->requiresTargetId() && ! $targetId) {
                $rowErrors[] = 'target_id wajib diisi untuk tipe '.$targetType->value;
            }

            if ($targetType && $targetId && ! $this->targetExists($targetType, $targetId)) {
                $rowErrors[] = 'target_id tidak ditemukan';
            }

            $priority = $data['priority'] ?? AnnouncementPriority::NORMAL->value;

            if (! in_array($priority, $allowedPriorities, true)) {
                $rowErrors[] = 'priority tidak valid ('.implode('/', $allowedPriorities).')';
            }

            if (! empty($errors) && count($errors) >= 20) {
                break;
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $line, 'messages' => $rowErrors];
                continue;
            }

            $isPublished = $this->parseImportBool($data['is_published'] ?? null, false);

            $valid[] = [
                'title' => $data['title'],
                'content' => $data['content'],
                'target_type' => $targetType->value,
                'target_id' => $targetType->requiresTargetId() ? $targetId : null,
                'created_by' => $request->user()->id,
                'priority' => $priority,
                'is_pinned' => false,
                'is_published' => $isPublished,
                'published_at' => $isPublished ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($errors)) {
            return redirect()->route('admin.publication.announcements.index')->with('import_result', [
                'success' => false, 'created' => 0,
                'rejected' => count($errors), 'errors' => $errors,
            ]);
        }

        Announcement::insert($valid);

        return redirect()->route('admin.publication.announcements.index')
            ->with('success', count($valid).' pengumuman berhasil diimpor.')
            ->with('import_result', [
                'success' => true, 'created' => count($valid), 'rejected' => 0, 'errors' => [],
            ]);
    }

    private function targetExists(AnnouncementTargetType $type, int $id): bool
    {
        return match ($type) {
            AnnouncementTargetType::FACULTY => Faculty::where('id', $id)->exists(),
            AnnouncementTargetType::STUDY_PROGRAM => StudyProgram::where('id', $id)->exists(),
            AnnouncementTargetType::COURSE_OFFERING => CourseOffering::where('id', $id)->exists(),
            AnnouncementTargetType::LECTURER => LecturerProfile::where('id', $id)->exists(),
            AnnouncementTargetType::STUDENT => StudentProfile::where('id', $id)->exists(),
            AnnouncementTargetType::GLOBAL => true,
        };
    }

    private function resolveTargetLabel(AnnouncementTargetType|string|null $type, ?int $id): ?string
    {
        if (! $type instanceof AnnouncementTargetType) {
            try {
                $type = AnnouncementTargetType::from((string) $type);
            } catch (\ValueError) {
                return null;
            }
        }

        if ($type === AnnouncementTargetType::GLOBAL || ! $id) {
            return null;
        }

        return match ($type) {
            AnnouncementTargetType::FACULTY => Faculty::where('id', $id)->value('name'),
            AnnouncementTargetType::STUDY_PROGRAM => $this->studyProgramLabel($id),
            AnnouncementTargetType::COURSE_OFFERING => $this->courseOfferingLabel($id),
            AnnouncementTargetType::LECTURER => LecturerProfile::with('user:id,first_name,last_name')->find($id)?->user?->name,
            AnnouncementTargetType::STUDENT => $this->studentLabel($id),
            default => null,
        };
    }

    private function studyProgramLabel(int $id): ?string
    {
        $program = StudyProgram::with('faculty:id,name')->find($id);

        if (! $program) {
            return null;
        }

        return $program->name.($program->faculty ? ' ('.$program->faculty->name.')' : '');
    }

    private function courseOfferingLabel(int $id): ?string
    {
        $offering = CourseOffering::with(['course:id,code,name', 'academicYear:id,name'])->find($id);

        if (! $offering) {
            return null;
        }

        return ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-')
            .($offering->academicYear ? ' ('.$offering->academicYear->name.')' : '');
    }

    private function studentLabel(int $id): ?string
    {
        $profile = StudentProfile::with('user:id,first_name,last_name')->find($id);

        if (! $profile || ! $profile->user) {
            return null;
        }

        return $profile->user->name.($profile->nim ? ' ('.$profile->nim.')' : '');
    }

    private function deleteAttachment(Announcement $announcement): void
    {
        if ($announcement->attachment_path && Storage::disk('public')->exists($announcement->attachment_path)) {
            Storage::disk('public')->delete($announcement->attachment_path);
        }
    }

    /**
     * URL relatif ke file public storage — kebal beda APP_URL antar environment.
     */
    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.$path : null;
    }

    private function parseImportBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y'], true);
    }
}
