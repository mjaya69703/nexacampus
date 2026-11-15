<?php

namespace App\Http\Controllers\Admin\StudentAffairs;

use App\Enums\PointEntryCategory;
use App\Http\Controllers\Controller;
use App\Models\StudentAffairs\StudentPointEntry;
use App\Models\User;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use App\Support\StudentAffairs\StudentPointService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Poin kemahasiswaan — papan peringkat dan buku besar poin.
 *
 * Poin diberikan manual oleh admin atau otomatis saat kehadiran event
 * ditandai (lihat StudentEventController@attendance). Halaman ini tidak
 * menyediakan edit bebas: koreksi dilakukan dengan menambah entri baru
 * atau menghapus entri yang salah.
 */
class StudentPointController extends Controller
{
    public function index(Request $request, StudentPointService $points): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:30',
            'sort' => 'nullable|in:awarded_at,points,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = StudentPointEntry::query()
            ->with([
                'user:id,first_name,last_name,username',
                'user.studentProfile:id,user_id,nim,study_program_id',
                'user.studentProfile.studyProgram:id,name',
                'awarder:id,first_name,last_name',
            ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->whereHas('user', function ($sub) use ($q) {
                $sub->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhereHas('studentProfile', fn ($profile) => $profile->where('nim', 'like', "%{$q}%"));
            });
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category', $validated['category']);
        }

        $sort = $validated['sort'] ?? 'awarded_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction)->orderByDesc('id');

        $perPage = (int) ($validated['perPage'] ?? 10);
        $entries = $query->paginate($perPage)->withQueryString();

        $leaderboard = $points->leaderboard(limit: 20)
            ->map(fn (array $row) => [
                'rank' => $row['rank'],
                'userId' => $row['user']->id,
                'name' => $row['user']->name,
                'nim' => $row['user']->studentProfile?->nim,
                'program' => $row['user']->studentProfile?->studyProgram?->name,
                'total' => $row['total'],
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/StudentAffairs/Points/Index', [
            'shell' => ShellProps::make($request->user(), 'Kemahasiswaan', 'Poin & Leaderboard'),
            'can' => [
                'create' => ActivePermission::check('student-point.create'),
                'delete' => ActivePermission::check('student-point.delete'),
            ],
            'stats' => [
                'totalPoints' => (int) StudentPointEntry::query()->sum('points'),
                'participants' => StudentPointEntry::query()->distinct('user_id')->count('user_id'),
                'entries' => StudentPointEntry::count(),
                'thisMonth' => (int) StudentPointEntry::query()
                    ->where('awarded_at', '>=', now()->startOfMonth()->toDateString())
                    ->sum('points'),
            ],
            'leaderboard' => $leaderboard,
            'data' => [
                'rows' => collect($entries->items())->values()->map(fn (StudentPointEntry $entry, int $i) => [
                    'id' => $entry->id,
                    'no' => ($entries->firstItem() ?? 0) + $i,
                    'student' => $entry->user?->name ?? '-',
                    'nim' => $entry->user?->studentProfile?->nim,
                    'program' => $entry->user?->studentProfile?->studyProgram?->name,
                    'points' => (int) $entry->points,
                    'category' => $entry->category->value,
                    'categoryLabel' => $entry->category->label(),
                    'categoryTone' => $entry->category->tone(),
                    'description' => $entry->description,
                    'awardedAt' => $entry->awarded_at?->format('d M Y'),
                    'awardedBy' => $entry->awarder?->name,
                    'deleteUrl' => route('admin.student-affairs.points.destroy', $entry->id),
                ])->all(),
                'currentPage' => $entries->currentPage(),
                'lastPage' => $entries->lastPage(),
                'perPage' => $entries->perPage(),
                'total' => $entries->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'category' => $validated['category'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'categories' => PointEntryCategory::options(),
            ],
            'urls' => [
                'index' => route('admin.student-affairs.points.index'),
                'store' => route('admin.student-affairs.points.store'),
                'bulkDestroy' => route('admin.student-affairs.points.bulk-destroy'),
                'searchStudents' => route('admin.student-affairs.points.search-students'),
            ],
        ]);
    }

    public function store(Request $request, StudentPointService $points)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'points' => ['required', 'integer', 'min:-1000', 'max:1000', 'not_in:0'],
            'category' => ['required', Rule::in(array_keys(PointEntryCategory::options()))],
            'description' => ['required', 'string', 'max:255'],
            'awarded_at' => ['nullable', 'date'],
        ]);

        $student = User::query()->whereKey($validated['user_id'])->whereHas('studentProfile')->first();

        if (! $student) {
            return back()->withErrors(['user_id' => 'Mahasiswa tidak ditemukan.'])->withInput();
        }

        $points->award(
            $student,
            (int) $validated['points'],
            PointEntryCategory::from($validated['category']),
            $validated['description'],
            null,
            $request->user(),
            $validated['awarded_at'] ?? null,
        );

        return back()->with('success', 'Poin berhasil diberikan kepada '.$student->name.'.');
    }

    public function destroy(Request $request, int $id)
    {
        $entry = StudentPointEntry::findOrFail($id);
        $entry->update(['deleted_by' => $request->user()->id]);
        $entry->delete();

        return back()->with('success', 'Entri poin berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:student_point_entries,id',
        ]);

        $entries = StudentPointEntry::whereIn('id', $validated['ids'])->get();

        foreach ($entries as $entry) {
            $entry->update(['deleted_by' => $request->user()->id]);
            $entry->delete();
        }

        return back()->with('success', $entries->count().' entri poin berhasil dihapus.');
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
}
