<?php

namespace App\Support\Financial;

use App\Models\Financial\StudentInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExportService
{
    public function invoiceTypeRows(): Collection
    {
        return StudentInvoice::query()
            ->selectRaw('invoice_type, COUNT(*) as invoice_count, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(outstanding_amount) as outstanding_amount')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy('invoice_type')
            ->orderBy('invoice_type')
            ->get()
            ->map(fn (StudentInvoice $row) => [
                'invoice_type' => str($row->invoice_type)->replace('_', ' ')->title()->toString(),
                'invoice_count' => (int) $row->invoice_count,
                'total_amount' => (float) $row->total_amount,
                'paid_amount' => (float) $row->paid_amount,
                'outstanding_amount' => (float) $row->outstanding_amount,
            ]);
    }

    public function streamCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->headings());

            foreach ($rows as $row) {
                fputcsv($handle, $this->row($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function streamXlsx(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($this->headings(), null, 'A1');

            foreach ($rows->values() as $index => $row) {
                $sheet->fromArray($this->row($row), null, 'A'.($index + 2));
            }

            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(Collection $rows, string $filename, array $meta = []): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.financial-summary-pdf', [
            'rows' => $rows,
            'meta' => $meta,
        ])->render());
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function headings(): array
    {
        return ['Invoice Type', 'Invoices', 'Total Amount', 'Paid Amount', 'Outstanding Amount'];
    }

    private function row(array $row): array
    {
        return [
            $row['invoice_type'],
            $row['invoice_count'],
            $row['total_amount'],
            $row['paid_amount'],
            $row['outstanding_amount'],
        ];
    }
}
