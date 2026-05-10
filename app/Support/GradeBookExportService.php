<?php

namespace App\Support;

use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlanDetail;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradeBookExportService
{
    public function lecturerRows(User $user, array $filters = []): Collection
    {
        $lecturerProfile = $user->lecturerProfile()->first();

        if (! $lecturerProfile) {
            return collect();
        }

        $offeringIds = CourseOfferingLecturer::query()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->pluck('course_offering_id');

        if ($offeringIds->isEmpty()) {
            return collect();
        }

        $query = StudyPlanDetail::query()
            ->with([
                'courseOffering.course',
                'courseOffering.academicYear',
                'courseOffering.studyProgram',
                'studyPlan.studentProfile.user',
                'studentGrade.components',
            ])
            ->whereIn('course_offering_id', $offeringIds)
            ->when(filled($filters['course_offering_id'] ?? null), fn ($query) => $query->where('course_offering_id', $filters['course_offering_id']))
            ->when(filled($filters['academic_year_id'] ?? null), fn ($query) => $query->whereHas('courseOffering', fn ($offering) => $offering->where('academic_year_id', $filters['academic_year_id'])))
            ->when(filled($filters['semester_no'] ?? null), fn ($query) => $query->whereHas('courseOffering', fn ($offering) => $offering->where('semester_no', $filters['semester_no'])));

        $rows = $query->get()->map(fn (StudyPlanDetail $detail) => $this->mapDetail($detail));

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search) {
                $haystack = mb_strtolower(implode(' ', [
                    $row['nim'],
                    $row['student_name'],
                    $row['course'],
                    $row['class'],
                    $row['study_program'],
                    $row['academic_year'],
                ]));

                return str_contains($haystack, $search);
            });
        }

        return $rows->sortBy(['course', 'class', 'student_name'])->values();
    }

    public function statistics(Collection $rows): array
    {
        $scores = $rows->pluck('final_score')->filter(fn ($score) => $score !== null)->map(fn ($score) => (float) $score)->values();
        $gradedCount = $scores->count();
        $passedCount = $rows->where('result_status', 'Passed')->count();
        $average = $gradedCount > 0 ? round($scores->avg(), 2) : null;
        $variance = $gradedCount > 0 ? $scores->map(fn ($score) => ($score - $average) ** 2)->avg() : null;

        return [
            'total_students' => $rows->count(),
            'graded_count' => $gradedCount,
            'finalized_count' => $rows->whereIn('grade_status', ['Finalized', 'Published'])->count(),
            'average_score' => $average,
            'median_score' => $this->median($scores),
            'highest_score' => $gradedCount > 0 ? round($scores->max(), 2) : null,
            'lowest_score' => $gradedCount > 0 ? round($scores->min(), 2) : null,
            'pass_rate' => $rows->count() > 0 ? round(($passedCount / $rows->count()) * 100, 1) : 0,
            'standard_deviation' => $variance !== null ? round(sqrt($variance), 2) : null,
        ];
    }

    public function distribution(Collection $rows): Collection
    {
        $letters = ['A', 'AB', 'B', 'BC', 'C', 'D', 'E'];
        $total = max($rows->filter(fn ($row) => $row['letter_grade'] !== '-')->count(), 1);

        return collect($letters)->map(function (string $letter) use ($rows, $total) {
            $count = $rows->where('letter_grade', $letter)->count();

            return [
                'letter' => $letter,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        });
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

            foreach (range('A', 'M') as $column) {
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
        $dompdf->loadHtml(view('exports.grade-book-pdf', [
            'rows' => $rows,
            'statistics' => $this->statistics($rows),
            'distribution' => $this->distribution($rows),
            'context' => $context,
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function mapDetail(StudyPlanDetail $detail): array
    {
        $student = $detail->studyPlan?->studentProfile;
        $offering = $detail->courseOffering;
        $grade = $detail->studentGrade;

        return [
            'study_plan_detail_id' => $detail->id,
            'nim' => $student?->nim ?? '-',
            'student_name' => $student?->user?->name ?? '-',
            'course' => ($offering?->course?->code ?? '-').' - '.($offering?->course?->name ?? '-'),
            'class' => $offering?->label ?? '-',
            'academic_year' => $offering?->academicYear?->name ?? '-',
            'semester_no' => $offering?->semester_no ?? '-',
            'study_program' => $offering?->studyProgram?->name ?? '-',
            'final_score' => $grade?->final_score !== null ? (float) $grade->final_score : null,
            'letter_grade' => $grade?->letter_grade ?? '-',
            'grade_point' => $grade?->grade_point !== null ? (float) $grade->grade_point : null,
            'grade_status' => $grade?->grade_status ?? 'Draft',
            'result_status' => $grade?->result_status ?? '-',
            'component_count' => $grade?->components?->count() ?? 0,
        ];
    }

    private function headings(): array
    {
        return ['NIM', 'Mahasiswa', 'Program Studi', 'Mata Kuliah', 'Kelas', 'Tahun Akademik', 'Semester', 'Final Score', 'Nilai Huruf', 'Grade Point', 'Lifecycle', 'Status', 'Komponen'];
    }

    private function exportRow(array $row): array
    {
        return [
            $row['nim'],
            $row['student_name'],
            $row['study_program'],
            $row['course'],
            $row['class'],
            $row['academic_year'],
            $row['semester_no'],
            $row['final_score'],
            $row['letter_grade'],
            $row['grade_point'],
            $row['grade_status'],
            $row['result_status'],
            $row['component_count'],
        ];
    }

    private function median(Collection $scores): ?float
    {
        $count = $scores->count();

        if ($count === 0) {
            return null;
        }

        $sorted = $scores->sort()->values();
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 2)
            : round($sorted[$middle], 2);
    }
}
