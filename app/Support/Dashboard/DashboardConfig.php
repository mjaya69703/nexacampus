<?php

namespace App\Support\Dashboard;

class DashboardConfig
{
    public static function defaults(): array
    {
        return [
            'admission' => [
                'stat_card' => true,
                'trend_chart' => true,
                'top_schools' => true,
                'top_programs' => true,
                'status_funnel' => true,
                'recent_table' => true,
                'quota_progress' => true,
            ],
            'financial' => [
                'stat_card' => true,
                'revenue_chart' => true,
                'invoice_status_chart' => true,
                'arrears_table' => true,
                'scholarship_stats' => true,
                'recent_payments' => true,
                'overdue_alert' => true,
                'payment_method_chart' => true,
            ],
            'academic' => [
                'stat_card' => true,
                'gpa_chart' => true,
                'krs_approval_table' => true,
                'attendance_chart' => true,
                'grade_appeal_table' => true,
            ],
            'student_services' => [
                'stat_card' => true,
                'complaint_resolution_chart' => true,
                'pending_requests_table' => true,
                'graduation_progress' => true,
            ],
            'organization' => [
                'stat_card' => true,
                'pending_approvals' => true,
                'tridharma_stats' => true,
            ],
            'publication_alumni' => [
                'stat_card' => true,
                'tracer_study_response_rate' => true,
                'alumni_employment_chart' => true,
                'job_board_stats' => true,
            ],
            'system_health' => [
                'stat_card' => true,
                'warnings' => true,
                'recent_activities' => true,
            ],
        ];
    }

    public static function labels(): array
    {
        return [
            'admission' => 'Modul PMB & Penerimaan',
            'financial' => 'Modul Keuangan & SPP/UKT',
            'academic' => 'Modul Akademik',
            'student_services' => 'Modul Layanan Mahasiswa',
            'organization' => 'Modul Kepegawaian & SDM',
            'publication_alumni' => 'Modul Publikasi & Alumni',
            'system_health' => 'Modul System Health (Superuser)',
        ];
    }

    /**
     * Sub-widget labels per module, keyed by config key.
     */
    public static function subWidgetLabels(): array
    {
        return [
            'admission' => [
                'stat_card' => 'Card Statistik PMB',
                'trend_chart' => 'Grafik Tren Pendaftaran Bulanan',
                'top_schools' => 'Top Asal Sekolah (SMA/SMK)',
                'top_programs' => 'Sebaran Program Studi',
                'status_funnel' => 'Funnel Pipeline Status PMB',
                'recent_table' => 'Tabel 5 Pendaftar Terbaru',
                'quota_progress' => 'Progress Kuota per Prodi',
            ],
            'financial' => [
                'stat_card' => 'Card Statistik Keuangan',
                'revenue_chart' => 'Grafik Tren Penerimaan SPP/UKT',
                'invoice_status_chart' => 'Donut Status Tagihan',
                'arrears_table' => 'Tabel Tunggakan Tertinggi',
                'scholarship_stats' => 'Ringkasan Penerima Beasiswa',
                'recent_payments' => 'Tabel Pembayaran Terbaru',
                'overdue_alert' => 'Alert Tagihan Jatuh Tempo',
                'payment_method_chart' => 'Grafik Metode Pembayaran',
            ],
            'academic' => [
                'stat_card' => 'Card Statistik Akademik',
                'gpa_chart' => 'Distribusi IPK Mahasiswa',
                'krs_approval_table' => 'KRS Menunggu Persetujuan DPA',
                'attendance_chart' => 'Rata-rata Kehadiran Perkuliahan',
                'grade_appeal_table' => 'Banding Nilai Pending',
            ],
            'student_services' => [
                'stat_card' => 'Card Statistik Layanan',
                'complaint_resolution_chart' => 'Status Pengaduan',
                'pending_requests_table' => 'Permohonan Menunggu Persetujuan',
                'graduation_progress' => 'Progress Yudisium/Wisuda',
            ],
            'organization' => [
                'stat_card' => 'Card Statistik Kepegawaian',
                'pending_approvals' => 'Persetujuan Cuti/Izin Pending',
                'tridharma_stats' => 'Statistik Tridharma',
            ],
            'publication_alumni' => [
                'stat_card' => 'Card Statistik Publikasi & Alumni',
                'tracer_study_response_rate' => 'Response Rate Tracer Study',
                'alumni_employment_chart' => 'Status Pekerjaan Alumni',
                'job_board_stats' => 'Statistik Lowongan Kerja',
            ],
            'system_health' => [
                'stat_card' => 'Card Statistik Sistem',
                'warnings' => 'Peringatan Anomali Sistem',
                'recent_activities' => 'Aktivitas Sistem Terkini',
            ],
        ];
    }

    public static function forUser(?\Illuminate\Database\Eloquent\Model $user): array
    {
        $stored = is_array($user?->dashboard_widget_config ?? null)
            ? $user->dashboard_widget_config
            : json_decode((string) ($user->dashboard_widget_config ?? ''), true);

        if (! is_array($stored)) {
            $stored = [];
        }

        return array_replace_recursive(self::defaults(), $stored);
    }
}
