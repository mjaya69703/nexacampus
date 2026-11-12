<?php

namespace App\Http\Controllers\Student;

use App\Enums\ThesisDocumentCategory;
use App\Enums\ThesisMilestoneStatus;
use App\Enums\ThesisStatus;
use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Thesis;
use App\Models\Academic\ThesisDefense;
use App\Models\Academic\ThesisDocument;
use App\Models\Academic\ThesisMilestone;
use App\Models\Academic\ThesisSupervisor;
use App\Support\Academic\ThesisService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentThesisController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->with(['user:id,first_name,last_name', 'studyProgram:id,name'])->first();

        $thesis = $profile
            ? Thesis::query()
                ->where('student_profile_id', $profile->id)
                ->with([
                    'academicYear:id,name',
                    'approvedBy:id,first_name,last_name',
                    'supervisors.lecturerProfile.user:id,first_name,last_name',
                    'milestones.reviewer:id,first_name,last_name',
                    'documents.uploader:id,first_name,last_name',
                    'defenses.examiners.lecturerProfile.user:id,first_name,last_name',
                ])
                ->latest()
                ->first()
            : null;

        return Inertia::render('Student/Thesis/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Skripsi / Tugas Akhir'),
            'hasProfile' => (bool) $profile,
            'student' => $profile ? [
                'name' => $profile->user?->name,
                'nim' => $profile->nim,
                'program' => $profile->studyProgram?->name,
                'semester' => $profile->current_semester,
            ] : null,
            'thesis' => $thesis ? $this->mapThesis($thesis) : null,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                ->map(fn ($year) => ['id' => $year->id, 'name' => $year->name])->all(),
            'types' => Thesis::TYPE_LABELS,
            'documentCategories' => collect(ThesisDocumentCategory::options())
                ->except([ThesisDocumentCategory::PROPOSAL->value])
                ->all(),
            'urls' => [
                'index' => route('student.thesis.index'),
                'store' => route('student.thesis.store'),
                'cancel' => route('student.thesis.cancel'),
                'documentStore' => route('student.thesis.documents.store'),
            ],
        ]);
    }

    public function store(Request $request, ThesisService $service)
    {
        $profile = $request->user()->studentProfile;

        abort_unless($profile, 403, 'Profil mahasiswa tidak ditemukan.');

        $validated = $this->validateProposal($request);

        $thesis = $service->createProposal($profile, $validated, $request->user());

        if ($request->hasFile('proposal_file')) {
            $service->storeProposalFile($thesis, $request->user(), $request->file('proposal_file'));
        }

        return redirect()->route('student.thesis.index')
            ->with('success', 'Pengajuan skripsi berhasil dikirim. Tunggu persetujuan admin/prodi.');
    }

    public function update(Request $request, int $id, ThesisService $service)
    {
        $profile = $request->user()->studentProfile;
        abort_unless($profile, 403);

        $thesis = Thesis::where('student_profile_id', $profile->id)->findOrFail($id);

        if (! in_array($thesis->status, [ThesisStatus::DRAFT->value, ThesisStatus::SUBMITTED->value, ThesisStatus::REJECTED->value], true)) {
            return back()->with('error', 'Pengajuan hanya dapat diubah sebelum disetujui.');
        }

        $validated = $this->validateProposal($request, true);

        $thesis->update([
            'academic_year_id' => $validated['academic_year_id'],
            'type' => $validated['type'],
            'title' => $validated['title'],
            'topic' => $validated['topic'] ?? null,
            'abstract' => $validated['abstract'] ?? null,
            'keywords' => $validated['keywords'] ?? null,
            'status' => ThesisStatus::SUBMITTED->value,
            'proposal_submitted_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
            'updated_by' => $request->user()->id,
        ]);

        if ($request->hasFile('proposal_file')) {
            $service->storeProposalFile($thesis, $request->user(), $request->file('proposal_file'));
        }

        return redirect()->route('student.thesis.index')
            ->with('success', 'Pengajuan skripsi berhasil diperbarui.');
    }

    public function submitMilestone(Request $request, ThesisMilestone $milestone, ThesisService $service)
    {
        $profile = $request->user()->studentProfile;
        abort_unless($profile, 403);

        $thesis = $milestone->thesis()->firstOrFail();
        abort_unless((int) $thesis->student_profile_id === (int) $profile->id, 404);

        if (! in_array($milestone->status, [
            ThesisMilestoneStatus::PENDING->value,
            ThesisMilestoneStatus::IN_PROGRESS->value,
            ThesisMilestoneStatus::REVISION->value,
        ], true)) {
            return back()->with('error', 'Milestone ini sudah dikirim atau disetujui.');
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:10240',
        ]);

        $service->submitMilestone($milestone, $request->user(), $validated['note'] ?? null);

        if ($request->hasFile('file')) {
            $service->storeDocument($thesis, $request->user(), $request->file('file'), [
                'category' => ThesisDocumentCategory::CHAPTER->value,
                'title' => 'Milestone: '.$milestone->title,
                'notes' => $validated['note'] ?? null,
            ]);
        }

        return back()->with('success', 'Milestone berhasil dikirim untuk direview pembimbing.');
    }

    public function storeDocument(Request $request, ThesisService $service)
    {
        $profile = $request->user()->studentProfile;
        abort_unless($profile, 403);

        $thesis = Thesis::where('student_profile_id', $profile->id)->latest()->firstOrFail();

        if (in_array($thesis->status, [ThesisStatus::COMPLETED->value, ThesisStatus::CANCELLED->value], true)) {
            return back()->with('error', 'Dokumen tidak dapat diunggah karena skripsi sudah selesai/dibatalkan.');
        }

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(collect(ThesisDocumentCategory::options())
                ->except([ThesisDocumentCategory::PROPOSAL->value])
                ->all()))],
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:10240',
        ]);

        $service->storeDocument($thesis, $request->user(), $request->file('file'), $validated);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroyDocument(Request $request, ThesisDocument $document, ThesisService $service)
    {
        $profile = $request->user()->studentProfile;
        abort_unless($profile, 403);

        $thesis = $document->thesis;
        abort_unless($thesis && (int) $thesis->student_profile_id === (int) $profile->id, 404);

        if ($thesis->status === ThesisStatus::COMPLETED->value) {
            return back()->with('error', 'Dokumen skripsi yang sudah selesai tidak dapat dihapus.');
        }

        $service->deleteDocument($document);

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }

    public function cancel(Request $request, ThesisService $service)
    {
        $profile = $request->user()->studentProfile;
        abort_unless($profile, 403);

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $thesis = Thesis::where('student_profile_id', $profile->id)->latest()->firstOrFail();

        if (! in_array($thesis->status, [ThesisStatus::DRAFT->value, ThesisStatus::SUBMITTED->value, ThesisStatus::REJECTED->value], true)) {
            return back()->with('error', 'Pengajuan yang sudah berjalan tidak dapat dibatalkan sendiri. Hubungi admin/prodi.');
        }

        $service->cancel($thesis, $request->user(), $validated['reason']);

        return redirect()->route('student.thesis.index')
            ->with('success', 'Pengajuan skripsi berhasil dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProposal(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'type' => ['required', Rule::in(array_keys(Thesis::TYPE_LABELS))],
            'title' => 'required|string|max:255',
            'topic' => 'nullable|string|max:255',
            'abstract' => 'nullable|string|max:5000',
            'keywords' => 'nullable|string|max:255',
            'proposal_file' => ($isUpdate ? 'nullable' : 'nullable').'|file|mimes:pdf,doc,docx|max:10240',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapThesis(Thesis $thesis): array
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
            'academicYearId' => $thesis->academic_year_id,
            'createdAt' => $thesis->created_at?->format('d M Y H:i'),
            'proposalSubmittedAt' => $thesis->proposal_submitted_at?->format('d M Y H:i'),
            'approvedAt' => $thesis->approved_at?->format('d M Y H:i'),
            'approvedBy' => $thesis->approvedBy?->name,
            'rejectedAt' => $thesis->rejected_at?->format('d M Y H:i'),
            'rejectionReason' => $thesis->rejection_reason,
            'finalScore' => $thesis->final_score !== null ? (float) $thesis->final_score : null,
            'finalGrade' => $thesis->final_grade,
            'completedAt' => $thesis->completed_at?->format('d M Y H:i'),
            'repositoryPublished' => (bool) $thesis->repository_published,
            'repositoryPublishedAt' => $thesis->repository_published_at?->format('d M Y H:i'),
            'notes' => $thesis->notes,
            'canEdit' => in_array($thesis->status, [ThesisStatus::DRAFT->value, ThesisStatus::SUBMITTED->value, ThesisStatus::REJECTED->value], true),
            'canCancel' => in_array($thesis->status, [ThesisStatus::DRAFT->value, ThesisStatus::SUBMITTED->value, ThesisStatus::REJECTED->value], true),
            'canUpload' => ! in_array($thesis->status, [ThesisStatus::COMPLETED->value, ThesisStatus::CANCELLED->value], true),
            'updateUrl' => route('student.thesis.update', $thesis->id),
            'proposal' => $proposalDocument ? [
                'fileName' => $proposalDocument->file_name,
                'downloadUrl' => route('student.thesis.documents.download', $proposalDocument),
            ] : null,
            'supervisors' => $thesis->supervisors->where('is_active', true)->map(fn (ThesisSupervisor $supervisor) => [
                'id' => $supervisor->id,
                'name' => $supervisor->lecturerProfile?->user?->name,
                'nidn' => $supervisor->lecturerProfile?->nidn,
                'roleLabel' => $supervisor->roleLabel(),
                'assignedAt' => $supervisor->assigned_at?->format('d M Y'),
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
                'canSubmit' => in_array($milestone->status, [
                    ThesisMilestoneStatus::PENDING->value,
                    ThesisMilestoneStatus::IN_PROGRESS->value,
                    ThesisMilestoneStatus::REVISION->value,
                ], true),
                'submitUrl' => route('student.thesis.milestones.submit', $milestone),
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
                'downloadUrl' => route('student.thesis.documents.download', $document),
                'deleteUrl' => route('student.thesis.documents.destroy', $document),
            ])->values()->all(),
            'defenses' => $thesis->defenses->map(fn (ThesisDefense $defense) => [
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
                'examiners' => $defense->examiners->map(fn ($examiner) => [
                    'name' => $examiner->lecturerProfile?->user?->name,
                    'roleLabel' => $examiner->roleLabel(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
