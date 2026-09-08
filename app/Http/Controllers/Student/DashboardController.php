<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\StudentGrade;
use App\Models\Academic\StudentRegistration;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyPlanDetail;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
use App\Models\Financial\StudentInvoice;
use App\Models\Publication\Announcement;
use App\Models\Publication\AnnouncementRead;
use App\Support\Financial\InvoiceStatusService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->studentProfile()->with(['studyProgram.faculty', 'entryAcademicYear'])->first();

        if (! $profile) {
            return Inertia::render('Student/Dashboard', [
                'shell' => ShellProps::make($user, 'Dashboard', 'Dashboard Mahasiswa'),
                'hasProfile' => false,
                'student' => ['name' => $user->name],
                'tiles' => [],
                'stats' => ['courses' => 0, 'credits' => 0, 'publishedGrades' => 0, 'transcripts' => 0],
                'academic' => ['ips' => '-', 'ipk' => '-', 'programName' => '-', 'activeYear' => '-'],
                'attendance' => ['total' => 0, 'attended' => 0, 'absent' => 0, 'rate' => null],
                'grades' => [],
                'schedules' => [],
                'finance' => ['total' => 0, 'outstanding' => 0, 'outstandingLabel' => 'Rp 0', 'overdue' => 0, 'paid' => 0, 'invoices' => []],
                'announcements' => ['unread' => 0, 'items' => []],
                'urls' => $this->urls(),
            ]);
        }

        $activeYear = AcademicYear::query()->where('is_active', true)->latest('start_date')->first();

        $currentRegistration = $activeYear
            ? StudentRegistration::where('student_profile_id', $profile->id)->where('academic_year_id', $activeYear->id)->latest('id')->first()
            : StudentRegistration::where('student_profile_id', $profile->id)->latest('id')->first();

        $currentPlan = $activeYear
            ? StudyPlan::where('student_profile_id', $profile->id)->where('academic_year_id', $activeYear->id)->latest('id')->first()
            : StudyPlan::where('student_profile_id', $profile->id)->latest('id')->first();

        $latestResult = StudyResult::where('student_profile_id', $profile->id)->latest('id')->first();

        $detailQuery = StudyPlanDetail::query();
        if ($currentPlan) {
            $detailQuery->where('study_plan_id', $currentPlan->id);
        } else {
            $detailQuery->whereRaw('1 = 0');
        }

        $publishedQuery = StudentGrade::query()
            ->whereHas('studyPlanDetail.studyPlan', fn ($query) => $query->where('student_profile_id', $profile->id))
            ->where('grade_status', 'Published');

        $publishedCount = (clone $publishedQuery)->count();
        $publishedAvg = (clone $publishedQuery)->whereNotNull('grade_point')->avg('grade_point');

        $attendanceBase = AttendanceRecord::query()->where('student_profile_id', $profile->id);
        $totalAttendance = (clone $attendanceBase)->count();
        $attendedCount = (clone $attendanceBase)->whereIn('status', ['Present', 'Late', 'Excused', 'Sick'])->count();
        $absentCount = (clone $attendanceBase)->where('status', 'Absent')->count();

        $grades = StudentGrade::query()
            ->with(['studyPlanDetail.courseOffering.course'])
            ->whereHas('studyPlanDetail.studyPlan', fn ($query) => $query->where('student_profile_id', $profile->id))
            ->where('grade_status', 'Published')
            ->latest('graded_at')
            ->limit(8)
            ->get()
            ->map(fn ($grade) => [
                'id' => $grade->id,
                'courseName' => $grade->studyPlanDetail?->courseOffering?->course?->name ?? '-',
                'finalScore' => $grade->final_score !== null ? number_format((float) $grade->final_score, 2) : null,
                'letter' => $grade->letter_grade,
                'gradePoint' => $grade->grade_point !== null ? number_format((float) $grade->grade_point, 2) : null,
                'gradedLabel' => $grade->graded_at?->format('d M Y'),
            ])
            ->values()
            ->all();

        // Samakan perilaku Blade: segarkan status tagihan bermasalah saat dibaca.
        StudentInvoice::query()
            ->where('student_profile_id', $profile->id)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->get()
            ->each(fn (StudentInvoice $invoice) => app(InvoiceStatusService::class)->refresh($invoice));

        $invoices = StudentInvoice::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', '!=', 'draft')
            ->get();

        $priority = ['overdue' => 1, 'partially_paid' => 2, 'issued' => 3, 'paid' => 4];
        $recentInvoices = $invoices
            ->sortBy(fn ($invoice) => $priority[$invoice->status] ?? 9)
            ->take(3)
            ->values()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'typeLabel' => ucwords(str_replace('_', ' ', (string) $invoice->invoice_type)),
                'dueLabel' => $invoice->due_date?->format('d M Y'),
                'outstanding' => (float) $invoice->outstanding_amount,
                'outstandingLabel' => 'Rp '.number_format((float) $invoice->outstanding_amount, 0, ',', '.'),
                'status' => $invoice->status,
                'url' => route('student.financial.invoices.show', $invoice),
            ])
            ->values()
            ->all();

        $outstanding = (float) $invoices->whereNotIn('status', ['paid', 'cancelled'])->sum('outstanding_amount');

        $schedules = $currentPlan ? $this->schedules($currentPlan->id) : [];

        $announcements = Announcement::queryForStudent($user)->with('creator')->limit(6)->get();
        $readIds = $announcements->isEmpty() ? [] : AnnouncementRead::where('user_id', $user->id)
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->pluck('announcement_id')
            ->all();

        return Inertia::render('Student/Dashboard', [
            'shell' => ShellProps::make($user, 'Dashboard', 'Dashboard Mahasiswa'),
            'hasProfile' => true,
            'student' => [
                'name' => $user->name,
                'nim' => $profile->nim,
                'programName' => $profile->studyProgram?->name ?? '-',
                'facultyName' => $profile->studyProgram?->faculty?->name ?? '-',
                'academicStatus' => $profile->academic_status,
                'semester' => $profile->current_semester,
                'lastLogin' => $user->last_login_at?->format('d M Y H:i'),
            ],
            'tiles' => [
                ['label' => 'Status Akademik', 'value' => $profile->academic_status ?? '-'],
                ['label' => 'Registrasi', 'value' => $currentRegistration?->registration_status ?? '-'],
                ['label' => 'KRS', 'value' => $currentPlan?->status ?? '-'],
                ['label' => 'Tahun Aktif', 'value' => $activeYear?->name ?? '-'],
            ],
            'stats' => [
                'courses' => (clone $detailQuery)->count(),
                'credits' => (int) (clone $detailQuery)->sum('credits'),
                'publishedGrades' => $publishedCount,
                'passedCourses' => TranscriptEntry::query()
                    ->where('student_profile_id', $profile->id)
                    ->where('result_status', 'Passed')
                    ->count(),
            ],
            'academic' => [
                'ips' => $latestResult?->semester_gpa ? number_format((float) $latestResult->semester_gpa, 2) : '-',
                'ipk' => $latestResult?->cumulative_gpa
                    ? number_format((float) $latestResult->cumulative_gpa, 2)
                    : ($publishedAvg ? number_format((float) $publishedAvg, 2) : '-'),
                'programName' => $profile->studyProgram?->name ?? '-',
                'activeYear' => $activeYear?->name ?? '-',
            ],
            'attendance' => [
                'total' => $totalAttendance,
                'attended' => $attendedCount,
                'absent' => $absentCount,
                'rate' => $totalAttendance > 0 ? round(($attendedCount / $totalAttendance) * 100, 1) : null,
            ],
            'grades' => $grades,
            'schedules' => $schedules,
            'finance' => [
                'total' => $invoices->count(),
                'outstanding' => $outstanding,
                'outstandingLabel' => 'Rp '.number_format($outstanding, 0, ',', '.'),
                'overdue' => $invoices->where('status', 'overdue')->count(),
                'paid' => $invoices->where('status', 'paid')->count(),
                'invoices' => $recentInvoices,
            ],
            'announcements' => [
                'unread' => Announcement::unreadCountForStudent($user),
                'items' => $announcements->map(fn ($announcement) => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'creator' => $announcement->creator?->name ?? '-',
                    'publishedLabel' => $announcement->published_at?->format('d M Y') ?? '-',
                    'isRead' => in_array($announcement->id, $readIds, true),
                    'isPinned' => (bool) $announcement->is_pinned,
                    'priority' => $announcement->priority?->value ?? (string) $announcement->priority,
                    'url' => route('student.announcements.show', $announcement),
                ])->values()->all(),
            ],
            'urls' => $this->urls(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schedules(int $studyPlanId): array
    {
        $hariId = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];
        $isoDay = [
            'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4,
            'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7,
        ];

        return StudyPlanDetail::query()
            ->with(['courseOffering.course', 'courseOffering.courseSchedules.room.building'])
            ->where('study_plan_id', $studyPlanId)
            ->get()
            ->flatMap(function ($detail) use ($hariId, $isoDay) {
                $offering = $detail->courseOffering;

                if (! $offering) {
                    return [];
                }

                $courseName = $offering->course?->name ?? $offering->course?->title ?? ($offering->label ?: '-');

                return $offering->courseSchedules
                    ->where('is_active', true)
                    ->map(function ($schedule) use ($courseName, $hariId, $isoDay) {
                        $diff = (($isoDay[$schedule->day_of_week] ?? 1) - now()->dayOfWeekIso + 7) % 7;
                        $date = now()->addDays($diff);

                        return [
                            'courseName' => $courseName,
                            'hari' => $hariId[$schedule->day_of_week] ?? $schedule->day_of_week,
                            'tanggal' => $date->format('d M'),
                            'sortKey' => $date->format('Y-m-d').$this->formatTime($schedule->start_time),
                            'timeLabel' => $this->formatTime($schedule->start_time).' - '.$this->formatTime($schedule->end_time),
                            'roomLabel' => ($schedule->room?->building?->name ?? '-').' / '.($schedule->room?->name ?? '-'),
                            'mode' => $schedule->delivery_mode,
                        ];
                    });
            })
            ->sortBy('sortKey')
            ->values()
            ->take(8)
            ->all();
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && strlen($value) >= 5) {
            return substr($value, 0, 5);
        }

        return '-';
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        return [
            'registration' => route('student.registration.index'),
            'studyPlan' => route('student.study-plan.index'),
            'grades' => route('student.grades.index'),
            'schedule' => route('student.schedule.index'),
            'transcript' => route('student.transcript.index'),
            'invoices' => route('student.financial.invoices'),
            'announcements' => route('student.announcements.index'),
        ];
    }
}
