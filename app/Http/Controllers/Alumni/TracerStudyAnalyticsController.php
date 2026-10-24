<?php

namespace App\Http\Controllers\Alumni;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Alumni\TracerStudyCampaign;
use App\Support\ActivePermission;
use App\Support\Alumni\TracerStudyExportService;
use App\Support\Alumni\TracerStudyService;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TracerStudyAnalyticsController extends Controller
{
    public function show(Request $request, TracerStudyCampaign $tracerStudyCampaign): Response
    {
        $tracerStudyCampaign->load('academicYear:id,name');
        $tracerStudyCampaign->loadCount('responses');

        $analytics = app(TracerStudyService::class)->analytics($tracerStudyCampaign);

        return Inertia::render('Admin/Alumni/TracerStudy/Analytics', [
            'shell' => ShellProps::make($request->user(), 'Alumni', 'Analytics Tracer Study'),
            'can' => [
                'viewCampaign' => ActivePermission::check('tracer-study-campaign.view'),
                'viewResponses' => ActivePermission::check('tracer-study-response.viewAny'),
            ],
            'campaign' => [
                'id' => $tracerStudyCampaign->id,
                'title' => $tracerStudyCampaign->title,
                'academicYear' => $tracerStudyCampaign->academicYear?->name,
                'status' => $tracerStudyCampaign->status,
                'statusLabel' => CampaignStatus::tryFrom($tracerStudyCampaign->status)?->label() ?? $tracerStudyCampaign->status,
                'startDate' => $tracerStudyCampaign->start_date?->format('d M Y'),
                'endDate' => $tracerStudyCampaign->end_date?->format('d M Y'),
                'totalSent' => (int) $tracerStudyCampaign->total_sent,
                'totalResponded' => (int) $tracerStudyCampaign->total_responded,
                'responseRate' => $tracerStudyCampaign->responseRate(),
                'responsesCount' => (int) $tracerStudyCampaign->responses_count,
            ],
            'analytics' => $analytics,
            'urls' => [
                'show' => route('admin.alumni.tracer-study-campaigns.show', $tracerStudyCampaign),
                'responses' => route('admin.alumni.tracer-study-responses.index', ['campaign' => $tracerStudyCampaign->id]),
                'exportCsv' => route('admin.alumni.tracer-study.export', ['id' => $tracerStudyCampaign->id, 'format' => 'csv']),
                'exportXlsx' => route('admin.alumni.tracer-study.export', ['id' => $tracerStudyCampaign->id, 'format' => 'xlsx']),
                'exportPdf' => route('admin.alumni.tracer-study.export', ['id' => $tracerStudyCampaign->id, 'format' => 'pdf']),
            ],
        ]);
    }

    public function export(int $id, string $format): StreamedResponse|HttpResponse
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
