<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Support\ActivePermission;
use App\Support\Organization\AcademicLeaderContext;
use App\Support\Organization\LecturerWorkloadExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LecturerWorkloadExportController extends Controller
{
    public function __construct(private readonly LecturerWorkloadExportService $exports) {}

    public function admin(Request $request, string $format): Response
    {
        abort_unless(ActivePermission::check('lecturer-workload-submission.viewAny'), 403);

        return $this->download($format, LecturerWorkloadSubmission::query(), [
            'scope' => 'Admin Kepegawaian',
            'generated_by' => $request->user()?->name,
            'generated_at' => now()->format('d M Y H:i'),
        ]);
    }

    public function academicLeader(Request $request, string $format): Response
    {
        $context = app(AcademicLeaderContext::class);
        $facultyIds = $context->facultyIds($request->user());
        $programIds = $context->studyProgramIds($request->user());

        $query = LecturerWorkloadSubmission::query()
            ->whereHas('lecturerProfile', function (Builder $query) use ($facultyIds, $programIds) {
                $query->where(function (Builder $nested) use ($facultyIds, $programIds) {
                    if ($programIds) $nested->orWhereIn('study_program_id', $programIds);
                    if ($facultyIds) $nested->orWhereIn('faculty_id', $facultyIds);
                    if (! $programIds && ! $facultyIds) $nested->whereRaw('1 = 0');
                });
            });

        return $this->download($format, $query, [
            'scope' => 'Pimpinan Akademik',
            'generated_by' => $request->user()?->name,
            'generated_at' => now()->format('d M Y H:i'),
        ]);
    }

    private function download(string $format, Builder $query, array $context): Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $rows = $this->exports->rows($query);
        $filename = 'rekap-bkd-'.now()->format('Ymd-His').'.'.$format;

        return match ($format) {
            'csv' => $this->exports->streamCsv($rows, $filename),
            'xlsx' => $this->exports->streamXlsx($rows, $filename),
            default => $this->exports->pdf($rows, $filename, $context),
        };
    }
}
