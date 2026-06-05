<?php

namespace App\Http\Controllers\StudentService;

use App\Http\Controllers\Controller;
use App\Models\StudentService\StudentComplaintAttachment;
use App\Support\ActivePermission;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ComplaintAttachmentController extends Controller
{
    public function admin(StudentComplaintAttachment $attachment): Response
    {
        $attachment->loadMissing('complaint');

        abort_unless(ActivePermission::check('student-complaint.view'), 403);
        $this->authorizeAdminScope($attachment);

        return $this->download($attachment);
    }

    public function student(StudentComplaintAttachment $attachment): Response
    {
        $attachment->loadMissing('complaint');
        $studentProfile = auth()->user()?->studentProfile;

        abort_unless($studentProfile && (int) $attachment->complaint?->student_profile_id === (int) $studentProfile->id, 404);

        return $this->download($attachment);
    }

    private function authorizeAdminScope(StudentComplaintAttachment $attachment): void
    {
        if (in_array(session('active_role'), ['superuser', 'admin'], true)) {
            return;
        }

        $complaint = $attachment->complaint;
        $unitIds = auth()->user()?->activeWorkUnits()->pluck('work_units.id')->all() ?? [];

        abort_unless(
            $complaint?->assigned_user_id === auth()->id()
            || in_array($complaint?->assigned_work_unit_id, $unitIds, true),
            403
        );
    }

    private function download(StudentComplaintAttachment $attachment): Response
    {
        abort_unless($attachment->file_path && Storage::disk('public')->exists($attachment->file_path), 404);

        return response(Storage::disk('public')->get($attachment->file_path), 200, [
            'Content-Type' => Storage::disk('public')->mimeType($attachment->file_path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $attachment->file_name ?: 'attachment').'"',
        ]);
    }
}
