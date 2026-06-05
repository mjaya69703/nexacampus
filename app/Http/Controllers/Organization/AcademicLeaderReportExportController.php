<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Support\Organization\AcademicLeaderReportExportService;
use App\Support\Organization\AcademicLeaderContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AcademicLeaderReportExportController extends Controller
{
    public function __construct(private readonly AcademicLeaderReportExportService $exports) {}

    public function __invoke(Request $request, string $type, string $format): Response
    {
        abort_unless(app(AcademicLeaderContext::class)->hasScope($request->user()), 403);
        abort_unless(in_array($type, ['lecturers', 'classes', 'alerts'], true), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $rows = $this->exports->rows($type);
        $filename = 'laporan-'.$type.'-'.now()->format('Ymd-His').'.'.$format;
        $context = [
            'title' => match ($type) {
                'lecturers' => 'Laporan Dosen Dalam Scope',
                'classes' => 'Laporan Kelas dan Kehadiran',
                default => 'Laporan Alert Akademik',
            },
            'generated_by' => $request->user()?->name,
            'generated_at' => now()->format('d M Y H:i'),
        ];

        return match ($format) {
            'csv' => $this->exports->streamCsv($rows, $filename),
            'xlsx' => $this->exports->streamXlsx($rows, $filename),
            default => $this->exports->pdf($rows, $filename, $context),
        };
    }
}
