<?php

namespace App\Http\Controllers\Student;

use App\Enums\AnnouncementPriority;
use App\Http\Controllers\Controller;
use App\Models\Publication\Announcement;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengumuman portal mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - Query pengumuman kelas hanya memakai pendaftaran yang masih aktif
 *   (Draft/Taken pada KRS Draft/Submitted/Approved). Sebelumnya kelas yang
 *   sudah dibatalkan tetap menerima pengumuman.
 * - Unduh lampiran memakai scope `queryForStudent()`; sebelumnya mahasiswa
 *   bisa mengunduh lampiran pengumuman milik kelas lain hanya dengan id.
 */
class StudentAnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', Rule::in(array_keys(AnnouncementPriority::options()))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $priority = (string) ($filters['priority'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');
        $perPage = (int) ($filters['per_page'] ?? 10);

        $query = Announcement::queryForStudent($user)->with('creator');

        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $nested->where('title', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        if ($priority !== '') {
            $query->where('priority', $priority);
        }

        if ($from !== '') {
            $query->whereDate('published_at', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('published_at', '<=', $to);
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        $rows = collect($paginator->items())->map(function (Announcement $announcement) use ($user) {
            return [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'excerpt' => str($announcement->content ?? '')
                    ->stripTags()
                    ->squish()
                    ->limit(180)
                    ->toString(),
                'priority' => $announcement->priority?->value,
                'priorityLabel' => $announcement->priority?->label() ?? '-',
                'priorityTone' => match ($announcement->priority) {
                    AnnouncementPriority::URGENT => 'red',
                    AnnouncementPriority::IMPORTANT => 'amber',
                    default => 'gray',
                },
                'isPinned' => (bool) $announcement->is_pinned,
                'creator' => $announcement->creator?->name ?? '-',
                'publishedAt' => $announcement->published_at?->format('d M Y H:i') ?? '-',
                'publishedHuman' => $announcement->published_at?->diffForHumans(),
                'hasAttachment' => filled($announcement->attachment_name),
                'url' => route('student.announcements.show', ['id' => $announcement->id]),
                'isUnread' => false,
            ];
        })->values()->all();

        // Status baca dihitung lewat satu query agregat supaya tidak N+1.
        $readIds = Announcement::query()
            ->whereIn('id', collect($rows)->pluck('id'))
            ->whereHas('reads', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rows as $index => $row) {
            $rows[$index]['isUnread'] = ! in_array($row['id'], $readIds, true);
        }

        return Inertia::render('Student/Announcements/Index', [
            'shell' => ShellProps::make($user, 'Publikasi', 'Pengumuman'),
            'hasProfile' => $user->studentProfile()->exists(),
            'announcements' => [
                'rows' => $rows,
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'q' => $search,
                'priority' => $priority,
                'from' => $from,
                'to' => $to,
                'perPage' => $perPage,
            ],
            'priorities' => collect(AnnouncementPriority::cases())
                ->map(fn (AnnouncementPriority $case) => ['value' => $case->value, 'label' => $case->label()])
                ->values()
                ->all(),
            'stats' => [
                'unread' => Announcement::unreadCountForStudent($user),
                'total' => $paginator->total(),
            ],
            'urls' => [
                'index' => route('student.announcements.index'),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();

        $announcement = Announcement::queryForStudent($user)
            ->with('creator')
            ->whereKey($id)
            ->first();

        abort_unless($announcement !== null, 404, 'Pengumuman tidak ditemukan atau tidak ditujukan untukmu.');

        $announcement->markAsReadBy($user->id);

        return Inertia::render('Student/Announcements/Show', [
            'shell' => ShellProps::make($user, 'Publikasi', 'Detail Pengumuman'),
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => (string) ($announcement->content ?? ''),
                'priority' => $announcement->priority?->value,
                'priorityLabel' => $announcement->priority?->label() ?? 'Normal',
                'priorityTone' => match ($announcement->priority) {
                    AnnouncementPriority::URGENT => 'red',
                    AnnouncementPriority::IMPORTANT => 'amber',
                    default => 'gray',
                },
                'targetLabel' => $announcement->target_type?->label() ?? '-',
                'isPinned' => (bool) $announcement->is_pinned,
                'creator' => $announcement->creator?->name ?? '-',
                'publishedAt' => $announcement->published_at?->format('d M Y, H:i') ?? '-',
                'publishedHuman' => $announcement->published_at?->diffForHumans(),
                'attachment' => $announcement->attachment_path === null ? null : [
                    'name' => $announcement->attachment_name ?? 'attachment',
                    'type' => strtoupper((string) ($announcement->attachment_type ?? 'file')),
                    'size' => $announcement->formatted_attachment_size ?? '-',
                    'url' => route('student.announcements.attachment', ['id' => $announcement->id]),
                ],
            ],
            'urls' => [
                'index' => route('student.announcements.index'),
            ],
        ]);
    }

    public function downloadAttachment(Request $request, int $id): StreamedResponse|RedirectResponse
    {
        $announcement = Announcement::queryForStudent($request->user())
            ->whereKey($id)
            ->first();

        abort_unless($announcement !== null && $announcement->attachment_path !== null, 404);

        $disk = Storage::disk('public');

        if (! $disk->exists($announcement->attachment_path)) {
            return back()->with('error', 'File lampiran tidak ditemukan di server.');
        }

        return $disk->download(
            $announcement->attachment_path,
            $announcement->attachment_name ?? 'attachment'
        );
    }
}
