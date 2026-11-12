<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\ThesisDefenseStatus;
use App\Enums\ThesisDocumentCategory;
use App\Enums\ThesisMilestoneStatus;
use App\Enums\ThesisStatus;
use App\Http\Controllers\Controller;
use App\Models\Academic\Thesis;
use App\Models\Academic\ThesisDefense;
use App\Models\Academic\ThesisDocument;
use App\Models\Academic\ThesisMilestone;
use App\Support\Academic\ThesisService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThesisSupervisionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $lecturerProfile = $user->lecturerProfile;

        abort_unless($lecturerProfile, 403, 'Profil dosen tidak ditemukan.');

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:30',
            'sort' => 'nullable|in:id,title,status,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = Thesis::query()
            ->where(function ($scope) use ($lecturerProfile) {
                $scope->whereHas('supervisors', fn ($supervisor) => $supervisor
                    ->where('lecturer_profile_id', $lecturerProfile->id)
                    ->where('is_active', true))
                    ->orWhereHas('defenses.examiners', fn ($examiner) => $examiner
                        ->where('lecturer_profile_id', $lecturerProfile->id));
            })
            ->with([
                'studentProfile.user:id,first_name,last_name',
                'studentProfile.studyProgram:id,name',
                'academicYear:id,name',
                'activeSupervisors.lecturerProfile.user:id,first_name,last_name',
                'resultDefense:id,thesis_id,type,scheduled_at,status,result,score,grade',
            ]);

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$q}%")
                ->orWhere('topic', 'like', "%{$q}%")
                ->orWhereHas('studentProfile', fn ($student) => $student
                    ->where('nim', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%"))));
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $theses = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Lecturer/Theses/Index', [
            'shell' => ShellProps::make($user, 'Bimbingan', 'Bimbingan Skripsi / Tugas Akhir'),
            'hasProfile' => true,
            'stats' => [
                'total' => (clone $query)->reorder()->count(),
                'active' => (clone $query)->reorder()->whereIn('status', ThesisStatus::activeValues())->count(),
                'defenseReady' => (clone $query)->reorder()->where('status', ThesisStatus::DEFENSE_READY->value)->count(),
                'completed' => (clone $query)->reorder()->where('status', ThesisStatus::COMPLETED->value)->count(),
                'awaitingReview' => ThesisMilestone::query()
                    ->where('status', ThesisMilestoneStatus::SUBMITTED->value)
                    ->whereHas('thesis.supervisors', fn ($supervisor) => $supervisor
                        ->where('lecturer_profile_id', $lecturerProfile->id)
                        ->where('is_active', true))
                    ->count(),
            ],
            'data' => [
                'rows' => collect($theses->items())->values()->map(fn (Thesis $thesis, int $i) => [
                    'id' => $thesis->id,
                    'no' => ($theses->firstItem() ?? 0) + $i,
                    'student' => trim(($thesis->studentProfile?->user?->first_name ?? '').' '.($thesis->studentProfile?->user?->last_name ?? '')) ?: '-',
                    'nim' => $thesis->studentProfile?->nim,
                    'program' => $thesis->studentProfile?->studyProgram?->name,
                    'title' => $thesis->title,
                    'typeLabel' => $thesis->typeLabel(),
                    'status' => $thesis->status,
                    'statusLabel' => $thesis->statusLabel(),
                    'statusTone' => $thesis->statusTone(),
                    'supervisors' => $thesis->activeSupervisors
                        ->map(fn ($supervisor) => trim(($supervisor->lecturerProfile?->user?->first_name ?? '').' '.($supervisor->lecturerProfile?->user?->last_name ?? '')))
                        ->filter()
                        ->implode(', '),
                    'year' => $thesis->academicYear?->name,
                    'defenseAt' => $thesis->resultDefense?->scheduled_at?->format('d M Y H:i'),
                    'finalGrade' => $thesis->final_grade,
                    'createdAt' => $thesis->created_at?->format('d M Y'),
                    'showUrl' => route('lecturer.theses.show', $thesis),
                ])->all(),
                'currentPage' => $theses->currentPage(),
                'lastPage' => $theses->lastPage(),
                'perPage' => $theses->perPage(),
                'total' => $theses->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => ThesisStatus::options(),
            ],
            'urls' => [
                'index' => route('lecturer.theses.index'),
            ],
        ]);
    }

    public function show(Request $request, Thesis $thesis): Response
    {
        $lecturerProfile = $request->user()->lecturerProfile;

        abort_unless($lecturerProfile && $this->canAccess($thesis, $lecturerProfile->id), 404);

        $thesis->load([
            'studentProfile.user:id,first_name,last_name,email',
            'studentProfile.studyProgram:id,name',
            'academicYear:id,name',
            'approvedBy:id,first_name,last_name',
            'supervisors.lecturerProfile.user:id,first_name,last_name',
            'milestones.reviewer:id,first_name,last_name',
            'documents.uploader:id,first_name,last_name',
            'defenses.examiners.lecturerProfile.user:id,first_name,last_name',
        ]);

        return Inertia::render('Lecturer/Theses/Show', [
            'shell' => ShellProps::make($request->user(), 'Bimbingan', 'Detail Bimbingan Skripsi'),
            'thesis' => $this->mapThesis($thesis, $lecturerProfile->id),
            'urls' => [
                'index' => route('lecturer.theses.index'),
            ],
        ]);
    }

    public function reviewMilestone(Request $request, ThesisMilestone $milestone, ThesisService $service)
    {
        $lecturerProfile = $request->user()->lecturerProfile;
        abort_unless($lecturerProfile, 403);

        $thesis = $milestone->thesis()->firstOrFail();
        abort_unless($thesis->hasSupervisor($lecturerProfile->id), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ThesisMilestoneStatus::APPROVED->value,
                ThesisMilestoneStatus::REVISION->value,
            ])],
            'feedback' => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === ThesisMilestoneStatus::REVISION->value && blank($validated['feedback'] ?? null)) {
            return back()->withErrors(['feedback' => 'Catatan revisi wajib diisi.']);
        }

        $service->reviewMilestone($milestone, $request->user(), $validated['status'], $validated['feedback'] ?? null);

        return back()->with('success', 'Review milestone berhasil disimpan.');
    }

    public function scoreDefense(Request $request, ThesisDefense $defense)
    {
        $lecturerProfile = $request->user()->lecturerProfile;
        abort_unless($lecturerProfile, 403);

        $examiner = $defense->examiners()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->firstOrFail();

        $thesis = $defense->thesis()->firstOrFail();

        if (in_array($thesis->status, [ThesisStatus::COMPLETED->value, ThesisStatus::CANCELLED->value], true)) {
            return back()->with('error', 'Penilaian tidak dapat diubah karena skripsi sudah selesai/dibatalkan.');
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $examiner->update([
            'score' => $validated['score'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Nilai sidang berhasil disimpan.');
    }

    private function canAccess(Thesis $thesis, int $lecturerProfileId): bool
    {
        return $thesis->hasSupervisor($lecturerProfileId) || $thesis->hasExaminer($lecturerProfileId);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapThesis(Thesis $thesis, int $lecturerProfileId): array
    {
        $proposalDocument = $thesis->documents
            ->firstWhere('category', ThesisDocumentCategory::PROPOSAL->value);

        return [
            'id' => $thesis->id,
            'title' => $thesis->title,
            'topic' => $thesis->topic,
            'abstract' => $thesis->abstract,
            'keywords' => $thesis->keywords,
            'type' => $thesis->type,
            'typeLabel' => $thesis->typeLabel(),
            'status' => $thesis->status,
            'statusLabel' => $thesis->statusLabel(),
            'statusTone' => $thesis->statusTone(),
            'year' => $thesis->academicYear?->name,
            'createdAt' => $thesis->created_at?->format('d M Y H:i'),
            'proposalSubmittedAt' => $thesis->proposal_submitted_at?->format('d M Y H:i'),
            'approvedAt' => $thesis->approved_at?->format('d M Y H:i'),
            'approvedBy' => $thesis->approvedBy?->name,
            'finalScore' => $thesis->final_score !== null ? (float) $thesis->final_score : null,
            'finalGrade' => $thesis->final_grade,
            'completedAt' => $thesis->completed_at?->format('d M Y H:i'),
            'repositoryPublished' => (bool) $thesis->repository_published,
            'notes' => $thesis->notes,
            'student' => [
                'name' => $thesis->studentProfile?->user?->name,
                'nim' => $thesis->studentProfile?->nim,
                'program' => $thesis->studentProfile?->studyProgram?->name,
                'entryYear' => $thesis->studentProfile?->entry_year,
                'semester' => $thesis->studentProfile?->current_semester,
                'email' => $thesis->studentProfile?->user?->email,
            ],
            'proposal' => $proposalDocument ? [
                'fileName' => $proposalDocument->file_name,
                'downloadUrl' => route('lecturer.theses.documents.download', $proposalDocument),
            ] : null,
            'supervisors' => $thesis->supervisors->where('is_active', true)->map(fn ($supervisor) => [
                'id' => $supervisor->id,
                'name' => $supervisor->lecturerProfile?->user?->name,
                'nidn' => $supervisor->lecturerProfile?->nidn,
                'roleLabel' => $supervisor->roleLabel(),
                'assignedAt' => $supervisor->assigned_at?->format('d M Y'),
                'isMe' => (int) $supervisor->lecturer_profile_id === $lecturerProfileId,
            ])->values()->all(),
            'milestones' => $thesis->milestones->map(fn (ThesisMilestone $milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'sequence' => $milestone->sequence,
                'status' => $milestone->status,
                'statusLabel' => $milestone->statusLabel(),
                'statusTone' => $milestone->statusTone(),
                'dueDate' => $milestone->due_date?->format('d M Y'),
                'submittedAt' => $milestone->submitted_at?->format('d M Y H:i'),
                'reviewedAt' => $milestone->reviewed_at?->format('d M Y H:i'),
                'reviewedBy' => $milestone->reviewer?->name,
                'feedback' => $milestone->feedback,
                'canReview' => $milestone->status === ThesisMilestoneStatus::SUBMITTED->value
                    && $thesis->hasSupervisor($lecturerProfileId),
                'reviewUrl' => route('lecturer.theses.milestones.review', $milestone),
            ])->values()->all(),
            'documents' => $thesis->documents->map(fn (ThesisDocument $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->category,
                'categoryLabel' => $document->categoryLabel(),
                'fileName' => $document->file_name,
                'fileSize' => $document->formattedSize(),
                'version' => $document->version,
                'uploadedBy' => $document->uploader?->name,
                'createdAt' => $document->created_at?->format('d M Y H:i'),
                'downloadUrl' => route('lecturer.theses.documents.download', $document),
            ])->values()->all(),
            'defenses' => $thesis->defenses->map(function (ThesisDefense $defense) use ($lecturerProfileId, $thesis) {
                $myExaminer = $defense->examiners->firstWhere('lecturer_profile_id', $lecturerProfileId);

                return [
                    'id' => $defense->id,
                    'type' => $defense->type,
                    'typeLabel' => $defense->typeLabel(),
                    'scheduledAt' => $defense->scheduled_at?->format('d M Y H:i'),
                    'durationMinutes' => $defense->duration_minutes,
                    'room' => $defense->room,
                    'meetingLink' => $defense->meeting_link,
                    'status' => $defense->status,
                    'statusLabel' => $defense->statusLabel(),
                    'statusTone' => $defense->statusTone(),
                    'result' => $defense->result,
                    'resultLabel' => $defense->resultLabel(),
                    'score' => $defense->score !== null ? (float) $defense->score : null,
                    'grade' => $defense->grade,
                    'notes' => $defense->notes,
                    'completedAt' => $defense->completed_at?->format('d M Y H:i'),
                    'myExaminer' => $myExaminer ? [
                        'id' => $myExaminer->id,
                        'role' => $myExaminer->role,
                        'roleLabel' => $myExaminer->roleLabel(),
                        'score' => $myExaminer->score !== null ? (float) $myExaminer->score : null,
                        'notes' => $myExaminer->notes,
                        'scoreUrl' => route('lecturer.theses.defenses.score', $defense),
                        'canScore' => in_array($defense->status, [
                            ThesisDefenseStatus::SCHEDULED->value,
                            ThesisDefenseStatus::COMPLETED->value,
                        ], true) && ! in_array($thesis->status, [ThesisStatus::COMPLETED->value, ThesisStatus::CANCELLED->value], true),
                    ] : null,
                    'examiners' => $defense->examiners->map(fn ($examiner) => [
                        'name' => $examiner->lecturerProfile?->user?->name,
                        'roleLabel' => $examiner->roleLabel(),
                        'score' => $examiner->score !== null ? (float) $examiner->score : null,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
