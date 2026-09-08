<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ActivePermission;
use App\Support\Dashboard\DashboardService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kokpit operasional admin — adaptif berbasis izin, bukan berbasis nama role.
 *
 * Controller ini TIDAK me-render "dashboard admin" yang fixed. Ia merakit
 * array sections[] secara dinamis: setiap section mendeklarasikan permission
 * syaratnya, dan hanya section yang lolos ActivePermission::check() yang
 * dikirim ke React beserta datanya. Role baru otomatis mendapat dashboard
 * berisi tepat apa yang boleh ia akses — tanpa perubahan kode.
 *
 * Seluruh angka diambil dari DashboardService (cache 60 detik); controller
 * ini hanya memetakan bentuk service ke kontrak generik sections[].
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $service = app(DashboardService::class);
        $sections = [];

        if (ActivePermission::check('admission-application.viewAny')) {
            $sections[] = $this->admissionSection($service->admission());
        }

        if (ActivePermission::any(['student-invoice.viewAny', 'payment.viewAny'])) {
            $sections[] = $this->financialSection($service->financial());
        }

        if (ActivePermission::any(['course-offering.viewAny', 'study-plan.viewAny'])) {
            $sections[] = $this->academicSection($service->academic());
        }

        if (ActivePermission::any(['service-letter-request.viewAny', 'student-complaint.viewAny'])) {
            $sections[] = $this->studentServicesSection($service->studentServices());
        }

        if (ActivePermission::any(['employee-profile.viewAny', 'tridharma-record.viewAny'])) {
            $sections[] = $this->organizationSection($service->organization());
        }

        if (ActivePermission::any(['announcement.viewAny', 'alumni-profile.viewAny'])) {
            $sections[] = $this->publicationAlumniSection($service->publicationAlumni());
        }

        if (ActivePermission::check('dashboard.manage')) {
            $sections[] = $this->systemHealthSection($service->systemHealth());
        }

        if (ActivePermission::check('activity-log.viewAny')) {
            $sections[] = $this->activitySection($service->recentActivities(6));
        }

        return Inertia::render('Admin/Dashboard', [
            'shell' => ShellProps::make($user, 'Dashboard', 'Kokpit Operasional'),
            'hero' => [
                'name' => $user->name,
                'lastLogin' => $user->last_login_at?->format('d M Y H:i'),
                'roleLabel' => $request->session()->get('active_role'),
                'sectionCount' => count($sections),
            ],
            'sections' => $sections,
        ]);
    }

    // ── Section builders ──────────────────────────────────────────────

    private function admissionSection(array $stats): array
    {
        $total = (int) ($stats['applicants_count'] ?? 0);

        return [
            'key' => 'admission',
            'title' => 'Penerimaan Mahasiswa Baru',
            'subtitle' => 'Pendaftar, verifikasi, kuota, dan konversi',
            'icon' => 'fas fa-user-plus',
            'accent' => 'brand',
            'stats' => [
                ['label' => 'Total pendaftar', 'value' => $total, 'tone' => ''],
                ['label' => 'Menunggu verifikasi', 'value' => (int) ($stats['pending_verification_count'] ?? 0), 'tone' => 'amber'],
                ['label' => 'Diterima', 'value' => (int) ($stats['accepted_count'] ?? 0), 'tone' => 'green'],
                ['label' => 'Jadi mahasiswa', 'value' => (int) ($stats['converted_count'] ?? 0), 'tone' => ''],
            ],
            'quickLinks' => $this->links([
                ['Kelola pendaftar', 'admin.admission.admission-applications.index'],
            ]),
            'bars' => [
                [
                    'title' => 'Tren pendaftaran 6 bulan',
                    'labels' => collect($stats['monthly_trend'] ?? [])->pluck('label')->all(),
                    'series' => collect($stats['monthly_trend'] ?? [])->pluck('count')->map(fn ($v) => (int) $v)->all(),
                ],
            ],
            'donuts' => [],
            'progress' => [
                [
                    'title' => 'Corong status',
                    'items' => [
                        ['label' => 'Draft', 'sub' => null, 'value' => (int) ($stats['status_counts']['draft'] ?? 0), 'max' => max($total, 1), 'display' => (string) ($stats['status_counts']['draft'] ?? 0)],
                        ['label' => 'Diverifikasi', 'sub' => null, 'value' => (int) ($stats['status_counts']['submitted'] ?? 0), 'max' => max($total, 1), 'display' => (string) ($stats['status_counts']['submitted'] ?? 0)],
                        ['label' => 'Diterima', 'sub' => null, 'value' => (int) ($stats['status_counts']['accepted'] ?? 0), 'max' => max($total, 1), 'display' => (string) ($stats['status_counts']['accepted'] ?? 0)],
                        ['label' => 'Konversi maba', 'sub' => null, 'value' => (int) ($stats['converted_count'] ?? 0), 'max' => max($total, 1), 'display' => (string) ($stats['converted_count'] ?? 0)],
                    ],
                ],
                [
                    'title' => 'Keterisian kuota per prodi',
                    'items' => collect($stats['quota_progress'] ?? [])->map(fn ($q) => [
                        'label' => $q['name'],
                        'sub' => $q['accepted'].'/'.$q['quota'],
                        'value' => (int) $q['accepted'],
                        'max' => max((int) $q['quota'], 1),
                        'display' => $q['percentage'].'%',
                    ])->all(),
                ],
            ],
            'rows' => [
                [
                    'title' => 'Pendaftar terbaru',
                    'actionLabel' => null,
                    'actionUrl' => null,
                    'items' => collect($stats['recent_applicants'] ?? [])->map(fn ($a) => [
                        'title' => $a['name'],
                        'sub' => $a['application_number'].' · '.$a['high_school'].' · '.$a['study_program'],
                        'badge' => $a['status_label'],
                        'badgeTone' => $this->statusTone($a['status_label']),
                    ])->all(),
                ],
            ],
            'alerts' => [],
        ];
    }

    private function financialSection(array $stats): array
    {
        $overdueCount = (int) ($stats['overdue_count'] ?? 0);

        return [
            'key' => 'financial',
            'title' => 'Keuangan',
            'subtitle' => 'Pemasukan, tagihan, cicilan, dan hold',
            'icon' => 'fas fa-wallet',
            'accent' => 'gold',
            'stats' => [
                ['label' => 'Pemasukan terkonfirmasi', 'value' => $this->rupiahShort((float) ($stats['paid_sum'] ?? 0)), 'tone' => 'green'],
                ['label' => 'Tagihan belum lunas', 'value' => (int) ($stats['unpaid_invoices_count'] ?? 0), 'tone' => 'red'],
                ['label' => 'Cicilan menunggu', 'value' => (int) ($stats['pending_installments_count'] ?? 0), 'tone' => 'amber'],
                ['label' => 'Hold aktif', 'value' => (int) ($stats['holds_count'] ?? 0), 'tone' => $stats['holds_count'] > 0 ? 'red' : ''],
            ],
            'quickLinks' => $this->links([
                ['Tagihan', 'admin.financial.student-invoices.index'],
                ['Pembayaran', 'admin.financial.payments.index'],
            ]),
            'bars' => [
                [
                    'title' => 'Pendapatan 6 bulan (juta Rp)',
                    'labels' => $stats['monthly_revenue']['labels'] ?? [],
                    'series' => $stats['monthly_revenue']['data'] ?? [],
                ],
            ],
            'donuts' => [
                [
                    'title' => 'Status tagihan',
                    'labels' => $stats['invoice_status_breakdown']['labels'] ?? [],
                    'series' => $stats['invoice_status_breakdown']['series'] ?? [],
                ],
                [
                    'title' => 'Metode pembayaran',
                    'labels' => array_keys($stats['payment_methods'] ?? []),
                    'series' => array_values($stats['payment_methods'] ?? []),
                ],
            ],
            'progress' => [],
            'rows' => [
                [
                    'title' => 'Tunggakan terbesar',
                    'actionLabel' => null,
                    'actionUrl' => null,
                    'items' => collect($stats['top_arrears'] ?? [])->map(fn ($a) => [
                        'title' => $a['student_name'],
                        'sub' => $a['nim'],
                        'badge' => $this->rupiahShort((float) $a['amount']),
                        'badgeTone' => 'red',
                    ])->all(),
                ],
                [
                    'title' => 'Pembayaran terbaru',
                    'actionLabel' => null,
                    'actionUrl' => null,
                    'items' => collect($stats['recent_payments'] ?? [])->map(fn ($p) => [
                        'title' => $p['reference_number'].' · '.$p['student_name'],
                        'sub' => $p['paid_at'],
                        'badge' => $this->rupiahShort((float) $p['amount']),
                        'badgeTone' => 'green',
                    ])->all(),
                ],
            ],
            'alerts' => [
                $overdueCount > 0
                    ? ['tone' => 'red', 'title' => $overdueCount.' tagihan jatuh tempo', 'message' => 'Total tunggakan '.$this->rupiahShort((float) ($stats['overdue_amount'] ?? 0)).'.', 'items' => []]
                    : ['tone' => 'green', 'title' => 'Semua tagihan aman', 'message' => 'Tidak ada tagihan jatuh tempo.', 'items' => []],
            ],
        ];
    }

    private function academicSection(array $stats): array
    {
        $pendingKrs = (int) ($stats['pending_krs_count'] ?? 0);

        return [
            'key' => 'academic',
            'title' => 'Akademik',
            'subtitle' => 'Kelas, KRS, kehadiran, dan sanggahan nilai',
            'icon' => 'fas fa-graduation-cap',
            'accent' => 'brand',
            'stats' => [
                ['label' => 'Mahasiswa aktif', 'value' => (int) ($stats['active_students_count'] ?? 0), 'tone' => ''],
                ['label' => 'Kelas ditawarkan', 'value' => (int) ($stats['course_offerings_count'] ?? 0), 'tone' => ''],
                ['label' => 'KRS menunggu ACC', 'value' => $pendingKrs, 'tone' => $pendingKrs > 0 ? 'amber' : 'green'],
                ['label' => 'Rata-rata kehadiran', 'value' => $stats['attendance_rate'] !== null ? $stats['attendance_rate'].'%' : '–', 'tone' => ''],
            ],
            'quickLinks' => $this->links([
                ['Kelas', 'admin.academic.course-offerings.index'],
                ['KRS', 'admin.academic.study-plans.index'],
            ]),
            'bars' => [
                [
                    'title' => 'Sebaran IPK',
                    'labels' => $stats['gpa_distribution']['labels'] ?? [],
                    'series' => $stats['gpa_distribution']['series'] ?? [],
                ],
            ],
            'donuts' => [
                [
                    'title' => 'Kehadiran',
                    'labels' => $stats['attendance_breakdown']['labels'] ?? [],
                    'series' => $stats['attendance_breakdown']['series'] ?? [],
                ],
            ],
            'progress' => [],
            'rows' => [
                [
                    'title' => 'KRS menunggu persetujuan',
                    'actionLabel' => $pendingKrs > 0 ? 'Proses KRS' : null,
                    'actionUrl' => $this->url('admin.academic.study-plans.index'),
                    'items' => collect($stats['pending_krs_list'] ?? [])->map(fn ($k) => [
                        'title' => $k['student_name'],
                        'sub' => $k['nim'].' · Semester '.$k['semester_no'].' · diajukan '.$k['submitted_at'],
                        'badge' => null,
                        'badgeTone' => '',
                    ])->all(),
                ],
                [
                    'title' => 'Sanggahan nilai terbuka',
                    'actionLabel' => null,
                    'actionUrl' => null,
                    'items' => collect($stats['pending_appeals'] ?? [])->map(fn ($a) => [
                        'title' => $a['student_name'].' · '.$a['course'],
                        'sub' => 'Nilai diajukan: '.$a['requested_score'],
                        'badge' => $a['status_label'],
                        'badgeTone' => 'amber',
                    ])->all(),
                ],
            ],
            'alerts' => [],
        ];
    }

    private function studentServicesSection(array $stats): array
    {
        $totalGrad = (int) ($stats['graduation_applications_count'] ?? 0);
        $approvedGrad = (int) ($stats['graduation_approved_count'] ?? 0);

        return [
            'key' => 'student-services',
            'title' => 'Layanan Mahasiswa',
            'subtitle' => 'Surat, cuti, pengaduan, dan wisuda',
            'icon' => 'fas fa-file-signature',
            'accent' => 'green',
            'stats' => [
                ['label' => 'Surat pending', 'value' => (int) ($stats['pending_letters_count'] ?? 0), 'tone' => 'amber'],
                ['label' => 'Cuti menunggu', 'value' => (int) ($stats['pending_leaves_count'] ?? 0), 'tone' => 'amber'],
                ['label' => 'Pengaduan aktif', 'value' => (int) ($stats['active_complaints_count'] ?? 0), 'tone' => 'red'],
                ['label' => 'Pendaftar wisuda', 'value' => $totalGrad, 'tone' => ''],
            ],
            'quickLinks' => $this->links([
                ['Surat', 'admin.student-services.service-letter-requests.index'],
                ['Pengaduan', 'admin.student-services.student-complaints.index'],
            ]),
            'bars' => [],
            'donuts' => [
                [
                    'title' => 'Status pengaduan',
                    'labels' => $stats['complaint_breakdown']['labels'] ?? [],
                    'series' => $stats['complaint_breakdown']['series'] ?? [],
                ],
            ],
            'progress' => [
                [
                    'title' => 'Persetujuan wisuda',
                    'items' => [
                        ['label' => 'Disetujui', 'sub' => $approvedGrad.' dari '.$totalGrad.' pendaftar', 'value' => $approvedGrad, 'max' => max($totalGrad, 1), 'display' => $totalGrad > 0 ? round(($approvedGrad / $totalGrad) * 100, 1).'%' : '–'],
                    ],
                ],
            ],
            'rows' => [
                [
                    'title' => 'Antrean terbaru',
                    'actionLabel' => null,
                    'actionUrl' => null,
                    'items' => collect($stats['pending_requests'] ?? [])->map(fn ($r) => [
                        'title' => $r['detail'],
                        'sub' => $r['student_name'].' · '.$r['created_at'],
                        'badge' => $r['type'],
                        'badgeTone' => $r['type'] === 'Cuti' ? 'amber' : '',
                    ])->all(),
                ],
            ],
            'alerts' => [],
        ];
    }

    private function organizationSection(array $stats): array
    {
        $pendingLeave = (int) ($stats['pending_leave_count'] ?? 0);

        return [
            'key' => 'organization',
            'title' => 'Kepegawaian',
            'subtitle' => 'Pegawai, presensi, cuti, dan tridharma',
            'icon' => 'fas fa-users',
            'accent' => 'brand',
            'stats' => [
                ['label' => 'Pegawai aktif', 'value' => (int) ($stats['employees_count'] ?? 0), 'tone' => ''],
                ['label' => 'Presensi hari ini', 'value' => (int) ($stats['attendance_today_count'] ?? 0), 'tone' => ''],
                ['label' => 'Cuti menunggu', 'value' => $pendingLeave, 'tone' => $pendingLeave > 0 ? 'amber' : 'green'],
                ['label' => 'Rekaman tridharma', 'value' => (int) ($stats['tridharma_count'] ?? 0), 'tone' => ''],
            ],
            'quickLinks' => $this->links([
                ['Pegawai', 'admin.organization.employee-profiles.index'],
                ['Cuti pegawai', 'admin.organization.employee-leave-requests.index'],
            ]),
            'bars' => [],
            'donuts' => [
                [
                    'title' => 'Tridharma',
                    'labels' => $stats['tridharma_breakdown']['labels'] ?? [],
                    'series' => $stats['tridharma_breakdown']['series'] ?? [],
                ],
            ],
            'progress' => [],
            'rows' => [
                [
                    'title' => 'Cuti menunggu persetujuan',
                    'actionLabel' => $pendingLeave > 0 ? 'Proses cuti' : null,
                    'actionUrl' => $this->url('admin.organization.employee-leave-requests.index'),
                    'items' => collect($stats['pending_leaves'] ?? [])->map(fn ($l) => [
                        'title' => $l['employee_name'],
                        'sub' => $l['leave_type'].' · diajukan '.$l['created_at'],
                        'badge' => null,
                        'badgeTone' => '',
                    ])->all(),
                ],
            ],
            'alerts' => [],
        ];
    }

    private function publicationAlumniSection(array $stats): array
    {
        $rate = $stats['tracer_response_rate'] ?? null;

        return [
            'key' => 'publication-alumni',
            'title' => 'Publikasi & Alumni',
            'subtitle' => 'Pengumuman, tracer study, dan bursa kerja',
            'icon' => 'fas fa-bullhorn',
            'accent' => 'gold',
            'stats' => [
                ['label' => 'Alumni terdaftar', 'value' => (int) ($stats['alumni_count'] ?? 0), 'tone' => ''],
                ['label' => 'Lowongan aktif', 'value' => (int) ($stats['jobs_count'] ?? 0), 'tone' => 'green'],
                ['label' => 'Pengumuman terbit', 'value' => (int) ($stats['announcements_count'] ?? 0), 'tone' => ''],
                ['label' => 'Respons tracer', 'value' => $rate !== null ? $rate.'%' : '–', 'tone' => $rate !== null && $rate >= 50 ? 'green' : 'amber'],
            ],
            'quickLinks' => $this->links([
                ['Pengumuman', 'admin.publication.announcements.index'],
                ['Alumni', 'admin.alumni.alumni-profiles.index'],
            ]),
            'bars' => [],
            'donuts' => [
                [
                    'title' => 'Status kerja alumni',
                    'labels' => $stats['employment_breakdown']['labels'] ?? [],
                    'series' => $stats['employment_breakdown']['series'] ?? [],
                ],
            ],
            'progress' => [],
            'rows' => [
                [
                    'title' => 'Lowongan terbaru',
                    'actionLabel' => null,
                    'actionUrl' => null,
                    'items' => collect($stats['recent_jobs'] ?? [])->map(fn ($j) => [
                        'title' => $j['title'],
                        'sub' => $j['company'].' · s.d. '.$j['deadline'],
                        'badge' => null,
                        'badgeTone' => '',
                    ])->all(),
                ],
            ],
            'alerts' => [],
        ];
    }

    private function systemHealthSection(array $stats): array
    {
        $warnings = $stats['warnings'] ?? [];
        $total = (int) ($warnings['total'] ?? 0);
        $items = collect($warnings)
            ->except('total')
            ->filter(fn ($count) => (int) $count > 0)
            ->map(fn ($count, $key) => $this->warningLabel($key).': '.$count)
            ->values()
            ->all();

        return [
            'key' => 'system-health',
            'title' => 'Kesehatan Sistem',
            'subtitle' => 'Pengguna, role, menu, dan anomali konfigurasi',
            'icon' => 'fas fa-server',
            'accent' => 'red',
            'stats' => [
                ['label' => 'Pengguna', 'value' => (int) ($stats['users_count'] ?? 0), 'tone' => ''],
                ['label' => 'Role', 'value' => (int) ($stats['roles_count'] ?? 0), 'tone' => ''],
                ['label' => 'Permission', 'value' => (int) ($stats['permissions_count'] ?? 0), 'tone' => ''],
                ['label' => 'Peringatan', 'value' => $total, 'tone' => $total > 0 ? 'red' : 'green'],
            ],
            'quickLinks' => $this->links([
                ['Pengguna', 'admin.system.users.index'],
                ['Menu', 'admin.system.menus.index'],
            ]),
            'bars' => [],
            'donuts' => [],
            'progress' => [],
            'rows' => [],
            'alerts' => [
                $total > 0
                    ? ['tone' => 'red', 'title' => 'Peringatan anomali sistem', 'message' => $total.' temuan perlu perhatian.', 'items' => $items]
                    : ['tone' => 'green', 'title' => 'Sistem sehat', 'message' => 'Tidak ada anomali konfigurasi.', 'items' => []],
            ],
        ];
    }

    private function activitySection(array $activities): array
    {
        return [
            'key' => 'activity',
            'title' => 'Aktivitas Sistem',
            'subtitle' => 'Jejak aksi terbaru di seluruh modul',
            'icon' => 'fas fa-history',
            'accent' => 'brand',
            'stats' => [],
            'quickLinks' => $this->links([
                ['Log aktivitas', 'admin.system.activity-logs.index'],
            ]),
            'bars' => [],
            'donuts' => [],
            'progress' => [],
            'rows' => [
                [
                    'title' => 'Aktivitas terkini',
                    'actionLabel' => null,
                    'actionUrl' => null,
                    'items' => collect($activities)->map(fn ($a) => [
                        'title' => $a['description'],
                        'sub' => $a['causer'].' · '.$a['at'],
                        'badge' => null,
                        'badgeTone' => '',
                    ])->all(),
                ],
            ],
            'alerts' => [],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Bangun quick links; route yang tidak terdaftar otomatis dibuang
     * (aman untuk kombinasi permission/role apa pun).
     *
     * @param  array<int, array{0: string, 1: string}>  $pairs
     */
    private function links(array $pairs): array
    {
        return collect($pairs)
            ->map(fn ($pair) => ['label' => $pair[0], 'url' => $this->url($pair[1])])
            ->filter(fn ($link) => $link['url'] !== null)
            ->values()
            ->all();
    }

    private function url(string $routeName): ?string
    {
        return Route::has($routeName) ? route($routeName) : null;
    }

    private function rupiahShort(float $amount): string
    {
        if ($amount >= 1_000_000_000) {
            return 'Rp '.$this->trimDecimal($amount / 1_000_000_000).' M';
        }

        if ($amount >= 1_000_000) {
            return 'Rp '.$this->trimDecimal($amount / 1_000_000).' jt';
        }

        if ($amount >= 1_000) {
            return 'Rp '.$this->trimDecimal($amount / 1_000).' rb';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function trimDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }

    private function statusTone(string $label): string
    {
        return match (strtolower($label)) {
            'accepted', 'diterima' => 'green',
            'rejected', 'ditolak' => 'red',
            'submitted', 'terkirim', 'pending' => 'amber',
            default => '',
        };
    }

    private function warningLabel(string $key): string
    {
        return match ($key) {
            'roles_without_permissions' => 'Role tanpa permission',
            'menus_without_permission' => 'Menu tanpa permission',
            'inactive_menus' => 'Menu nonaktif',
            'orphan_child_menus' => 'Submenu yatim',
            'invalid_route_menus' => 'Menu dengan route invalid',
            default => $key,
        };
    }
}
