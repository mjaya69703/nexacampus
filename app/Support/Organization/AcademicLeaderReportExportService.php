<?php

namespace App\Support\Organization;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicLeaderReportExportService
{
    public function __construct(private readonly AcademicLeaderOversightService $oversight) {}

    public function rows(string $type): Collection
    {
        return match ($type) {
            'lecturers' => $this->oversight->lecturerRows()->map(fn ($row) => [
                'Dosen' => $row['name'],
                'NIDN' => $row['nidn'],
                'Program Studi' => $row['program'],
                'Fakultas' => $row['faculty'],
                'Kelas Aktif' => $row['active_classes'],
                'Status BKD' => $row['workload_status_label'],
                'SKS BKD' => $row['workload_sks'],
                'Skor Performa' => $row['performance_score'] !== null ? number_format((float) $row['performance_score'], 2) : '-',
                'EDOM' => $row['edom_score'] !== null ? number_format((float) $row['edom_score'], 2) : '-',
                'Respon EDOM' => $row['edom_responses'],
            ]),
            'classes' => $this->oversight->classRows()->map(fn ($row) => [
                'Kelas' => $row['course'].' '.$row['label'],
                'Kode' => $row['code'],
                'Program Studi' => $row['program'],
                'Fakultas' => $row['faculty'],
                'Dosen' => implode(', ', $row['lecturers']) ?: '-',
                'Mahasiswa' => $row['student_count'],
                'Jadwal Aktif' => $row['schedule_count'],
                'Pertemuan' => $row['session_count'].'/'.$row['target_meetings'],
                'Sesi Dibuka' => $row['opened_sessions'],
                'Belum Ditutup Lama' => $row['opened_too_long'],
                'Kehadiran' => $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '-',
                'Risiko' => $this->oversight->classRiskLabel($row['risk_level']),
            ]),
            default => $this->oversight->alerts()->map(fn ($row) => [
                'Level' => ucfirst($row['level']),
                'Judul' => $row['title'],
                'Keterangan' => $row['description'],
                'Area' => $row['target'],
            ]),
        };
    }

    public function streamCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->headings($rows));

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function streamXlsx(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $headings = $this->headings($rows);
            $sheet->fromArray($headings, null, 'A1');

            foreach ($rows->values() as $index => $row) {
                $sheet->fromArray(array_values($row), null, 'A'.($index + 2));
            }

            foreach (range('A', chr(64 + max(1, count($headings)))) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(Collection $rows, string $filename, array $context): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.academic-leader-report-pdf', [
            'rows' => $rows,
            'headings' => $this->headings($rows),
            'context' => $context,
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function headings(Collection $rows): array
    {
        return array_keys($rows->first() ?: ['Data' => '']);
    }
}
