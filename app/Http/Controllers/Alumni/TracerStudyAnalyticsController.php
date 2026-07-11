<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\Alumni\TracerStudyCampaign;
use App\Support\Alumni\TracerStudyExportService;
use App\Support\Alumni\TracerStudyService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TracerStudyAnalyticsController extends Controller
{
    public function show(int $id): Response
    {
        $campaign = TracerStudyCampaign::query()
            ->with(['academicYear'])
            ->withCount('responses')
            ->findOrFail($id);

        $service = app(TracerStudyService::class);
        $analytics = $service->analytics($campaign);

        return response()->view('components.admin.alumni.tracer-study-analytics', [
            'campaign' => $campaign,
            'analytics' => $analytics,
            'menus' => 'Alumni',
            'pages' => 'Analytics Tracer Study: ' . $campaign->title,
        ])->setStatusCode(200);
    }

    public function export(int $id, string $format): StreamedResponse
    {
        $campaign = TracerStudyCampaign::findOrFail($id);
        $exportService = app(TracerStudyExportService::class);

        return match ($format) {
            'xlsx' => $exportService->streamXlsx($campaign),
            'pdf' => $exportService->streamPdf($campaign),
            default => $exportService->streamCsv($campaign),
        };
    }
}
