<?php

namespace App\Support\Organization;

use App\Models\Organization\LecturerWorkloadSubmission;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LecturerWorkloadExportService
{
    public function rows(?Builder $query = null): Collection
    {
        $query ??= LecturerWorkloadSubmission::query();

        return $query
            ->with(['owner', 'period', 'lecturerProfile.studyProgram.faculty'])
            ->latest('updated_at')
            ->get()
            ->map(fn (LecturerWorkloadSubmission $submission) => [
                'lecturer' => $submission->owner?->name ?? '-',
                'study_program' => $submission->lecturerProfile?->studyProgram?->name ?? '-',
                'faculty' => $submission->lecturerProfile?->studyProgram?->faculty?->name ?? '-',
                'period' => $submission->period?->name ?? '-',
                'status' => $this->statusLabel($submission->status),
                'teaching_sks' => (float) $submission->teaching_sks,
                'structural_sks' => (float) $submission->structural_sks,
                'tridharma_sks' => (float) $submission->tridharma_sks,
                'total_sks' => (float) $submission->total_sks,
                'submitted_at' => $submission->submitted_at?->format('d M Y H:i') ?? '-',
                'approved_at' => $submission->approved_at?->format('d M Y H:i') ?? '-',
            ]);
    }

    public function streamCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->headings());

            foreach ($rows as $row) {
                fputcsv($handle, $this->exportRow($row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function streamXlsx(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($this->headings(), null, 'A1');

            foreach ($rows->values() as $index => $row) {
                $sheet->fromArray($this->exportRow($row), null, 'A'.($index + 2));
            }

            foreach (range('A', 'K') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(Collection $rows, string $filename, array $context = []): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.lecturer-workload-pdf', [
            'rows' => $rows,
            'context' => $context,
            'stats' => [
                'total' => $rows->count(),
                'approved' => $rows->where('status', 'Disetujui')->count(),
                'avg_sks' => $rows->count() ? round((float) $rows->avg('total_sks'), 2) : 0,
            ],
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'in_approval' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => 'Draf',
        };
    }

    private function headings(): array
    {
        return ['Dosen', 'Program Studi', 'Fakultas', 'Periode', 'Status', 'Mengajar', 'Jabatan', 'Tridharma', 'Total SKS', 'Diajukan', 'Disetujui'];
    }

    private function exportRow(array $row): array
    {
        return [
            $row['lecturer'],
            $row['study_program'],
            $row['faculty'],
            $row['period'],
            $row['status'],
            $row['teaching_sks'],
            $row['structural_sks'],
            $row['tridharma_sks'],
            $row['total_sks'],
            $row['submitted_at'],
            $row['approved_at'],
        ];
    }
}
