<?php

use App\Models\Academic\CourseOffering;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionExamSchedule;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\JobPosting;
use App\Models\Financial\FinancialHold;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
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
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];
    public array $warnings = [];
    public array $recentActivities = [];
    public array $widgetConfig = [];

    public bool $isSuperuser = false;
    public ?string $lastLoginAt = null;

    public function mount(): void
    {
        $this->isSuperuser = session('active_role') === 'superuser';
        $this->loadDashboardData();
        $this->loadWidgetConfig();
    }

    #[On('dashboard-widgets-updated')]
    public function loadWidgetConfig(?array $config = null): void
    {
        $defaultConfig = [
            'admission' => [
                'stat_card' => true,
                'trend_chart' => true,
                'top_schools' => true,
                'top_programs' => true,
                'status_funnel' => true,
                'recent_table' => true,
                'exam_schedule' => true,
                'quota_progress' => true,
            ],
            'financial' => [
                'stat_card' => true,
                'revenue_chart' => true,
                'invoice_status_chart' => true,
                'arrears_table' => true,
                'scholarship_stats' => true,
                'installment_table' => true,
                'overdue_alert' => true,
                'payment_method_chart' => true,
            ],
            'academic' => [
                'stat_card' => true,
                'gpa_chart' => true,
                'krs_approval_table' => true,
                'attendance_chart' => true,
                'grade_appeal_table' => true,
                'schedule_conflict_alert' => true,
            ],
            'student_services' => [
                'stat_card' => true,
                'services_types_chart' => true,
                'pending_requests_table' => true,
                'graduation_progress' => true,
                'complaint_resolution_chart' => true,
            ],
            'organization' => [
                'stat_card' => true,
                'attendance_chart' => true,
                'pending_approvals' => true,
                'edom_scores' => true,
                'bkd_submission_progress' => true,
                'leave_balance_summary' => true,
            ],
            'publication_alumni' => [
                'stat_card' => true,
                'tracer_study_chart' => true,
                'job_board_stats' => true,
                'tracer_study_response_rate' => true,
                'alumni_employment_chart' => true,
            ],
            'system_health' => [
                'stat_card' => true,
                'warnings' => true,
            ],
        ];

        $sessionConfig = session('admin_dashboard_widgets_config', []);
        $this->widgetConfig = $config ?? array_replace_recursive($defaultConfig, $sessionConfig);
    }

    public function loadDashboardData(): void
    {
        $user = auth()->user();
        $this->lastLoginAt = optional($user)->last_login_at?->format('d M Y H:i');

        // Superuser system metrics
        if ($this->isSuperuser) {
            $invalidRouteMenus = Menu::query()
                ->where('type', 'link')
                ->whereNotNull('route_name')
                ->get()
                ->filter(fn ($menu) => ! \Illuminate\Support\Facades\Route::has($menu->route_name))
                ->count();

            $this->warnings = [
                'roles_without_permissions' => Role::doesntHave('permissions')->count(),
                'menus_without_permission' => Menu::where('type', 'link')->whereNull('permission_name')->count(),
                'inactive_menus' => Menu::where('is_active', false)->count(),
                'orphan_child_menus' => Menu::whereNotNull('parent_id')->whereDoesntHave('parent')->count(),
                'invalid_route_menus' => $invalidRouteMenus,
            ];
            $this->warnings['total'] = array_sum($this->warnings);
        }

        $totalApplicants = AdmissionApplication::count();

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
                'count' => $s->count,
                'percentage' => $totalApplicants > 0 ? round(($s->count / $totalApplicants) * 100, 1) : 0,
            ])
            ->toArray();

        $topPrograms = AdmissionApplication::query()
            ->with('studyProgram')
            ->whereNotNull('study_program_id')
            ->selectRaw('study_program_id, count(*) as count')
            ->groupBy('study_program_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->studyProgram?->name ?? 'Belum Dipilih',
                'count' => $p->count,
                'percentage' => $totalApplicants > 0 ? round(($p->count / $totalApplicants) * 100, 1) : 0,
            ])
            ->toArray();

        $statusCounts = [
            'draft' => AdmissionApplication::where('status', 'draft')->count(),
            'submitted' => AdmissionApplication::where('status', 'submitted')->count(),
            'accepted' => AdmissionApplication::where('status', 'accepted')->count(),
            'converted' => AdmissionApplication::whereNotNull('converted_at')->count(),
        ];

        // Domain Module Stats (safely queried with fallback)
        $this->stats = [
            // Superuser stats
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'menus' => Menu::count(),

            // PMB / Admission Stats
            'admission_applicants_count' => $totalApplicants,
            'admission_pending_verification_count' => $statusCounts['submitted'],
            'admission_accepted_count' => $statusCounts['accepted'],
            'admission_converted_count' => $statusCounts['converted'],
            'admission_active_periods_count' => AdmissionPeriod::where('is_active', true)->count(),
            'admission_exam_schedules_count' => AdmissionExamSchedule::count(),
            'admission_avg_score' => (float) (AdmissionApplication::whereNotNull('final_score')->avg('final_score') ?? 0),
            'admission_status_counts' => $statusCounts,
            'top_schools' => $topSchools,
            'top_programs' => $topPrograms,
            'recent_applicants' => AdmissionApplication::with('studyProgram')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($app) => [
                    'id' => $app->id,
                    'application_number' => $app->application_number,
                    'name' => $app->full_name,
                    'high_school' => $app->high_school_name ?? '-',
                    'study_program' => $app->studyProgram?->name ?? '-',
                    'status_label' => ucfirst($app->status),
                    'status_badge_class' => match ($app->status) {
                        'accepted' => 'bg-success-lt text-success',
                        'rejected' => 'bg-danger-lt text-danger',
                        'submitted' => 'bg-warning-lt text-warning',
                        default => 'bg-secondary-lt text-secondary',
                    },
                ])->toArray(),

            // Financial Stats
            'financial_paid_sum' => (float) Payment::where('status', 'success')->sum('amount'),
            'financial_unpaid_invoices_count' => StudentInvoice::whereIn('status', ['unpaid', 'partially_paid'])->count(),
            'financial_pending_installments_count' => InvoiceInstallmentRequest::where('status', 'pending')->count(),
            'financial_holds_count' => FinancialHold::where('status', 'active')->count(),
            'recent_payments' => Payment::with('user')
                ->where('status', 'success')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'reference_number' => $p->reference_number ?? ('PAY-'.$p->id),
                    'student_name' => $p->user?->name ?? 'Mahasiswa',
                    'amount' => $p->amount,
                    'paid_at' => $p->created_at?->format('d M Y H:i') ?? '-',
                ])->toArray(),

            // Academic Stats
            'academic_course_offerings_count' => CourseOffering::count(),
            'academic_pending_krs_count' => 0, // Placeholder for pending KRS approvals
            'academic_active_students_count' => StudentProfile::where('academic_status', 'active')->count(),
            'academic_study_programs_count' => StudyProgram::where('is_active', true)->count(),

            // Student Services Stats
            'services_pending_letters_count' => ServiceLetterRequest::where('status', 'pending')->count(),
            'services_pending_leave_count' => StudentLeaveApplication::where('status', 'pending')->count(),
            'services_pending_complaints_count' => StudentComplaint::whereIn('status', ['submitted', 'in_progress'])->count(),
            'services_graduation_applicants_count' => GraduationApplication::count(),

            // Organization & HR Stats
            'org_employees_count' => EmployeeProfile::where('is_active', true)->count(),
            'org_attendance_today_count' => EmployeeAttendanceRecord::whereDate('attendance_date', today())->count(),
            'org_pending_leave_count' => EmployeeLeaveRequest::where('status', 'pending')->count(),
            'org_tridharma_count' => TridharmaRecord::count(),

            // Publication & Alumni Stats
            'pub_announcements_count' => Announcement::where('is_published', true)->count(),
            'pub_faqs_count' => Faq::where('is_active', true)->count(),
            'pub_alumni_count' => AlumniProfile::count(),
            'pub_jobs_count' => JobPosting::where('is_active', true)->count(),
        ];

        $this->recentActivities = \Spatie\Activitylog\Models\Activity::query()
            ->with('causer')
            ->latest()
            ->limit(6)
            ->get()
            ->all();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Dashboard',
            'pages' => 'Control Center Dashboard',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    {{-- Control Center Header --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
        <div class="card-body p-4 p-md-4 text-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-lg rounded-3 bg-white bg-opacity-15 border border-white border-opacity-20 text-white flex-shrink-0">
                        <i class="fas fa-gauge-high fs-3"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                                Role Active: {{ strtoupper(session('active_role') ?? 'Admin') }}
                            </span>
                            @if($isSuperuser)
                                <span class="badge bg-danger text-white fw-bold px-3 py-1 rounded-pill">Superuser</span>
                            @endif
                        </div>
                        <h2 class="fw-black text-white mb-1">Selamat Datang, {{ auth()->user()?->name }}</h2>
                        <span class="text-white-50 small">Sistem Control Center Dashboard Terintegrasi NexaCampus.</span>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <livewire:admin.dashboard.widget-configurator />
                </div>
            </div>
        </div>
    </div>

    {{-- 1. Superuser System Health Widget (Khusus Superuser) --}}
    @if($isSuperuser && ($widgetConfig['system_health']['stat_card'] ?? true))
        <x-admin.dashboard.widgets.system-health
            :stats="$stats"
            :warnings="$warnings"
            :activities="$recentActivities" />
    @endif

    {{-- 2. Modul PMB & Penerimaan Widget --}}
    <x-admin.dashboard.widgets.admission
        :stats="$stats"
        :subWidgets="array_keys(array_filter($widgetConfig['admission'] ?? []))" />

    {{-- 3. Modul Keuangan & SPP/UKT Widget --}}
    <x-admin.dashboard.widgets.financial
        :stats="$stats"
        :subWidgets="array_keys(array_filter($widgetConfig['financial'] ?? []))" />

    {{-- 4. Modul Akademik & Perkuliahan Widget --}}
    <x-admin.dashboard.widgets.academic
        :stats="$stats"
        :subWidgets="array_keys(array_filter($widgetConfig['academic'] ?? []))" />

    {{-- 5. Modul Layanan Mahasiswa Widget --}}
    <x-admin.dashboard.widgets.student-services
        :stats="$stats"
        :subWidgets="array_keys(array_filter($widgetConfig['student_services'] ?? []))" />

    {{-- 6. Modul Kepegawaian & SDM Widget --}}
    <x-admin.dashboard.widgets.organization
        :stats="$stats"
        :subWidgets="array_keys(array_filter($widgetConfig['organization'] ?? []))" />

    {{-- 7. Modul Publikasi & Alumni Widget --}}
    <x-admin.dashboard.widgets.publication-alumni
        :stats="$stats"
        :subWidgets="array_keys(array_filter($widgetConfig['publication_alumni'] ?? []))" />

    {{-- Quick Access & Activity Audit --}}
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header border-bottom p-3 p-md-4 d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fas fa-bolt fs-6"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Akses Cepat Modul Utama</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-2">
                        @activecan('admission-application.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.admission.admission-applications.index') }}" class="btn btn-outline-primary w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-user-plus fs-4"></i>
                                    <div>
                                        <div class="fw-bold">Pendaftar PMB</div>
                                        <div class="small text-muted">Verifikasi Dokumen</div>
                                    </div>
                                </a>
                            </div>
                        @endactivecan

                        @activecan('student-invoice.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-outline-success w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-wallet fs-4"></i>
                                    <div>
                                        <div class="fw-bold">Tagihan UKT</div>
                                        <div class="small text-muted">Financial Billing</div>
                                    </div>
                                </a>
                            </div>
                        @endactivecan

                        @activecan('course-offering.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.academic.course-offerings.index') }}" class="btn btn-outline-info w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-graduation-cap fs-4"></i>
                                    <div>
                                        <div class="fw-bold">Penawaran Kelas</div>
                                        <div class="small text-muted">Akademik &amp; Jadwal</div>
                                    </div>
                                </a>
                            </div>
                        @endactivecan

                        @activecan('service-letter-request.viewAny')
                            <div class="col-6">
                                <a href="{{ route('admin.student-services.letter-requests.index') }}" class="btn btn-outline-warning w-100 p-3 rounded-3 text-start d-flex align-items-center gap-2">
                                    <i class="fas fa-file-signature fs-4"></i>
                                    <div>
                                        <div class="fw-bold">Layanan Surat</div>
                                        <div class="small text-muted">Permohonan Surat</div>
                                    </div>
                                </a>
                            </div>
                        @endactivecan
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header border-bottom p-3 p-md-4 d-flex align-items-center gap-3">
                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fas fa-clock-rotate-left fs-6"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Aktivitas Sistem Terkini</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if(count($recentActivities) > 0)
                        <div class="divide-y">
                            @foreach($recentActivities as $act)
                                <div class="py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary-lt text-primary me-1">{{ $act->description }}</span>
                                        <span class="fw-semibold text-dark small">{{ optional($act->causer)->name ?? 'System' }}</span>
                                    </div>
                                    <small class="text-muted">{{ $act->created_at?->diffForHumans() }}</small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small text-center py-4">Belum ada catatan aktivitas sistem.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
