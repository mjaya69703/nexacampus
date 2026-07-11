<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Support\Academic\AdminAcademicExportService;
use App\Support\ActivePermission;
use App\Support\ResourceRegistry;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAcademicExportController extends Controller
{
    public function pdf(string $resource, AdminAcademicExportService $service): Response
    {
        $this->authorizeResource($resource);

        $payload = $service->payload($resource);
        $html = view('templates.pdf.academic.table-export', [
            ...$payload,
            'generatedAt' => now()->format('d M Y H:i'),
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$resource.'-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    public function importTemplate(string $resource, AdminAcademicExportService $service): StreamedResponse
    {
        $this->authorizeResource($resource);

        $headers = $service->importHeaders($resource);

        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fclose($handle);
        }, $resource.'-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeResource(string $plural): void
    {
        $resource = collect(ResourceRegistry::all())
            ->first(fn (array $item) => ($item['area'] ?? null) === 'academic' && ($item['plural'] ?? null) === $plural);

        abort_unless($resource, 404);
        abort_unless(ActivePermission::check(($resource['resource'] ?? $plural).'.viewAny'), 403);
    }
}
