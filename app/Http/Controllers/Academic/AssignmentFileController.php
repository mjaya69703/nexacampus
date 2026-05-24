<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\AssignmentFile;
use App\Models\Academic\AssignmentSubmissionFile;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\StudyPlan;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AssignmentFileController extends Controller
{
    public function lecturerInstruction(AssignmentFile $file): Response
    {
        $file->loadMissing('assignment');

        abort_unless($this->lecturerCanAccess($file->assignment->course_offering_id), 403);

        return $this->serve($file->file_path, $file->file_name);
    }

    public function studentInstruction(AssignmentFile $file): Response
    {
        $file->loadMissing('assignment');

        abort_unless($this->studentCanAccess($file->assignment->course_offering_id), 403);

        return $this->serve($file->file_path, $file->file_name);
    }

    public function lecturerSubmission(AssignmentSubmissionFile $file): Response
    {
        $file->loadMissing('submission.assignment');

        abort_unless($this->lecturerCanAccess($file->submission->assignment->course_offering_id), 403);

        return $this->serve($file->file_path, $file->file_name);
    }

    public function studentSubmission(AssignmentSubmissionFile $file): Response
    {
        $file->loadMissing('submission');

        abort_unless($file->submission?->student_profile_id === auth()->user()?->studentProfile?->id, 403);

        return $this->serve($file->file_path, $file->file_name);
    }

    private function lecturerCanAccess(int $courseOfferingId): bool
    {
        $lecturerProfileId = auth()->user()?->lecturerProfile?->id;

        return $lecturerProfileId && CourseOfferingLecturer::query()
            ->where('course_offering_id', $courseOfferingId)
            ->where('lecturer_profile_id', $lecturerProfileId)
            ->where('is_active', true)
            ->exists();
    }

    private function studentCanAccess(int $courseOfferingId): bool
    {
        $studentProfileId = auth()->user()?->studentProfile?->id;

        return $studentProfileId && StudyPlan::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereHas('details', fn ($query) => $query->where('course_offering_id', $courseOfferingId))
            ->exists();
    }

    private function serve(string $path, string $name): Response
    {
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.str_replace(['"', "'"], '', $name).'"',
        ]);
    }
}
