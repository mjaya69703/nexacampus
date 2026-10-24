<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\LecturerProfile;
use App\Models\Publication\Announcement;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        $offering = \App\Models\Academic\CourseOffering::with(['academicYear', 'studyProgram', 'course'])->findOrFail($id);
        return Inertia::render('Lecturer/CourseOfferings/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail Kelas'),
            'offering' => ['id' => $offering->id, 'courseCode' => $offering->course?->code, 'courseName' => $offering->course?->name, 'label' => $offering->label, 'academicYear' => $offering->academicYear?->name, 'studyProgram' => $offering->studyProgram?->name, 'status' => $offering->status],
            'urls' => ['index' => route('lecturer.course-offerings.index'), 'students' => route('lecturer.course-offerings.students', ['offeringId' => $id]), 'attendance' => route('lecturer.course-offerings.attendance', ['offeringId' => $id]), 'grades' => route('lecturer.course-offerings.grades', ['offeringId' => $id])],
        ]);
    }

    public function courseOfferingsStudents(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/CourseOfferings/Students', [
            'shell' => ShellProps::make($user, 'Akademik', 'Mahasiswa Kelas'),
            'offeringId' => $offeringId,
            'urls' => ['index' => route('lecturer.course-offerings.index'), 'show' => route('lecturer.course-offerings.show', ['id' => $offeringId])],
        ]);
    }

    public function courseOfferingsAttendance(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/CourseOfferings/Attendance', [
            'shell' => ShellProps::make($user, 'Akademik', 'Absensi Kelas'),
            'offeringId' => $offeringId,
            'urls' => ['index' => route('lecturer.course-offerings.index'), 'show' => route('lecturer.course-offerings.show', ['id' => $offeringId])],
        ]);
    }

    public function courseOfferingsGrades(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/CourseOfferings/Grades', [
            'shell' => ShellProps::make($user, 'Akademik', 'Nilai Kelas'),
            'offeringId' => $offeringId,
            'urls' => ['index' => route('lecturer.course-offerings.index'), 'show' => route('lecturer.course-offerings.show', ['id' => $offeringId])],
        ]);
    }

    // ---------------------------------------------------------------- Course Materials Index/Show
    public function courseMaterialsIndex(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/CourseMaterials/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Materi Kuliah'),
            'offeringId' => $offeringId,
            'urls' => ['list' => route('lecturer.course-materials.list')],
        ]);
    }

    public function courseMaterialsShow(Request $request, $material): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/CourseMaterials/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail Materi'),
            'material' => $material,
            'urls' => ['list' => route('lecturer.course-materials.list')],
        ]);
    }

    // ---------------------------------------------------------------- Assignments Create/Edit/Show
    public function assignmentsCreate(Request $request, int $offeringId): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/Assignments/Create', [
            'shell' => ShellProps::make($user, 'Akademik', 'Buat Tugas'),
            'offeringId' => $offeringId,
            'urls' => ['index' => route('lecturer.assignments.index')],
        ]);
    }

    public function assignmentsEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/Assignments/Edit', [
            'shell' => ShellProps::make($user, 'Akademik', 'Edit Tugas'),
            'id' => $id,
            'urls' => ['index' => route('lecturer.assignments.index'), 'show' => route('lecturer.assignments.show', ['id' => $id])],
        ]);
    }

    public function assignmentsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/Assignments/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail Tugas'),
            'id' => $id,
            'urls' => ['index' => route('lecturer.assignments.index')],
        ]);
    }

    // ---------------------------------------------------------------- Attendance Sessions Edit
    public function attendanceSessionsEdit(Request $request, int $sessionId): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/AttendanceSessions/Edit', [
            'shell' => ShellProps::make($user, 'Akademik', 'Edit Sesi Absensi'),
            'sessionId' => $sessionId,
            'urls' => ['index' => route('lecturer.course-offerings.index')],
        ]);
    }

    // ---------------------------------------------------------------- Student Grades Edit
    public function studentGradesEdit(Request $request, int $id): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/StudentGrades/Edit', [
            'shell' => ShellProps::make($user, 'Akademik', 'Edit Nilai'),
            'id' => $id,
            'urls' => ['index' => route('lecturer.student-grades.index')],
        ]);
    }

    // ---------------------------------------------------------------- Workloads Show
    public function workloadsShow(Request $request, int $id): Response
    {
        $user = $request->user();
        return Inertia::render('Lecturer/Workloads/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail BKD'),
            'id' => $id,
            'urls' => ['index' => route('lecturer.workloads.index')],
        ]);
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
                ->where('lecturer_profile_id', $profile->id)
                ->where('student_profile_id', $student->id)
                ->latest()->get()->map(fn ($n) => ['id' => $n->id, 'topic' => $n->topic, 'notes' => $n->notes, 'status' => $n->status])->values()->all();
        }

        return Inertia::render('Lecturer/AcademicAdvising/Show', [
            'shell' => ShellProps::make($user, 'Akademik', 'Detail Bimbingan'),
            'assignmentId' => $assignmentId,
            'assignment' => $assignment,
            'progress' => $progress,
            'pendingPlans' => $pendingPlans,
            'notes' => $notes,
            'urls' => ['index' => route('lecturer.academic-advising.index')],
        ]);
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
            $selectedPeriodId = $periods->first()?->id;

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
        $submissions = \App\Models\Organization\LecturerWorkloadSubmission::query()->where('user_id', $user->id)->with('period')->latest('updated_at')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'period' => $s->period?->name ?? '-',
                'status' => $s->status,
                'teachingSks' => $s->teaching_sks,
                'structuralSks' => $s->structural_sks,
                'tridharmaSks' => $s->tridharma_sks,
                'totalSks' => $s->total_sks,
                'updatedAt' => $s->updated_at?->format('d M Y H:i'),
                'url' => route('lecturer.workloads.show', ['id' => $s->id]),
            ])->values()->all();
        return Inertia::render('Lecturer/Workloads/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Beban Kerja'),
            'periods' => $periods,
            'submissions' => $submissions,
            'selectedPeriodId' => (string) (collect($periods)->first()['id'] ?? ''),
            'urls' => ['index' => route('lecturer.workloads.index')],
        ]);
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
                    'originalScore' => $appeal->original_score,
                    'currentScore' => $appeal->studentGrade?->final_score,
                    'resolvedScore' => $appeal->resolved_score,
                    'submittedAt' => $appeal->submitted_at?->format('d M Y H:i') ?? '-',
                ];
            })->values()->all();
        }

        return Inertia::render('Lecturer/GradeAppeals/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Keberatan Nilai'),
            'hasProfile' => $hasProfile,
            'statusFilter' => $statusFilter,
            'appealRows' => $appealRows,
            'summary' => $summary,
            'urls' => ['index' => route('lecturer.grade-appeals.index')],
        ]);
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
        $rows = [];
        $statistics = [];
        $distribution = [];
        $courseOptions = [];
        $yearOptions = [];
        $semesterOptions = [1,2,3,4,5,6,7,8];
        if ($profile) {
            $courseOptions = \App\Models\Academic\CourseOfferingLecturer::query()
                ->where('lecturer_profile_id', $profile->id)->where('is_active', true)
                ->with(['courseOffering.course', 'courseOffering.academicYear'])
                ->get()->map(fn ($a) => ['id' => $a->courseOffering->id, 'label' => ($a->courseOffering->course?->code ?? '-').' - '.($a->courseOffering->course?->name ?? '-').' ('.($a->courseOffering->academicYear?->name ?? '-').')'])->values()->all();
            $yearOptions = \App\Models\Academic\AcademicYear::query()->orderByDesc('start_date')->get()->map(fn ($y) => ['id' => $y->id, 'name' => $y->name])->values()->all();
        }
        return Inertia::render('Lecturer/StudentGrades/GradeBook', [
            'shell' => ShellProps::make($user, 'Akademik', 'Buku Nilai'),
            'rows' => $rows,
            'statistics' => $statistics,
            'distribution' => $distribution,
            'courseOptions' => $courseOptions,
            'yearOptions' => $yearOptions,
            'semesterOptions' => $semesterOptions,
            'urls' => ['index' => route('lecturer.student-grades.grade-book')],
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
        }
        return Inertia::render('Lecturer/Assignments/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Tugas'),
            'offerings' => $offerings,
            'rows' => $rows,
            'urls' => [
                'index' => route('lecturer.assignments.index'),
                'create' => count($offerings) ? route('lecturer.assignments.create', ['offeringId' => $offerings[0]['id']]) : route('lecturer.assignments.index'),
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
        abort_unless($profile, 403);

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
                'status' => $a->status,
                'statusLabel' => $a->statusLabel(),
                'schedule' => $a->starts_at?->format('d M Y H:i').' - '.$a->ends_at?->format('H:i'),
                'mode' => $a->consultationSlot?->modeLabel() ?? '-',
                'place' => $a->consultationSlot?->location ?: $a->consultationSlot?->meeting_link ?: '-',
            ])->values()->all();

        return Inertia::render('Lecturer/Consultations/Index', [
            'shell' => ShellProps::make($user, 'Akademik', 'Konsultasi'),
            'stats' => $stats,
            'slots' => $slots,
            'appointments' => $appointments,
            'urls' => ['index' => route('lecturer.consultations.index')],
        ]);
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
}
