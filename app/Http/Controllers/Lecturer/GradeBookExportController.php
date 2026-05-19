<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Support\GradeBookExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GradeBookExportController extends Controller
{
    public function __construct(private readonly GradeBookExportService $exports) {}

    public function csv(Request $request): Response
    {
        return $this->exports->streamCsv($this->rows($request), $this->filename('csv'));
    }

    public function xlsx(Request $request): Response
    {
        return $this->exports->streamXlsx($this->rows($request), $this->filename('xlsx'));
    }

    public function pdf(Request $request): Response
    {
        return $this->exports->pdf($this->rows($request), $this->filename('pdf'), [
            'generated_by' => $request->user()?->name,
            'generated_at' => now()->format('d M Y H:i'),
        ]);
    }

    private function rows(Request $request)
    {
        return $this->exports->lecturerRows($request->user(), $request->only([
            'course_offering_id',
            'academic_year_id',
            'semester_no',
            'search',
        ]));
    }

    private function filename(string $extension): string
    {
        return 'grade-book-'.now()->format('Ymd-His').'.'.$extension;
    }
}
