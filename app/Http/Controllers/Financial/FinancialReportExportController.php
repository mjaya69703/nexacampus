<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Support\Financial\FinancialReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinancialReportExportController extends Controller
{
    public function __construct(private readonly FinancialReportExportService $exports) {}

    public function csv(): Response
    {
        return $this->exports->streamCsv($this->exports->invoiceTypeRows(), $this->filename('csv'));
    }

    public function xlsx(): Response
    {
        return $this->exports->streamXlsx($this->exports->invoiceTypeRows(), $this->filename('xlsx'));
    }

    public function pdf(Request $request): Response
    {
        return $this->exports->pdf($this->exports->invoiceTypeRows(), $this->filename('pdf'), [
            'generated_by' => $request->user()?->name,
            'generated_at' => now()->format('d M Y H:i'),
        ]);
    }

    private function filename(string $extension): string
    {
        return 'financial-summary-'.now()->format('Ymd-His').'.'.$extension;
    }
}
