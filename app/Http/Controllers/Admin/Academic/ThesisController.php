<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Enums\PlagiarismStatus;
use App\Enums\ThesisDefenseResult;
use App\Enums\ThesisDefenseStatus;
use App\Enums\ThesisDocumentCategory;
use App\Enums\ThesisMilestoneStatus;
use App\Enums\ThesisStatus;
use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\Thesis;
use App\Models\Academic\ThesisDefense;
use App\Models\Academic\ThesisDocument;
use App\Models\Academic\ThesisMilestone;
use App\Support\Academic\ThesisService;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThesisController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:30',
            'year' => 'nullable|integer|exists:academic_years,id',
            'program' => 'nullable|integer|exists:study_programs,id',
            'supervisor' => 'nullable|integer|exists:lecturer_profiles,id',
            'sort' => 'nullable|in:id,title,status,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = Thesis::query()
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

        if (filled($validated['year'] ?? null)) {
            $query->where('academic_year_id', $validated['year']);
        }

        if (filled($validated['program'] ?? null)) {
            $query->whereHas('studentProfile', fn ($student) => $student->where('study_program_id', $validated['program']));
        }

        if (filled($validated['supervisor'] ?? null)) {
            $query->whereHas('activeSupervisors', fn ($supervisor) => $supervisor->where('lecturer_profile_id', $validated['supervisor']));
        }

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        $perPage = (int) ($validated['perPage'] ?? 10);
        $theses = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Academic/Theses/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Skripsi / Tugas Akhir'),
            'can' => [
                'create' => ActivePermission::check('thesis.create'),
                'update' => ActivePermission::check('thesis.update'),
                'delete' => ActivePermission::check('thesis.delete'),
                'view' => ActivePermission::check('thesis.view'),
                'approve' => ActivePermission::check('thesis.approve'),
                'defense' => ActivePermission::check('thesis.defense'),
                'grade' => ActivePermission::check('thesis.grade'),
            ],
            'stats' => [
                'total' => Thesis::count(),
                'submitted' => Thesis::where('status', ThesisStatus::SUBMITTED->value)->count(),
                'inProgress' => Thesis::where('status', ThesisStatus::IN_PROGRESS->value)->count(),
                'defenseReady' => Thesis::where('status', ThesisStatus::DEFENSE_READY->value)->count(),
                'completed' => Thesis::where('status', ThesisStatus::COMPLETED->value)->count(),
            ],
            'data' => [
                'rows' => collect($theses->items())->values()->map(fn (Thesis $thesis, int $i) => [
                    'id' => $thesis->id,
                    'no' => ($theses->firstItem() ?? 0) + $i,
                    'student' => trim(($thesis->studentProfile?->user?->first_name ?? '').' '.($thesis->studentProfile?->user?->last_name ?? '')) ?: '-',
                    'nim' => $thesis->studentProfile?->nim,
                    'program' => $thesis->studentProfile?->studyProgram?->name,
                    'title' => $thesis->title,
                    'topic' => $thesis->topic,
                    'type' => $thesis->type,
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
                    'showUrl' => route('admin.academic.theses.show', $thesis),
                    'editUrl' => route('admin.academic.theses.edit', $thesis),
                    'deleteUrl' => route('admin.academic.theses.destroy', $thesis),
                ])->all(),
                'currentPage' => $theses->currentPage(),
                'lastPage' => $theses->lastPage(),
                'perPage' => $theses->perPage(),
                'total' => $theses->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'year' => $validated['year'] ?? '',
                'program' => $validated['program'] ?? '',
                'supervisor' => $validated['supervisor'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'options' => [
                'statuses' => ThesisStatus::options(),
                'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name'])
                    ->map(fn ($year) => ['id' => $year->id, 'name' => $year->name])->all(),
                'programs' => StudyProgram::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($program) => ['id' => $program->id, 'name' => $program->name])->all(),
            ],
            'urls' => [
                'index' => route('admin.academic.theses.index'),
                'create' => route('admin.academic.theses.create'),
                'bulkDestroy' => route('admin.academic.theses.bulk-destroy'),
                'searchLecturers' => route('admin.academic.theses.search-lecturers'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/Theses/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Skripsi / Tugas Akhir'),
            'mode' => 'create',
            'thesis' => [
                'student_profile_id' => '',
                'student_label' => '',
                'academic_year_id' => AcademicYear::query()->where('is_active', true)->value('id') ?? '',
                'type' => Thesis::TYPE_SKRIPSI,
                'title' => '',
                'topic' => '',
                'abstract' => '',
                'keywords' => '',
                'notes' => '',
            ],
            'years' => $this->yearOptions(),
            'types' => Thesis::TYPE_LABELS,
            'urls' => [
                'index' => route('admin.academic.theses.index'),
                'submit' => route('admin.academic.theses.store'),
                'searchStudents' => route('admin.academic.theses.search-students'),
            ],
        ]);
    }

    public function store(Request $request, ThesisService $service)
    {
        $validated = $this->validatePayload($request);
        $student = StudentProfile::findOrFail($validated['student_profile_id']);

        $thesis = $service->createProposal($student, $validated, $request->user());

        if ($request->hasFile('proposal_file')) {
            $service->storeProposalFile($thesis, $request->user(), $request->file('proposal_file'));
        }

        return redirect()->route('admin.academic.theses.show', $thesis)
            ->with('success', 'Pengajuan skripsi "'.$thesis->title.'" berhasil dibuat.');
    }

    public function edit(Request $request, Thesis $thesis): Response
    {
        $thesis->load(['studentProfile.user:id,first_name,last_name', 'studentProfile.studyProgram:id,name']);

        return Inertia::render('Admin/Academic/Theses/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Skripsi / Tugas Akhir'),
            'mode' => 'edit',
            'thesis' => [
                'id' => $thesis->id,
                'student_profile_id' => $thesis->student_profile_id,
                'student_label' => trim(($thesis->studentProfile?->nim ? $thesis->studentProfile->nim.' - ' : '').($thesis->studentProfile?->user?->name ?? '')),
                'academic_year_id' => $thesis->academic_year_id,
                'type' => $thesis->type,
                'title' => $thesis->title,
                'topic' => $thesis->topic ?? '',
                'abstract' => $thesis->abstract ?? '',
                'keywords' => $thesis->keywords ?? '',
                'notes' => $thesis->notes ?? '',
            ],
            'years' => $this->yearOptions(),
            'types' => Thesis::TYPE_LABELS,
            'urls' => [
                'index' => route('admin.academic.theses.index'),
                'submit' => route('admin.academic.theses.update', $thesis),
                'searchStudents' => route('admin.academic.theses.search-students'),
            ],
        ]);
    }

    public function update(Request $request, Thesis $thesis, ThesisService $service)
    {
        if (in_array($thesis->status, [ThesisStatus::COMPLETED->value, ThesisStatus::CANCELLED->value], true)) {
            return back()->with('error', 'Skripsi yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }

        $validated = $this->validatePayload($request);

        $thesis->update([
            'academic_year_id' => $validated['academic_year_id'],
            'type' => $validated['type'],
            'title' => $validated['title'],
            'topic' => $validated['topic'] ?? null,
            'abstract' => $validated['abstract'] ?? null,
            'keywords' => $validated['keywords'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        if ($request->hasFile('proposal_file')) {
            $service->storeProposalFile($thesis, $request->user(), $request->file('proposal_file'));
        }

        return redirect()->route('admin.academic.theses.show', $thesis)
            ->with('success', 'Data skripsi berhasil diperbarui.');
    }

    public function show(Request $request, Thesis $thesis): Response
    {
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

        return Inertia::render('Admin/Academic/Theses/Show', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Detail Skripsi / Tugas Akhir'),
            'can' => [
                'update' => ActivePermission::check('thesis.update'),
                'delete' => ActivePermission::check('thesis.delete'),
                'approve' => ActivePermission::check('thesis.approve'),
                'defense' => ActivePermission::check('thesis.defense'),
                'grade' => ActivePermission::check('thesis.grade'),
                'plagiarism' => ActivePermission::check('thesis.plagiarism'),
            ],
            'thesis' => $this->mapShow($thesis),
            'options' => [
                'types' => Thesis::TYPE_LABELS,
                'milestoneStatuses' => ThesisMilestoneStatus::options(),
                'defenseTypes' => ThesisDefense::TYPE_LABELS,
                'examinerRoles' => Thesis::EXAMINER_ROLE_LABELS,
                'documentCategories' => ThesisDocumentCategory::options(),
                'defenseResults' => ThesisDefenseResult::options(),
                'plagiarismStatuses' => PlagiarismStatus::options(),
            ],
            'urls' => [
                'index' => route('admin.academic.theses.index'),
                'edit' => route('admin.academic.theses.edit', $thesis),
                'delete' => route('admin.academic.theses.destroy', $thesis),
                'approve' => route('admin.academic.theses.approve', $thesis),
                'reject' => route('admin.academic.theses.reject', $thesis),
                'milestoneStore' => route('admin.academic.theses.milestones.store', $thesis),
                'defenseStore' => route('admin.academic.theses.defenses.store', $thesis),
                'documentStore' => route('admin.academic.theses.documents.store', $thesis),
                'finalize' => route('admin.academic.theses.finalize', $thesis),
                'plagiarism' => route('admin.academic.theses.plagiarism.update', $thesis),
                'searchLecturers' => route('admin.academic.theses.search-lecturers'),
            ],
        ]);
    }

    public function destroy(Request $request, Thesis $thesis)
    {
        if ($thesis->status === ThesisStatus::COMPLETED->value) {
            return back()->with('error', 'Skripsi yang sudah selesai tidak dapat dihapus.');
        }

        $thesis->update(['deleted_by' => $request->user()->id]);
        $thesis->delete();

        return redirect()->route('admin.academic.theses.index')
            ->with('success', 'Skripsi "'.$thesis->title.'" berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:theses,id',
        ]);

        $count = 0;

        DB::transaction(function () use ($validated, $request, &$count) {
            $theses = Thesis::whereIn('id', $validated['ids'])
                ->where('status', '!=', ThesisStatus::COMPLETED->value)
                ->get();

            foreach ($theses as $thesis) {
                $thesis->update(['deleted_by' => $request->user()->id]);
                $thesis->delete();
                $count++;
            }
        });

        return back()->with('success', $count.' skripsi berhasil dihapus.');
    }

    public function approve(Request $request, Thesis $thesis, ThesisService $service)
    {
        $validated = $request->validate([
            'supervisor_ids' => 'required|array|min:1',
            'supervisor_ids.*' => 'integer|exists:lecturer_profiles,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $service->approve($thesis, $request->user(), $validated['supervisor_ids'], $validated['notes'] ?? null);

        return back()->with('success', 'Pengajuan disetujui dan pembimbing berhasil ditetapkan.');
    }

    public function reject(Request $request, Thesis $thesis, ThesisService $service)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $service->reject($thesis, $request->user(), $validated['reason']);

        return back()->with('success', 'Pengajuan skripsi ditolak dengan catatan.');
    }

    public function storeMilestone(Request $request, Thesis $thesis, ThesisService $service)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'sequence' => 'nullable|integer|min:1|max:99',
        ]);

        $sequence = $validated['sequence'] ?? ((int) $thesis->milestones()->max('sequence') + 1);

        $thesis->milestones()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'sequence' => $sequence,
            'status' => ThesisMilestoneStatus::PENDING->value,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $service->syncStatus($thesis->refresh());

        return back()->with('success', 'Milestone bimbingan berhasil ditambahkan.');
    }

    public function updateMilestone(Request $request, ThesisMilestone $milestone, ThesisService $service)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'sequence' => 'nullable|integer|min:1|max:99',
            'status' => ['nullable', Rule::in(array_keys(ThesisMilestoneStatus::options()))],
            'feedback' => 'nullable|string|max:2000',
        ]);

        $status = $validated['status'] ?? null;
        unset($validated['status']);

        $milestone->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        if ($status !== null) {
            $service->reviewMilestone($milestone, $request->user(), $status, $validated['feedback'] ?? null);
        } else {
            $service->syncStatus($milestone->thesis()->firstOrFail());
        }

        return back()->with('success', 'Milestone bimbingan berhasil diperbarui.');
    }

    public function destroyMilestone(Request $request, ThesisMilestone $milestone, ThesisService $service)
    {
        $thesis = $milestone->thesis()->firstOrFail();
        $milestone->update(['updated_by' => $request->user()->id]);
        $milestone->delete();
        $service->syncStatus($thesis);

        return back()->with('success', 'Milestone bimbingan berhasil dihapus.');
    }

    public function storeDefense(Request $request, Thesis $thesis, ThesisService $service)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ThesisDefense::TYPE_LABELS))],
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:30|max:480',
            'room' => 'nullable|string|max:100',
            'meeting_link' => 'nullable|url|max:255',
            'notes' => 'nullable|string|max:2000',
            'examiners' => 'required|array|min:1',
            'examiners.*.lecturer_profile_id' => 'required|integer|exists:lecturer_profiles,id',
            'examiners.*.role' => ['required', Rule::in(array_keys(Thesis::EXAMINER_ROLE_LABELS))],
        ]);

        $examinerIds = collect($validated['examiners'])->pluck('lecturer_profile_id');
        if ($examinerIds->duplicates()->isNotEmpty()) {
            return back()->withErrors(['examiners' => 'Dosen penguji tidak boleh dipilih lebih dari sekali.']);
        }

        $service->scheduleDefense($thesis, $validated, $request->user());

        return back()->with('success', 'Jadwal sidang berhasil dibuat.');
    }

    public function updateDefense(Request $request, ThesisDefense $defense)
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:30|max:480',
            'room' => 'nullable|string|max:100',
            'meeting_link' => 'nullable|url|max:255',
            'notes' => 'nullable|string|max:2000',
            'status' => ['nullable', Rule::in([ThesisDefenseStatus::SCHEDULED->value, ThesisDefenseStatus::POSTPONED->value])],
        ]);

        $defense->update([
            ...$validated,
            'status' => $validated['status'] ?? $defense->status,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Jadwal sidang berhasil diperbarui.');
    }

    public function cancelDefense(Request $request, ThesisDefense $defense)
    {
        $defense->update([
            'status' => ThesisDefenseStatus::CANCELLED->value,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Jadwal sidang dibatalkan.');
    }

    public function completeDefense(Request $request, ThesisDefense $defense, ThesisService $service)
    {
        $validated = $request->validate([
            'result' => ['required', Rule::in(array_keys(ThesisDefenseResult::options()))],
            'score' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'examiner_scores' => 'nullable|array',
            'examiner_scores.*' => 'nullable|numeric|min:0|max:100',
            'examiner_notes' => 'nullable|array',
            'examiner_notes.*' => 'nullable|string|max:1000',
        ]);

        $service->completeDefense($defense, $request->user(), $validated);

        return back()->with('success', 'Hasil sidang berhasil disimpan.');
    }

    public function storeDocument(Request $request, Thesis $thesis, ThesisService $service)
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(ThesisDocumentCategory::options()))],
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:10240',
        ]);

        $service->storeDocument($thesis, $request->user(), $request->file('file'), $validated);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroyDocument(Request $request, ThesisDocument $document, ThesisService $service)
    {
        $service->deleteDocument($document);

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }

    public function finalize(Request $request, Thesis $thesis, ThesisService $service)
    {
        $validated = $request->validate([
            'final_score' => 'required|numeric|min:0|max:100',
            'publish' => 'boolean',
        ]);

        $service->finalize(
            $thesis,
            $request->user(),
            (float) $validated['final_score'],
            (bool) ($validated['publish'] ?? false),
        );

        return back()->with('success', 'Nilai akhir difinalisasi'.(($validated['publish'] ?? false) ? ' dan karya dipublikasikan ke repositori.' : '.'));
    }

    public function updatePlagiarism(Request $request, Thesis $thesis, ThesisService $service)
    {
        $validated = $request->validate([
            'plagiarism_status' => ['required', Rule::in(array_keys(PlagiarismStatus::options()))],
            'plagiarism_score' => 'nullable|numeric|min:0|max:100',
            'plagiarism_tool' => 'nullable|string|max:100',
            'plagiarism_checked_at' => 'nullable|date',
            'plagiarism_notes' => 'nullable|string|max:2000',
        ]);

        $service->updatePlagiarism($thesis, $request->user(), $validated);

        $message = 'Metadata cek plagiarisme berhasil diperbarui.';

        if ($validated['plagiarism_status'] === PlagiarismStatus::FLAGGED->value) {
            $message .= ' Karya otomatis ditarik dari repositori publik.';
        }

        return back()->with('success', $message);
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $students = StudentProfile::query()
            ->with(['user:id,first_name,last_name,email', 'studyProgram:id,name'])
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nim', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"));
            }))
            ->orderBy('nim')
            ->limit(20)
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => trim(($student->nim ? $student->nim.' - ' : '').($student->user?->name ?? '').($student->studyProgram ? ' ('.$student->studyProgram->name.')' : '')),
            ])
            ->all();

        return response()->json(['options' => $students]);
    }

    public function searchLecturers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $lecturers = LecturerProfile::query()
            ->with('user:id,first_name,last_name')
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nidn', 'like', "%{$q}%")
                    ->orWhere('nidk', 'like', "%{$q}%")
                    ->orWhere('nip', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%"));
            }))
            ->orderBy('nidn')
            ->limit(20)
            ->get()
            ->map(fn (LecturerProfile $lecturer) => [
                'id' => $lecturer->id,
                'label' => trim(($lecturer->nidn ? $lecturer->nidn.' - ' : '').($lecturer->user?->name ?? '')),
            ])
            ->all();

        return response()->json(['options' => $lecturers]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'student_profile_id' => 'required|integer|exists:student_profiles,id',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'type' => ['required', Rule::in(array_keys(Thesis::TYPE_LABELS))],
            'title' => 'required|string|max:255',
            'topic' => 'nullable|string|max:255',
            'abstract' => 'nullable|string|max:5000',
            'keywords' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'proposal_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function yearOptions(): array
    {
        return AcademicYear::query()->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn ($year) => ['id' => $year->id, 'name' => $year->name])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapShow(Thesis $thesis): array
    {
        $student = $thesis->studentProfile;

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
            'rejectedAt' => $thesis->rejected_at?->format('d M Y H:i'),
            'rejectionReason' => $thesis->rejection_reason,
            'finalScore' => $thesis->final_score !== null ? (float) $thesis->final_score : null,
            'finalGrade' => $thesis->final_grade,
            'completedAt' => $thesis->completed_at?->format('d M Y H:i'),
            'repositoryPublished' => (bool) $thesis->repository_published,
            'repositoryPublishedAt' => $thesis->repository_published_at?->format('d M Y H:i'),
            'plagiarismStatus' => $thesis->plagiarism_status,
            'plagiarismStatusLabel' => $thesis->plagiarismStatusLabel(),
            'plagiarismStatusTone' => $thesis->plagiarismStatusTone(),
            'plagiarismScore' => $thesis->plagiarism_score !== null ? (float) $thesis->plagiarism_score : null,
            'plagiarismTool' => $thesis->plagiarism_tool,
            'plagiarismCheckedAt' => $thesis->plagiarism_checked_at?->format('d M Y H:i'),
            'plagiarismCheckedAtInput' => $thesis->plagiarism_checked_at?->format('Y-m-d\TH:i'),
            'plagiarismNotes' => $thesis->plagiarism_notes,
            'notes' => $thesis->notes,
            'student' => [
                'id' => $student?->id,
                'name' => $student?->user?->name,
                'nim' => $student?->nim,
                'program' => $student?->studyProgram?->name,
                'entryYear' => $student?->entry_year,
                'semester' => $student?->current_semester,
                'email' => $student?->user?->email,
            ],
            'proposal' => $thesis->proposal_file_path ? [
                'fileName' => $thesis->proposal_file_name,
                'downloadUrl' => route('admin.academic.theses.documents.download', [
                    'document' => $thesis->documents->firstWhere('category', ThesisDocumentCategory::PROPOSAL->value)?->id ?? 0,
                ]),
            ] : null,
            'supervisors' => $thesis->supervisors->map(fn ($supervisor) => [
                'id' => $supervisor->id,
                'lecturerProfileId' => $supervisor->lecturer_profile_id,
                'name' => $supervisor->lecturerProfile?->user?->name,
                'nidn' => $supervisor->lecturerProfile?->nidn,
                'role' => $supervisor->role,
                'roleLabel' => $supervisor->roleLabel(),
                'isActive' => (bool) $supervisor->is_active,
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
                'updateUrl' => route('admin.academic.theses.milestones.update', $milestone),
                'deleteUrl' => route('admin.academic.theses.milestones.destroy', $milestone),
            ])->values()->all(),
            'documents' => $thesis->documents->map(fn (ThesisDocument $document) => $this->mapDocument($document))->values()->all(),
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
                'completedAt' => $defense->completed_at?->format('d M Y H:i'),
                'examiners' => $defense->examiners->map(fn ($examiner) => [
                    'id' => $examiner->id,
                    'lecturerProfileId' => $examiner->lecturer_profile_id,
                    'name' => $examiner->lecturerProfile?->user?->name,
                    'nidn' => $examiner->lecturerProfile?->nidn,
                    'role' => $examiner->role,
                    'roleLabel' => $examiner->roleLabel(),
                    'score' => $examiner->score !== null ? (float) $examiner->score : null,
                    'notes' => $examiner->notes,
                ])->values()->all(),
                'completeUrl' => route('admin.academic.theses.defenses.complete', $defense),
                'cancelUrl' => route('admin.academic.theses.defenses.cancel', $defense),
                'updateUrl' => route('admin.academic.theses.defenses.update', $defense),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDocument(ThesisDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'category' => $document->category,
            'categoryLabel' => $document->categoryLabel(),
            'fileName' => $document->file_name,
            'fileSize' => $document->formattedSize(),
            'mimeType' => $document->mime_type,
            'version' => $document->version,
            'uploadedBy' => $document->uploader?->name,
            'notes' => $document->notes,
            'createdAt' => $document->created_at?->format('d M Y H:i'),
            'downloadUrl' => route('admin.academic.theses.documents.download', $document),
            'deleteUrl' => route('admin.academic.theses.documents.destroy', $document),
        ];
    }
}
