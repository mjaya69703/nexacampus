<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\CommentLike;
use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialBookmark;
use App\Models\Academic\CourseMaterialComment;
use App\Models\Academic\CourseMaterialFile;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\MaterialLike;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyPlanDetail;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Materi perkuliahan dan ruang belajar mahasiswa.
 *
 * Koreksi dari Blade lama:
 * - Enrollment ditentukan dari detail KRS aktif (Draft/Taken) pada rencana
 *   studi yang masih berlaku, bukan dari sembarang detail termasuk yang
 *   berstatus Dropped/Cancelled.
 * - Filter daftar kursus ikut menyaring program studi mahasiswa sehingga
 *   kelas dari prodi lain tidak pernah muncul.
 * - Statistik halaman dihitung dari seluruh materi yang boleh diakses
 *   sebelum filter pencarian/kelas diterapkan, sehingga angka tidak berubah
 *   hanya karena mahasiswa mengetik kata kunci.
 * - Bookmark dan like memverifikasi akses materi agar ID materi milik
 *   mahasiswa lain tidak bisa diubah hanya dengan menebak ID.
 * - Komentar diskusi (tambah, balas, edit, hapus, like) dipindahkan dari
 *   komponen Livewire ke endpoint Inertia dengan validasi kepemilikan.
 * - `edited_at` kini benar-benar tersimpan (sebelumnya tidak masuk fillable
 *   sehingga label "diedit" selalu tampil tanpa waktu edit).
 */
class StudentLearningController extends Controller
{
    public function materials(Request $request): Response
    {
        return $this->renderMaterials($request, null);
    }

    public function offeringMaterials(Request $request, CourseOffering $offering): Response
    {
        $profile = $this->studentProfile($request);

        abort_unless(
            $profile !== null && $this->enrolledOfferingIds($profile)->contains((int) $offering->id),
            403,
            'Anda tidak terdaftar pada kelas ini.',
        );

        return $this->renderMaterials($request, $offering->id);
    }

