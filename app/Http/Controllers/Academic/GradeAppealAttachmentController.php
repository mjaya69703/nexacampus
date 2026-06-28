<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\CourseOfferingLecturer;
use App\Models\Academic\GradeAppealAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeAppealAttachmentController extends Controller
{
    public function studentPreview(GradeAppealAttachment $attachment): BinaryFileResponse
    {
        $attachment->loadMissing('appeal');
        $studentProfile = auth()->user()?->studentProfile;

        abort_unless($studentProfile && (int) $attachment->appeal?->student_profile_id === (int) $studentProfile->id, Response::HTTP_NOT_FOUND);

        return $this->inlineFile($attachment);
    }

    public function lecturerPreview(GradeAppealAttachment $attachment): BinaryFileResponse
    {
        $attachment->loadMissing('appeal');
        $lecturerProfile = auth()->user()?->lecturerProfile;

        abort_unless($lecturerProfile, Response::HTTP_FORBIDDEN);

        $canAccess = (int) $attachment->appeal?->lecturer_profile_id === (int) $lecturerProfile->id
            || CourseOfferingLecturer::query()
                ->where('course_offering_id', $attachment->appeal?->course_offering_id)
                ->where('lecturer_profile_id', $lecturerProfile->id)
                ->where('is_active', true)
                ->exists();

        abort_unless($canAccess, Response::HTTP_NOT_FOUND);

        return $this->inlineFile($attachment);
    }

    private function inlineFile(GradeAppealAttachment $attachment): BinaryFileResponse
    {
        abort_unless(Storage::exists($attachment->file_path), Response::HTTP_NOT_FOUND);

        $mime = Storage::mimeType($attachment->file_path) ?: ($attachment->mime_type ?: 'application/octet-stream');

        return response()->file(Storage::path($attachment->file_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $attachment->file_name).'"',
        ]);
    }
}
