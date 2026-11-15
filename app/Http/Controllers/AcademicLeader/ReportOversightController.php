<?php

namespace App\Http\Controllers\AcademicLeader;

use App\Http\Controllers\Controller;
use App\Support\Inertia\ShellProps;
use App\Support\Organization\AcademicLeaderContext;
use App\Support\Organization\AcademicLeaderOversightService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pusat laporan dan ringkasan alert pimpinan akademik.
 *
 * Alert diurutkan berdasarkan level (kritis → perhatian → info) sebelum
 * dipotong menjadi delapan item, sehingga temuan berat tidak hilang di
 * balik alert informational yang lebih dulu terdeteksi.
 */
class ReportOversightController extends Controller
{
    public function index(
        Request $request,
        AcademicLeaderContext $context,
        AcademicLeaderOversightService $oversight,
    ): Response {
        $overview = $oversight->dashboardStats($request->user());

        return Inertia::render('AcademicLeader/Reports/Index', [
            'shell' => ShellProps::make($request->user(), 'Pemantauan Akademik', 'Laporan'),
            'hasScope' => $context->hasScope($request->user()),
            'stats' => [
                'lecturers' => $overview['lecturers'],
                'classes' => $overview['classes'],
                'alerts' => $overview['alerts'],
                'problemClasses' => $overview['problem_classes'],
                'pendingWorkloads' => $overview['pending_workloads'],
                'avgPerformance' => round((float) $overview['avg_performance'], 2),
            ],
            'alerts' => $oversight->prioritizedAlerts($request->user(), 8)
                ->map(fn (array $alert) => [
                    'level' => $alert['level'],
                    'title' => $alert['title'],
                    'description' => $alert['description'],
                    'target' => $alert['target'],
                ])
                ->all(),
            'reports' => [
                [
                    'type' => 'lecturers',
                    'title' => 'Dosen Dalam Scope',
                    'description' => 'Data dosen, BKD terakhir, kelas aktif, dan performa.',
                    'count' => $overview['lecturers'],
                ],
                [
                    'type' => 'classes',
                    'title' => 'Kelas & Kehadiran',
                    'description' => 'Data kelas, dosen pengampu, pertemuan, dan kehadiran.',
                    'count' => $overview['classes'],
                ],
                [
                    'type' => 'alerts',
                    'title' => 'Alert Akademik',
                    'description' => 'Daftar kelas atau dosen yang perlu perhatian pimpinan.',
                    'count' => $overview['alerts'],
                ],
            ],
            'urls' => [
                'index' => route('academic-leader.reports.index'),
                'dashboard' => route('academic-leader.dashboard.index'),
                'lecturers' => route('academic-leader.lecturers.index'),
                'classes' => route('academic-leader.classes.index'),
                'edom' => route('academic-leader.edom.index'),
                'export' => [
                    'csv' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'csv']),
                    'xlsx' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'xlsx']),
                    'pdf' => route('academic-leader.reports.export', ['type' => 'lecturers', 'format' => 'pdf']),
                ],
                'reportExport' => [
                    'lecturers' => $this->exportUrls('lecturers'),
                    'classes' => $this->exportUrls('classes'),
                    'alerts' => $this->exportUrls('alerts'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function exportUrls(string $type): array
    {
        return collect(['csv', 'xlsx', 'pdf'])
            ->mapWithKeys(fn (string $format) => [
                $format => route('academic-leader.reports.export', ['type' => $type, 'format' => $format]),
            ])
            ->all();
    }
}
