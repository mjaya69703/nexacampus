<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\Assignment;
use App\Models\Academic\CourseOfferingLecturer;
use App\Support\AssignmentReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignmentReportExportController extends Controller
{
    public function __construct(private readonly AssignmentReportExportService $exports) {}

    public function csv(Request $request, Assignment $assignment): Response
    {
        $this->authorizeLecturer($request, $assignment);

        return $this->exports->streamCsv($this->exports->rows($assignment), $this->filename($assignment, 'csv'));
    }

    public function xlsx(Request $request, Assignment $assignment): Response
    {
        $this->authorizeLecturer($request, $assignment);

        return $this->exports->streamXlsx($this->exports->rows($assignment), $this->filename($assignment, 'xlsx'));
    }

    public function pdf(Request $request, Assignment $assignment): Response
    {
        $this->authorizeLecturer($request, $assignment);

        $assignment->load(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.studyProgram']);

        return $this->exports->pdf($assignment, $this->exports->rows($assignment), $this->filename($assignment, 'pdf'), [
            'generated_by' => $request->user()?->name,
            'generated_at' => now()->format('d M Y H:i'),
        ]);
    }

    private function authorizeLecturer(Request $request, Assignment $assignment): void
    {
        $lecturerProfileId = $request->user()?->lecturerProfile?->id;

        abort_unless($lecturerProfileId && CourseOfferingLecturer::query()
            ->where('course_offering_id', $assignment->course_offering_id)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->exists(), 403);
    }

    private function filename(Assignment $assignment, string $extension): string
    {
        return 'assignment-'.$assignment->id.'-report-'.now()->format('Ymd-His').'.'.$extension;
    }
}
