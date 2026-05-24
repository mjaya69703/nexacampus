<?php

namespace App\Support;

use App\Models\Academic\Assignment;
use App\Models\Academic\AssignmentSubmission;
use App\Models\Academic\StudyPlanDetail;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentReportExportService
{
    public function rows(Assignment $assignment): Collection
    {
        $students = StudyPlanDetail::query()
            ->where('course_offering_id', $assignment->course_offering_id)
            ->whereHas('studyPlan.studentProfile.user')
            ->with(['studyPlan.studentProfile.user'])
            ->get()
            ->map(fn (StudyPlanDetail $detail) => $detail->studyPlan?->studentProfile)
            ->filter()
            ->unique('id')
            ->values();

        $submissionMap = $assignment->submissions()
            ->with(['studentProfile.user', 'files', 'grade'])
            ->get()
            ->keyBy('student_profile_id');

        return $students->map(function ($student) use ($assignment, $submissionMap) {
            $submission = $submissionMap->get($student->id);
            $grade = $submission?->grade;
            $status = $this->status($assignment, $submission);

            return [
                'nim' => $student->nim ?? '-',
                'student_name' => $student->user?->name ?? '-',
                'status' => $status,
                'submitted_at' => $submission?->submitted_at?->format('d M Y H:i') ?? '-',
                'file_count' => $submission?->files?->count() ?? 0,
                'raw_score' => $grade?->score !== null ? (float) $grade->score : null,
                'score_100' => $grade?->score !== null ? $this->normaliseScore((float) $grade->score, (float) $assignment->max_score) : null,
                'feedback' => $grade?->feedback ?? '',
                'return_note' => $grade?->return_note ?? '',
            ];
        })->sortBy('student_name')->values();
    }

    public function statistics(Collection $rows): array
    {
        $scores = $rows->pluck('score_100')->filter(fn ($score) => $score !== null)->map(fn ($score) => (float) $score)->values();
        $submitted = $rows->whereNotIn('status', ['missing', 'not_submitted'])->count();

        return [
            'total_students' => $rows->count(),
            'submitted_count' => $submitted,
            'graded_count' => $rows->where('status', 'graded')->count(),
            'returned_count' => $rows->where('status', 'returned')->count(),
            'missing_count' => $rows->whereIn('status', ['missing', 'not_submitted'])->count(),
            'late_count' => $rows->where('status', 'late')->count(),
            'submission_rate' => $rows->count() > 0 ? round(($submitted / $rows->count()) * 100, 1) : 0,
            'average_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
            'highest_score' => $scores->isNotEmpty() ? round($scores->max(), 2) : null,
            'lowest_score' => $scores->isNotEmpty() ? round($scores->min(), 2) : null,
        ];
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

            foreach (range('A', 'I') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(Assignment $assignment, Collection $rows, string $filename, array $context = []): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.assignment-report-pdf', [
            'assignment' => $assignment,
            'rows' => $rows,
            'statistics' => $this->statistics($rows),
            'context' => $context,
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function status(Assignment $assignment, ?AssignmentSubmission $submission): string
    {
        $gradeStatus = in_array($submission?->grade?->status, ['graded', 'returned'], true)
            ? $submission->grade->status
            : null;

        $status = $gradeStatus ?? $submission?->status ?? ($assignment->isPastDue() ? 'missing' : 'not_submitted');

        if ($status === 'submitted' && $submission?->submitted_at && $assignment->due_at && $submission->submitted_at->gt($assignment->due_at)) {
            return 'late';
        }

        return $status;
    }

    private function normaliseScore(float $score, float $maxScore): float
    {
        return round(min(100, max(0, ($score / max($maxScore, 1)) * 100)), 2);
    }

    private function headings(): array
    {
        return ['NIM', 'Mahasiswa', 'Status', 'Submitted At', 'Jumlah File', 'Skor Mentah', 'Skor 100', 'Feedback', 'Catatan Revisi'];
    }

    private function exportRow(array $row): array
    {
        return [
            $row['nim'],
            $row['student_name'],
            $row['status'],
            $row['submitted_at'],
            $row['file_count'],
            $row['raw_score'],
            $row['score_100'],
            $row['feedback'],
            $row['return_note'],
        ];
    }
}
