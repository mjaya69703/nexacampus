<?php

namespace App\Support\Alumni;

use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TracerStudyExportService
{
    /**
     * Stream a CSV export of tracer study responses.
     */
    public function streamCsv(TracerStudyCampaign $campaign): StreamedResponse
    {
        $responses = $this->getResponses($campaign);
        $questions = $campaign->questions ?? [];
        $headers = $this->buildCsvHeaders($questions);

        return new StreamedResponse(function () use ($responses, $questions, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($responses as $response) {
                $row = $this->buildCsvRow($response, $questions);
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tracer-study-'.$campaign->id.'-'.now()->format('Ymd').'.csv"',
        ]);
    }

    /**
     * Stream an XLSX-compatible export (tab-separated for simplicity).
     * For full XLSX support, consider using a package like maatwebsite/excel.
     */
    public function streamXlsx(TracerStudyCampaign $campaign): StreamedResponse
    {
        $responses = $this->getResponses($campaign);
        $questions = $campaign->questions ?? [];
        $headers = $this->buildCsvHeaders($questions);

        return new StreamedResponse(function () use ($responses, $questions, $headers) {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, "\t");

            foreach ($responses as $response) {
                $row = $this->buildCsvRow($response, $questions);
                fputcsv($handle, $row, "\t");
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tracer-study-'.$campaign->id.'-'.now()->format('Ymd').'.xls"',
        ]);
    }

    /**
     * Stream a simple HTML-based PDF export.
     * For full PDF support, consider using barryvdh/laravel-dompdf.
     */
    public function streamPdf(TracerStudyCampaign $campaign): StreamedResponse
    {
        $responses = $this->getResponses($campaign);
        $questions = $campaign->questions ?? [];
        $headers = $this->buildCsvHeaders($questions);

        $html = '<html><head><meta charset="UTF-8"><title>Tracer Study Export</title>';
        $html .= '<style>table{border-collapse:collapse;width:100%;}th,td{border:1px solid #333;padding:4px 6px;font-size:11px;}th{background:#f0f0f0;}</style>';
        $html .= '</head><body>';
        $html .= '<h2>Tracer Study: '.$campaign->title.'</h2>';
        $html .= '<p>Exported: '.now()->format('d M Y H:i').'</p>';
        $html .= '<table><thead><tr>';

        foreach ($headers as $h) {
            $html .= '<th>'.e($h).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($responses as $response) {
            $row = $this->buildCsvRow($response, $questions);
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.e($cell).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        return new StreamedResponse(function () use ($html) {
            echo $html;
        }, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tracer-study-'.$campaign->id.'-'.now()->format('Ymd').'.html"',
        ]);
    }

    private function getResponses(TracerStudyCampaign $campaign): Collection
    {
        return TracerStudyResponse::query()
            ->where('tracer_study_campaign_id', $campaign->id)
            ->with(['alumniProfile.studyProgram'])
            ->orderBy('submitted_at')
            ->get();
    }

    private function buildCsvHeaders(array $questions): array
    {
        $baseHeaders = [
            'No',
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

        $questionHeaders = collect($questions)->map(fn ($q) => $q['text'])->toArray();

        return array_merge($baseHeaders, $questionHeaders);
    }

    private function buildCsvRow(TracerStudyResponse $response, array $questions): array
    {
        $alumni = $response->alumniProfile;

        $baseRow = [
            '', // Will be filled with sequence number
            $alumni?->nim ?? '-',
            $alumni?->full_name ?? '-',
            $alumni?->studyProgram?->name ?? '-',
            $alumni?->graduation_year ?? '-',
            $response->employment_status ?? '-',
            $response->employer_name ?? '-',
            $response->job_title ?? '-',
            $response->job_relevance ?? '-',
            $response->time_to_employment_months ?? '-',
            $response->salary_range ?? '-',
            $response->further_study ? 'Ya' : 'Tidak',
            $response->submitted_at?->format('d M Y H:i') ?? '-',
        ];

        // Add question answers
        $answers = $response->answers ?? [];
        foreach ($questions as $q) {
            $answer = $answers[$q['id']] ?? '-';
            $baseRow[] = is_array($answer) ? implode(', ', $answer) : $answer;
        }

        return $baseRow;
    }
}
