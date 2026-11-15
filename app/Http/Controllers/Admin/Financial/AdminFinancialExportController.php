<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\ActivePermission;
use App\Support\Financial\AdminFinancialExportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * PDF laporan tabel per resource financial — kop kampus + tanda tangan,
 * template generik yang sama dengan modul akademik.
 */
class AdminFinancialExportController extends Controller
{
    private const PERMISSIONS = [
        'tuition-fees' => 'tuition-fee',
        'clearance-policies' => 'clearance-policy',
        'scholarships' => 'scholarship',
        'invoice-schedules' => 'invoice-schedule',
        'student-invoices' => 'student-invoice',
        'payments' => 'payment',
        'invoice-adjustments' => 'invoice-adjustment',
        'installment-requests' => 'installment-request',
        'student-scholarships' => 'student-scholarship',
        'student-credits' => 'student-credit',
        'financial-holds' => 'financial-hold',
    ];

    public function pdf(string $resource, AdminFinancialExportService $service): Response
    {
        $prefix = self::PERMISSIONS[$resource] ?? null;

        abort_unless($prefix, 404);
        abort_unless(ActivePermission::check($prefix.'.viewAny'), 403);

        $payload = $service->payload($resource);
        $campus = Campus::first();
        $html = view('templates.pdf.academic.table-export', [
            ...$payload,
            'generatedAt' => now()->format('d M Y H:i'),
            'campusName' => $campus?->name ?? System::value('app_name') ?? config('app.name'),
            'campusAddress' => collect([$campus?->address, $campus?->city, $campus?->province, $campus?->postal_code])->filter()->join(' '),
            'campusContact' => collect([
                $campus?->phone ? 'Telp: '.$campus->phone : null,
                $campus?->email_info,
                $campus?->domain,
            ])->filter()->join(' | '),
            'logoBase64' => $this->logoBase64(),
            'generatedBy' => auth()->user()?->name ?? '-',
            'signCity' => $campus?->city ?? '',
            'signDate' => now()->translatedFormat('d F Y'),
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

    private function logoBase64(): ?string
    {
        foreach (['app_logo_horizontal', 'app_logo_vertikal'] as $column) {
            $filename = System::value($column);

            if (! $filename) {
                continue;
            }

            $path = Storage::disk('public')->path('images/logo/'.$filename);

            if (! is_file($path)) {
                continue;
            }

            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => null,
            };

            if ($mime) {
                return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }
}