    public function show(Request $request, CourseMaterial $material): Response
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);

        abort_if($material->is_published !== true, 404, 'Materi tidak ditemukan');
        abort_unless($this->canAccess($profile, $material), 403, 'Anda tidak memiliki akses ke materi ini');

        $material->load(['files', 'uploadedBy', 'courseOffering.course']);

        $bookmarked = $profile
            ? CourseMaterialBookmark::query()
                ->where('course_material_id', $material->id)
                ->where('student_profile_id', $profile->id)
                ->exists()
            : false;

        $liked = MaterialLike::query()
            ->where('course_material_id', $material->id)
            ->where('user_id', $user->id)
            ->exists();

        $limit = $this->commentLimit($request);

        $baseComments = CourseMaterialComment::query()
            ->where('course_material_id', $material->id)
            ->whereNull('parent_id')
            ->where('is_deleted', false);

        $totalComments = (clone $baseComments)->count();

        $comments = $baseComments
            ->with([
                'user.roles',
                'likes' => fn ($query) => $query->where('user_id', $user->id),
                'replies.user.roles',
                'replies.likes' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return Inertia::render('Student/Learning/Show', [
            'shell' => ShellProps::make($user, 'Academic', 'Learning'),
            'material' => [
                'id' => $material->id,
                'offeringId' => $material->course_offering_id,
                'courseCode' => $material->courseOffering?->course?->code ?? '-',
                'courseName' => $material->courseOffering?->course?->name ?? '-',
                'title' => $material->title,
                'description' => $material->description,
                'category' => $material->category,
                'categoryLabel' => $this->categoryLabel($material->category),
                'meetingNumber' => $material->meeting_number,
                'uploadedAt' => $material->created_at?->format('d M Y') ?? '-',
                'uploadedBy' => $material->uploadedBy?->name ?? '-',
                'downloadCount' => $material->files->isNotEmpty()
                    ? (int) $material->files->sum('download_count')
                    : (int) $material->download_count,
                'bookmarked' => $bookmarked,
                'liked' => $liked,
                'likesCount' => (int) ($material->likes_count ?? 0),
            ],
            'attachments' => $this->attachments($material),
            'comments' => [
                'items' => $this->commentRows($comments, $user->id),
                'total' => $totalComments,
                'limit' => $limit,
                'hasMore' => $totalComments > $limit,
            ],
            'urls' => $this->urls(),
        ]);
    }

    public function toggleBookmark(Request $request, CourseMaterial $material): RedirectResponse
    {
        $profile = $this->studentProfile($request);
        $this->assertMaterialAccess($profile, $material);

        $bookmark = CourseMaterialBookmark::query()
            ->where('course_material_id', $material->id)
            ->where('student_profile_id', $profile->id)
            ->first();

        if ($bookmark) {
            $bookmark->delete();

            return back()->with('success', 'Bookmark dihapus.');
        }

        CourseMaterialBookmark::create([
            'course_material_id' => $material->id,
            'student_profile_id' => $profile->id,
        ]);

        return back()->with('success', 'Materi ditambahkan ke bookmark.');
    }

    public function toggleLike(Request $request, CourseMaterial $material): RedirectResponse
    {
        $profile = $this->studentProfile($request);
        $this->assertMaterialAccess($profile, $material);

        $like = MaterialLike::query()
            ->where('course_material_id', $material->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($like) {
            $like->delete();
            CourseMaterial::query()
                ->whereKey($material->id)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');

            return back()->with('success', 'Like dihapus.');
        }

        MaterialLike::create([
            'course_material_id' => $material->id,
            'user_id' => $request->user()->id,
        ]);
        CourseMaterial::query()->whereKey($material->id)->increment('likes_count');

        return back()->with('success', 'Materi disukai!');
    }

    public function storeComment(Request $request, CourseMaterial $material): RedirectResponse
    {
        $profile = $this->studentProfile($request);
        $this->assertMaterialAccess($profile, $material);

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        if ($parentId !== null) {
            $parentExists = CourseMaterialComment::query()
                ->where('course_material_id', $material->id)
                ->whereNull('parent_id')
                ->where('is_deleted', false)
                ->whereKey($parentId)
                ->exists();

            if (! $parentExists) {
                throw ValidationException::withMessages([
                    'content' => 'Komentar yang ingin dibalas tidak ditemukan.',
                ]);
            }
        }

        CourseMaterialComment::create([
            'course_material_id' => $material->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'content' => $validated['content'],
            'likes_count' => 0,
        ]);

        return back()->with('success', $parentId ? 'Balasan berhasil ditambahkan!' : 'Komentar berhasil ditambahkan!');
    }

    public function updateComment(Request $request, CourseMaterialComment $comment): RedirectResponse
    {
        $this->assertOwnComment($request, $comment);

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $comment->update([
            'content' => $validated['content'],
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        return back()->with('success', 'Komentar berhasil diupdate!');
    }

    public function destroyComment(Request $request, CourseMaterialComment $comment): RedirectResponse
    {
        $this->assertOwnComment($request, $comment);

        $ids = $this->commentTreeIds((int) $comment->id);

        CommentLike::query()->whereIn('comment_id', $ids)->delete();
        CourseMaterialComment::query()->whereIn('id', $ids)->delete();

        return back()->with('success', 'Komentar berhasil dihapus!');
    }

    public function toggleCommentLike(Request $request, CourseMaterialComment $comment): RedirectResponse
    {
        $material = $comment->courseMaterial;

        abort_if($material === null, 404, 'Materi tidak ditemukan');

        $this->assertMaterialAccess($this->studentProfile($request), $material);

        $like = CommentLike::query()
            ->where('comment_id', $comment->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($like) {
            $like->delete();
            CourseMaterialComment::query()
                ->whereKey($comment->id)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count');

            return back()->with('success', 'Like komentar dihapus.');
        }

        CommentLike::create([
            'comment_id' => $comment->id,
            'user_id' => $request->user()->id,
        ]);
        CourseMaterialComment::query()->whereKey($comment->id)->increment('likes_count');

        return back()->with('success', 'Komentar disukai!');
    }

    private function renderMaterials(Request $request, ?int $forcedOfferingId): Response
    {
        $user = $request->user();
        $profile = $this->studentProfile($request);

        $validated = $request->validate([
            'offering' => ['nullable', 'integer'],
            'offeringId' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'bookmarked' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $enrolledOfferingIds = $profile ? $this->enrolledOfferingIds($profile) : collect();

        $offeringId = $forcedOfferingId;

        if ($offeringId === null) {
            // `offering` adalah nama parameter baru; `offeringId` dipertahankan
            // agar tautan lama (mis. dari halaman jadwal) tetap berfungsi.
            $requested = (int) ($validated['offering'] ?? $validated['offeringId'] ?? 0);

            if ($requested > 0) {
                // ID kelas yang bukan milik mahasiswa diabaikan agar tautan lama
                // tidak menampilkan kelas prodi lain.
                $offeringId = $enrolledOfferingIds->contains($requested) ? $requested : null;
            }
        }

        $search = trim((string) ($validated['search'] ?? ''));
        $bookmarkedOnly = ($validated['bookmarked'] ?? '0') === '1';

        $courses = $this->courseOptions($profile);

        $baseQuery = CourseMaterial::query()
            ->published()
            ->whereIn('course_offering_id', $enrolledOfferingIds);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'bookmarked' => $profile
                ? (clone $baseQuery)->whereIn(
                    'id',
                    CourseMaterialBookmark::query()
                        ->where('student_profile_id', $profile->id)
                        ->select('course_material_id'),
                )->count()
                : 0,
            'courses' => $courses->count(),
            'withFiles' => (clone $baseQuery)->has('files')->count()
                + (clone $baseQuery)->doesntHave('files')->whereNotNull('file_path')->count(),
        ];

        $query = (clone $baseQuery)
            ->with([
                'files',
                'courseOffering.course',
            ])
            ->withCount('files');

        if ($offeringId !== null) {
            $query->where('course_offering_id', $offeringId);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if ($bookmarkedOnly) {
            $query->whereIn(
                'id',
                CourseMaterialBookmark::query()
                    ->where('student_profile_id', $profile?->id)
                    ->select('course_material_id'),
            );
        }

        $materials = $query->latest('created_at')->get();

        $bookmarkedIds = $profile
            ? CourseMaterialBookmark::query()
                ->where('student_profile_id', $profile->id)
                ->whereIn('course_material_id', $materials->pluck('id'))
                ->pluck('course_material_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $likedIds = MaterialLike::query()
            ->where('user_id', $user->id)
            ->whereIn('course_material_id', $materials->pluck('id'))
            ->pluck('course_material_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Inertia::render('Student/Learning/Materials', [
            'shell' => ShellProps::make($user, 'Academic', 'My Materials'),
            'hasProfile' => $profile !== null,
            'student' => $profile ? [
                'name' => $user->name,
                'nim' => $profile->nim,
                'program' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->studyProgram?->faculty?->name ?? '-',
                'semester' => $profile->current_semester,
            ] : null,
            'courses' => $courses->values()->all(),
            'filters' => [
                'offeringId' => $offeringId,
                'search' => $search,
                'bookmarkedOnly' => $bookmarkedOnly,
                'lockedOffering' => $forcedOfferingId !== null,
            ],
            'stats' => $stats,
            'materials' => $materials
                ->map(fn (CourseMaterial $material) => $this->materialRow(
                    $material,
                    in_array((int) $material->id, $bookmarkedIds, true),
                    in_array((int) $material->id, $likedIds, true),
                ))
                ->values()
                ->all(),
            'urls' => $this->urls(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function materialRow(CourseMaterial $material, bool $bookmarked, bool $liked): array
    {
        $offering = $material->courseOffering;
        $course = $offering?->course;
        $hasLegacyFile = $material->files->isEmpty() && filled($material->file_path);

        return [
            'id' => $material->id,
            'offeringId' => $material->course_offering_id,
            'title' => $material->title,
            'description' => Str::limit(strip_tags((string) $material->description), 180),
            'category' => $material->category,
            'categoryLabel' => $this->categoryLabel($material->category),
            'meetingNumber' => $material->meeting_number,
            'uploadedAt' => $material->created_at?->format('d M Y H:i') ?? '-',
            'courseCode' => $course?->code ?? $offering?->code ?? '-',
            'courseName' => $course?->name ?? $offering?->label ?? '-',
            'classLabel' => $offering?->label,
            'fileCount' => $material->files->count() + ($hasLegacyFile ? 1 : 0),
            'primaryFileType' => $material->files->first()?->file_type ?? $material->file_type,
            'bookmarked' => $bookmarked,
            'liked' => $liked,
            'likesCount' => (int) ($material->likes_count ?? 0),
            'downloadCount' => $material->files->isNotEmpty()
                ? (int) $material->files->sum('download_count')
                : (int) $material->download_count,
            'files' => $material->files
                ->map(fn (CourseMaterialFile $file) => $this->fileRow($material, $file))
                ->values()
                ->all(),
            'legacyFile' => $hasLegacyFile ? [
                'id' => null,
                'fileName' => $material->file_name ?? 'material',
                'fileType' => $material->file_type,
                'sizeLabel' => $this->formatFileSize($material->file_size),
                'downloadUrl' => route('student.course-materials.download', ['id' => $material->id]),
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachments(CourseMaterial $material): array
    {
        $rows = $material->files
            ->map(fn (CourseMaterialFile $file) => $this->fileRow($material, $file))
            ->values()
            ->all();

        if ($rows === [] && filled($material->file_path)) {
            $rows[] = [
                'id' => null,
                'fileName' => $material->file_name ?? 'material',
                'fileType' => $material->file_type,
                'sizeLabel' => $this->formatFileSize($material->file_size),
                'downloadCount' => (int) $material->download_count,
                'isLink' => $material->file_type === 'link',
                'isPdf' => $material->file_type === 'pdf',
                'embedUrl' => $material->file_type === 'link' ? $this->videoEmbedUrl((string) $material->file_path) : null,
                'downloadUrl' => route('student.course-materials.download', ['id' => $material->id]),
                'previewUrl' => $material->file_type === 'pdf'
                    ? route('student.learning.preview', ['material' => $material->id])
                    : null,
                'linkUrl' => $material->file_type === 'link' ? $material->file_path : null,
                'openLinkUrl' => null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function fileRow(CourseMaterial $material, CourseMaterialFile $file): array
    {
        $isLink = $file->file_type === 'link';

        return [
            'id' => $file->id,
            'fileName' => $file->file_name,
            'fileType' => $file->file_type,
            'sizeLabel' => $isLink ? '-' : $this->formatFileSize($file->file_size),
            'downloadCount' => (int) $file->download_count,
            'isLink' => $isLink,
            'isPdf' => $file->file_type === 'pdf',
            'embedUrl' => $isLink ? $this->videoEmbedUrl((string) $file->file_path) : null,
            'downloadUrl' => $isLink
                ? null
                : route('student.course-materials.download', ['id' => $material->id, 'fileId' => $file->id]),
            'previewUrl' => $file->file_type === 'pdf'
                ? route('student.learning.preview', ['material' => $material->id, 'fileId' => $file->id])
                : null,
            'linkUrl' => $isLink ? $file->file_path : null,
            'openLinkUrl' => route('student.learning.link', ['material' => $material->id, 'fileId' => $file->id]),
        ];
    }

    private function studentProfile(Request $request): ?StudentProfile
    {
        return $request->user()?->studentProfile()->with('studyProgram.faculty')->first();
    }

    /**
     * @return Collection<int, int>
     */
    private function enrolledOfferingIds(StudentProfile $profile): Collection
    {
        return StudyPlanDetail::query()
            ->activeEnrollment()
            ->whereHas('studyPlan', fn ($query) => $query->where('student_profile_id', $profile->id))
            ->pluck('course_offering_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function courseOptions(?StudentProfile $profile): Collection
    {
        if (! $profile) {
            return collect();
        }

        return StudyPlanDetail::query()
            ->activeEnrollment()
            ->whereHas('studyPlan', fn ($query) => $query->where('student_profile_id', $profile->id))
            ->with(['courseOffering.course', 'courseOffering.academicYear'])
            ->get()
            ->unique('course_offering_id')
            ->map(function (StudyPlanDetail $detail) {
                $offering = $detail->courseOffering;

                return [
                    'id' => $offering?->id,
                    'courseCode' => $offering?->course?->code ?? $offering?->code ?? '-',
                    'courseName' => $offering?->course?->name ?? $offering?->label ?? '-',
                    'label' => $offering?->label,
                    'academicYear' => $offering?->academicYear?->name,
                ];
            })
            ->filter(fn (array $row) => $row['id'] !== null)
            ->sortBy([['courseCode', 'asc'], ['label', 'asc']])
            ->values();
    }

    private function canAccess(?StudentProfile $profile, CourseMaterial $material): bool
    {
        if (! $profile) {
            return false;
        }

        return $this->enrolledOfferingIds($profile)->contains((int) $material->course_offering_id);
    }

    private function assertMaterialAccess(?StudentProfile $profile, CourseMaterial $material): void
    {
        abort_if($material->is_published !== true, 404, 'Materi tidak ditemukan');
        abort_unless($this->canAccess($profile, $material), 403, 'Anda tidak memiliki akses ke materi ini');
    }

    private function assertOwnComment(Request $request, CourseMaterialComment $comment): void
    {
        abort_if((int) $comment->user_id !== (int) $request->user()->id, 403, 'Anda tidak memiliki izin untuk mengubah komentar ini');

        $material = $comment->courseMaterial;

        abort_if($material === null, 404, 'Materi tidak ditemukan');

        $this->assertMaterialAccess($this->studentProfile($request), $material);
    }

    /**
     * @return array<int, int>
     */
    private function commentTreeIds(int $rootId): array
    {
        $allIds = [$rootId];
        $queue = [$rootId];

        while ($queue !== []) {
            $childIds = CourseMaterialComment::query()
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($childIds === []) {
                break;
            }

            $allIds = array_merge($allIds, $childIds);
            $queue = $childIds;
        }

        return array_values(array_unique($allIds));
    }

    /**
     * @param  Collection<int, CourseMaterialComment>  $comments
     * @return array<int, array<string, mixed>>
     */
    private function commentRows(Collection $comments, int $userId): array
    {
        return $comments
            ->map(fn (CourseMaterialComment $comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'userName' => $comment->user?->name ?? 'Unknown',
                'userRole' => $comment->user?->roles->pluck('name')->first() ?? 'user',
                'createdAt' => $comment->created_at?->diffForHumans(),
                'editedAt' => $comment->edited_at?->diffForHumans(),
                'isEdited' => (bool) $comment->is_edited,
                'likesCount' => (int) $comment->likes_count,
                'isLiked' => $comment->likes->isNotEmpty(),
                'canEdit' => (int) $comment->user_id === $userId,
                'replies' => $comment->replies
                    ->map(fn (CourseMaterialComment $reply) => [
                        'id' => $reply->id,
                        'content' => $reply->content,
                        'userName' => $reply->user?->name ?? 'Unknown',
                        'userRole' => $reply->user?->roles->pluck('name')->first() ?? 'user',
                        'createdAt' => $reply->created_at?->diffForHumans(),
                        'editedAt' => $reply->edited_at?->diffForHumans(),
                        'isEdited' => (bool) $reply->is_edited,
                        'likesCount' => (int) $reply->likes_count,
                        'isLiked' => $reply->likes->isNotEmpty(),
                        'canEdit' => (int) $reply->user_id === $userId,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function commentLimit(Request $request): int
    {
        $limit = (int) $request->query('comments', '10');

        if ($limit < 10) {
            $limit = 10;
        }

        if ($limit > 100) {
            $limit = 100;
        }

        return (int) (ceil($limit / 10) * 10);
    }

    private function categoryLabel(?string $category): string
    {
        return match ($category) {
            'syllabus' => 'Silabus',
            'lecture_notes' => 'Catatan Kuliah',
            'assignments' => 'Tugas',
            'references' => 'Referensi',
            default => $category ? Str::title(str_replace('_', ' ', $category)) : 'Umum',
        };
    }

    private function formatFileSize(?int $bytes): string
    {
        if (! $bytes) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 2).' '.$units[$unit];
    }

    private function videoEmbedUrl(?string $url): ?string
    {
        $normalized = trim((string) $url);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('~(youtu\.be/|youtube\.com/watch\?v=|youtube\.com/embed/)([A-Za-z0-9_-]{6,})~', $normalized, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[2];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'index' => route('student.course-materials.index'),
            'offering' => route('student.course-materials.offering', ['offering' => '__ID__']),
            'download' => route('student.course-materials.download', ['id' => '__ID__']),
            'show' => route('student.learning.show', ['material' => '__ID__']),
            'bookmark' => route('student.learning.bookmark', ['material' => '__ID__']),
            'like' => route('student.learning.like', ['material' => '__ID__']),
            'commentStore' => route('student.learning.comments.store', ['material' => '__ID__']),
            'commentUpdate' => route('student.learning.comments.update', ['comment' => '__ID__']),
            'commentDelete' => route('student.learning.comments.destroy', ['comment' => '__ID__']),
            'commentLike' => route('student.learning.comments.like', ['comment' => '__ID__']),
        ];
    }
}
