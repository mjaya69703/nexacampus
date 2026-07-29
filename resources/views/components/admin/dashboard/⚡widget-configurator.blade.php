<?php

use App\Support\ActivePermission;
use Livewire\Component;

new class extends Component
{
    public bool $isOpen = false;
    public array $widgetConfig = [];

    public function mount(): void
    {
        $this->loadConfig();
    }

    public function loadConfig(): void
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
        $this->widgetConfig = array_replace_recursive($defaultConfig, $sessionConfig);
    }

    public function toggleSubWidget(string $module, string $subWidget): void
    {
        if (! (auth()->user()?->hasRole('superuser') || ActivePermission::check('dashboard.manage'))) {
            session()->flash('error', 'Anda tidak memiliki akses untuk mengubah konfigurasi widget.');

            return;
        }

        $current = $this->widgetConfig[$module][$subWidget] ?? true;
        $this->widgetConfig[$module][$subWidget] = ! $current;

        session(['admin_dashboard_widgets_config' => $this->widgetConfig]);
        $this->dispatch('dashboard-widgets-updated', config: $this->widgetConfig);
    }

    public function resetToDefault(): void
    {
        session()->forget('admin_dashboard_widgets_config');
        $this->loadConfig();
        $this->dispatch('dashboard-widgets-updated', config: $this->widgetConfig);
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div>
    @if(auth()->user()?->hasRole('superuser') || \App\Support\ActivePermission::check('dashboard.manage'))
        <button type="button" class="btn btn-outline-dark rounded-pill fw-bold d-inline-flex align-items-center gap-2 shadow-sm px-3 py-2" data-bs-toggle="offcanvas" data-bs-target="#offcanvasWidgetConfig">
            <i class="fas fa-sliders text-warning"></i>
            <span>Atur Konfigurasi Widget</span>
        </button>

        <div class="offcanvas offcanvas-end rounded-start-4" tabindex="-1" id="offcanvasWidgetConfig" aria-labelledby="offcanvasWidgetConfigLabel">
            <div class="offcanvas-header border-bottom bg-dark text-white p-3 p-md-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-sliders fs-5"></i>
                    </div>
                    <div>
                        <h5 class="offcanvas-title fw-bold text-white mb-0" id="offcanvasWidgetConfigLabel">Pengaturan Widget Dashboard</h5>
                        <small class="text-white text-opacity-75">Centang sub-widget &amp; grafik analitik yang ingin ditampilkan.</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>

            <div class="offcanvas-body p-3 p-md-4">
                {{-- Admission Widgets --}}
                <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                    <div class="card-header bg-primary bg-opacity-10 p-3 fw-bold text-primary d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-user-plus me-2"></i>Modul PMB &amp; Penerimaan</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="adm_stat"
                                @if($widgetConfig['admission']['stat_card'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'stat_card')">
                            <label class="form-check-label fw-semibold" for="adm_stat">Card Statistik PMB (Pendaftar &amp; Gelombang)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="adm_trend"
                                @if($widgetConfig['admission']['trend_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'trend_chart')">
                            <label class="form-check-label fw-semibold" for="adm_trend">Grafik ApexChart Tren Pendaftaran PMB</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="adm_schools"
                                @if($widgetConfig['admission']['top_schools'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'top_schools')">
                            <label class="form-check-label fw-semibold" for="adm_schools">Demografi Top Asal Sekolah (SMA/SMK)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="adm_programs"
                                @if($widgetConfig['admission']['top_programs'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'top_programs')">
                            <label class="form-check-label fw-semibold" for="adm_programs">Donut Chart Sebaran Program Studi</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="adm_funnel"
                                @if($widgetConfig['admission']['status_funnel'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'status_funnel')">
                            <label class="form-check-label fw-semibold" for="adm_funnel">Funnel Pipeline Status PMB</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="adm_table"
                                @if($widgetConfig['admission']['recent_table'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'recent_table')">
                            <label class="form-check-label fw-semibold" for="adm_table">Tabel 5 Pendaftar Terbaru</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="adm_exam"
                                @if($widgetConfig['admission']['exam_schedule'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'exam_schedule')">
                            <label class="form-check-label fw-semibold" for="adm_exam">Jadwal Seleksi Ujian / CBT</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="adm_quota"
                                @if($widgetConfig['admission']['quota_progress'] ?? true) checked @endif
                                wire:click="toggleSubWidget('admission', 'quota_progress')">
                            <label class="form-check-label fw-semibold" for="adm_quota">Progress Kuota PMB per Prodi</label>
                        </div>
                    </div>
                </div>

                {{-- Financial Widgets --}}
                <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                    <div class="card-header bg-success bg-opacity-10 p-3 fw-bold text-success d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-wallet me-2"></i>Modul Keuangan &amp; SPP</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="fin_stat"
                                @if($widgetConfig['financial']['stat_card'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'stat_card')">
                            <label class="form-check-label fw-semibold" for="fin_stat">Card Statistik Keuangan (Pembayaran &amp; Hold)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="fin_revenue"
                                @if($widgetConfig['financial']['revenue_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'revenue_chart')">
                            <label class="form-check-label fw-semibold" for="fin_revenue">Grafik ApexChart Tren Penerimaan SPP</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="fin_status"
                                @if($widgetConfig['financial']['invoice_status_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'invoice_status_chart')">
                            <label class="form-check-label fw-semibold" for="fin_status">Donut Chart Status Tagihan Mahasiswa</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="fin_arrears"
                                @if($widgetConfig['financial']['arrears_table'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'arrears_table')">
                            <label class="form-check-label fw-semibold" for="fin_arrears">Tabel Tunggakan SPP</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="fin_scholarship"
                                @if($widgetConfig['financial']['scholarship_stats'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'scholarship_stats')">
                            <label class="form-check-label fw-semibold" for="fin_scholarship">Ringkasan Penerima Beasiswa</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="fin_table"
                                @if($widgetConfig['financial']['installment_table'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'installment_table')">
                            <label class="form-check-label fw-semibold" for="fin_table">Tabel Pembayaran Terbaru</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="fin_overdue"
                                @if($widgetConfig['financial']['overdue_alert'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'overdue_alert')">
                            <label class="form-check-label fw-semibold" for="fin_overdue">Alert Tagihan Jatuh Tempo</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="fin_paymethod"
                                @if($widgetConfig['financial']['payment_method_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('financial', 'payment_method_chart')">
                            <label class="form-check-label fw-semibold" for="fin_paymethod">Grafik Metode Pembayaran</label>
                        </div>
                    </div>
                </div>

                {{-- Academic Widgets --}}
                <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                    <div class="card-header bg-info bg-opacity-10 p-3 fw-bold text-info d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-graduation-cap me-2"></i>Modul Akademik</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="acad_stat"
                                @if($widgetConfig['academic']['stat_card'] ?? true) checked @endif
                                wire:click="toggleSubWidget('academic', 'stat_card')">
                            <label class="form-check-label fw-semibold" for="acad_stat">Card Statistik Akademik (Kelas &amp; Mahasiswa)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="acad_gpa"
                                @if($widgetConfig['academic']['gpa_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('academic', 'gpa_chart')">
                            <label class="form-check-label fw-semibold" for="acad_gpa">Grafik ApexChart Distribusi IPK Mahasiswa</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="acad_krs"
                                @if($widgetConfig['academic']['krs_approval_table'] ?? true) checked @endif
                                wire:click="toggleSubWidget('academic', 'krs_approval_table')">
                            <label class="form-check-label fw-semibold" for="acad_krs">Daftar KRS Butuh Persetujuan DPA</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="acad_att"
                                @if($widgetConfig['academic']['attendance_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('academic', 'attendance_chart')">
                            <label class="form-check-label fw-semibold" for="acad_att">Rata-rata Kehadiran Mahasiswa</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="acad_appeal"
                                @if($widgetConfig['academic']['grade_appeal_table'] ?? true) checked @endif
                                wire:click="toggleSubWidget('academic', 'grade_appeal_table')">
                            <label class="form-check-label fw-semibold" for="acad_appeal">Daftar Banding Nilai Pending</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="acad_conflict"
                                @if($widgetConfig['academic']['schedule_conflict_alert'] ?? true) checked @endif
                                wire:click="toggleSubWidget('academic', 'schedule_conflict_alert')">
                            <label class="form-check-label fw-semibold" for="acad_conflict">Deteksi Konflik Jadwal</label>
                        </div>
                    </div>
                </div>

                {{-- Student Services Widgets --}}
                <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                    <div class="card-header bg-warning bg-opacity-10 p-3 fw-bold text-warning d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-hands-helping me-2"></i>Modul Layanan Mahasiswa</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="serv_stat"
                                @if($widgetConfig['student_services']['stat_card'] ?? true) checked @endif
                                wire:click="toggleSubWidget('student_services', 'stat_card')">
                            <label class="form-check-label fw-semibold" for="serv_stat">Card Statistik Layanan (Surat &amp; Cuti)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="serv_types"
                                @if($widgetConfig['student_services']['services_types_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('student_services', 'services_types_chart')">
                            <label class="form-check-label fw-semibold" for="serv_types">Grafik ApexChart Permohonan Jenis Surat</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="serv_pending"
                                @if($widgetConfig['student_services']['pending_requests_table'] ?? true) checked @endif
                                wire:click="toggleSubWidget('student_services', 'pending_requests_table')">
                            <label class="form-check-label fw-semibold" for="serv_pending">Tabel Layanan Menunggu Persetujuan</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="serv_grad"
                                @if($widgetConfig['student_services']['graduation_progress'] ?? true) checked @endif
                                wire:click="toggleSubWidget('student_services', 'graduation_progress')">
                            <label class="form-check-label fw-semibold" for="serv_grad">Progress Yudisium Batch Aktif</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="serv_complaint"
                                @if($widgetConfig['student_services']['complaint_resolution_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('student_services', 'complaint_resolution_chart')">
                            <label class="form-check-label fw-semibold" for="serv_complaint">Grafik Status Pengaduan</label>
                        </div>
                    </div>
                </div>

                {{-- Organization & HR Widgets --}}
                <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                    <div class="card-header bg-purple bg-opacity-10 p-3 fw-bold text-purple d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-sitemap me-2"></i>Modul Kepegawaian &amp; SDM</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="org_stat"
                                @if($widgetConfig['organization']['stat_card'] ?? true) checked @endif
                                wire:click="toggleSubWidget('organization', 'stat_card')">
                            <label class="form-check-label fw-semibold" for="org_stat">Card Statistik Kepegawaian (Pegawai &amp; Presensi)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="org_att"
                                @if($widgetConfig['organization']['attendance_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('organization', 'attendance_chart')">
                            <label class="form-check-label fw-semibold" for="org_att">Grafik ApexChart Presensi Kehadiran Pegawai</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="org_pending"
                                @if($widgetConfig['organization']['pending_approvals'] ?? true) checked @endif
                                wire:click="toggleSubWidget('organization', 'pending_approvals')">
                            <label class="form-check-label fw-semibold" for="org_pending">Daftar Persetujuan Cuti/Izin Menunggu</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="org_edom"
                                @if($widgetConfig['organization']['edom_scores'] ?? true) checked @endif
                                wire:click="toggleSubWidget('organization', 'edom_scores')">
                            <label class="form-check-label fw-semibold" for="org_edom">Ringkasan Nilai EDOM Dosen</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="org_bkd"
                                @if($widgetConfig['organization']['bkd_submission_progress'] ?? true) checked @endif
                                wire:click="toggleSubWidget('organization', 'bkd_submission_progress')">
                            <label class="form-check-label fw-semibold" for="org_bkd">Progress Pengumpulan BKD</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="org_leave"
                                @if($widgetConfig['organization']['leave_balance_summary'] ?? true) checked @endif
                                wire:click="toggleSubWidget('organization', 'leave_balance_summary')">
                            <label class="form-check-label fw-semibold" for="org_leave">Ringkasan Saldo Cuti Pegawai</label>
                        </div>
                    </div>
                </div>

                {{-- Publication & Alumni Widgets --}}
                <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                    <div class="card-header bg-teal bg-opacity-10 p-3 fw-bold text-teal d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-bullhorn me-2"></i>Modul Publikasi &amp; Alumni</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="pub_stat"
                                @if($widgetConfig['publication_alumni']['stat_card'] ?? true) checked @endif
                                wire:click="toggleSubWidget('publication_alumni', 'stat_card')">
                            <label class="form-check-label fw-semibold" for="pub_stat">Card Statistik Publikasi &amp; Alumni</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="pub_tracer"
                                @if($widgetConfig['publication_alumni']['tracer_study_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('publication_alumni', 'tracer_study_chart')">
                            <label class="form-check-label fw-semibold" for="pub_tracer">Grafik ApexChart Tracer Study Masa Tunggu</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="pub_job"
                                @if($widgetConfig['publication_alumni']['job_board_stats'] ?? true) checked @endif
                                wire:click="toggleSubWidget('publication_alumni', 'job_board_stats')">
                            <label class="form-check-label fw-semibold" for="pub_job">Statistik Lowongan Kerja (Job Board)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="pub_response"
                                @if($widgetConfig['publication_alumni']['tracer_study_response_rate'] ?? true) checked @endif
                                wire:click="toggleSubWidget('publication_alumni', 'tracer_study_response_rate')">
                            <label class="form-check-label fw-semibold" for="pub_response">Response Rate Tracer Study</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="pub_employ"
                                @if($widgetConfig['publication_alumni']['alumni_employment_chart'] ?? true) checked @endif
                                wire:click="toggleSubWidget('publication_alumni', 'alumni_employment_chart')">
                            <label class="form-check-label fw-semibold" for="pub_employ">Grafik Status Pekerjaan Alumni</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-ghost-danger px-3 py-1.5" wire:click="resetToDefault">
                        <i class="fas fa-rotate-left me-1"></i> Reset Default
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 py-1.5" data-bs-dismiss="offcanvas">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
