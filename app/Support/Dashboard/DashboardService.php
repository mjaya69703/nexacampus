<?php

namespace App\Support\Dashboard;

use App\Models\Academic\AttendanceRecord;
use App\Models\Academic\CourseOffering;
use App\Models\Academic\GradeAppeal;
use App\Models\Academic\StudyPlan;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\StudyResult;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionExamSchedule;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\AdmissionQuota;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\JobPosting;
use App\Models\Financial\FinancialHold;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\StudentScholarship;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\TridharmaRecord;
use App\Models\Publication\Announcement;
use App\Models\Publication\Faq;
use App\Models\Settings\Menu;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\ServiceLetterRequest;
use App\Models\StudentService\StudentComplaint;
use App\Models\StudentService\StudentLeaveApplication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardService
{
    public const CACHE_TTL = 60;

    public function admission(): array
    {
        return $this->cached('admission', function () {
            $totalApplicants = AdmissionApplication::count();

            $statusCounts = [
                'draft' => AdmissionApplication::where('status', 'draft')->count(),
                'submitted' => AdmissionApplication::where('status', 'submitted')->count(),
                'accepted' => AdmissionApplication::where('status', 'accepted')->count(),
                'converted' => AdmissionApplication::whereNotNull('converted_at')->count(),
            ];

            $monthlyTrend = collect(range(5, 0))->map(function ($monthsAgo) {
                $date = now()->subMonths($monthsAgo);

                return [
                    'label' => $date->translatedFormat('M y'),
                    'count' => AdmissionApplication::whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count(),
                ];
            })->values()->toArray();

            $topSchools = AdmissionApplication::query()
                ->whereNotNull('high_school_name')
                ->where('high_school_name', '!=', '')
                ->selectRaw('high_school_name as name, count(*) as count')
                ->groupBy('high_school_name')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($s) => [
                    'name' => $s->name,
                    'count' => (int) $s->count,
                    'percentage' => $totalApplicants > 0 ? round(($s->count / $totalApplicants) * 100, 1) : 0,
                ])
                ->toArray();

            $topPrograms = AdmissionApplication::query()
                ->with('studyProgram:id,name')
                ->whereNotNull('study_program_id')
                ->selectRaw('study_program_id, count(*) as count')
                ->groupBy('study_program_id')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'name' => $p->studyProgram?->name ?? 'Belum Dipilih',
                    'count' => (int) $p->count,
                    'percentage' => $totalApplicants > 0 ? round(($p->count / $totalApplicants) * 100, 1) : 0,
                ])
                ->toArray();

            $recentApplicants = AdmissionApplication::with('studyProgram:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($app) => [
                    'id' => $app->id,
                    'application_number' => $app->application_number,
                    'name' => $app->full_name,
                    'high_school' => $app->high_school_name ?: '-',
                    'study_program' => $app->studyProgram?->name ?? '-',
                    'status_label' => ucfirst($app->status),
                    'status_badge_class' => match ($app->status) {
                        'accepted' => 'bg-success-lt text-success',
                        'rejected' => 'bg-danger-lt text-danger',
                        'submitted' => 'bg-warning-lt text-warning',
                        default => 'bg-secondary-lt text-secondary',
                    },
                ])
                ->toArray();

            $quotaProgress = AdmissionQuota::with('studyProgram:id,name')
                ->orderByDesc('quota')
                ->limit(5)
                ->get()
                ->map(fn ($quota) => [
                    'name' => $quota->studyProgram?->name ?? '-',
                    'quota' => (int) $quota->quota,
                    'accepted' => (int) $quota->accepted_count,
                    'percentage' => $quota->quota > 0 ? min(100, round(($quota->accepted_count / $quota->quota) * 100)) : 0,
                ])
                ->toArray();

            return [
                'applicants_count' => $totalApplicants,
                'pending_verification_count' => $statusCounts['submitted'],
                'accepted_count' => $statusCounts['accepted'],
                'converted_count' => $statusCounts['converted'],
                'active_periods_count' => AdmissionPeriod::where('is_active', true)->count(),
                'upcoming_exams_count' => AdmissionExamSchedule::whereDate('exam_date', '>=', today())->count(),
                'avg_score' => (float) (AdmissionApplication::whereNotNull('final_score')->avg('final_score') ?? 0),
                'status_counts' => $statusCounts,
                'monthly_trend' => $monthlyTrend,
                'top_schools' => $topSchools,
                'top_programs' => $topPrograms,
                'recent_applicants' => $recentApplicants,
                'quota_progress' => $quotaProgress,
            ];
        });
    }

    public function financial(): array
    {
        return $this->cached('financial', function () {
            $unpaidStatuses = ['pending', 'partially_paid', 'overdue'];

            $invoiceStatusBreakdown = StudentInvoice::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $monthlyRevenue = collect(range(5, 0))->map(function ($monthsAgo) {
                $date = now()->subMonths($monthsAgo);
                $sum = Payment::where('status', 'verified')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->sum('amount');

                return [
                    'label' => $date->translatedFormat('M y'),
                    'amount' => round(((float) $sum) / 1_000_000, 1),
                ];
            })->values();

            $paymentMethods = Payment::where('status', 'verified')
                ->selectRaw('payment_method, count(*) as total')
                ->groupBy('payment_method')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->mapWithKeys(fn ($row) => [ucfirst(str_replace('_', ' ', $row->payment_method)) => (int) $row->total])
                ->toArray();

            $recentPayments = Payment::with(['studentProfile.user:id,first_name,last_name'])
                ->where('status', 'verified')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'reference_number' => $p->payment_number,
                    'student_name' => $this->profileName($p->studentProfile?->user),
                    'amount' => (float) $p->amount,
                    'paid_at' => ($p->paid_at ?? $p->created_at)?->format('d M Y H:i') ?? '-',
                ])
                ->toArray();

            $topArrears = StudentInvoice::with(['studentProfile.user:id,first_name,last_name'])
                ->whereIn('status', $unpaidStatuses)
                ->orderByDesc('outstanding_amount')
                ->limit(5)
                ->get()
                ->filter(fn ($inv) => (float) $inv->outstanding_amount > 0)
                ->map(fn ($inv) => [
                    'student_name' => $this->profileName($inv->studentProfile?->user),
                    'nim' => $inv->studentProfile?->nim ?? '-',
                    'amount' => (float) $inv->outstanding_amount,
                ])
                ->values()
                ->toArray();

            $scholarships = StudentScholarship::with('scholarship:id,name')
                ->selectRaw('scholarship_id, count(*) as total')
                ->groupBy('scholarship_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->scholarship?->name ?? '-',
                    'count' => (int) $row->total,
                ])
                ->toArray();

            $overdueQuery = StudentInvoice::whereDate('due_date', '<', today())
                ->whereIn('status', $unpaidStatuses);

            return [
                'paid_sum' => (float) Payment::where('status', 'verified')->sum('amount'),
                'unpaid_invoices_count' => StudentInvoice::whereIn('status', $unpaidStatuses)->count(),
                'pending_installments_count' => InvoiceInstallmentRequest::where('status', 'pending')->count(),
                'holds_count' => FinancialHold::where('status', 'active')->count(),
                'overdue_count' => (clone $overdueQuery)->count(),
                'overdue_amount' => (float) (clone $overdueQuery)->sum('outstanding_amount'),
                'monthly_revenue' => ['labels' => $monthlyRevenue->pluck('label')->all(), 'data' => $monthlyRevenue->pluck('amount')->all()],
                'invoice_status_breakdown' => [
                    'labels' => ['Lunas', 'Belum Dibayar', 'Sebagian', 'Overdue', 'Draft', 'Dibatalkan'],
                    'series' => [
                        (int) ($invoiceStatusBreakdown['paid'] ?? 0),
                        (int) (($invoiceStatusBreakdown['pending'] ?? 0) + ($invoiceStatusBreakdown['overdue'] ?? 0)),
                        (int) ($invoiceStatusBreakdown['partially_paid'] ?? 0),
                        (int) ($invoiceStatusBreakdown['overdue'] ?? 0),
                        (int) ($invoiceStatusBreakdown['draft'] ?? 0),
                        (int) ($invoiceStatusBreakdown['cancelled'] ?? 0),
                    ],
                ],
                'payment_methods' => $paymentMethods,
                'recent_payments' => $recentPayments,
                'top_arrears' => $topArrears,
                'scholarships' => $scholarships,
            ];
        });
    }

    public function academic(): array
    {
        return $this->cached('academic', function () {
            $gpaLabels = ['< 2.00', '2.00 - 2.49', '2.50 - 2.99', '3.00 - 3.49', '>= 3.50'];

            $bucketRow = StudyResult::whereNotNull('cumulative_gpa')
                ->selectRaw("
                    sum(case when cumulative_gpa < 2.00 then 1 else 0 end) as b1,
                    sum(case when cumulative_gpa >= 2.00 and cumulative_gpa < 2.50 then 1 else 0 end) as b2,
                    sum(case when cumulative_gpa >= 2.50 and cumulative_gpa < 3.00 then 1 else 0 end) as b3,
                    sum(case when cumulative_gpa >= 3.00 and cumulative_gpa < 3.50 then 1 else 0 end) as b4,
                    sum(case when cumulative_gpa >= 3.50 then 1 else 0 end) as b5
                ")
                ->first();

            $gpaSeries = $bucketRow
                ? [(int) $bucketRow->b1, (int) $bucketRow->b2, (int) $bucketRow->b3, (int) $bucketRow->b4, (int) $bucketRow->b5]
                : array_fill(0, count($gpaLabels), 0);

            $attendanceBreakdown = AttendanceRecord::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');
            $attendanceTotal = (int) $attendanceBreakdown->sum();

            $pendingKrsList = StudyPlan::with(['studentProfile.user:id,first_name,last_name'])
                ->where('status', 'Submitted')
                ->latest('submitted_at')
                ->limit(5)
                ->get()
                ->map(fn ($plan) => [
                    'id' => $plan->id,
                    'student_name' => $this->profileName($plan->studentProfile?->user),
                    'nim' => $plan->studentProfile?->nim ?? '-',
                    'semester_no' => $plan->semester_no ?? '-',
                    'submitted_at' => $plan->submitted_at?->format('d M Y') ?? '-',
                ])
                ->toArray();

            $pendingAppeals = GradeAppeal::with(['studentUser:id,first_name,last_name', 'courseOffering:id,course_id'])
                ->whereIn('status', ['submitted', 'under_review'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($appeal) => [
                    'id' => $appeal->id,
                    'student_name' => $this->profileName($appeal->studentUser),
                    'course' => $appeal->courseOffering?->label ?? ('Kelas #'.$appeal->course_offering_id),
                    'requested_score' => $appeal->requested_score,
                    'status_label' => str_replace('_', ' ', ucfirst($appeal->status)),
                ])
                ->toArray();

            return [
                'course_offerings_count' => CourseOffering::count(),
                'active_students_count' => \App\Models\Academic\StudentProfile::where('academic_status', 'Aktif')->count(),
                'study_programs_count' => StudyProgram::where('is_active', true)->count(),
                'pending_krs_count' => StudyPlan::where('status', 'Submitted')->count(),
                'attendance_rate' => $attendanceTotal > 0 ? round((($attendanceBreakdown['Present'] ?? 0) / $attendanceTotal) * 100, 1) : null,
                'attendance_breakdown' => [
                    'labels' => ['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Absen'],
                    'series' => [
                        (int) ($attendanceBreakdown['Present'] ?? 0),
                        (int) ($attendanceBreakdown['Sick'] ?? 0),
                        (int) ($attendanceBreakdown['Excused'] ?? 0),
                        (int) ($attendanceBreakdown['Late'] ?? 0),
                        (int) ($attendanceBreakdown['Absent'] ?? 0),
                    ],
                ],
                'gpa_distribution' => ['labels' => $gpaLabels, 'series' => $gpaSeries],
                'pending_krs_list' => $pendingKrsList,
                'pending_appeals' => $pendingAppeals,
            ];
        });
    }

    public function studentServices(): array
    {
        return $this->cached('student_services', function () {
            $complaintBreakdown = StudentComplaint::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $graduationApproved = GraduationApplication::whereIn('status', ['approved', 'finalized'])->count();

            return [
                'pending_letters_count' => ServiceLetterRequest::where('status', 'pending')->count(),
                'pending_leaves_count' => StudentLeaveApplication::where('status', 'pending')->count(),
                'active_complaints_count' => StudentComplaint::whereIn('status', ['submitted', 'in_progress'])->count(),
                'graduation_applications_count' => GraduationApplication::count(),
                'graduation_approved_count' => $graduationApproved,
                'complaint_breakdown' => [
                    'labels' => ['Baru', 'Diproses', 'Selesai', 'Ditolak'],
                    'series' => [
                        (int) ($complaintBreakdown['submitted'] ?? 0),
                        (int) ($complaintBreakdown['in_progress'] ?? 0),
                        (int) (($complaintBreakdown['resolved'] ?? 0) + ($complaintBreakdown['closed'] ?? 0)),
                        (int) ($complaintBreakdown['rejected'] ?? 0),
                    ],
                ],
                'pending_requests' => collect()
                    ->merge(
                        ServiceLetterRequest::with(['studentProfile.user:id,first_name,last_name'])
                            ->where('status', 'pending')
                            ->latest()
                            ->limit(4)
                            ->get()
                            ->map(fn ($r) => [
                                'type' => 'Surat',
                                'detail' => $r->letterType?->name ?? 'Permohonan Surat',
                                'student_name' => $this->profileName($r->studentProfile?->user),
                                'created_at' => $r->created_at?->format('d M Y'),
                            ])
                    )
                    ->merge(
                        StudentLeaveApplication::with(['studentProfile.user:id,first_name,last_name'])
                            ->where('status', 'pending')
                            ->latest()
                            ->limit(4)
                            ->get()
                            ->map(fn ($r) => [
                                'type' => 'Cuti',
                                'detail' => 'Pengajuan Cuti Akademik',
                                'student_name' => $this->profileName($r->studentProfile?->user),
                                'created_at' => $r->created_at?->format('d M Y'),
                            ])
                    )
                    ->take(6)
                    ->values()
                    ->toArray(),
            ];
        });
    }

    public function organization(): array
    {
        return $this->cached('organization', function () {
            $tridharmaByType = TridharmaRecord::query()
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type');

            $pendingLeaves = EmployeeLeaveRequest::with(['employeeProfile.user:id,first_name,last_name', 'leaveType:id,name'])
                ->where('status', 'pending')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($leave) => [
                    'id' => $leave->id,
                    'employee_name' => $this->profileName($leave->employeeProfile?->user),
                    'leave_type' => $leave->leaveType?->name ?? 'Izin/Cuti',
                    'created_at' => $leave->created_at?->format('d M Y'),
                ])
                ->toArray();

            return [
                'employees_count' => EmployeeProfile::where('is_active', true)->count(),
                'attendance_today_count' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->count(),
                'pending_leave_count' => EmployeeLeaveRequest::where('status', 'pending')->count(),
                'tridharma_count' => TridharmaRecord::count(),
                'tridharma_breakdown' => [
                    'labels' => ['Penelitian', 'PkM', 'Publikasi'],
                    'series' => [
                        (int) ($tridharmaByType['research'] ?? 0),
                        (int) ($tridharmaByType['community_service'] ?? 0),
                        (int) ($tridharmaByType['publication'] ?? 0),
                    ],
                ],
                'pending_leaves' => $pendingLeaves,
            ];
        });
    }

    public function publicationAlumni(): array
    {
        return $this->cached('publication_alumni', function () {
            $employmentBreakdown = AlumniProfile::query()
                ->selectRaw('employment_status, count(*) as total')
                ->groupBy('employment_status')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            $tracerSent = DB::table('tracer_study_campaigns')->sum('total_sent');
            $tracerResponded = DB::table('tracer_study_campaigns')->sum('total_responded');

            return [
                'announcements_count' => Announcement::where('is_published', true)->count(),
                'faqs_count' => Faq::where('is_active', true)->count(),
                'alumni_count' => AlumniProfile::count(),
                'jobs_count' => JobPosting::where('is_active', true)->count(),
                'tracer_sent' => (int) $tracerSent,
                'tracer_responded' => (int) $tracerResponded,
                'tracer_response_rate' => $tracerSent > 0 ? round(($tracerResponded / $tracerSent) * 100, 1) : null,
                'employment_breakdown' => [
                    'labels' => $employmentBreakdown->pluck('employment_status')->map(fn ($s) => ucfirst(str_replace('_', ' ', (string) $s)))->all(),
                    'series' => $employmentBreakdown->pluck('total')->map(fn ($v) => (int) $v)->all(),
                ],
                'recent_jobs' => JobPosting::where('is_active', true)
                    ->orderByDesc('posted_date')
                    ->limit(5)
                    ->get()
                    ->map(fn ($job) => [
                        'title' => $job->title,
                        'company' => $job->company_name,
                        'deadline' => $job->deadline_date?->format('d M Y') ?? '-',
                    ])
                    ->toArray(),
            ];
        });
    }

    public function systemHealth(): array
    {
        $invalidRouteMenus = Menu::query()
            ->where('type', 'link')
            ->whereNotNull('route_name')
            ->get()
            ->filter(fn ($menu) => ! Route::has($menu->route_name))
            ->count();

        $warnings = [
            'roles_without_permissions' => Role::doesntHave('permissions')->count(),
            'menus_without_permission' => Menu::where('type', 'link')->whereNull('permission_name')->count(),
            'inactive_menus' => Menu::where('is_active', false)->count(),
            'orphan_child_menus' => Menu::whereNotNull('parent_id')->whereDoesntHave('parent')->count(),
            'invalid_route_menus' => $invalidRouteMenus,
        ];
        $warnings['total'] = array_sum(array_diff_key($warnings, ['total' => 0]));

        return [
            'users_count' => User::count(),
            'roles_count' => Role::count(),
            'permissions_count' => Permission::count(),
            'menus_count' => Menu::count(),
            'warnings' => $warnings,
        ];
    }

    public function recentActivities(int $limit = 6): array
    {
        return Activity::query()
            ->with('causer:id,first_name,last_name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($act) => [
                'description' => $act->description,
                'causer' => $this->profileName($act->causer) ?: 'System',
                'at' => $act->created_at?->diffForHumans(),
            ])
            ->toArray();
    }

    protected function cached(string $module, callable $resolver): array
    {
        return Cache::remember("dashboard.stats.{$module}", self::CACHE_TTL, $resolver);
    }

    protected function profileName(?User $user): string
    {
        if (! $user) {
            return '-';
        }

        return trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: '-';
    }
}
