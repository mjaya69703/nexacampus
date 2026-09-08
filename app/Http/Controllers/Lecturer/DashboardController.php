<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Assignment;
use App\Models\Academic\AttendanceSession;
use App\Models\Academic\ConsultationAppointment;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\GradeAppeal;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\Organization\TridharmaRecord;
use App\Models\Publication\Announcement;
use App\Models\Publication\AnnouncementRead;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->lecturerProfile()->with(['studyProgram', 'faculty'])->first();

        if (! $profile) {
            return Inertia::render('Lecturer/Dashboard', [
                'shell' => ShellProps::make($user, 'Dashboard', 'Dashboard Dosen'),
                'hasProfile' => false,
                'lecturer' => ['name' => $user->name],
                'inbox' => [],
                'week' => [],
                'classes' => [],
                'bkd' => null,
                'tridharma' => null,
                'announcements' => ['unread' => 0, 'items' => []],
            ]);
        }

        $lecturerId = $profile->id;
        $activeYear = AcademicYear::where('is_active', true)->orderByDesc('start_date')->first();

        $offeringIds = CourseOffering::query()
            ->join('course_offering_lecturers as col', 'col.course_offering_id', '=', 'course_offerings.id')
            ->where('col.lecturer_profile_id', $lecturerId)
            ->where('col.is_active', true)
            ->pluck('course_offerings.id')
            ->all();

        $activeOfferingIds = $activeYear
            ? CourseOffering::query()
                ->join('course_offering_lecturers as col', 'col.course_offering_id', '=', 'course_offerings.id')
                ->where('col.lecturer_profile_id', $lecturerId)
                ->where('col.is_active', true)
                ->where('course_offerings.academic_year_id', $activeYear->id)
                ->pluck('course_offerings.id')
                ->all()
            : [];

        $inbox = array_values(array_filter([
            $this->sessionsInbox($offeringIds),
            $this->gradingInbox($offeringIds),
            $this->appealsInbox($user, $lecturerId, $offeringIds),
            $this->consultationsInbox($lecturerId),
            $this->draftGradesInbox($activeOfferingIds),
            $this->announcementDraftsInbox($user),
        ]));

        return Inertia::render('Lecturer/Dashboard', [
            'shell' => ShellProps::make($user, 'Dashboard', 'Dashboard Dosen'),
            'hasProfile' => true,
            'lecturer' => [
                'name' => $user->name,
                'programName' => $profile->studyProgram?->name,
                'facultyName' => $profile->faculty?->name,
                'activeYearName' => $activeYear?->name,
            ],
            'inbox' => $inbox,
            'summary' => $this->semesterSummary($lecturerId, $activeOfferingIds, $activeYear),
            'week' => $this->weekTimeline($offeringIds, $lecturerId),
            'classes' => $this->classList($activeOfferingIds),
            'classesUrl' => route('lecturer.course-offerings.index'),
            'gradesUrl' => route('lecturer.student-grades.index'),
            'bkd' => $this->bkdSummary($user),
            'tridharma' => $this->tridharmaSummary($user),
            'announcements' => $this->announcementsFeed($user),
        ]);
    }

    /**
     * Strip ringkas semester aktif: selalu ada, berdenominator,
     * dan semuanya bisa diklik. Bukan stat vanity.
     *
     * @return array<string, mixed>
     */
    private function semesterSummary(int $lecturerId, array $activeOfferingIds, $activeYear): array
    {
        $students = 0;
        $sks = 0;
        $sessionsWeek = 0;

        if ($activeOfferingIds !== []) {
            $students = (int) StudyPlanDetail::query()
                ->join('study_plans', 'study_plans.id', '=', 'study_plan_details.study_plan_id')
                ->whereIn('study_plan_details.course_offering_id', $activeOfferingIds)
                ->distinct('study_plans.student_profile_id')
                ->count('study_plans.student_profile_id');

            $sks = (int) CourseOffering::whereIn('id', $activeOfferingIds)->sum('credits');

            $sessionsWeek = AttendanceSession::query()
                ->whereIn('course_offering_id', $activeOfferingIds)
                ->whereBetween('meeting_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->count();
        }

        $advisees = \App\Models\Academic\AcademicAdvisorAssignment::where('lecturer_profile_id', $lecturerId)
            ->where('is_active', true)
            ->when($activeYear, fn ($query) => $query->where(fn ($builder) => $builder
                ->where('academic_year_id', $activeYear->id)
                ->orWhereNull('academic_year_id')))
            ->count();

        return [
            'classes' => count($activeOfferingIds),
            'sks' => $sks,
            'students' => $students,
            'sessionsWeek' => $sessionsWeek,
            'advisees' => $advisees,
            'yearName' => $activeYear?->name,
            'classesUrl' => route('lecturer.course-offerings.index'),
            'calendarUrl' => route('lecturer.calendar.index'),
            'advisingUrl' => route('lecturer.academic-advising.index'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sessionsInbox(array $offeringIds): ?array
    {
        if ($offeringIds === []) {
            return null;
        }

        $sessions = AttendanceSession::query()
            ->with('courseOffering.course')
            ->whereIn('course_offering_id', $offeringIds)
            ->whereDate('meeting_date', now()->toDateString())
            ->whereNotIn('status', ['Opened', 'Closed'])
            ->orderBy('start_time')
            ->get();

        if ($sessions->isEmpty()) {
            return null;
        }

        return [
            'key' => 'sessions',
            'tone' => 'red',
            'title' => 'Sesi hari ini belum dibuka',
            'count' => $sessions->count(),
            'items' => $sessions->take(3)->map(fn ($session) => [
                'label' => ($session->courseOffering?->course?->code ?? 'MK').' · P'.$session->meeting_no.' · '.substr((string) $session->start_time, 0, 5),
                'url' => route('lecturer.course-offerings.attendance', ['offeringId' => $session->course_offering_id]),
            ])->values()->all(),
            'moreUrl' => route('lecturer.calendar.index'),
            'moreLabel' => 'Lihat kalender',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gradingInbox(array $offeringIds): ?array
    {
        if ($offeringIds === []) {
            return null;
        }

        $assignments = Assignment::query()
            ->whereIn('course_offering_id', $offeringIds)
            ->withCount([
                'submissions',
                'submissions as graded_count' => fn ($query) => $query->whereHas('grade', fn ($grade) => $grade->where('status', 'graded')),
                'submissions as returned_count' => fn ($query) => $query->whereHas('grade', fn ($grade) => $grade->where('status', 'returned')),
            ])
            ->orderBy('due_at')
            ->get()
            ->map(fn ($assignment) => [
                'assignment' => $assignment,
                'queue' => max(0, (int) $assignment->submissions_count - (int) $assignment->graded_count - (int) $assignment->returned_count),
            ])
            ->filter(fn ($row) => $row['queue'] > 0)
            ->values();

        if ($assignments->isEmpty()) {
            return null;
        }

        return [
            'key' => 'grading',
            'tone' => 'amber',
            'title' => 'Tugas menunggu dinilai',
            'count' => $assignments->sum('queue'),
            'items' => $assignments->take(3)->map(fn ($row) => [
                'label' => $row['assignment']->title.' · '.$row['queue'].' naskah'.($row['assignment']->due_at ? ' · tenggat '.$row['assignment']->due_at->format('d M Y') : ''),
                'url' => route('lecturer.assignments.show', $row['assignment']),
            ])->values()->all(),
            'moreUrl' => route('lecturer.assignments.index'),
            'moreLabel' => 'Semua tugas',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function appealsInbox($user, int $lecturerId, array $offeringIds): ?array
    {
        $query = GradeAppeal::query()->whereIn('status', ['submitted', 'under_review']);

        $query->where(function ($builder) use ($lecturerId, $offeringIds) {
            $builder->where('lecturer_profile_id', $lecturerId);
            if ($offeringIds !== []) {
                $builder->orWhereIn('course_offering_id', $offeringIds);
            }
        });

        $count = $query->count();

        if ($count === 0) {
            return null;
        }

        return [
            'key' => 'appeals',
            'tone' => 'red',
            'title' => 'Sanggahan nilai menunggu keputusan',
            'count' => $count,
            'items' => [],
            'moreUrl' => route('lecturer.grade-appeals.index'),
            'moreLabel' => 'Review sekarang',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consultationsInbox(int $lecturerId): ?array
    {
        $requested = ConsultationAppointment::where('lecturer_profile_id', $lecturerId)
            ->where('status', 'requested')
            ->count();
        $overdue = ConsultationAppointment::where('lecturer_profile_id', $lecturerId)
            ->where('status', 'confirmed')
            ->where('starts_at', '<', now())
            ->count();

        $total = $requested + $overdue;

        if ($total === 0) {
            return null;
        }

        $parts = [];
        if ($requested > 0) {
            $parts[] = $requested.' permintaan baru';
        }
        if ($overdue > 0) {
            $parts[] = $overdue.' lewat waktu belum diselesaikan';
        }

        return [
            'key' => 'consultations',
            'tone' => 'amber',
            'title' => 'Konsultasi perlu respon',
            'count' => $total,
            'items' => [['label' => implode(' · ', $parts), 'url' => route('lecturer.consultations.index')]],
            'moreUrl' => route('lecturer.consultations.index'),
            'moreLabel' => 'Buka konsultasi',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function draftGradesInbox(array $activeOfferingIds): ?array
    {
        if ($activeOfferingIds === []) {
            return null;
        }

        $rows = $this->gradeProgressRows($activeOfferingIds)
            ->filter(fn ($row) => $row['remaining'] > 0)
            ->sortByDesc('remaining')
            ->take(3)
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'key' => 'drafts',
            'tone' => 'amber',
            'title' => 'Nilai masih draft',
            'count' => $rows->sum('remaining'),
            'items' => $rows->map(fn ($row) => [
                'label' => $row['code'].' · sisa '.$row['remaining'].' dari '.$row['total'],
                'url' => $row['gradesUrl'],
            ])->all(),
            'moreUrl' => route('lecturer.student-grades.index'),
            'moreLabel' => 'Rekap nilai',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function announcementDraftsInbox($user): ?array
    {
        $count = Announcement::where('created_by', $user->id)->where('is_published', false)->count();

        if ($count === 0) {
            return null;
        }

        return [
            'key' => 'announcement_drafts',
            'tone' => 'gray',
            'title' => 'Draft pengumuman belum terbit',
            'count' => $count,
            'items' => [],
            'moreUrl' => route('lecturer.announcements.index'),
            'moreLabel' => 'Kelola pengumuman',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weekTimeline(array $offeringIds, int $lecturerId): array
    {
        $items = collect();
        $from = now()->startOfDay();
        $to = now()->addDays(7)->endOfDay();

        if ($offeringIds !== []) {
            $sessions = AttendanceSession::query()
                ->with('courseOffering.course')
                ->whereIn('course_offering_id', $offeringIds)
                ->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()])
                ->orderBy('meeting_date')
                ->orderBy('start_time')
                ->limit(10)
                ->get();

            foreach ($sessions as $session) {
                $items->push([
                    'kind' => 'session',
                    'sortKey' => $session->meeting_date->format('Y-m-d').' '.substr((string) $session->start_time, 0, 5),
                    'dateLabel' => $session->meeting_date->format('d M Y'),
                    'timeLabel' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
                    'title' => ($session->courseOffering?->course?->code ?? 'MK').' · Pertemuan '.$session->meeting_no,
                    'subtitle' => $session->courseOffering?->course?->name ?? '',
                    'url' => route('lecturer.course-offerings.attendance', ['offeringId' => $session->course_offering_id]),
                ]);
            }

            $assignments = Assignment::query()
                ->whereIn('course_offering_id', $offeringIds)
                ->whereBetween('due_at', [$from, $to])
                ->orderBy('due_at')
                ->limit(10)
                ->get();

            foreach ($assignments as $assignment) {
                $items->push([
                    'kind' => 'deadline',
                    'sortKey' => $assignment->due_at->format('Y-m-d H:i'),
                    'dateLabel' => $assignment->due_at->format('d M Y'),
                    'timeLabel' => $assignment->due_at->format('H:i'),
                    'title' => 'Tenggat: '.$assignment->title,
                    'subtitle' => '',
                    'url' => route('lecturer.assignments.show', $assignment),
                ]);
            }
        }

        $consultations = ConsultationAppointment::query()
            ->with('studentProfile.user')
            ->where('lecturer_profile_id', $lecturerId)
            ->where('status', 'confirmed')
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        foreach ($consultations as $appointment) {
            $items->push([
                'kind' => 'consultation',
                'sortKey' => $appointment->starts_at->format('Y-m-d H:i'),
                'dateLabel' => $appointment->starts_at->format('d M Y'),
                'timeLabel' => $appointment->starts_at->format('H:i'),
                'title' => 'Konsultasi: '.($appointment->studentProfile?->user?->name ?? 'Mahasiswa'),
                'subtitle' => $appointment->topic ?? '',
                'url' => route('lecturer.consultations.index'),
            ]);
        }

        return $items->sortBy('sortKey')->take(10)->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classList(array $activeOfferingIds): array
    {
        if ($activeOfferingIds === []) {
            return [];
        }

        $offerings = CourseOffering::query()
            ->with(['course', 'academicYear'])
            ->whereIn('id', $activeOfferingIds)
            ->orderBy('id')
            ->get();

        $studentCounts = StudyPlanDetail::query()
            ->selectRaw('study_plan_details.course_offering_id, COUNT(DISTINCT study_plans.student_profile_id) as total')
            ->join('study_plans', 'study_plans.id', '=', 'study_plan_details.study_plan_id')
            ->whereIn('study_plan_details.course_offering_id', $activeOfferingIds)
            ->groupBy('study_plan_details.course_offering_id')
            ->pluck('total', 'study_plan_details.course_offering_id');

        $progress = $this->gradeProgressRows($activeOfferingIds)->keyBy('id');

        return $offerings->map(fn ($offering) => [
            'id' => $offering->id,
            'code' => $offering->course?->code ?? '-',
            'name' => $offering->course?->name ?? '-',
            'label' => $offering->label,
            'yearName' => $offering->academicYear?->name,
            'students' => (int) ($studentCounts[$offering->id] ?? 0),
            'graded' => (int) ($progress[$offering->id]['done'] ?? 0),
            'totalGrades' => (int) ($progress[$offering->id]['total'] ?? 0),
            'attendanceUrl' => route('lecturer.course-offerings.attendance', ['offeringId' => $offering->id]),
            'gradesUrl' => route('lecturer.course-offerings.grades', ['offeringId' => $offering->id]),
            'materialsUrl' => route('lecturer.course-materials.index', ['offeringId' => $offering->id]),
        ])->values()->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function gradeProgressRows(array $activeOfferingIds)
    {
        $totals = StudyPlanDetail::query()
            ->selectRaw('course_offering_id, COUNT(*) as total')
            ->whereIn('course_offering_id', $activeOfferingIds)
            ->groupBy('course_offering_id')
            ->pluck('total', 'course_offering_id');

        $done = StudentGrade::query()
            ->selectRaw('study_plan_details.course_offering_id, COUNT(*) as done')
            ->join('study_plan_details', 'study_plan_details.id', '=', 'student_grades.study_plan_detail_id')
            ->whereIn('study_plan_details.course_offering_id', $activeOfferingIds)
            ->whereIn('student_grades.grade_status', ['Finalized', 'Published'])
            ->groupBy('study_plan_details.course_offering_id')
            ->pluck('done', 'study_plan_details.course_offering_id');

        $offerings = CourseOffering::query()
            ->with('course')
            ->whereIn('id', $activeOfferingIds)
            ->get()
            ->keyBy('id');

        return collect($activeOfferingIds)->map(function ($id) use ($totals, $done, $offerings) {
            $total = (int) ($totals[$id] ?? 0);
            $finished = min((int) ($done[$id] ?? 0), $total);

            return [
                'id' => $id,
                'code' => $offerings[$id]?->course?->code ?? '-',
                'total' => $total,
                'done' => $finished,
                'remaining' => max(0, $total - $finished),
                'gradesUrl' => route('lecturer.course-offerings.grades', ['offeringId' => $id]),
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bkdSummary($user): ?array
    {
        $submission = \App\Models\Organization\LecturerWorkloadSubmission::query()
            ->with('period')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $openPeriod = LecturerWorkloadPeriod::query()
            ->where('status', 'open')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();

        if (! $submission && ! $openPeriod) {
            return null;
        }

        $status = $submission?->status;
        $label = match ($status) {
            'draft' => 'Draft perlu disiapkan',
            'revision', 'rejected', 'cancelled' => 'Perlu revisi & submit ulang',
            'submitted', 'in_approval' => 'Menunggu approval',
            'approved' => 'Disetujui',
            default => $openPeriod ? 'Periode terbuka' : 'Belum ada pengajuan',
        };

        return [
            'periodName' => $submission?->period?->name ?? $openPeriod?->name,
            'status' => $status,
            'statusLabel' => $label,
            'url' => route('lecturer.workloads.index'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tridharmaSummary($user): ?array
    {
        $toSubmit = TridharmaRecord::where('user_id', $user->id)->whereIn('status', ['draft', 'rejected'])->count();
        $inProgress = TridharmaRecord::where('user_id', $user->id)->whereIn('status', ['approved', 'active', 'completed'])->count();

        if ($toSubmit === 0 && $inProgress === 0) {
            return null;
        }

        return [
            'toSubmit' => $toSubmit,
            'inProgress' => $inProgress,
            'url' => route('lecturer.tridharma.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function announcementsFeed($user): array
    {
        $items = Announcement::queryForLecturer($user)
            ->with('creator')
            ->limit(5)
            ->get();

        $readIds = $items->isEmpty() ? [] : AnnouncementRead::where('user_id', $user->id)
            ->whereIn('announcement_id', $items->pluck('id'))
            ->pluck('announcement_id')
            ->all();

        return [
            'unread' => Announcement::unreadCountForLecturer($user),
            'allUrl' => route('lecturer.announcements.index'),
            'items' => $items->map(fn ($announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'creator' => $announcement->creator?->name ?? '-',
                'publishedLabel' => $announcement->published_at?->format('d M Y') ?? '-',
                'isRead' => in_array($announcement->id, $readIds, true),
                'isPinned' => (bool) $announcement->is_pinned,
                'priority' => $announcement->priority?->value ?? (string) $announcement->priority,
                'url' => route('lecturer.announcements.show', $announcement),
            ])->values()->all(),
        ];
    }
}
