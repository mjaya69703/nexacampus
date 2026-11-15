<?php

namespace App\Http\Controllers\Home;

use App\Enums\ThesisDocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\Thesis;
use App\Models\Academic\ThesisDocument;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\Inertia\PublicUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Repositori publik skripsi/tugas akhir.
 *
 * Hanya karya berstatus selesai dan telah ditandai `repository_published`
 * yang tampil. Berkas yang dapat diunduh publik dibatasi pada dokumen final.
 */
class ThesisRepositoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'program' => 'nullable|integer|exists:study_programs,id',
            'year' => 'nullable|integer|exists:academic_years,id',
            'type' => ['nullable', Rule::in(array_keys(Thesis::TYPE_LABELS))],
            'sort' => 'nullable|in:latest,title,score',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = Thesis::query()
            ->repositoryPublished()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name',
                'academicYear:id,name',
                'activeSupervisors.lecturerProfile.user:id,first_name,last_name',
            ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('topic', 'like', "%{$q}%")
                ->orWhere('abstract', 'like', "%{$q}%")
                ->orWhere('keywords', 'like', "%{$q}%")
                ->orWhereHas('studentProfile', fn ($student) => $student
                    ->where('nim', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%"))));
        }

        if (filled($validated['program'] ?? null)) {
            $query->whereHas('studentProfile', fn ($student) => $student
                ->where('study_program_id', $validated['program']));
        }

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        $sort = $validated['sort'] ?? 'latest';

        match ($sort) {
            'title' => $query->orderBy('title'),
            'score' => $query->orderByDesc('final_score')->orderByDesc('completed_at'),
            default => $query->orderByDesc('completed_at')->orderByDesc('id'),
        };

        $theses = $query->paginate(9)->withQueryString();

        $repositoryQuery = fn () => Thesis::query()->repositoryPublished();

        $programIds = $repositoryQuery()
            ->join('student_profiles', 'student_profiles.id', '=', 'theses.student_profile_id')
            ->distinct()
            ->pluck('student_profiles.study_program_id')
            ->filter()
            ->values();

        $yearIds = $repositoryQuery()->distinct()->pluck('academic_year_id')->filter()->values();

        return $this->render($request, 'Home/Repository/Index', [
            'stats' => [
                'total' => $repositoryQuery()->count(),
                'programs' => $programIds->count(),
                'years' => $yearIds->count(),
            ],
            'rows' => collect($theses->items())->map(fn (Thesis $thesis) => $this->mapCard($thesis))->values()->all(),
            'page' => [
                'currentPage' => $theses->currentPage(),
                'lastPage' => $theses->lastPage(),
                'perPage' => $theses->perPage(),
                'total' => $theses->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'program' => $validated['program'] ?? '',
                'year' => $validated['year'] ?? '',
                'type' => $validated['type'] ?? '',
                'sort' => $sort,
            ],
            'options' => [
                'programs' => StudyProgram::query()
                    ->whereIn('id', $programIds)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($program) => ['id' => $program->id, 'name' => $program->name])
                    ->all(),
                'years' => AcademicYear::query()
                    ->whereIn('id', $yearIds)
                    ->orderByDesc('start_date')
                    ->get(['id', 'name'])
                    ->map(fn ($year) => ['id' => $year->id, 'name' => $year->name])
                    ->all(),
                'types' => Thesis::TYPE_LABELS,
            ],
        ]);
    }

    public function show(Request $request, Thesis $thesis): Response
    {
        $thesis = Thesis::query()
            ->repositoryPublished()
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name,faculty_id',
                'studentProfile.studyProgram.faculty:id,name',
                'academicYear:id,name',
                'activeSupervisors.lecturerProfile.user:id,first_name,last_name',
                'documents' => fn ($query) => $query->where('category', ThesisDocumentCategory::FINAL->value),
            ])
            ->findOrFail($thesis->id);

        $finalDocument = $thesis->documents->first();

        return $this->render($request, 'Home/Repository/Show', [
            'thesis' => [
                'id' => $thesis->id,
                'title' => $thesis->title,
                'topic' => $thesis->topic,
                'abstract' => $thesis->abstract,
                'keywords' => $thesis->keywords,
                'typeLabel' => $thesis->typeLabel(),
                'year' => $thesis->academicYear?->name,
                'completedAt' => $thesis->completed_at?->translatedFormat('d F Y'),
                'finalScore' => $thesis->final_score !== null ? (float) $thesis->final_score : null,
                'finalGrade' => $thesis->final_grade,
                'plagiarismStatusLabel' => $thesis->plagiarismStatusLabel(),
                'plagiarismStatusTone' => $thesis->plagiarismStatusTone(),
                'plagiarismScore' => $thesis->plagiarism_score !== null ? (float) $thesis->plagiarism_score : null,
                'plagiarismTool' => $thesis->plagiarism_tool,
                'plagiarismCheckedAt' => $thesis->plagiarism_checked_at?->translatedFormat('d F Y'),
                'student' => [
                    'name' => $thesis->studentProfile?->user?->name,
                    'nim' => $thesis->studentProfile?->nim,
                    'program' => $thesis->studentProfile?->studyProgram?->name,
                    'faculty' => $thesis->studentProfile?->studyProgram?->faculty?->name,
                ],
                'supervisors' => $thesis->activeSupervisors
                    ->map(fn ($supervisor) => [
                        'name' => $supervisor->lecturerProfile?->user?->name,
                        'nidn' => $supervisor->lecturerProfile?->nidn,
                        'roleLabel' => $supervisor->roleLabel(),
                    ])
                    ->values()
                    ->all(),
                'finalDocument' => $finalDocument ? [
                    'fileName' => $finalDocument->file_name,
                    'fileSize' => $finalDocument->formattedSize(),
                    'downloadUrl' => route('root.repository.documents.download', $finalDocument),
                ] : null,
            ],
            'backUrl' => route('root.repository.index'),
        ]);
    }

    public function download(ThesisDocument $document): StreamedResponse
    {
        $thesis = $document->thesis;

        abort_unless($thesis && $thesis->isRepositoryPublished(), 404);
        abort_unless($document->category === ThesisDocumentCategory::FINAL->value, 404);
        abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download(
            $document->file_path,
            $document->file_name ?: basename($document->file_path),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCard(Thesis $thesis): array
    {
        return [
            'id' => $thesis->id,
            'title' => $thesis->title,
            'abstract' => Str::limit(strip_tags((string) ($thesis->abstract ?? $thesis->topic)), 220),
            'author' => $thesis->studentProfile?->user?->name ?? '-',
            'nim' => $thesis->studentProfile?->nim,
            'program' => $thesis->studentProfile?->studyProgram?->name,
            'year' => $thesis->academicYear?->name,
            'typeLabel' => $thesis->typeLabel(),
            'finalGrade' => $thesis->final_grade,
            'completedAt' => $thesis->completed_at?->format('d M Y'),
            'plagiarismStatusLabel' => $thesis->plagiarismStatusLabel(),
            'plagiarismStatusTone' => $thesis->plagiarismStatusTone(),
            'plagiarismScore' => $thesis->plagiarism_score !== null ? (float) $thesis->plagiarism_score : null,
            'supervisors' => $thesis->activeSupervisors
                ->map(fn ($supervisor) => $supervisor->lecturerProfile?->user?->name)
                ->filter()
                ->implode(', '),
            'detailUrl' => route('root.repository.show', $thesis),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(Request $request, string $component, array $props = []): Response
    {
        $user = $request->user();
        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute) ? route($dashboardRoute) : route('auth.select-role');

        return Inertia::render($component, array_merge([
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
                'description' => System::value('app_description') ?? 'Sistem informasi akademik perguruan tinggi terpadu.',
            ],
            'user' => PublicUser::make($user, $dashboardUrl),
            'links' => [
                'login' => route('auth.signin-index'),
                'admission' => route('root.admission.apply'),
                'admissionStatus' => route('root.admission.status'),
                'tuition' => route('root.admission.tuition'),
                'requirements' => route('root.admission.requirements'),
                'faq' => route('root.faq'),
                'contact' => route('root.kontak'),
                'announcements' => route('root.publication.announcements'),
            ],
        ], $props));
    }
}
