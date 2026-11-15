<?php

namespace App\Support\Alumni;

use App\Enums\EmploymentStatus;
use App\Enums\JobRelevance;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TracerStudyExportService
{
    /**
     * Stream a CSV export of tracer study responses.
     */
    public function streamCsv(TracerStudyCampaign $campaign): StreamedResponse
    {
        return $this->streamResponses(
            $this->getResponses($campaign),
            $campaign->questions ?? [],
            $campaign->title,
            'csv',
        );
    }

    /**
     * Stream a real XLSX export (bukan TSV yang disamarkan seperti versi lama).
     */
    public function streamXlsx(TracerStudyCampaign $campaign): StreamedResponse|Response
    {
        return $this->streamResponses(
            $this->getResponses($campaign),
            $campaign->questions ?? [],
            $campaign->title,
            'xlsx',
        );
    }

    /**
     * Stream a real PDF export via Dompdf (bukan HTML mentah seperti versi lama).
     */
    public function streamPdf(TracerStudyCampaign $campaign): StreamedResponse|Response
    {
        return $this->streamResponses(
            $this->getResponses($campaign),
            $campaign->questions ?? [],
            $campaign->title,
            'pdf',
        );
    }

    /**
     * Export an arbitrary response collection. Dipakai halaman daftar respon
     * (bisa lintas kampanye) maupun halaman analytics per kampanye.
     *
     * @param  Collection<int, TracerStudyResponse>  $responses
     * @param  array<int, array<string, mixed>>  $questions
     */
    public function streamResponses(
        Collection $responses,
        array $questions,
        string $title,
        string $format = 'csv',
    ): StreamedResponse|Response {
        $format = in_array($format, ['xlsx', 'pdf'], true) ? $format : 'csv';
        $headers = $this->buildHeaders($questions);
        $rows = $this->buildRows($responses, $questions);
        $safeTitle = filled($title) ? $title : 'Tracer Study';
        $filename = 'tracer-study-'.str($safeTitle)->slug()->limit(40, '')->toString().'-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            return $this->pdfResponse($safeTitle, $headers, $rows, $filename.'.pdf');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');

        foreach ($rows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.($i + 2));
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $mime = $format === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename.'.'.$format, ['Content-Type' => $mime]);
    }

    /**
     * @return Collection<int, TracerStudyResponse>
     */
    public function getResponses(TracerStudyCampaign $campaign): Collection
    {
        return TracerStudyResponse::query()
            ->where('tracer_study_campaign_id', $campaign->id)
            ->with(['alumniProfile.studyProgram', 'campaign'])
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, string>
     */
    public function buildHeaders(array $questions): array
    {
        $baseHeaders = [
            'No',
            'Kampanye',
            'NIM',
            'Nama',
            'Program Studi',
            'Tahun Lulus',
            'Status Kerja',
            'Perusahaan',
            'Jabatan',
            'Relevansi Pekerjaan',
            'Waktu ke Kerja (bulan)',
            'Range Gaji',
            'Studi Lanjut',
            'Submitted At',
        ];

        $questionHeaders = collect($questions)
            ->map(fn ($question, $index) => filled($question['text'] ?? null)
                ? (string) $question['text']
                : 'Pertanyaan '.($index + 1))
            ->values()
            ->all();

        return array_merge($baseHeaders, $questionHeaders);
    }

    /**
     * @param  Collection<int, TracerStudyResponse>  $responses
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<int, mixed>>
     */
    public function buildRows(Collection $responses, array $questions): array
    {
        $rows = [];

        foreach ($responses->values() as $index => $response) {
            $alumni = $response->alumniProfile;
            $row = [
                $index + 1,
                $response->campaign?->title ?? '-',
                $alumni?->nim ?? '-',
                $alumni?->full_name ?? '-',
                $alumni?->studyProgram?->name ?? '-',
                $alumni?->graduation_year ?? '-',
                EmploymentStatus::tryFrom((string) $response->employment_status)?->label()
                    ?? ($response->employment_status ?? '-'),
                $response->employer_name ?? '-',
                $response->job_title ?? '-',
                JobRelevance::tryFrom((string) $response->job_relevance)?->label()
                    ?? ($response->job_relevance ?? '-'),
                $response->time_to_employment_months ?? '-',
                $response->salary_range ?? '-',
                $response->further_study ? 'Ya' : 'Tidak',
                $response->submitted_at?->format('d M Y H:i') ?? '-',
            ];

            $answers = $response->answers ?? [];

            foreach ($questions as $qIndex => $question) {
                $questionId = $question['id'] ?? 'q'.$qIndex;
                $answer = $answers[$questionId] ?? '-';
                $row[] = is_array($answer) ? implode(', ', $answer) : $answer;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function pdfResponse(string $title, array $headers, array $rows, string $filename): Response
    {
        $html = '<html><head><meta charset="UTF-8"><title>'.e($title).'</title>';
        $html .= '<style>
            body{font-family:DejaVu Sans, sans-serif;font-size:9px;color:#111;}
            h2{font-size:14px;margin:0 0 4px;}
            p{font-size:9px;margin:0 0 10px;color:#555;}
            table{border-collapse:collapse;width:100%;}
            th,td{border:1px solid #bbb;padding:3px 4px;text-align:left;vertical-align:top;}
            th{background:#eee;font-size:9px;}
        </style></head><body>';
        $html .= '<h2>Tracer Study: '.e($title).'</h2>';
        $html .= '<p>Diekspor '.e(now()->format('d M Y H:i')).' &middot; '.count($rows).' respon</p>';
        $html .= '<table><thead><tr>';

        foreach ($headers as $header) {
            $html .= '<th>'.e($header).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $html .= '<td>'.e((string) ($cell ?? '-')).'</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
