<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicAdvisorAssignment;
use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentFile;
use App\Models\Academic\AssignmentGrade;
use App\Models\Academic\AssignmentStatusHistory;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\ConsultationAppointment;
use App\Models\Academic\ConsultationSlot;
use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialFile;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\GradeAppeal;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentAdvisorNote;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentGradeComponent;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\Publication\Announcement;
use App\Support\AcademicAdvisorService;
use App\Support\Academic\AcademicAttendanceQrService;
use App\Support\AssignmentGradeBookSyncService;
use App\Support\AssignmentReportExportService;
use App\Support\GradeBookExportService;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\LecturerWorkloadService;
use App\Support\StudentGradeCalculator;
use App\Support\StudentProgressAnalyticsService;
use App\Support\TranscriptSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LecturerPortalController extends Controller
{
    // ---------------------------------------------------------------- Announcements
    // ---------------------------------------------------------------- Course Offerings Show
    public function courseOfferingsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $assignment = $this->assignedOffering($request, $id);
        $offering = $assignment->courseOffering;

        $enrolledCount = $this->enrolledQuery($offering->id)->count();
        $sessions = AttendanceSession::query()
            ->where('course_offering_id', $offering->id)
            ->withCount('records')
            ->orderBy('meeting_no')
            ->get();
        $materials = CourseMaterial::query()
            ->withCount('files')
            ->where('course_offering_id', $offering->id)
            ->latest('id')
            ->limit(5)
            ->get();
        $assignments = Assignment::query()
            ->withCount(['submissions as submitted_count', 'submissions as graded_count' => fn ($q) => $q->whereHas('grade', fn ($g) => $g->where('status', 'graded'))])
            ->where('course_offering_id', $offering->id)
            ->orderByDesc('due_at')
            ->limit(5)
            ->get();

        $schedules = $offering->courseSchedules()
            ->where('is_active', true)
            ->with(['room.building'])
            ->get()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'day' => $schedule->day_of_week,
                'time' => $this->formatTime($schedule->start_time).' - '.$this->formatTime($schedule->end_time),
                'sessionType' => $schedule->session_type ?? '-',
                'deliveryMode' => $schedule->delivery_mode ?? '-',
                'room' => $schedule->room?->name ?? '-',
                'building' => $schedule->room?->building?->name ?? '-',
            ])->values()->all();

        return Inertia::render('Lecturer/CourseOfferings/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail Kelas'),
            'offering' => [
                'id' => $offering->id,
                'courseCode' => $offering->course?->code ?? '-',
                'courseName' => $offering->course?->name ?? '-',
                'label' => $offering->label ?? '-',
                'classCode' => $offering->code ?? '-',
                'academicYear' => $offering->academicYear?->name ?? '-',
                'studyProgram' => $offering->studyProgram?->name ?? '-',
                'semesterNo' => $offering->semester_no,
                'credits' => $offering->credits ?? $offering->course?->credits ?? 0,
                'capacity' => $offering->capacity,
                'enrolledCount' => $enrolledCount,
                'deliveryMode' => $offering->delivery_mode ?? '-',
                'status' => $offering->status ?? '-',
                'lecturerRole' => $assignment->role ?? '-',
                'classStartDate' => $offering->class_start_date?->format('d M Y') ?? '-',
                'classEndDate' => $offering->class_end_date?->format('d M Y') ?? '-',
            ],
            'stats' => [
                'students' => $enrolledCount,
                'sessions' => $sessions->count(),
                'materials' => CourseMaterial::where('course_offering_id', $offering->id)->count(),
                'assignments' => Assignment::where('course_offering_id', $offering->id)->count(),
                'completedSessions' => $sessions->where('status', 'Closed')->count(),
            ],
            'schedules' => $schedules,
            'sessions' => $sessions->map(fn ($session) => [
                'id' => $session->id,
                'meetingNo' => $session->meeting_no,
                'date' => $session->meeting_date?->format('d M Y') ?? '-',
                'time' => $this->formatTime($session->start_time).' - '.$this->formatTime($session->end_time),
                'topic' => $session->topic ?: '-',
                'status' => $session->status,
                'recordsCount' => $session->records_count,
                'editUrl' => route('lecturer.attendance-sessions.edit', ['sessionId' => $session->id]),
            ])->values()->all(),
            'materials' => $materials->map(fn ($material) => [
                'id' => $material->id,
                'title' => $material->title,
                'category' => $material->category,
                'meetingNumber' => $material->meeting_number,
                'isPublished' => (bool) $material->is_published,
                'filesCount' => $material->files_count,
                'url' => route('lecturer.course-materials.show', ['material' => $material->id]),
            ])->values()->all(),
            'assignments' => $assignments->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'dueAt' => $item->due_at?->format('d M Y H:i') ?? '-',
                'isPublished' => (bool) $item->is_published,
                'submitted' => $item->submitted_count,
                'graded' => $item->graded_count,
                'url' => route('lecturer.assignments.show', ['id' => $item->id]),
            ])->values()->all(),
            'urls' => [
                'index' => route('lecturer.course-offerings.index'),
                'students' => route('lecturer.course-offerings.students', ['offeringId' => $id]),
                'attendance' => route('lecturer.course-offerings.attendance', ['offeringId' => $id]),
                'grades' => route('lecturer.course-offerings.grades', ['offeringId' => $id]),
                'materials' => route('lecturer.course-materials.index', ['offeringId' => $id]),
                'assignments' => route('lecturer.assignments.index'),
                'createAssignment' => route('lecturer.assignments.create', ['offeringId' => $id]),
            ],
        ]);
    }

    public function courseOfferingsStudents(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        $assignment = $this->assignedOffering($request, $offeringId);
        $offering = $assignment->courseOffering;

        $students = $this->enrolledQuery($offeringId)
            ->with(['studyPlan.studentProfile.user', 'studyPlan.studentProfile.studyProgram'])
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                $student = $detail->studyPlan?->studentProfile;

                return [
                    'studyPlanDetailId' => $detail->id,
                    'nim' => $student?->nim ?? '-',
                    'name' => $student?->user?->name ?? '-',
                    'studyProgram' => $student?->studyProgram?->name ?? '-',
                    'semester' => $student?->current_semester ?? '-',
                    'status' => $student?->academic_status ?? '-',
                    'credits' => (int) $detail->credits,
                    'isRepeat' => (bool) $detail->is_repeat,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();

        return Inertia::render('Lecturer/CourseOfferings/Students', [
            'shell' => ShellProps::make($user, 'Akademik', 'Mahasiswa Kelas'),
            'offeringId' => $offeringId,
            'classInfo' => [
                'course' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-'),
                'class' => $offering->label ?? '-',
                'academicYear' => $offering->academicYear?->name ?? '-',
                'studyProgram' => $offering->studyProgram?->name ?? '-',
            ],
            'students' => $students,
            'stats' => [
                'total' => count($students),
                'repeat' => collect($students)->where('isRepeat', true)->count(),
                'credits' => (int) collect($students)->sum('credits'),
            ],
            'urls' => [
                'index' => route('lecturer.course-offerings.index'),
                'show' => route('lecturer.course-offerings.show', ['id' => $offeringId]),
                'grades' => route('lecturer.course-offerings.grades', ['offeringId' => $offeringId]),
            ],
        ]);
    }

    public function courseOfferingsAttendance(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        $assignment = $this->assignedOffering($request, $offeringId);
        $offering = $assignment->courseOffering;

        $sessions = AttendanceSession::query()
            ->where('course_offering_id', $offeringId)
            ->withCount([
                'records',
                'records as present_count' => fn ($q) => $q->where('status', 'Present'),
                'records as late_count' => fn ($q) => $q->where('status', 'Late'),
                'records as absent_count' => fn ($q) => $q->where('status', 'Absent'),
            ])
            ->orderBy('meeting_no')
            ->get();

        $enrolled = $this->enrolledQuery($offeringId)->count();

        return Inertia::render('Lecturer/CourseOfferings/Attendance', [
            'shell' => ShellProps::make($user, 'Akademik', 'Absensi Kelas'),
            'offeringId' => $offeringId,
            'classInfo' => [
                'course' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-'),
                'class' => $offering->label ?? '-',
                'academicYear' => $offering->academicYear?->name ?? '-',
                'studyProgram' => $offering->studyProgram?->name ?? '-',
            ],
            'sessions' => $sessions->map(fn ($session) => [
                'id' => $session->id,
                'meetingNo' => $session->meeting_no,
                'date' => $session->meeting_date?->format('d M Y') ?? '-',
                'time' => $this->formatTime($session->start_time).' - '.$this->formatTime($session->end_time),
                'topic' => $session->topic ?: '-',
                'status' => $session->status,
                'attendanceMethod' => $session->attendance_method ?? 'manual',
                'recordsCount' => $session->records_count,
                'presentCount' => $session->present_count,
                'lateCount' => $session->late_count,
                'absentCount' => $session->absent_count,
                'editUrl' => route('lecturer.attendance-sessions.edit', ['sessionId' => $session->id]),
                'openUrl' => route('lecturer.course-offerings.attendance.open', ['offeringId' => $offeringId, 'sessionId' => $session->id]),
                'qrUrl' => route('lecturer.course-offerings.attendance.qr', ['offeringId' => $offeringId, 'sessionId' => $session->id]),
            ])->values()->all(),
            'stats' => [
                'sessions' => $sessions->count(),
                'opened' => $sessions->where('status', 'Opened')->count(),
                'closed' => $sessions->where('status', 'Closed')->count(),
                'enrolled' => $enrolled,
            ],
            'urls' => [
                'index' => route('lecturer.course-offerings.index'),
                'show' => route('lecturer.course-offerings.show', ['id' => $offeringId]),
            ],
        ]);
    }

    public function courseOfferingsGrades(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        $assignment = $this->assignedOffering($request, $offeringId);
        $offering = $assignment->courseOffering;

        $gradeRows = $this->enrolledQuery($offeringId)
            ->with(['studyPlan.studentProfile.user', 'studentGrade.components'])
            ->get()
            ->map(function (StudyPlanDetail $detail) {
                $student = $detail->studyPlan?->studentProfile;
                $grade = $detail->studentGrade;

                return [
                    'studyPlanDetailId' => $detail->id,
                    'nim' => $student?->nim ?? '-',
                    'name' => $student?->user?->name ?? '-',
                    'finalScore' => $grade?->final_score,
                    'letterGrade' => $grade?->letter_grade ?? '-',
                    'gradePoint' => $grade?->grade_point,
                    'gradeStatus' => $grade?->grade_status ?? 'Draft',
                    'componentCount' => $grade?->components?->count() ?? 0,
                    'editUrl' => route('lecturer.student-grades.edit', ['id' => $detail->id]),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();

        $scored = collect($gradeRows)->filter(fn ($row) => $row['finalScore'] !== null);

        return Inertia::render('Lecturer/CourseOfferings/Grades', [
            'shell' => ShellProps::make($user, 'Akademik', 'Nilai Kelas'),
            'offeringId' => $offeringId,
            'classInfo' => [
                'course' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-'),
                'class' => $offering->label ?? '-',
                'academicYear' => $offering->academicYear?->name ?? '-',
                'studyProgram' => $offering->studyProgram?->name ?? '-',
            ],
            'gradeRows' => $gradeRows,
            'stats' => [
                'total' => count($gradeRows),
                'graded' => $scored->count(),
                'average' => $scored->isNotEmpty() ? round((float) $scored->avg('finalScore'), 2) : null,
                'published' => collect($gradeRows)->where('gradeStatus', 'Published')->count(),
                'draft' => collect($gradeRows)->where('gradeStatus', 'Draft')->count(),
            ],
            'urls' => [
                'index' => route('lecturer.course-offerings.index'),
                'show' => route('lecturer.course-offerings.show', ['id' => $offeringId]),
                'gradeBook' => route('lecturer.student-grades.grade-book', ['course_offering_id' => $offeringId]),
            ],
        ]);
    }

    // ---------------------------------------------------------------- Course Materials Index/Show
    public function courseMaterialsIndex(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        $assignment = $this->assignedOffering($request, $offeringId);
        $offering = $assignment->courseOffering;
        $schedule = $offering->courseSchedules()->where('is_active', true)->with('room.building')->first();

        $materials = CourseMaterial::query()
            ->with(['uploadedBy', 'files'])
            ->where('course_offering_id', $offeringId)
            ->orderByDesc('meeting_number')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Lecturer/CourseMaterials/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Materi Kuliah'),
            'offeringId' => $offeringId,
            'offeringInfo' => [
                'id' => $offering->id,
                'courseCode' => $offering->course?->code ?? '-',
                'courseName' => $offering->course?->name ?? '-',
                'label' => $offering->label ?? '-',
                'academicYear' => $offering->academicYear?->name ?? '-',
                'studyProgram' => $offering->studyProgram?->name ?? '-',
                'semesterNo' => $offering->semester_no,
                'room' => $schedule?->room?->name ?? '-',
                'building' => $schedule?->room?->building?->name ?? '-',
                'deliveryMode' => $schedule?->delivery_mode ?? $offering->delivery_mode ?? '-',
            ],
            'materials' => $materials->map(fn (CourseMaterial $material) => [
                'id' => $material->id,
                'title' => $material->title,
                'description' => $material->description,
                'category' => $material->category,
                'meetingNumber' => $material->meeting_number,
                'isPublished' => (bool) $material->is_published,
                'downloadCount' => (int) $material->files->sum('download_count'),
                'uploadedAt' => $material->created_at?->format('d M Y H:i') ?? '-',
                'uploadedBy' => $material->uploadedBy?->name ?? '-',
                'files' => $material->files->map(fn (CourseMaterialFile $file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'type' => $file->file_type,
                    'size' => $this->formatFileSize($file->file_size),
                    'isLink' => $file->file_type === 'link',
                    'url' => $this->materialFileUrl($material->id, $file),
                ])->values()->all(),
                'showUrl' => route('lecturer.course-materials.show', ['material' => $material->id]),
                'updateUrl' => route('lecturer.course-materials.update', ['id' => $material->id]),
                'deleteUrl' => route('lecturer.course-materials.destroy', ['id' => $material->id]),
                'toggleUrl' => route('lecturer.course-materials.toggle', ['id' => $material->id]),
            ])->values()->all(),
            'categories' => $this->materialCategories(),
            'stats' => [
                'total' => $materials->count(),
                'published' => $materials->where('is_published', true)->count(),
                'draft' => $materials->where('is_published', false)->count(),
                'files' => (int) $materials->sum(fn (CourseMaterial $m) => $m->files->count()),
            ],
            'urls' => [
                'list' => route('lecturer.course-materials.list'),
                'show' => route('lecturer.course-offerings.show', ['id' => $offeringId]),
                'store' => route('lecturer.course-materials.store', ['offeringId' => $offeringId]),
            ],
        ]);
    }

    public function courseMaterialsShow(Request $request, $material): Response
    {
        $user = $request->user();
        $materialModel = CourseMaterial::query()
            ->with(['files', 'uploadedBy', 'courseOffering.course', 'courseOffering.academicYear', 'versions'])
            ->findOrFail((int) $material);

        $this->assignedOffering($request, $materialModel->course_offering_id);
        $offering = $materialModel->courseOffering;

        return Inertia::render('Lecturer/CourseMaterials/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail Materi'),
            'material' => [
                'id' => $materialModel->id,
                'title' => $materialModel->title,
                'description' => $materialModel->description,
                'category' => $materialModel->category,
                'categoryLabel' => $this->materialCategories()[$materialModel->category] ?? ucfirst((string) $materialModel->category),
                'meetingNumber' => $materialModel->meeting_number,
                'isPublished' => (bool) $materialModel->is_published,
                'uploadedBy' => $materialModel->uploadedBy?->name ?? '-',
                'uploadedAt' => $materialModel->created_at?->format('d M Y H:i') ?? '-',
                'course' => ($offering?->course?->code ?? '-').' - '.($offering?->course?->name ?? '-'),
                'class' => $offering?->label ?? '-',
                'academicYear' => $offering?->academicYear?->name ?? '-',
                'downloadCount' => (int) $materialModel->files->sum('download_count'),
                'files' => $materialModel->files->map(fn (CourseMaterialFile $file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'type' => $file->file_type,
                    'size' => $this->formatFileSize($file->file_size),
                    'isLink' => $file->file_type === 'link',
                    'url' => $this->materialFileUrl($materialModel->id, $file),
                ])->values()->all(),
                'versions' => $materialModel->versions->map(fn ($version) => [
                    'id' => $version->id,
                    'number' => $version->version_number,
                    'changeType' => $version->change_type,
                    'summary' => $version->change_summary,
                    'createdAt' => $version->created_at?->format('d M Y H:i') ?? '-',
                ])->values()->all(),
            ],
            'urls' => [
                'list' => route('lecturer.course-materials.list'),
                'offering' => route('lecturer.course-materials.index', ['offeringId' => $materialModel->course_offering_id]),
            ],
        ]);
    }

    public function courseMaterialsStore(Request $request, int $offeringId): RedirectResponse
    {
        $this->assignedOffering($request, $offeringId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(array_keys($this->materialCategories()))],
            'meetingNumber' => ['nullable', 'integer', 'min:1', 'max:20'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:51200', 'mimes:pdf,ppt,pptx,doc,docx,mp4,jpg,jpeg,png'],
            'videoUrl' => ['nullable', 'url', 'max:255'],
            'videoTitle' => ['nullable', 'string', 'max:255'],
            'isPublished' => ['boolean'],
        ]);

        $material = CourseMaterial::create([
            'course_offering_id' => $offeringId,
            'uploaded_by' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'meeting_number' => $validated['meetingNumber'] ?? null,
            'is_published' => (bool) ($validated['isPublished'] ?? false),
            'created_by' => $request->user()->id,
        ]);

        $this->storeMaterialFiles($request, $material, $validated);
        $this->recordMaterialVersion($material->fresh('files'), 'created', 'Materi dibuat.');

        return redirect()
            ->route('lecturer.course-materials.index', ['offeringId' => $offeringId])
            ->with('success', 'Materi berhasil diupload.');
    }

    public function courseMaterialsUpdate(Request $request, int $id): RedirectResponse
    {
        $material = CourseMaterial::query()->with('files')->findOrFail($id);
        $this->assignedOffering($request, $material->course_offering_id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(array_keys($this->materialCategories()))],
            'meetingNumber' => ['nullable', 'integer', 'min:1', 'max:20'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:51200', 'mimes:pdf,ppt,pptx,doc,docx,mp4,jpg,jpeg,png'],
            'videoUrl' => ['nullable', 'url', 'max:255'],
            'videoTitle' => ['nullable', 'string', 'max:255'],
            'isPublished' => ['boolean'],
        ]);

        $material->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'meeting_number' => $validated['meetingNumber'] ?? null,
            'is_published' => (bool) ($validated['isPublished'] ?? false),
            'updated_by' => $request->user()->id,
        ]);

        $this->storeMaterialFiles($request, $material, $validated);
        $this->recordMaterialVersion($material->fresh('files'), 'updated', 'Materi diperbarui.');

        return redirect()
            ->route('lecturer.course-materials.index', ['offeringId' => $material->course_offering_id])
            ->with('success', 'Materi berhasil diperbarui.');
    }

    public function courseMaterialsDestroy(Request $request, int $id): RedirectResponse
    {
        $material = CourseMaterial::query()->with('files')->findOrFail($id);
        $this->assignedOffering($request, $material->course_offering_id);
        $offeringId = $material->course_offering_id;

        foreach ($material->files as $file) {
            $this->deleteMaterialFile($file);
        }

        $material->update(['deleted_by' => $request->user()->id]);
        $material->delete();

        return redirect()
            ->route('lecturer.course-materials.index', ['offeringId' => $offeringId])
            ->with('success', 'Materi berhasil dihapus.');
    }

    public function courseMaterialsToggle(Request $request, int $id): RedirectResponse
    {
        $material = CourseMaterial::query()->findOrFail($id);
        $this->assignedOffering($request, $material->course_offering_id);

        $material->update([
            'is_published' => ! $material->is_published,
            'updated_by' => $request->user()->id,
        ]);

        $this->recordMaterialVersion(
            $material->fresh('files'),
            'status_changed',
            $material->is_published ? 'Materi dipublikasikan.' : 'Materi disimpan sebagai draft.'
        );

        return back()->with('success', $material->is_published ? 'Materi dipublikasikan.' : 'Materi disimpan sebagai draft.');
    }

    // ---------------------------------------------------------------- Assignments Create/Edit/Show
    public function assignmentsCreate(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        $assignment = $this->assignedOffering($request, $offeringId);
        $offering = $assignment->courseOffering;

        return Inertia::render('Lecturer/Assignments/Create', [
            'shell' => ShellProps::make($user, 'Akademik', 'Buat Tugas'),
            'offeringId' => $offeringId,
            'offeringInfo' => [
                'course' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-'),
                'label' => $offering->label ?? '-',
                'academicYear' => $offering->academicYear?->name ?? '-',
                'studyProgram' => $offering->studyProgram?->name ?? '-',
                'enrolledCount' => $this->enrolledQuery($offeringId)->count(),
            ],
            'defaults' => [
                'title' => '',
                'description' => '',
                'dueAt' => '',
                'maxScore' => 100,
                'allowedFileTypes' => 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,zip',
                'maxFileSizeMb' => 10,
                'allowTextSubmission' => true,
                'allowFileSubmission' => true,
                'allowResubmission' => true,
                'acceptLateSubmission' => false,
                'isPublished' => true,
            ],
            'urls' => [
                'index' => route('lecturer.assignments.index'),
                'store' => route('lecturer.assignments.store', ['offeringId' => $offeringId]),
                'show' => route('lecturer.course-offerings.show', ['id' => $offeringId]),
            ],
        ]);
    }

    public function assignmentsStore(Request $request, int $offeringId): RedirectResponse
    {
        $this->assignedOffering($request, $offeringId);
        $validated = $this->validateAssignmentPayload($request);

        $assignment = DB::transaction(function () use ($request, $offeringId, $validated) {
            $assignment = Assignment::create($this->assignmentPayload($validated, $offeringId, $request->user()->id));

            $this->storeAssignmentFiles($request, $assignment);

            AssignmentStatusHistory::create([
                'assignment_id' => $assignment->id,
                'actor_id' => $request->user()->id,
                'status' => $assignment->is_published ? 'published' : 'created',
                'note' => 'Assignment created by lecturer.',
            ]);

            return $assignment;
        });

        return redirect()
            ->route('lecturer.assignments.show', ['id' => $assignment->id])
            ->with('success', 'Tugas berhasil dibuat.');
    }

    public function assignmentsEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        $assignment = Assignment::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram', 'files'])
            ->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);

        $offering = $assignment->courseOffering;

        return Inertia::render('Lecturer/Assignments/Edit', [
            'shell' => ShellProps::make($user, 'Akademik', 'Edit Tugas'),
            'id' => $assignment->id,
            'offeringId' => $assignment->course_offering_id,
            'offeringInfo' => [
                'course' => ($offering?->course?->code ?? '-').' - '.($offering?->course?->name ?? '-'),
                'label' => $offering?->label ?? '-',
                'academicYear' => $offering?->academicYear?->name ?? '-',
                'studyProgram' => $offering?->studyProgram?->name ?? '-',
                'enrolledCount' => $this->enrolledQuery($assignment->course_offering_id)->count(),
            ],
            'assignment' => [
                'title' => $assignment->title,
                'description' => $assignment->description ?? '',
                'dueAt' => $assignment->due_at?->format('Y-m-d\TH:i'),
                'maxScore' => (float) $assignment->max_score,
                'allowedFileTypes' => implode(',', $assignment->allowedExtensions()),
                'maxFileSizeMb' => max(1, (int) ceil(($assignment->max_file_size_kb ?? 10240) / 1024)),
                'allowTextSubmission' => (bool) $assignment->allow_text_submission,
                'allowFileSubmission' => (bool) $assignment->allow_file_submission,
                'allowResubmission' => (bool) $assignment->allow_resubmission,
                'acceptLateSubmission' => (bool) ($assignment->accept_late_submission ?? $assignment->allow_late_submission),
                'isPublished' => (bool) $assignment->is_published,
                'files' => $assignment->files->map(fn (AssignmentFile $file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'url' => route('lecturer.assignments.instructions.preview', ['file' => $file->id]),
                    'deleteUrl' => route('lecturer.assignments.files.destroy', ['id' => $assignment->id, 'fileId' => $file->id]),
                ])->values()->all(),
            ],
            'urls' => [
                'index' => route('lecturer.assignments.index'),
                'show' => route('lecturer.assignments.show', ['id' => $assignment->id]),
                'update' => route('lecturer.assignments.update', ['id' => $assignment->id]),
            ],
        ]);
    }

    public function assignmentsUpdate(Request $request, int $id): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);
        $validated = $this->validateAssignmentPayload($request);

        DB::transaction(function () use ($request, $assignment, $validated) {
            $assignment->update($this->assignmentPayload($validated, $assignment->course_offering_id, $request->user()->id));

            $this->storeAssignmentFiles($request, $assignment);

            AssignmentStatusHistory::create([
                'assignment_id' => $assignment->id,
                'actor_id' => $request->user()->id,
                'status' => 'updated',
                'note' => 'Assignment updated by lecturer.',
            ]);
        });

        return redirect()
            ->route('lecturer.assignments.show', ['id' => $assignment->id])
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    public function assignmentsDestroy(Request $request, int $id): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);

        foreach ($assignment->files as $file) {
            if ($file->file_path) {
                Storage::disk('public')->delete($file->file_path);
            }
            $file->delete();
        }

        $assignment->update(['deleted_by' => $request->user()->id]);
        $assignment->delete();

        return redirect()
            ->route('lecturer.assignments.index')
            ->with('success', 'Tugas berhasil dihapus.');
    }

    public function assignmentsDestroyFile(Request $request, int $id, int $fileId): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);
        $file = AssignmentFile::query()->where('assignment_id', $assignment->id)->findOrFail($fileId);

        if ($file->file_path) {
            Storage::disk('public')->delete($file->file_path);
        }
        $file->delete();

        return back()->with('success', 'Lampiran tugas dihapus.');
    }

    public function assignmentsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $assignment = Assignment::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram', 'files'])
            ->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);

        $submissionFilter = (string) $request->query('statusFilter', 'all');
        $search = trim((string) $request->query('search', ''));
        $syncService = app(AssignmentGradeBookSyncService::class);

        $students = $this->enrolledQuery($assignment->course_offering_id)
            ->with(['studyPlan.studentProfile.user'])
            ->get()
            ->map(fn (StudyPlanDetail $detail) => $detail->studyPlan?->studentProfile)
            ->filter()
            ->unique('id')
            ->values();

        $submissionMap = $assignment->submissions()
            ->with(['studentProfile.user', 'files', 'grade'])
            ->get()
            ->keyBy('student_profile_id');

        $rows = $students->map(function ($student) use ($submissionMap, $assignment) {
            $submission = $submissionMap->get($student->id);
            $grade = $submission?->grade;
            $gradeStatus = in_array($grade?->status, ['graded', 'returned'], true) ? $grade->status : null;
            $status = $gradeStatus ?? $submission?->status ?? ($assignment->isPastDue() ? 'missing' : 'not_submitted');

            if ($status === 'submitted' && $submission?->submitted_at && $assignment->due_at && $submission->submitted_at->gt($assignment->due_at)) {
                $status = 'late';
            }

            return [
                'studentId' => $student->id,
                'submissionId' => $submission?->id,
                'name' => $student->user?->name ?? '-',
                'nim' => $student->nim ?? '-',
                'status' => $status,
                'submittedAt' => $submission?->submitted_at?->format('d M Y H:i') ?? '-',
                'content' => $submission?->content,
                'score' => $grade?->score,
                'feedback' => $grade?->feedback,
                'gradedAt' => $grade?->graded_at?->format('d M Y H:i'),
                'returnNote' => $grade?->return_note,
                'files' => $submission?->files?->map(fn ($file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'size' => $this->formatFileSize($file->file_size),
                    'url' => route('lecturer.assignments.submissions.files.preview', ['file' => $file->id]),
                ])->values()->all() ?? [],
            ];
        })
            ->when($search !== '', function ($collection) use ($search) {
                $needle = mb_strtolower($search);

                return $collection->filter(function (array $row) use ($needle) {
                    return str_contains(mb_strtolower($row['name']), $needle) || str_contains(mb_strtolower($row['nim']), $needle);
                });
            })
            ->filter(fn (array $row) => match ($submissionFilter) {
                'review' => in_array($row['status'], ['submitted', 'late'], true),
                'graded' => $row['status'] === 'graded',
                'returned' => $row['status'] === 'returned',
                'missing' => in_array($row['status'], ['missing', 'not_submitted'], true),
                default => true,
            })
            ->sortBy('name')
            ->values()
            ->all();

        $reportService = app(AssignmentReportExportService::class);

        return Inertia::render('Lecturer/Assignments/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Review Tugas'),
            'id' => $assignment->id,
            'offeringId' => $assignment->course_offering_id,
            'assignment' => [
                'title' => $assignment->title,
                'description' => $assignment->description ?? '',
                'dueAt' => $assignment->due_at?->format('d M Y H:i') ?? '-',
                'maxScore' => (float) $assignment->max_score,
                'isPublished' => (bool) $assignment->is_published,
                'isPastDue' => $assignment->isPastDue(),
                'course' => ($assignment->courseOffering?->course?->code ?? '-').' - '.($assignment->courseOffering?->course?->name ?? '-'),
                'label' => $assignment->courseOffering?->label ?? '-',
                'academicYear' => $assignment->courseOffering?->academicYear?->name ?? '-',
                'allowTextSubmission' => (bool) $assignment->allow_text_submission,
                'allowFileSubmission' => (bool) $assignment->allow_file_submission,
                'allowResubmission' => (bool) $assignment->allow_resubmission,
                'acceptLateSubmission' => (bool) ($assignment->accept_late_submission ?? $assignment->allow_late_submission),
                'allowedFileTypes' => implode(', ', $assignment->allowedExtensions()),
                'maxFileSize' => $assignment->formattedMaxFileSize(),
                'files' => $assignment->files->map(fn (AssignmentFile $file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'url' => route('lecturer.assignments.instructions.preview', ['file' => $file->id]),
                ])->values()->all(),
                'gradeBookComponent' => $syncService->componentName($assignment),
            ],
            'rows' => $rows,
            'statistics' => $reportService->statistics($reportService->rows($assignment)),
            'filters' => ['status' => $submissionFilter, 'search' => $search],
            'gradeBookWeight' => 10,
            'urls' => [
                'index' => route('lecturer.assignments.index'),
                'edit' => route('lecturer.assignments.edit', ['id' => $assignment->id]),
                'destroy' => route('lecturer.assignments.destroy', ['id' => $assignment->id]),
                'grade' => route('lecturer.assignments.grade', ['id' => $assignment->id]),
                'return' => route('lecturer.assignments.return', ['id' => $assignment->id]),
                'sync' => route('lecturer.assignments.sync-gradebook', ['id' => $assignment->id]),
                'reportCsv' => route('lecturer.assignments.report.csv', ['assignment' => $assignment->id]),
                'reportXlsx' => route('lecturer.assignments.report.xlsx', ['assignment' => $assignment->id]),
                'reportPdf' => route('lecturer.assignments.report.pdf', ['assignment' => $assignment->id]),
            ],
        ]);
    }

    public function assignmentsSaveGrade(Request $request, int $id): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);

        $validated = $request->validate([
            'submissionId' => ['required', 'integer'],
            'score' => ['required', 'numeric', 'min:0', 'max:'.(float) $assignment->max_score],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission = $assignment->submissions()->findOrFail($validated['submissionId']);

        DB::transaction(function () use ($request, $assignment, $submission, $validated) {
            AssignmentGrade::query()->updateOrCreate(
                ['assignment_submission_id' => $submission->id],
                [
                    'score' => $validated['score'],
                    'feedback' => $validated['feedback'] ?: null,
                    'status' => 'graded',
                    'graded_at' => now(),
                    'graded_by' => $request->user()->id,
                    'returned_at' => null,
                    'returned_by' => null,
                    'return_note' => null,
                ]
            );

            $submission->update(['status' => 'graded']);

            AssignmentStatusHistory::create([
                'assignment_id' => $assignment->id,
                'assignment_submission_id' => $submission->id,
                'actor_id' => $request->user()->id,
                'status' => 'graded',
                'note' => 'Submission graded.',
                'meta' => ['score' => $validated['score']],
            ]);
        });

        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    public function assignmentsReturn(Request $request, int $id): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);

        $validated = $request->validate([
            'submissionId' => ['required', 'integer'],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $submission = $assignment->submissions()->findOrFail($validated['submissionId']);

        DB::transaction(function () use ($request, $assignment, $submission, $validated) {
            AssignmentGrade::query()->updateOrCreate(
                ['assignment_submission_id' => $submission->id],
                [
                    'score' => null,
                    'feedback' => null,
                    'status' => 'returned',
                    'graded_at' => null,
                    'graded_by' => null,
                    'returned_at' => now(),
                    'returned_by' => $request->user()->id,
                    'return_note' => $validated['note'],
                ]
            );

            $submission->update(['status' => 'returned']);

            AssignmentStatusHistory::create([
                'assignment_id' => $assignment->id,
                'assignment_submission_id' => $submission->id,
                'actor_id' => $request->user()->id,
                'status' => 'returned',
                'note' => $validated['note'],
            ]);
        });

        return back()->with('success', 'Submission dikembalikan untuk revisi.');
    }

    public function assignmentsSyncGradeBook(Request $request, int $id): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($id);
        $this->assertOfferingAccess($request, $assignment->course_offering_id);

        $validated = $request->validate([
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $result = app(AssignmentGradeBookSyncService::class)->sync($assignment, (float) $validated['weight'], $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $exception->validator->errors()->first());
        }

        return back()->with('success', "Grade book disinkronkan: {$result['synced']} mahasiswa masuk komponen {$result['component_name']}.");
    }

    // ---------------------------------------------------------------- Attendance Sessions Edit
    public function attendanceSessionsEdit(Request $request, int $sessionId): Response
    {
        $user = $request->user();
        $session = AttendanceSession::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear'])
            ->findOrFail($sessionId);
        $this->assertOfferingAccess($request, $session->course_offering_id);

        $existingRecords = AttendanceRecord::query()
            ->where('attendance_session_id', $sessionId)
            ->get()
            ->keyBy('student_profile_id');

        $students = $this->enrolledQuery($session->course_offering_id)
            ->with(['studyPlan.studentProfile.user'])
            ->get()
            ->map(function (StudyPlanDetail $detail) use ($existingRecords) {
                $student = $detail->studyPlan?->studentProfile;
                if (! $student) {
                    return null;
                }

                $record = $existingRecords->get($student->id);

                return [
                    'studentProfileId' => $student->id,
                    'nim' => $student->nim ?? '-',
                    'name' => $student->user?->name ?? '-',
                    'status' => $record?->status ?? 'Absent',
                    'notes' => $record?->notes,
                ];
            })
            ->filter()
            ->unique('studentProfileId')
            ->sortBy('name')
            ->values()
            ->all();

        return Inertia::render('Lecturer/AttendanceSessions/Edit', [
            'shell' => ShellProps::make($user, 'Akademik', 'Edit Sesi Absensi'),
            'sessionId' => $session->id,
            'sessionInfo' => [
                'offeringId' => $session->course_offering_id,
                'course' => ($session->courseOffering?->course?->code ?? '-').' - '.($session->courseOffering?->course?->name ?? '-'),
                'class' => $session->courseOffering?->label ?? '-',
                'academicYear' => $session->courseOffering?->academicYear?->name ?? '-',
                'meetingNo' => $session->meeting_no,
                'meetingDate' => $session->meeting_date?->format('d M Y') ?? '-',
                'time' => $this->formatTime($session->start_time).' - '.$this->formatTime($session->end_time),
                'topic' => $session->topic ?? '',
                'status' => $session->status,
            ],
            'students' => $students,
            'statusOptions' => [
                ['value' => 'Present', 'label' => 'Hadir'],
                ['value' => 'Late', 'label' => 'Terlambat'],
                ['value' => 'Excused', 'label' => 'Izin'],
                ['value' => 'Sick', 'label' => 'Sakit'],
                ['value' => 'Absent', 'label' => 'Alpa'],
            ],
            'urls' => [
                'save' => route('lecturer.attendance-sessions.save', ['sessionId' => $session->id]),
                'back' => route('lecturer.course-offerings.attendance', ['offeringId' => $session->course_offering_id]),
            ],
        ]);
    }

    public function attendanceSessionsSave(Request $request, int $sessionId): RedirectResponse
    {
        $session = AttendanceSession::query()->findOrFail($sessionId);
        $this->assertOfferingAccess($request, $session->course_offering_id);

        $validated = $request->validate([
            'topic' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Scheduled', 'Opened', 'Closed', 'Cancelled'])],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in(['Present', 'Late', 'Excused', 'Sick', 'Absent'])],
        ]);

        DB::transaction(function () use ($request, $session, $validated) {
            $session->update([
                'topic' => $validated['topic'] ?: null,
                'status' => $validated['status'],
                'updated_by' => $request->user()->id,
            ]);

            foreach ($validated['statuses'] as $studentProfileId => $status) {
                $record = AttendanceRecord::query()->firstOrNew([
                    'attendance_session_id' => $session->id,
                    'student_profile_id' => (int) $studentProfileId,
                ]);

                if (! $record->exists) {
                    $record->created_by = $request->user()->id;
                }

                $record->status = $status;
                $record->source = 'manual_lecturer';
                $record->verification_status = 'verified';
                $record->recorded_at = now();
                $record->recorded_by = $request->user()->id;
                $record->updated_by = $request->user()->id;
                $record->save();
            }
        });

        return redirect()
            ->route('lecturer.course-offerings.attendance', ['offeringId' => $session->course_offering_id])
            ->with('success', 'Absensi mahasiswa berhasil disimpan.');
    }

    public function attendanceSessionsOpen(Request $request, int $offeringId, int $sessionId): RedirectResponse
    {
        $this->assignedOffering($request, $offeringId);
        $session = AttendanceSession::query()
            ->where('course_offering_id', $offeringId)
            ->findOrFail($sessionId);

        app(AcademicAttendanceQrService::class)->openSession($session, $request->user()->id);

        return back()->with('success', 'Absensi QR dibuka. Mahasiswa sudah bisa scan.');
    }

    public function attendanceSessionsQr(Request $request, int $offeringId, int $sessionId): \Illuminate\Http\JsonResponse
    {
        $this->assignedOffering($request, $offeringId);
        $session = AttendanceSession::query()
            ->where('course_offering_id', $offeringId)
            ->findOrFail($sessionId);

        return response()->json([
            'meetingNo' => $session->meeting_no,
            'topic' => $session->topic ?: '-',
            'status' => $session->status,
            'openedAt' => $session->opened_at?->format('H:i:s') ?? '-',
            'payload' => $session->status === 'Opened' ? app(AcademicAttendanceQrService::class)->qrPayload($session) : null,
        ]);
    }

    // ---------------------------------------------------------------- Student Grades Edit
    public function studentGradesEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        $detail = StudyPlanDetail::query()
            ->with([
                'studyPlan.studentProfile.user',
                'studyPlan.academicYear',
                'studyPlan.studentProfile.studyProgram',
                'courseOffering.course',
                'courseOffering.academicYear',
            ])
            ->findOrFail($id);
        $this->assertOfferingAccess($request, $detail->course_offering_id);

        $studentGrade = StudentGrade::query()->firstOrCreate(
            ['study_plan_detail_id' => $detail->id],
            [
                'grade_status' => 'Draft',
                'graded_by' => $user->id,
                'created_by' => $user->id,
            ],
        );
        $studentGrade->load('components');

        $allowedStatuses = $studentGrade->grade_status === 'Published' ? ['Published'] : ['Draft', 'Finalized'];
        $components = $studentGrade->components->sortBy('sort_order')->values()
            ->map(fn (StudentGradeComponent $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'weightPercentage' => $component->weight_percentage,
                'score' => $component->score,
                'sortOrder' => $component->sort_order ?? 0,
                'notes' => $component->notes,
            ])->all();

        if (count($components) === 0 && $studentGrade->grade_status !== 'Published') {
            $components = [
                ['id' => null, 'name' => 'Tugas', 'weightPercentage' => 20, 'score' => null, 'sortOrder' => 1, 'notes' => null],
                ['id' => null, 'name' => 'Kuis', 'weightPercentage' => 20, 'score' => null, 'sortOrder' => 2, 'notes' => null],
                ['id' => null, 'name' => 'UTS', 'weightPercentage' => 30, 'score' => null, 'sortOrder' => 3, 'notes' => null],
                ['id' => null, 'name' => 'UAS', 'weightPercentage' => 30, 'score' => null, 'sortOrder' => 4, 'notes' => null],
            ];
        }

        $student = $detail->studyPlan?->studentProfile;
        $offering = $detail->courseOffering;

        return Inertia::render('Lecturer/StudentGrades/Edit', [
            'shell' => ShellProps::make($user, 'Akademik', 'Edit Nilai'),
            'id' => $detail->id,
            'studentInfo' => [
                'nim' => $student?->nim ?? '-',
                'name' => $student?->user?->name ?? '-',
                'studyProgram' => $student?->studyProgram?->name ?? '-',
                'course' => ($offering?->course?->code ?? '-').' - '.($offering?->course?->name ?? '-'),
                'class' => $offering?->label ?? '-',
                'academicYear' => $detail->studyPlan?->academicYear?->name ?? $offering?->academicYear?->name ?? '-',
            ],
            'grade' => [
                'notes' => $studentGrade->notes,
                'gradeStatus' => $studentGrade->grade_status ?? 'Draft',
                'finalScore' => $studentGrade->final_score,
                'letterGrade' => $studentGrade->letter_grade,
                'gradePoint' => $studentGrade->grade_point,
                'resultStatus' => $studentGrade->result_status,
                'isPublished' => $studentGrade->grade_status === 'Published',
            ],
            'components' => $components,
            'allowedStatuses' => $allowedStatuses,
            'urls' => [
                'save' => route('lecturer.student-grades.save', ['id' => $detail->id]),
                'index' => route('lecturer.student-grades.index'),
                'offering' => route('lecturer.course-offerings.grades', ['offeringId' => $detail->course_offering_id]),
            ],
        ]);
    }

    public function studentGradesSave(Request $request, int $id): RedirectResponse
    {
        $detail = StudyPlanDetail::query()
            ->with(['studyPlan', 'studentGrade.components'])
            ->findOrFail($id);
        $this->assertOfferingAccess($request, $detail->course_offering_id);

        $studentGrade = StudentGrade::query()->firstOrCreate(
            ['study_plan_detail_id' => $detail->id],
            [
                'grade_status' => 'Draft',
                'graded_by' => $request->user()->id,
                'created_by' => $request->user()->id,
            ],
        );

        if ($studentGrade->grade_status === 'Published') {
            return back()->withErrors(['locked' => 'Nilai yang sudah Published hanya bisa dikoreksi lewat workflow keberatan nilai.']);
        }

        $allowedStatuses = ['Draft', 'Finalized'];
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'gradeStatus' => ['required', Rule::in($allowedStatuses)],
            'components' => ['required', 'array', 'min:1'],
            'components.*.id' => ['nullable', 'integer'],
            'components.*.name' => ['required', 'string', 'max:255'],
            'components.*.weightPercentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.sortOrder' => ['nullable', 'integer', 'min:0'],
            'components.*.notes' => ['nullable', 'string'],
        ]);

        $totalWeight = collect($validated['components'])->sum(fn (array $component) => (float) ($component['weightPercentage'] ?? 0));

        if ($totalWeight > 100) {
            return back()->withErrors(['components' => 'Total bobot komponen tidak boleh melebihi 100%.'])->withInput();
        }

        if ($validated['gradeStatus'] === 'Finalized' && abs($totalWeight - 100.0) > 0.0001) {
            return back()->withErrors(['gradeStatus' => 'Untuk status Finalized, total bobot komponen harus tepat 100%.'])->withInput();
        }

        if ($validated['gradeStatus'] === 'Finalized') {
            $missing = collect($validated['components'])
                ->filter(fn (array $component) => $component['score'] === null || $component['score'] === '')
                ->map(fn (array $component) => $component['name'] !== '' ? $component['name'] : '(tanpa nama)');

            if ($missing->isNotEmpty()) {
                return back()->withErrors(['gradeStatus' => 'Untuk status Finalized, semua skor wajib terisi. Masih kosong: '.$missing->implode(', ').'.'])->withInput();
            }
        }

        DB::transaction(function () use ($request, $studentGrade, $validated) {
            $studentGrade->update([
                'notes' => $validated['notes'] ?: null,
                'grade_status' => $validated['gradeStatus'],
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
                'updated_by' => $request->user()->id,
            ]);

            $existingIds = $studentGrade->components()->pluck('id')->all();
            $savedIds = [];

            foreach ($validated['components'] as $index => $row) {
                $payload = [
                    'name' => $row['name'],
                    'weight_percentage' => ($row['weightPercentage'] ?? '') !== '' ? $row['weightPercentage'] : null,
                    'score' => ($row['score'] ?? '') !== '' ? $row['score'] : null,
                    'sort_order' => ($row['sortOrder'] ?? '') !== '' ? $row['sortOrder'] : $index + 1,
                    'notes' => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
                    'updated_by' => $request->user()->id,
                ];

                if (! empty($row['id'])) {
                    $component = $studentGrade->components()->find($row['id']);
                    if ($component) {
                        $component->update($payload);
                        $savedIds[] = $component->id;
                    }

                    continue;
                }

                $component = $studentGrade->components()->create($payload + ['created_by' => $request->user()->id]);
                $savedIds[] = $component->id;
            }

            $idsToDelete = array_diff($existingIds, $savedIds);
            if (! empty($idsToDelete)) {
                $studentGrade->components()->whereIn('id', $idsToDelete)->delete();
            }

            $studentGrade->load('components');
            $snapshot = (new StudentGradeCalculator)->buildSnapshot($studentGrade);
            $studentGrade->update($snapshot + ['updated_by' => $request->user()->id]);
        });

        $studentGrade->refresh();
        $studentGrade->load('components');

        if ($studentGrade->grade_status === 'Finalized') {
            $studentProfileId = (int) ($studentGrade->studyPlanDetail?->studyPlan?->student_profile_id ?? 0);
            $academicYearId = (int) ($studentGrade->studyPlanDetail?->studyPlan?->academic_year_id ?? 0);

            if ($studentProfileId > 0) {
                app(TranscriptSyncService::class)->syncStudent($studentProfileId, $academicYearId > 0 ? $academicYearId : null);
            }
        }

        return redirect()
            ->route('lecturer.student-grades.edit', ['id' => $detail->id])
            ->with('success', 'Nilai mahasiswa berhasil disimpan.');
    }

    // ---------------------------------------------------------------- Workloads Show
    public function workloadsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $submission = LecturerWorkloadSubmission::query()
            ->with(['period', 'items', 'approvalRequest.steps'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return Inertia::render('Lecturer/Workloads/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail BKD'),
            'id' => $submission->id,
            'submission' => [
                'id' => $submission->id,
                'period' => $submission->period?->name ?? '-',
                'periodStatus' => $submission->period?->status ?? '-',
                'status' => $submission->status,
                'teachingSks' => (float) $submission->teaching_sks,
                'structuralSks' => (float) $submission->structural_sks,
                'tridharmaSks' => (float) $submission->tridharma_sks,
                'totalSks' => (float) $submission->total_sks,
                'minimumSks' => (float) ($submission->period?->minimum_sks ?? 0),
                'maximumSks' => (float) ($submission->period?->maximum_sks ?? 0),
                'notes' => $submission->lecturer_notes,
                'generatedAt' => $submission->generated_at?->format('d M Y H:i'),
                'submittedAt' => $submission->submitted_at?->format('d M Y H:i'),
                'approvedAt' => $submission->approved_at?->format('d M Y H:i'),
                'items' => $submission->items->map(fn ($item) => [
                    'id' => $item->id,
                    'category' => $item->category,
                    'title' => $item->title,
                    'sks' => (float) $item->sks,
                    'description' => $item->description,
                ])->values()->all(),
                'steps' => collect($submission->approvalRequest?->steps ?? [])->map(fn ($step) => [
                    'id' => $step->id,
                    'order' => $step->sequence ?? $step->step_order ?? null,
                    'status' => $step->status,
                    'approverRole' => $step->approver_role ?? null,
                    'actedAt' => $step->acted_at?->format('d M Y H:i'),
                ])->values()->all(),
            ],
            'urls' => [
                'index' => route('lecturer.workloads.index'),
                'submit' => route('lecturer.workloads.submit', ['id' => $submission->id]),
                'regenerate' => route('lecturer.workloads.regenerate', ['id' => $submission->id]),
            ],
        ]);
    }

    public function workloadsSubmit(Request $request, int $id): RedirectResponse
    {
        $submission = LecturerWorkloadSubmission::query()->where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        app(LecturerWorkloadService::class)->submit($submission, $request->user(), $validated['notes'] ?? '');

        return back()->with('success', 'BKD berhasil diajukan.');
    }

    public function workloadsRegenerate(Request $request, int $id): RedirectResponse
    {
        $submission = LecturerWorkloadSubmission::query()->with('period')->where('user_id', $request->user()->id)->findOrFail($id);

        app(LecturerWorkloadService::class)->generateFor($request->user(), $submission->period);

        return back()->with('success', 'Draf BKD berhasil disiapkan ulang dari data terbaru.');
    }

    // ---------------------------------------------------------------- Academic Advising Show
    public function academicAdvisingShow(Request $request, int $assignmentId): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $advisorService = app(\App\Support\AcademicAdvisorService::class);
        $analytics = app(\App\Support\StudentProgressAnalyticsService::class);

        $advisorAssignment = \App\Models\Academic\AcademicAdvisorAssignment::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram.faculty', 'academicYear'])
            ->whereKey($assignmentId)
            ->where('lecturer_profile_id', $profile?->id)
            ->where('is_active', true)
            ->first();

        $assignment = [];
        $pendingPlans = [];
        $progress = [];
        $notes = [];
        if ($advisorAssignment && $advisorAssignment->studentProfile) {
            $student = $advisorAssignment->studentProfile;
            $assignment = [
                'studentName' => $student->user?->name ?? '-',
                'nim' => $student->nim ?? '-',
                'studyProgram' => $student->studyProgram?->name ?? '-',
                'faculty' => $student->studyProgram?->faculty?->name ?? '-',
                'semester' => $student->current_semester ?? '-',
                'academicStatus' => $student->academic_status ?? '-',
                'academicYear' => $advisorAssignment->academicYear?->name ?? 'Umum',
                'startDate' => $advisorAssignment->start_date?->format('d M Y') ?? '-',
                'endDate' => $advisorAssignment->end_date?->format('d M Y') ?? 'Aktif',
            ];
            $progress = $analytics->summarize($student);
            $pendingPlans = $advisorService->pendingPlansForStudent($student->id)
                ->map(fn ($plan) => [
                    'id' => $plan->id,
                    'year' => $plan->academicYear?->name ?? '-',
                    'semester' => $plan->semester_no ?? '-',
                    'courses' => (int) $plan->details_count,
                    'credits' => (int) $plan->total_credits,
                    'submittedAt' => $plan->submitted_at?->format('d M Y H:i') ?? '-',
                ])->values()->all();
            $notes = \App\Models\Academic\StudentAdvisorNote::query()
                ->where('academic_advisor_assignment_id', $advisorAssignment->id)
                ->where('lecturer_profile_id', $profile->id)
                ->latest()->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'topic' => $n->topic,
                    'notes' => $n->notes,
                    'recommendation' => $n->recommendation,
                    'followUpAt' => $n->follow_up_at?->format('d M Y'),
                    'status' => $n->status,
                    'visibleToStudent' => (bool) $n->visible_to_student,
                    'createdAt' => $n->created_at?->format('d M Y H:i'),
                ])->values()->all();
        }

        return Inertia::render('Lecturer/AcademicAdvising/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail Bimbingan'),
            'assignmentId' => $assignmentId,
            'assignment' => $assignment,
            'progress' => $progress,
            'pendingPlans' => $pendingPlans,
            'notes' => $notes,
            'urls' => [
                'index' => route('lecturer.academic-advising.index'),
                'approve' => route('lecturer.academic-advising.approve', ['assignmentId' => $assignmentId]),
                'reject' => route('lecturer.academic-advising.reject', ['assignmentId' => $assignmentId]),
                'note' => route('lecturer.academic-advising.notes.store', ['assignmentId' => $assignmentId]),
                'noteDone' => route('lecturer.academic-advising.notes.done', ['assignmentId' => $assignmentId, 'noteId' => '__NOTE__']),
            ],
        ]);
    }

    public function academicAdvisingApprove(Request $request, int $assignmentId): RedirectResponse
    {
        return $this->decideStudyPlan($request, $assignmentId, 'Approved');
    }

    public function academicAdvisingReject(Request $request, int $assignmentId): RedirectResponse
    {
        return $this->decideStudyPlan($request, $assignmentId, 'Rejected');
    }

    public function academicAdvisingStoreNote(Request $request, int $assignmentId): RedirectResponse
    {
        $advisorAssignment = $this->advisorAssignment($request, $assignmentId);

        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:180'],
            'notes' => ['required', 'string', 'max:4000'],
            'recommendation' => ['nullable', 'string', 'max:4000'],
            'followUpAt' => ['nullable', 'date'],
            'visibleToStudent' => ['boolean'],
        ]);

        StudentAdvisorNote::create([
            'academic_advisor_assignment_id' => $advisorAssignment->id,
            'student_profile_id' => $advisorAssignment->student_profile_id,
            'lecturer_profile_id' => $advisorAssignment->lecturer_profile_id,
            'topic' => $validated['topic'],
            'notes' => $validated['notes'],
            'recommendation' => $validated['recommendation'] ?? null,
            'follow_up_at' => $validated['followUpAt'] ?? null,
            'status' => 'Open',
            'visible_to_student' => (bool) ($validated['visibleToStudent'] ?? false),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Catatan bimbingan disimpan.');
    }

    public function academicAdvisingCompleteNote(Request $request, int $assignmentId, int $noteId): RedirectResponse
    {
        $advisorAssignment = $this->advisorAssignment($request, $assignmentId);

        StudentAdvisorNote::query()
            ->whereKey($noteId)
            ->where('academic_advisor_assignment_id', $advisorAssignment->id)
            ->where('lecturer_profile_id', $advisorAssignment->lecturer_profile_id)
            ->update(['status' => 'Done', 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Follow-up ditandai selesai.');
    }

    public function announcementsIndex(Request $request): Response
    {
        $user = $request->user();
        $tab = (string) $request->query('tab', 'inbox');
        $search = trim((string) $request->query('search', ''));
        $priority = (string) $request->query('filterPriority', '');
        $perPage = (int) $request->query('perPage', 10);
        if (! in_array($perPage, [10, 20, 50], true)) $perPage = 10;

        if ($tab === 'mine') {
            $query = Announcement::query()->with('creator')->where('created_by', $user->id);
        } else {
            $tab = 'inbox';
            $query = Announcement::queryForLecturer($user)->with('creator');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }
        if ($priority !== '') {
            $query->where('priority', $priority);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $rows = collect($paginator->items())->map(function (Announcement $a) use ($user) {
            return [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => str($a->content ?? '')->stripTags()->squish()->limit(180)->toString(),
                'priority' => $a->priority?->value,
                'priorityLabel' => $a->priority?->label() ?? '-',
                'isPinned' => (bool) $a->is_pinned,
                'creator' => $a->creator?->name ?? '-',
                'publishedAt' => $a->published_at?->format('d M Y H:i') ?? '-',
                'hasAttachment' => filled($a->attachment_name),
                'url' => route('lecturer.announcements.show', ['id' => $a->id]),
                'isMine' => (int) $a->created_by === (int) $user->id,
                'editUrl' => (int) $a->created_by === (int) $user->id ? route('lecturer.announcements.edit', ['id' => $a->id]) : null,
            ];
        })->values()->all();

        $inboxCount = Announcement::queryForLecturer($user)->count();
        $mineCount = Announcement::query()->where('created_by', $user->id)->count();
        $unreadCount = Announcement::unreadCountForLecturer($user);

        return Inertia::render('Lecturer/Announcements/Index', [
            'shell' => ShellProps::make($user, 'Publikasi', 'Pengumuman'),
            'tab' => $tab,
            'rows' => $rows,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => ['search' => $search, 'priority' => $priority, 'perPage' => $perPage],
            'counts' => ['inbox' => $inboxCount, 'mine' => $mineCount, 'unread' => $unreadCount],
            'urls' => [
                'index' => route('lecturer.announcements.index'),
                'create' => route('lecturer.announcements.create'),
            ],
        ]);
    }

    public function announcementsCreate(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;

        $courseOfferings = [];
        if ($profile) {
            $courseOfferings = CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)
                ->where('is_active', true)
                ->with(['courseOffering.course', 'courseOffering.academicYear'])
                ->get()
                ->map(fn ($col) => [
                    'id' => $col->courseOffering->id,
                    'label' => ($col->courseOffering->course?->code ?? '-').' - '.($col->courseOffering->course?->name ?? '-').' ('.($col->courseOffering->academicYear?->name ?? '-').')',
                ])->values()->all();
        }

        return Inertia::render('Lecturer/Announcements/Create', [
            'shell' => ShellProps::make($user, 'Publikasi', 'Buat Pengumuman'),
            'courseOfferings' => $courseOfferings,
            'priorities' => collect(\App\Enums\AnnouncementPriority::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values()->all(),
            'urls' => [
                'index' => route('lecturer.announcements.index'),
                'store' => route('lecturer.announcements.store'),
            ],
        ]);
    }

    public function announcementsStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'targetId' => 'required|integer',
            'priority' => 'required|in:'.implode(',', array_column(\App\Enums\AnnouncementPriority::cases(), 'value')),
            'isPinned' => 'boolean',
            'isPublished' => 'boolean',
            'publishedAt' => 'nullable|date',
            'scheduledAt' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ]);

        $data = [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'target_type' => \App\Enums\AnnouncementTargetType::COURSE_OFFERING->value,
            'target_id' => $validated['targetId'],
            'created_by' => $user->id,
            'priority' => $validated['priority'],
            'is_pinned' => (bool) ($validated['isPinned'] ?? false),
            'is_published' => (bool) ($validated['isPublished'] ?? false),
            'published_at' => ! empty($validated['isPublished']) ? ($validated['publishedAt'] ?? now()) : null,
            'scheduled_at' => $validated['scheduledAt'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $year = now()->format('Y');
            $month = now()->format('m');
            $path = $file->store("announcements/{$year}/{$month}", 'public');
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_type'] = $file->getClientOriginalExtension();
            $data['attachment_size'] = $file->getSize();
        }

        Announcement::create($data);

        return redirect()->route('lecturer.announcements.index')->with('success', 'Pengumuman berhasil dibuat!');
    }

    public function announcementsEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        $announcement = Announcement::where('id', $id)->where('created_by', $user->id)->firstOrFail();

        $profile = $user->lecturerProfile;
        $courseOfferings = [];
        if ($profile) {
            $courseOfferings = CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)
                ->where('is_active', true)
                ->with(['courseOffering.course', 'courseOffering.academicYear'])
                ->get()
                ->map(fn ($col) => [
                    'id' => $col->courseOffering->id,
                    'label' => ($col->courseOffering->course?->code ?? '-').' - '.($col->courseOffering->course?->name ?? '-').' ('.($col->courseOffering->academicYear?->name ?? '-').')',
                ])->values()->all();
        }

        return Inertia::render('Lecturer/Announcements/Edit', [
            'shell' => ShellProps::make($user, 'Publikasi', 'Edit Pengumuman'),
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'targetId' => $announcement->target_id,
                'priority' => $announcement->priority?->value,
                'isPinned' => (bool) $announcement->is_pinned,
                'isPublished' => (bool) $announcement->is_published,
                'publishedAt' => $announcement->published_at?->format('Y-m-d\TH:i'),
                'scheduledAt' => $announcement->scheduled_at?->format('Y-m-d\TH:i'),
                'attachmentName' => $announcement->attachment_name,
                'attachmentPath' => $announcement->attachment_path,
            ],
            'courseOfferings' => $courseOfferings,
            'priorities' => collect(\App\Enums\AnnouncementPriority::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values()->all(),
            'urls' => [
                'index' => route('lecturer.announcements.index'),
                'show' => route('lecturer.announcements.show', ['id' => $announcement->id]),
                'update' => route('lecturer.announcements.update', ['id' => $announcement->id]),
            ],
        ]);
    }

    public function announcementsUpdate(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $announcement = Announcement::where('id', $id)->where('created_by', $user->id)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'targetId' => 'required|integer',
            'priority' => 'required|in:'.implode(',', array_column(\App\Enums\AnnouncementPriority::cases(), 'value')),
            'isPinned' => 'boolean',
            'isPublished' => 'boolean',
            'publishedAt' => 'nullable|date',
            'scheduledAt' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'removeAttachment' => 'boolean',
        ]);

        $announcement->title = $validated['title'];
        $announcement->content = $validated['content'];
        $announcement->target_id = $validated['targetId'];
        $announcement->priority = $validated['priority'];
        $announcement->is_pinned = (bool) ($validated['isPinned'] ?? false);
        $announcement->is_published = (bool) ($validated['isPublished'] ?? false);
        $announcement->published_at = ! empty($validated['isPublished']) ? ($validated['publishedAt'] ?? now()) : null;
        $announcement->scheduled_at = $validated['scheduledAt'] ?? null;

        if (! empty($validated['removeAttachment']) && $announcement->attachment_path) {
            Storage::disk('public')->delete($announcement->attachment_path);
            $announcement->attachment_path = null;
            $announcement->attachment_name = null;
            $announcement->attachment_type = null;
            $announcement->attachment_size = null;
        }

        if ($request->hasFile('attachment')) {
            if ($announcement->attachment_path) Storage::disk('public')->delete($announcement->attachment_path);
            $file = $request->file('attachment');
            $year = now()->format('Y');
            $month = now()->format('m');
            $path = $file->store("announcements/{$year}/{$month}", 'public');
            $announcement->attachment_path = $path;
            $announcement->attachment_name = $file->getClientOriginalName();
            $announcement->attachment_type = $file->getClientOriginalExtension();
            $announcement->attachment_size = $file->getSize();
        }

        $announcement->save();

        return redirect()->route('lecturer.announcements.index')->with('success', 'Pengumuman berhasil diperbarui!');
    }

    public function announcementsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        $announcement = Announcement::queryForLecturer($user)->with('creator')->whereKey($id)->first();
        if (! $announcement) {
            // allow own
            $announcement = Announcement::where('id', $id)->where('created_by', $user->id)->with('creator')->first();
        }
        abort_unless($announcement, 404);

        return Inertia::render('Lecturer/Announcements/Show', [
            'shell' => ShellProps::make($user, 'Publikasi', 'Detail Pengumuman'),
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => (string) ($announcement->content ?? ''),
                'priority' => $announcement->priority?->value,
                'priorityLabel' => $announcement->priority?->label() ?? '-',
                'isPinned' => (bool) $announcement->is_pinned,
                'creator' => $announcement->creator?->name ?? '-',
                'publishedAt' => $announcement->published_at?->format('d M Y, H:i') ?? '-',
                'attachment' => $announcement->attachment_path === null ? null : [
                    'name' => $announcement->attachment_name ?? 'attachment',
                    'type' => strtoupper((string) ($announcement->attachment_type ?? 'file')),
                    'size' => $announcement->formatted_attachment_size ?? '-',
                    'url' => Storage::disk('public')->url($announcement->attachment_path),
                ],
            ],
            'urls' => [
                'index' => route('lecturer.announcements.index'),
                'edit' => (int) $announcement->created_by === (int) $user->id ? route('lecturer.announcements.edit', ['id' => $announcement->id]) : null,
            ],
        ]);
    }

    public function announcementsDestroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $announcement = Announcement::where('id', $id)->where('created_by', $user->id)->first();
        if (! $announcement) {
            return back()->with('error', 'Pengumuman tidak ditemukan atau bukan milik Anda.');
        }
        if ($announcement->attachment_path && Storage::disk('public')->exists($announcement->attachment_path)) {
            Storage::disk('public')->delete($announcement->attachment_path);
        }
        $announcement->update(['deleted_by' => $user->id]);
        $announcement->delete();
        return redirect()->route('lecturer.announcements.index')->with('success', 'Pengumuman berhasil dihapus.');
    }

    // ---------------------------------------------------------------- Calendar
    // ---------------------------------------------------------------- Course Offerings
    // ---------------------------------------------------------------- Consultations
    // ---------------------------------------------------------------- Course Materials List
    // ---------------------------------------------------------------- Assignments Index
    // ---------------------------------------------------------------- Student Grades Index
    // ---------------------------------------------------------------- EDOM Index
    public function edomIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile()->with(['studyProgram', 'faculty'])->first();
        $hasProfile = (bool) $profile;

        $periodOptions = [];
        $summary = ['average' => null, 'responses' => 0, 'available' => false, 'minimum' => 0, 'periodStatus' => '-'];
        $courseRows = [];
        $comments = [];
        $review = [];
        $selectedPeriodId = null;

        if ($profile) {
            $periodIds = \App\Models\Organization\EdomResponse::query()->where('lecturer_profile_id', $profile->id)->pluck('edom_period_id')
                ->merge(\App\Models\Organization\LecturerPerformanceReview::query()->where('lecturer_profile_id', $profile->id)->whereNotNull('edom_period_id')->pluck('edom_period_id'))
                ->filter()->unique()->values();
            $periods = \App\Models\Organization\EdomPeriod::query()->whereIn('id', $periodIds)->orderByDesc('starts_at')->orderByDesc('id')->get();
            $periodOptions = $periods->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'status' => $p->status])->values()->all();
            $requestedPeriod = (int) $request->query('period', 0);
            $selectedPeriodId = $periods->firstWhere('id', $requestedPeriod)?->id ?? $periods->first()?->id;

            if ($selectedPeriodId) {
                $period = \App\Models\Organization\EdomPeriod::query()->find($selectedPeriodId);
                if ($period) {
                    $aggregate = app(\App\Support\Organization\EdomService::class)->lecturerAggregate($profile->id, $period);
                    $summary = [
                        'average' => $aggregate['average'] !== null ? number_format((float) $aggregate['average'], 2) : null,
                        'responses' => $aggregate['responses'],
                        'available' => (bool) $aggregate['available'],
                        'minimum' => (int) $period->minimum_responses,
                        'periodStatus' => $period->status,
                    ];
                    $rev = \App\Models\Organization\LecturerPerformanceReview::query()
                        ->where('lecturer_profile_id', $profile->id)
                        ->where('edom_period_id', $period->id)
                        ->latest('calculated_at')->first();
                    if ($rev) {
                        $review = [
                            'finalScore' => $rev->final_score ? number_format((float) $rev->final_score, 2) : '-',
                            'teaching' => $rev->teaching_compliance_score ? number_format((float) $rev->teaching_compliance_score, 2).'%' : '-',
                            'attendance' => $rev->attendance_compliance_score ? number_format((float) $rev->attendance_compliance_score, 2).'%' : '-',
                            'workload' => $rev->workload_total_sks ? number_format((float) $rev->workload_total_sks, 2).' SKS' : '-',
                            'status' => $rev->status,
                            'calculatedAt' => $rev->calculated_at?->format('d M Y H:i') ?? '-',
                        ];
                    }
                    if ($aggregate['available']) {
                        $responses = \App\Models\Organization\EdomResponse::query()
                            ->where('lecturer_profile_id', $profile->id)
                            ->where('edom_period_id', $period->id)
                            ->with(['courseOffering.course', 'courseOffering.academicYear', 'answers.question'])
                            ->get();
                        $courseRows = $responses->groupBy('course_offering_id')->map(function ($items) {
                            $first = $items->first();
                            $scores = $items->flatMap(fn ($r) => $r->answers->pluck('score')->filter());
                            return [
                                'course' => trim(($first->courseOffering?->course?->code ? $first->courseOffering->course->code.' - ' : '').($first->courseOffering?->course?->name ?? '-')),
                                'label' => $first->courseOffering?->label ?? '-',
                                'academicYear' => $first->courseOffering?->academicYear?->name ?? '-',
                                'responses' => $items->count(),
                                'average' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null,
                            ];
                        })->sortByDesc('average')->values()->all();
                        $comments = collect($aggregate['comments'] ?? [])->take(12)->values()->all();
                    }
                }
            }
        }

        return Inertia::render('Lecturer/Edom/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Hasil EDOM Saya'),
            'hasProfile' => $hasProfile,
            'lecturerInfo' => $hasProfile ? ['name' => $user->name, 'nidn' => $profile->nidn ?? '-', 'studyProgram' => $profile->studyProgram?->name ?? '-', 'faculty' => $profile->faculty?->name ?? '-'] : [],
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $selectedPeriodId,
            'summary' => $summary,
            'courseRows' => $courseRows,
            'comments' => $comments,
            'review' => $review,
            'urls' => ['index' => route('lecturer.edom.index')],
        ]);
    }

    // ---------------------------------------------------------------- Workloads Index
    public function workloadsIndex(Request $request): Response
    {
        $user = $request->user();
        $periods = \App\Models\Organization\LecturerWorkloadPeriod::query()->whereIn('status', ['open', 'review'])->latest('starts_at')->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'status' => $p->status])->values()->all();
        $submissions = LecturerWorkloadSubmission::query()->where('user_id', $user->id)->with('period')->latest('updated_at')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'period' => $s->period?->name ?? '-',
                'periodId' => $s->lecturer_workload_period_id,
                'status' => $s->status,
                'teachingSks' => (float) $s->teaching_sks,
                'structuralSks' => (float) $s->structural_sks,
                'tridharmaSks' => (float) $s->tridharma_sks,
                'totalSks' => (float) $s->total_sks,
                'updatedAt' => $s->updated_at?->format('d M Y H:i'),
                'url' => route('lecturer.workloads.show', ['id' => $s->id]),
            ])->values()->all();

        $requestedPeriod = (string) $request->query('period', '');
        $selectedPeriodId = collect($periods)->contains(fn ($period) => (string) $period['id'] === $requestedPeriod)
            ? $requestedPeriod
            : (string) (collect($periods)->first()['id'] ?? '');

        return Inertia::render('Lecturer/Workloads/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Beban Kerja'),
            'periods' => $periods,
            'submissions' => $submissions,
            'selectedPeriodId' => $selectedPeriodId,
            'stats' => [
                'total' => count($submissions),
                'inApproval' => collect($submissions)->where('status', 'in_approval')->count(),
                'approved' => collect($submissions)->where('status', 'approved')->count(),
                'latestSks' => (float) (collect($submissions)->first()['totalSks'] ?? 0),
            ],
            'canGenerate' => $selectedPeriodId !== '',
            'urls' => [
                'index' => route('lecturer.workloads.index'),
                'generate' => route('lecturer.workloads.generate'),
            ],
        ]);
    }

    public function workloadsGenerate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periodId' => ['required', 'integer'],
        ]);

        $period = \App\Models\Organization\LecturerWorkloadPeriod::query()
            ->whereIn('status', ['open', 'review'])
            ->findOrFail($validated['periodId']);

        try {
            $submission = app(LecturerWorkloadService::class)->generateFor($request->user(), $period);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $exception->validator->errors()->first());
        }

        return redirect()
            ->route('lecturer.workloads.show', ['id' => $submission->id])
            ->with('success', 'Draf BKD berhasil disiapkan dari data terbaru.');
    }

    // ---------------------------------------------------------------- Grade Appeals Index
    public function gradeAppealsIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $hasProfile = (bool) $profile;
        $statusFilter = (string) $request->query('statusFilter', 'active');
        $appealRows = [];
        $summary = ['submitted' => 0, 'under_review' => 0, 'resolved' => 0];

        if ($profile) {
            $offeringIds = \App\Models\Academic\CourseOfferingLecturer::query()->where('lecturer_profile_id', $profile->id)->where('is_active', true)->pluck('course_offering_id');
            $base = \App\Models\Academic\GradeAppeal::query()->where(function ($q) use ($profile, $offeringIds) {
                $q->where('lecturer_profile_id', $profile->id)->orWhereIn('course_offering_id', $offeringIds);
            });
            $summaryRows = (clone $base)->get(['status']);
            $summary = [
                'submitted' => $summaryRows->where('status', 'submitted')->count(),
                'under_review' => $summaryRows->where('status', 'under_review')->count(),
                'resolved' => $summaryRows->whereIn('status', ['approved', 'rejected', 'closed'])->count(),
            ];
            $query = (clone $base)->with(['studentProfile.user', 'studentGrade', 'courseOffering.course', 'courseOffering.academicYear', 'attachments']);
            if ($statusFilter === 'active') {
                $query->whereIn('status', ['submitted', 'under_review']);
            } elseif ($statusFilter !== 'all') {
                $query->where('status', $statusFilter);
            }
            $appealRows = $query->latest('submitted_at')->latest('id')->get()->map(function ($appeal) {
                return [
                    'id' => $appeal->id,
                    'student' => $appeal->studentProfile?->user?->name ?? '-',
                    'nim' => $appeal->studentProfile?->nim ?? '-',
                    'course' => trim(($appeal->courseOffering?->course?->code ? $appeal->courseOffering->course->code.' - ' : '').($appeal->courseOffering?->course?->name ?? '-')),
                    'academicYear' => $appeal->courseOffering?->academicYear?->name ?? '-',
                    'status' => $appeal->status,
                    'category' => $appeal->reason_category,
                    'reason' => $appeal->reason,
                    'expectedOutcome' => $appeal->expected_outcome,
                    'lecturerResponse' => $appeal->lecturer_response,
                    'originalScore' => $appeal->original_score,
                    'requestedScore' => $appeal->requested_score,
                    'currentScore' => $appeal->studentGrade?->final_score,
                    'resolvedScore' => $appeal->resolved_score,
                    'submittedAt' => $appeal->submitted_at?->format('d M Y H:i') ?? '-',
                    'reviewedAt' => $appeal->reviewed_at?->format('d M Y H:i'),
                    'resolvedAt' => $appeal->resolved_at?->format('d M Y H:i'),
                    'canResolve' => in_array($appeal->status, ['submitted', 'under_review'], true),
                    'attachments' => $appeal->attachments->map(fn ($attachment) => [
                        'id' => $attachment->id,
                        'name' => $attachment->file_name ?? basename((string) $attachment->file_path),
                        'url' => route('lecturer.grade-appeal-attachments.preview', ['attachment' => $attachment->id]),
                    ])->values()->all(),
                ];
            })->values()->all();
        }

        return Inertia::render('Lecturer/GradeAppeals/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Keberatan Nilai'),
            'hasProfile' => $hasProfile,
            'statusFilter' => $statusFilter,
            'appealRows' => $appealRows,
            'summary' => $summary,
            'urls' => [
                'index' => route('lecturer.grade-appeals.index'),
                'resolve' => route('lecturer.grade-appeals.resolve', ['id' => '__APPEAL__']),
            ],
        ]);
    }

    public function gradeAppealsResolve(Request $request, int $id): RedirectResponse
    {
        $appeal = $this->authorizedAppeal($request, $id);

        if (! in_array($appeal->status, ['submitted', 'under_review'], true)) {
            return back()->with('error', 'Keberatan nilai ini sudah diproses.');
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'lecturerResponse' => ['required', 'string', 'min:10', 'max:4000'],
            'resolvedScore' => [$request->input('decision') === 'approved' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'lecturerResponse.min' => 'Berikan respons minimal 10 karakter.',
            'resolvedScore.required' => 'Isi skor koreksi saat menyetujui pengajuan.',
        ]);

        DB::transaction(function () use ($request, $appeal, $validated) {
            $resolvedScore = $validated['decision'] === 'approved' ? (float) $validated['resolvedScore'] : null;

            if ($validated['decision'] === 'approved' && $appeal->studentGrade) {
                $calculator = app(StudentGradeCalculator::class);
                $letterGrade = $calculator->resolveLetterGrade($resolvedScore);

                $appeal->studentGrade->update([
                    'final_score' => $resolvedScore,
                    'letter_grade' => $letterGrade,
                    'grade_point' => $calculator->resolveGradePoint($letterGrade),
                    'result_status' => $calculator->resolveResultStatus($letterGrade),
                    'graded_by' => $request->user()->id,
                    'graded_at' => now(),
                    'updated_by' => $request->user()->id,
                ]);
            }

            $appeal->update([
                'status' => $validated['decision'],
                'lecturer_response' => $validated['lecturerResponse'],
                'resolved_score' => $resolvedScore,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => $appeal->reviewed_at ?? now(),
                'resolved_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Keputusan keberatan nilai berhasil disimpan.');
    }

    // ---------------------------------------------------------------- Academic Advising Index
    public function academicAdvisingIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $search = trim((string) $request->query('search', ''));
        $students = [];
        $stats = ['totalStudents' => 0, 'generalAssignments' => 0, 'yearAssignments' => 0, 'studyPrograms' => 0, 'openNotes' => 0];
        $hasAccess = false;
        if ($profile) {
            $service = app(\App\Support\AcademicAdvisorService::class);
            if ($service->hasActiveAssignmentsForLecturer($profile->id)) {
                $hasAccess = true;
                $assignments = $service->assignedStudentsForLecturer($profile->id);
                $stats = [
                    'totalStudents' => $assignments->count(),
                    'generalAssignments' => $assignments->whereNull('academic_year_id')->count(),
                    'yearAssignments' => $assignments->whereNotNull('academic_year_id')->count(),
                    'studyPrograms' => $assignments->pluck('studentProfile.study_program_id')->filter()->unique()->count(),
                    'openNotes' => \App\Models\Academic\StudentAdvisorNote::query()->where('lecturer_profile_id', $profile->id)->where('status', 'Open')->count(),
                ];
                $filtered = $assignments;
                if ($search !== '') {
                    $s = mb_strtolower($search);
                    $filtered = $assignments->filter(function ($a) use ($s) {
                        $student = $a->studentProfile;
                        $haystack = mb_strtolower(($student?->nim ?? '').' '.($student?->user?->name ?? '').' '.($student?->studyProgram?->name ?? ''));
                        return str_contains($haystack, $s);
                    });
                }
                $students = $filtered->map(fn ($a) => [
                    'id' => $a->id,
                    'studentName' => $a->studentProfile?->user?->name ?? '-',
                    'nim' => $a->studentProfile?->nim ?? '-',
                    'studyProgram' => $a->studentProfile?->studyProgram?->name ?? '-',
                    'semester' => $a->studentProfile?->current_semester ?? '-',
                    'academicStatus' => $a->studentProfile?->academic_status ?? '-',
                    'academicYear' => $a->academicYear?->name ?? 'Umum',
                    'startDate' => $a->start_date?->format('d M Y') ?? '-',
                    'endDate' => $a->end_date?->format('d M Y') ?? 'Aktif',
                    'url' => route('lecturer.academic-advising.show', ['assignmentId' => $a->id]),
                ])->values()->all();
            }
        }
        return Inertia::render('Lecturer/AcademicAdvising/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Bimbingan Akademik'),
            'hasAccess' => $hasAccess,
            'students' => $students,
            'stats' => $stats,
            'search' => $search,
            'urls' => ['index' => route('lecturer.academic-advising.index')],
        ]);
    }

    // ---------------------------------------------------------------- Grade Book
    public function studentGradesGradeBook(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $search = trim((string) $request->query('search', ''));
        $courseOfferingId = (string) $request->query('course_offering_id', '');
        $academicYearId = (string) $request->query('academic_year_id', '');
        $semesterNo = (string) $request->query('semester_no', '');

        $rows = [];
        $statistics = [];
        $distribution = [];
        $courseOptions = [];
        $yearOptions = [];
        $semesterOptions = [];

        if ($profile) {
            $offeringIds = CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)
                ->where('is_active', true)
                ->pluck('course_offering_id');

            $offerings = CourseOffering::query()
                ->with(['course', 'academicYear'])
                ->whereIn('id', $offeringIds)
                ->orderByDesc('created_at')
                ->get();

            $courseOptions = $offerings->map(fn (CourseOffering $offering) => [
                'id' => (string) $offering->id,
                'label' => ($offering->course?->code ?? '-').' - '.($offering->course?->name ?? '-').' / '.($offering->label ?? '-'),
            ])->values()->all();

            $yearOptions = \App\Models\Academic\AcademicYear::query()
                ->whereIn('id', $offerings->pluck('academic_year_id')->filter()->unique())
                ->orderByDesc('start_date')
                ->get(['id', 'name'])
                ->map(fn ($year) => ['id' => (string) $year->id, 'label' => $year->name])
                ->values()->all();

            $semesterOptions = $offerings->pluck('semester_no')->filter()->unique()->sort()
                ->map(fn ($semester) => ['id' => (string) $semester, 'label' => 'Semester '.$semester])
                ->values()->all();

            $service = app(GradeBookExportService::class);
            $collection = $service->lecturerRows($user, [
                'course_offering_id' => $courseOfferingId,
                'academic_year_id' => $academicYearId,
                'semester_no' => $semesterNo,
                'search' => $search,
            ]);

            $rows = $collection->values()->all();
            $statistics = $service->statistics($collection);
            $distribution = $service->distribution($collection)->values()->all();
        }

        $exportQuery = array_filter([
            'course_offering_id' => $courseOfferingId,
            'academic_year_id' => $academicYearId,
            'semester_no' => $semesterNo,
            'search' => $search,
        ], fn ($value) => filled($value));

        return Inertia::render('Lecturer/StudentGrades/GradeBook', [
            'shell' => ShellProps::make($user, 'Akademik', 'Buku Nilai'),
            'rows' => $rows,
            'statistics' => $statistics,
            'distribution' => $distribution,
            'courseOptions' => $courseOptions,
            'yearOptions' => $yearOptions,
            'semesterOptions' => $semesterOptions,
            'filters' => [
                'search' => $search,
                'courseOfferingId' => $courseOfferingId,
                'academicYearId' => $academicYearId,
                'semesterNo' => $semesterNo,
            ],
            'urls' => [
                'index' => route('lecturer.student-grades.grade-book'),
                'gradesIndex' => route('lecturer.student-grades.index'),
                'exportCsv' => route('lecturer.student-grades.grade-book.export.csv', $exportQuery),
                'exportXlsx' => route('lecturer.student-grades.grade-book.export.xlsx', $exportQuery),
                'exportPdf' => route('lecturer.student-grades.grade-book.export.pdf', $exportQuery),
            ],
        ]);
    }

    public function studentGradesIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $search = trim((string) $request->query('search', ''));
        $gradeRows = [];
        if ($profile) {
            $offeringIds = \App\Models\Academic\CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)->where('is_active', true)
                ->pluck('course_offering_id');
            if (! $offeringIds->isEmpty()) {
                $rows = \App\Models\Academic\StudyPlanDetail::query()
                    ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram', 'studyPlan.studentProfile.user', 'studentGrade.components'])
                    ->whereIn('course_offering_id', $offeringIds)
                    ->whereHas('studyPlan', fn ($q) => $q->where('status', 'Approved'))
                    ->where('status', 'Taken')
                    ->get()
                    ->map(function ($detail) {
                        $student = $detail->studyPlan?->studentProfile;
                        $offering = $detail->courseOffering;
                        $grade = $detail->studentGrade;
                        return [
                            'studyPlanDetailId' => $detail->id,
                            'nim' => $student?->nim ?? '-',
                            'studentName' => $student?->user?->name ?? '-',
                            'course' => ($offering?->course?->code ?? '-') . ' - ' . ($offering?->course?->name ?? '-'),
                            'class' => $offering?->label ?? '-',
                            'academicYear' => $offering?->academicYear?->name ?? '-',
                            'studyProgram' => $offering?->studyProgram?->name ?? '-',
                            'finalScore' => $grade?->final_score,
                            'letterGrade' => $grade?->letter_grade ?? '-',
                            'gradePoint' => $grade?->grade_point,
                            'gradeStatus' => $grade?->grade_status ?? 'Draft',
                            'componentCount' => $grade?->components?->count() ?? 0,
                            'editUrl' => route('lecturer.student-grades.edit', ['id' => $detail->id]),
                        ];
                    });
                if ($search !== '') {
                    $rows = $rows->filter(function ($row) use ($search) {
                        $text = mb_strtolower(implode(' ', [$row['nim'], $row['studentName'], $row['course'], $row['class'], $row['studyProgram']]));
                        return str_contains($text, mb_strtolower($search));
                    });
                }
                $gradeRows = $rows->sortBy(['course', 'studentName'])->values()->all();
            }
        }
        return Inertia::render('Lecturer/StudentGrades/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Nilai Mahasiswa'),
            'gradeRows' => $gradeRows,
            'search' => $search,
            'urls' => ['index' => route('lecturer.student-grades.index'), 'gradeBook' => route('lecturer.student-grades.grade-book')],
        ]);
    }

    public function assignmentsIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $offeringId = (string) $request->query('offering_id', '');
        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'published', 'draft', 'overdue', 'review'], true)) $statusFilter = 'all';
        $offerings = [];
        $rows = [];
        if ($profile) {
            $assignments = \App\Models\Academic\CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)->where('is_active', true)
                ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram'])
                ->latest('id')->get();
            $offerings = $assignments->map(function ($a) {
                $o = $a->courseOffering;
                if (! $o) return null;
                return [
                    'id' => $o->id,
                    'courseCode' => $o->course?->code ?? '-',
                    'courseName' => $o->course?->name ?? '-',
                    'label' => $o->label ?? '-',
                    'academicYear' => $o->academicYear?->name ?? '-',
                    'studyProgram' => $o->studyProgram?->name ?? '-',
                    'enrolledCount' => \App\Models\Academic\StudyPlanDetail::query()->where('course_offering_id', $o->id)->whereHas('studyPlan', fn ($q) => $q->where('status', 'Approved'))->where('status', 'Taken')->count(),
                    'createUrl' => route('lecturer.assignments.create', ['offeringId' => $o->id]),
                ];
            })->filter()->values()->all();

            $offeringIds = collect($offerings)->pluck('id');
            $rows = \App\Models\Academic\Assignment::query()
                ->with(['courseOffering.course', 'courseOffering.academicYear', 'submissions.grade'])
                ->whereIn('course_offering_id', $offeringIds)
                ->orderBy('due_at')
                ->get()
                ->map(function ($assignment) {
                    $enrolled = \App\Models\Academic\StudyPlanDetail::query()->where('course_offering_id', $assignment->course_offering_id)->whereHas('studyPlan', fn ($q) => $q->where('status', 'Approved'))->where('status', 'Taken')->count();
                    $submitted = $assignment->submissions->count();
                    $graded = $assignment->submissions->filter(fn ($s) => $s->grade?->status === 'graded')->count();
                    $returned = $assignment->submissions->filter(fn ($s) => $s->grade?->status === 'returned')->count();
                    return [
                        'id' => $assignment->id,
                        'offeringId' => $assignment->course_offering_id,
                        'title' => $assignment->title,
                        'course' => trim(($assignment->courseOffering?->course?->code ? $assignment->courseOffering->course->code.' - ' : '').($assignment->courseOffering?->course?->name ?? '-')),
                        'label' => $assignment->courseOffering?->label ?? '-',
                        'academicYear' => $assignment->courseOffering?->academicYear?->name ?? '-',
                        'dueAt' => $assignment->due_at?->format('d M Y H:i') ?? '-',
                        'isPublished' => (bool) $assignment->is_published,
                        'isPastDue' => (bool) $assignment->isPastDue(),
                        'enrolled' => $enrolled,
                        'submitted' => $submitted,
                        'graded' => $graded,
                        'returned' => $returned,
                        'reviewQueue' => max(0, $submitted - $graded - $returned),
                        'progress' => $enrolled > 0 ? min(100, (int) round(($submitted / $enrolled) * 100)) : 0,
                        'url' => route('lecturer.assignments.show', ['id' => $assignment->id]),
                        'editUrl' => route('lecturer.assignments.edit', ['id' => $assignment->id]),
                    ];
                })->values()->all();

            $rows = collect($rows)
                ->filter(function (array $row) use ($offeringId, $statusFilter) {
                    if ($offeringId !== '' && (string) $row['offeringId'] !== $offeringId) {
                        return false;
                    }

                    return match ($statusFilter) {
                        'published' => $row['isPublished'],
                        'draft' => ! $row['isPublished'],
                        'overdue' => $row['isPastDue'],
                        'review' => $row['reviewQueue'] > 0,
                        default => true,
                    };
                })
                ->values()
                ->all();
        }
        $selectedCreate = collect($offerings)->firstWhere('id', (int) $offeringId) ?? collect($offerings)->first();
        return Inertia::render('Lecturer/Assignments/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Tugas'),
            'offerings' => $offerings,
            'rows' => $rows,
            'filters' => ['offeringId' => $offeringId, 'status' => $statusFilter],
            'urls' => [
                'index' => route('lecturer.assignments.index'),
                'create' => $selectedCreate ? route('lecturer.assignments.create', ['offeringId' => $selectedCreate['id']]) : route('lecturer.assignments.index'),
            ],
        ]);
    }

    public function courseMaterialsList(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $offerings = [];
        if ($profile) {
            $assignments = \App\Models\Academic\CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)
                ->where('is_active', true)
                ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram', 'courseOffering.courseSchedules.room.building'])
                ->latest('id')->get();
            $offerings = $assignments->map(function ($a) {
                $o = $a->courseOffering;
                if (! $o) return null;
                $enrolled = \App\Models\Academic\StudyPlanDetail::query()->where('course_offering_id', $o->id)->whereHas('studyPlan', fn ($q) => $q->where('status', 'Approved'))->where('status', 'Taken')->count();
                $sched = $o->courseSchedules->where('is_active', true)->first();
                return [
                    'id' => $o->id,
                    'courseCode' => $o->course?->code ?? '-',
                    'courseName' => $o->course?->name ?? '-',
                    'label' => $o->label ?? '-',
                    'academicYear' => $o->academicYear?->name ?? '-',
                    'studyProgram' => $o->studyProgram?->name ?? '-',
                    'deliveryMode' => $sched?->delivery_mode ?? $o->delivery_mode ?? '-',
                    'room' => $sched?->room?->name ?? '-',
                    'building' => $sched?->room?->building?->name ?? '-',
                    'enrolledCount' => $enrolled,
                    'url' => route('lecturer.course-materials.index', ['offeringId' => $o->id]) ?? route('lecturer.course-materials.list'),
                ];
            })->filter()->values()->all();
        }
        return Inertia::render('Lecturer/CourseMaterials/List', [
            'shell' => ShellProps::make($user, 'Akademik', 'Materi Kuliah'),
            'hasProfile' => (bool) $profile,
            'offerings' => $offerings,
            'urls' => ['list' => route('lecturer.course-materials.list'), 'index' => route('lecturer.course-materials.index', ['offeringId' => 0])],
        ]);
    }

    public function consultationsIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile;
        $hasProfile = (bool) $profile;

        $stats = ['activeSlots' => 0, 'requested' => 0, 'confirmed' => 0, 'completed' => 0];
        $slots = [];
        $appointments = [];

        if ($profile) {
            $profileId = $profile->id;

            $stats = [
                'activeSlots' => \App\Models\Academic\ConsultationSlot::query()->where('lecturer_profile_id', $profileId)->where('is_active', true)->count(),
                'requested' => \App\Models\Academic\ConsultationAppointment::query()->where('lecturer_profile_id', $profileId)->where('status', 'requested')->count(),
                'confirmed' => \App\Models\Academic\ConsultationAppointment::query()->where('lecturer_profile_id', $profileId)->where('status', 'confirmed')->where('starts_at', '>=', now())->count(),
                'completed' => \App\Models\Academic\ConsultationAppointment::query()->where('lecturer_profile_id', $profileId)->where('status', 'completed')->whereDate('appointment_date', '>=', now()->startOfMonth()->toDateString())->count(),
            ];

            $slots = \App\Models\Academic\ConsultationSlot::query()
                ->where('lecturer_profile_id', $profileId)
                ->withCount(['appointments as upcomingCount' => fn ($q) => $q->whereIn('status', ['requested', 'confirmed', 'waitlisted'])->where('starts_at', '>=', now())])
                ->orderBy('weekday')->orderBy('start_time')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'weekday' => $s->weekdayLabel(),
                    'time' => substr((string) $s->start_time, 0, 5).' - '.substr((string) $s->end_time, 0, 5),
                    'slotMinutes' => $s->slot_minutes,
                    'capacity' => $s->capacity,
                    'mode' => $s->modeLabel(),
                    'location' => $s->location ?: $s->meeting_link ?: '-',
                    'isActive' => (bool) $s->is_active,
                    'upcomingCount' => $s->upcoming_count,
                ])->values()->all();

            $appointments = \App\Models\Academic\ConsultationAppointment::query()
                ->with(['studentProfile.user', 'studentProfile.studyProgram', 'consultationSlot'])
                ->where('lecturer_profile_id', $profileId)
                ->whereIn('status', ['requested', 'confirmed'])
                ->orderBy('starts_at')
                ->take(50)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'student' => $a->studentProfile?->user?->name ?? '-',
                    'nim' => $a->studentProfile?->nim ?? '-',
                    'studyProgram' => $a->studentProfile?->studyProgram?->name ?? '-',
                    'topic' => $a->topic,
                    'studentNotes' => $a->student_notes,
                    'lecturerNotes' => $a->lecturer_notes,
                    'status' => $a->status,
                    'statusLabel' => $a->statusLabel(),
                    'schedule' => $a->starts_at?->format('d M Y H:i').' - '.$a->ends_at?->format('H:i'),
                    'mode' => $a->consultationSlot?->modeLabel() ?? '-',
                    'place' => $a->consultationSlot?->location ?: $a->consultationSlot?->meeting_link ?: '-',
                    'statusUrl' => route('lecturer.consultations.appointments.status', ['id' => $a->id]),
                    'noteUrl' => route('lecturer.consultations.appointments.note', ['id' => $a->id]),
                ])->values()->all();
        }

        return Inertia::render('Lecturer/Consultations/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Konsultasi'),
            'hasProfile' => $hasProfile,
            'stats' => $stats,
            'slots' => $slots,
            'appointments' => $appointments,
            'weekdays' => [
                ['value' => 1, 'label' => 'Senin'],
                ['value' => 2, 'label' => 'Selasa'],
                ['value' => 3, 'label' => 'Rabu'],
                ['value' => 4, 'label' => 'Kamis'],
                ['value' => 5, 'label' => 'Jumat'],
                ['value' => 6, 'label' => 'Sabtu'],
                ['value' => 7, 'label' => 'Minggu'],
            ],
            'modes' => [
                ['value' => 'in_person', 'label' => 'Tatap Muka'],
                ['value' => 'online', 'label' => 'Daring'],
                ['value' => 'hybrid', 'label' => 'Hybrid'],
            ],
            'urls' => [
                'index' => route('lecturer.consultations.index'),
                'storeSlot' => route('lecturer.consultations.slots.store'),
                'updateSlot' => route('lecturer.consultations.slots.update', ['id' => '__SLOT__']),
                'toggleSlot' => route('lecturer.consultations.slots.toggle', ['id' => '__SLOT__']),
                'deleteSlot' => route('lecturer.consultations.slots.destroy', ['id' => '__SLOT__']),
            ],
        ]);
    }

    public function consultationSlotStore(Request $request): RedirectResponse
    {
        $profile = $request->user()->lecturerProfile;
        abort_unless($profile, 403);

        $validated = $this->validateConsultationSlot($request);

        ConsultationSlot::query()->create($validated + [
            'lecturer_profile_id' => $profile->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Jadwal konsultasi berhasil disimpan.');
    }

    public function consultationSlotUpdate(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->lecturerProfile;
        abort_unless($profile, 403);

        $slot = ConsultationSlot::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', $profile->id)
            ->firstOrFail();

        $slot->update($this->validateConsultationSlot($request) + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Jadwal konsultasi berhasil diperbarui.');
    }

    public function consultationSlotToggle(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->lecturerProfile;
        abort_unless($profile, 403);

        $slot = ConsultationSlot::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', $profile->id)
            ->firstOrFail();

        $slot->update([
            'is_active' => ! $slot->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $slot->is_active ? 'Jadwal konsultasi diaktifkan.' : 'Jadwal konsultasi dinonaktifkan.');
    }

    public function consultationSlotDestroy(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->lecturerProfile;
        abort_unless($profile, 403);

        $slot = ConsultationSlot::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', $profile->id)
            ->firstOrFail();

        if ($slot->appointments()->whereIn('status', ['requested', 'confirmed'])->exists()) {
            return back()->with('error', 'Jadwal masih memiliki konsultasi aktif sehingga tidak bisa dihapus.');
        }

        $slot->delete();

        return back()->with('success', 'Jadwal konsultasi dihapus.');
    }

    public function consultationAppointmentStatus(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->lecturerProfile;
        abort_unless($profile, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'completed', 'cancelled', 'rejected', 'no_show'])],
        ]);

        $appointment = ConsultationAppointment::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', $profile->id)
            ->firstOrFail();

        $payload = ['status' => $validated['status'], 'updated_by' => $request->user()->id];

        if ($validated['status'] === 'confirmed') {
            $payload['confirmed_at'] = now();
        } elseif ($validated['status'] === 'completed') {
            $payload['completed_at'] = now();
        } elseif (in_array($validated['status'], ['cancelled', 'rejected', 'no_show'], true)) {
            $payload['cancelled_at'] = now();
        }

        $appointment->update($payload);

        return back()->with('success', 'Status konsultasi diperbarui.');
    }

    public function consultationAppointmentNote(Request $request, int $id): RedirectResponse
    {
        $profile = $request->user()->lecturerProfile;
        abort_unless($profile, 403);

        $validated = $request->validate([
            'lecturerNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        ConsultationAppointment::query()
            ->whereKey($id)
            ->where('lecturer_profile_id', $profile->id)
            ->firstOrFail()
            ->update([
                'lecturer_notes' => $validated['lecturerNotes'] ?: null,
                'updated_by' => $request->user()->id,
            ]);

        return back()->with('success', 'Catatan konsultasi disimpan.');
    }

    public function courseOfferingsIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile()->with(['studyProgram', 'faculty'])->first();

        $hasProfile = $profile !== null;
        $lecturerInfo = ['name' => $user->name, 'nidn' => '-', 'study_program' => '-', 'faculty' => '-', 'employment_status' => '-'];
        $offerings = [];
        $yearOptions = [];
        $statusOptions = [];
        $modeOptions = [];
        $stats = ['total_offerings' => 0, 'total_schedules' => 0, 'total_sessions' => 0, 'total_students' => 0];

        if ($profile) {
            $lecturerInfo = [
                'name' => $user->name,
                'nidn' => $profile->nidn ?? '-',
                'study_program' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->faculty?->name ?? '-',
                'employment_status' => $profile->employment_status ?? '-',
            ];

            $assigned = \App\Models\Academic\CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)
                ->where('is_active', true)
                ->with(['courseOffering.academicYear', 'courseOffering.studyProgram', 'courseOffering.course', 'courseOffering.courseSchedules', 'courseOffering.attendanceSessions'])
                ->latest('id')
                ->get();

            $offerings = $assigned->map(function ($assignment) {
                $offering = $assignment->courseOffering;
                if (! $offering) return null;
                $enrolledCount = \App\Models\Academic\StudyPlanDetail::query()
                    ->where('course_offering_id', $offering->id)
                    ->whereHas('studyPlan', fn ($q) => $q->where('status', 'Approved'))
                    ->where('status', 'Taken')
                    ->count();
                return [
                    'id' => $offering->id,
                    'academicYear' => $offering->academicYear?->name ?? '-',
                    'studyProgram' => $offering->studyProgram?->name ?? '-',
                    'courseCode' => $offering->course?->code ?? '-',
                    'courseName' => $offering->course?->name ?? '-',
                    'label' => $offering->label ?? '-',
                    'classCode' => $offering->code ?? '-',
                    'semesterNo' => $offering->semester_no,
                    'credits' => $offering->credits ?? $offering->course?->credits ?? 0,
                    'deliveryMode' => $offering->delivery_mode ?? '-',
                    'status' => $offering->status ?? '-',
                    'lecturerRole' => $assignment->role ?? '-',
                    'scheduleCount' => $offering->courseSchedules->where('is_active', true)->count(),
                    'sessionCount' => $offering->attendanceSessions->count(),
                    'capacity' => $offering->capacity,
                    'enrolledCount' => $enrolledCount,
                    'room' => $offering->courseSchedules->where('is_active', true)->first()?->room?->name ?? '-',
                    'building' => $offering->courseSchedules->where('is_active', true)->first()?->room?->building?->name ?? '-',
                    'url' => route('lecturer.course-offerings.show', ['id' => $offering->id]),
                ];
            })->filter()->sortByDesc(fn ($o) => $o['academicYear'])->values()->all();

            $yearOptions = collect($offerings)->pluck('academicYear')->filter()->unique()->values()->all();
            $statusOptions = collect($offerings)->pluck('status')->filter()->unique()->values()->all();
            $modeOptions = collect($offerings)->pluck('deliveryMode')->filter()->unique()->values()->all();
            $stats = [
                'totalOfferings' => count($offerings),
                'totalSchedules' => (int) collect($offerings)->sum('scheduleCount'),
                'totalSessions' => (int) collect($offerings)->sum('sessionCount'),
                'totalStudents' => (int) collect($offerings)->sum('enrolledCount'),
            ];
        }

        return Inertia::render('Lecturer/CourseOfferings/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Kelas Saya'),
            'hasProfile' => $hasProfile,
            'lecturerInfo' => $lecturerInfo,
            'offerings' => $offerings,
            'yearOptions' => $yearOptions,
            'statusOptions' => $statusOptions,
            'modeOptions' => $modeOptions,
            'stats' => $stats,
            'urls' => ['index' => route('lecturer.course-offerings.index')],
        ]);
    }

    public function calendarIndex(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile()->with(['studyProgram', 'faculty'])->first();

        $hasProfile = $profile !== null;
        $lecturerInfo = ['name' => $user->name, 'nidn' => '-', 'study_program' => '-', 'faculty' => '-'];
        $schedules = [];
        $yearOptions = [];
        $modeOptions = [];

        if ($profile) {
            $lecturerInfo = [
                'name' => $user->name,
                'nidn' => $profile->nidn ?? '-',
                'study_program' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->faculty?->name ?? '-',
            ];

            $schedCollection = \App\Models\Academic\CourseSchedule::query()
                ->where('is_active', true)
                ->where(function ($query) use ($profile) {
                    $query->where('lecturer_profile_id', $profile->id)
                        ->orWhereHas('courseOffering.lecturers', function ($q) use ($profile) {
                            $q->where('lecturer_profile_id', $profile->id)->where('is_active', true);
                        });
                })
                ->with(['courseOffering.academicYear', 'courseOffering.studyProgram', 'courseOffering.course', 'room.building'])
                ->get();

            $dayOrder = ['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>7];
            $schedules = $schedCollection->map(function ($schedule) use ($dayOrder) {
                $offering = $schedule->courseOffering;
                $start = $schedule->start_time;
                $end = $schedule->end_time;
                $fmt = function ($v) {
                    if ($v instanceof \DateTimeInterface) return $v->format('H:i');
                    if (is_string($v) && strlen($v) >= 5) return substr($v, 0, 5);
                    return '-';
                };
                return [
                    'id' => $schedule->id,
                    'courseOfferingId' => $offering?->id,
                    'academicYear' => $offering?->academicYear?->name ?? '-',
                    'studyProgram' => $offering?->studyProgram?->name ?? '-',
                    'courseCode' => $offering?->course?->code ?? '-',
                    'courseName' => $offering?->course?->name ?? '-',
                    'classLabel' => $offering?->label ?? '-',
                    'classCode' => $offering?->code ?? '-',
                    'day' => $schedule->day_of_week,
                    'dayOrder' => $dayOrder[$schedule->day_of_week] ?? 99,
                    'startTime' => $fmt($start),
                    'endTime' => $fmt($end),
                    'sessionType' => $schedule->session_type ?? '-',
                    'deliveryMode' => $schedule->delivery_mode ?? '-',
                    'room' => $schedule->room?->name ?? '-',
                    'building' => $schedule->room?->building?->name ?? '-',
                    'meetingLink' => $schedule->meeting_link,
                    'notes' => $schedule->notes,
                ];
            })->sortBy(fn ($s) => sprintf('%02d-%s', $s['dayOrder'], $s['startTime']))->values()->all();

            $yearOptions = collect($schedules)->pluck('academicYear')->filter()->unique()->values()->all();
            $modeOptions = collect($schedules)->pluck('deliveryMode')->filter()->unique()->values()->all();
        }

        return Inertia::render('Lecturer/Calendar/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Kalender Mengajar'),
            'hasProfile' => $hasProfile,
            'lecturerInfo' => $lecturerInfo,
            'schedules' => $schedules,
            'yearOptions' => $yearOptions,
            'modeOptions' => $modeOptions,
            'urls' => ['index' => route('lecturer.calendar.index')],
        ]);
    }

    // ---------------------------------------------------------------- Helpers

    /**
     * Resolve the active lecturer assignment for an offering or abort.
     */
    private function assignedOffering(Request $request, int $offeringId): CourseOfferingLecturer
    {
        $profile = $request->user()?->lecturerProfile()->first();
        abort_unless($profile, 403);

        $assignment = CourseOfferingLecturer::query()
            ->where('course_offering_id', $offeringId)
            ->where('lecturer_profile_id', $profile->id)
            ->where('is_active', true)
            ->with(['courseOffering.academicYear', 'courseOffering.studyProgram', 'courseOffering.course', 'courseOffering.courseSchedules.room.building'])
            ->first();

        abort_unless($assignment && $assignment->courseOffering, 404);

        return $assignment;
    }

    private function assertOfferingAccess(Request $request, int $offeringId): void
    {
        $this->assignedOffering($request, $offeringId);
    }

    private function advisorAssignment(Request $request, int $assignmentId): AcademicAdvisorAssignment
    {
        $profileId = $request->user()?->lecturerProfile?->id;
        abort_unless($profileId, 403);

        return AcademicAdvisorAssignment::query()
            ->whereKey($assignmentId)
            ->where('lecturer_profile_id', $profileId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function decideStudyPlan(Request $request, int $assignmentId, string $decision): RedirectResponse
    {
        $advisorAssignment = $this->advisorAssignment($request, $assignmentId);

        $validated = $request->validate([
            'planId' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = StudyPlan::query()
            ->whereKey($validated['planId'])
            ->where('student_profile_id', $advisorAssignment->student_profile_id)
            ->firstOrFail();

        $service = app(AcademicAdvisorService::class);

        try {
            if ($decision === 'Approved') {
                $service->approveStudyPlanAsAdvisor($advisorAssignment->lecturer_profile_id, $plan, $validated['notes'] ?? null, $request->user()->id);
            } else {
                $service->rejectStudyPlanAsAdvisor($advisorAssignment->lecturer_profile_id, $plan, $validated['notes'] ?? null, $request->user()->id);
            }
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $exception->validator->errors()->first());
        }

        return back()->with('success', $decision === 'Approved' ? 'KRS berhasil disetujui.' : 'KRS berhasil ditolak.');
    }

    private function authorizedAppeal(Request $request, int $appealId): GradeAppeal
    {
        $profile = $request->user()?->lecturerProfile()->first();
        abort_unless($profile, 403);

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $profile->id)
            ->where('is_active', true)
            ->pluck('course_offering_id');

        return GradeAppeal::query()
            ->with(['studentGrade', 'attachments'])
            ->whereKey($appealId)
            ->where(function ($query) use ($profile, $offeringIds) {
                $query->where('lecturer_profile_id', $profile->id)
                    ->orWhereIn('course_offering_id', $offeringIds);
            })
            ->firstOrFail();
    }

    private function enrolledQuery(int $offeringId)
    {
        return StudyPlanDetail::query()
            ->where('course_offering_id', $offeringId)
            ->officiallyEnrolled();
    }

    private function validateConsultationSlot(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'slotMinutes' => ['required', 'integer', 'min:15', 'max:180'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'consultationMode' => ['required', Rule::in(['in_person', 'online', 'hybrid'])],
            'location' => ['nullable', 'string', 'max:160'],
            'meetingLink' => ['nullable', 'url', 'max:255'],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['boolean'],
        ]);

        return [
            'title' => $validated['title'],
            'weekday' => $validated['weekday'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'slot_minutes' => $validated['slotMinutes'],
            'capacity' => $validated['capacity'],
            'consultation_mode' => $validated['consultationMode'],
            'location' => $validated['location'] ?: null,
            'meeting_link' => $validated['meetingLink'] ?: null,
            'starts_on' => $validated['startsOn'] ?: null,
            'ends_on' => $validated['endsOn'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ];
    }

    private function validateAssignmentPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'dueAt' => ['required', 'date'],
            'maxScore' => ['required', 'numeric', 'min:1', 'max:9999'],
            'allowedFileTypes' => ['nullable', 'string', 'max:255'],
            'maxFileSizeMb' => ['required', 'integer', 'min:1', 'max:100'],
            'allowTextSubmission' => ['boolean'],
            'allowFileSubmission' => ['boolean'],
            'allowResubmission' => ['boolean'],
            'acceptLateSubmission' => ['boolean'],
            'isPublished' => ['boolean'],
            'instructionFiles' => ['nullable', 'array'],
            'instructionFiles.*' => ['file', 'max:20480'],
            'removeFileIds' => ['nullable', 'array'],
            'removeFileIds.*' => ['integer'],
        ]);
    }

    private function assignmentPayload(array $validated, int $offeringId, int $actorId): array
    {
        $extensions = collect(explode(',', (string) ($validated['allowedFileTypes'] ?? '')))
            ->map(fn ($value) => trim(strtolower($value)))
            ->filter()
            ->values()
            ->all();

        return [
            'course_offering_id' => $offeringId,
            'created_by' => $actorId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'due_at' => $validated['dueAt'],
            'due_date' => $validated['dueAt'],
            'max_score' => $validated['maxScore'],
            'allowed_file_types' => $extensions ?: null,
            'max_file_size_mb' => $validated['maxFileSizeMb'],
            'max_file_size_kb' => $validated['maxFileSizeMb'] * 1024,
            'allow_text_submission' => (bool) ($validated['allowTextSubmission'] ?? false),
            'allow_file_submission' => (bool) ($validated['allowFileSubmission'] ?? false),
            'allow_resubmission' => (bool) ($validated['allowResubmission'] ?? false),
            'accept_late_submission' => (bool) ($validated['acceptLateSubmission'] ?? false),
            'allow_late_submission' => (bool) ($validated['acceptLateSubmission'] ?? false),
            'is_published' => (bool) ($validated['isPublished'] ?? false),
            'published_at' => (bool) ($validated['isPublished'] ?? false) ? now() : null,
        ];
    }

    private function storeAssignmentFiles(Request $request, Assignment $assignment): void
    {
        foreach ((array) $request->file('instructionFiles', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('assignments/instructions', 'public');

            $assignment->files()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'created_by' => $request->user()->id,
            ]);
        }
    }

    private function storeMaterialFiles(Request $request, CourseMaterial $material, array $validated): void
    {
        foreach ((array) $request->file('files', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('course-materials', 'public');
            $size = $file->getSize();
            if ($size === false || $size === null) {
                $fullPath = storage_path('app/public/'.$path);
                $size = file_exists($fullPath) ? filesize($fullPath) : null;
            }

            $material->files()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $size,
            ]);
        }

        if (! empty($validated['videoUrl'])) {
            $material->files()->create([
                'file_path' => $validated['videoUrl'],
                'file_name' => $validated['videoTitle'] ?: 'Video Link',
                'file_type' => 'link',
                'file_size' => null,
            ]);
        }
    }

    private function deleteMaterialFile(CourseMaterialFile $file): void
    {
        if ($file->file_type !== 'link' && $file->file_path && Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();
    }

    private function recordMaterialVersion(CourseMaterial $material, string $changeType, ?string $summary = null): void
    {
        $material->loadMissing('files');

        $latestVersion = (int) $material->versions()->max('version_number');

        $material->versions()->create([
            'version_number' => $latestVersion + 1,
            'change_type' => $changeType,
            'title' => $material->title,
            'description' => $material->description,
            'category' => $material->category,
            'meeting_number' => $material->meeting_number,
            'is_published' => $material->is_published,
            'files_snapshot' => $material->files->map(fn ($file) => [
                'file_name' => $file->file_name,
                'file_type' => $file->file_type,
                'file_size' => $file->file_size,
                'download_count' => $file->download_count,
            ])->values()->all(),
            'change_summary' => $summary,
            'created_by' => auth()->id(),
        ]);
    }

    private function materialFileUrl(int $materialId, CourseMaterialFile $file): string
    {
        if ($file->file_type === 'link') {
            return (string) $file->file_path;
        }

        return route('lecturer.course-materials.download', ['id' => $materialId, 'fileId' => $file->id]);
    }

    private function materialCategories(): array
    {
        return [
            'syllabus' => 'Silabus',
            'lecture_notes' => 'Catatan Kuliah',
            'assignments' => 'Penugasan',
            'references' => 'Referensi',
        ];
    }

    private function formatFileSize(?int $bytes): string
    {
        if (! $bytes || $bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2, ',', '.').' MB';
        }

        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 1, ',', '.').' KB';
        }

        return $bytes.' B';
    }

    private function formatTime($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && strlen($value) >= 5) {
            return substr($value, 0, 5);
        }

        return '-';
    }
}
