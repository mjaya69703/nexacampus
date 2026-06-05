<?php

namespace App\Http\Controllers\StudentService;

use App\Http\Controllers\Controller;
use App\Models\StudentService\ServiceLetterRequest;
use App\Support\ActivePermission;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ServiceLetterDownloadController extends Controller
{
    public function admin(ServiceLetterRequest $request): Response
    {
        abort_unless(ActivePermission::check('service-letter-request.view'), 403);

        return $this->download($request);
    }

    public function student(ServiceLetterRequest $request): Response
    {
        $studentProfile = auth()->user()?->studentProfile;

        abort_unless($studentProfile && (int) $request->student_profile_id === (int) $studentProfile->id, 404);

        return $this->download($request);
    }

    private function download(ServiceLetterRequest $request): Response
    {
        abort_unless($request->isDownloadable(), 404);

        $path = $request->finalFilePath();
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = $request->request_number.'.'.$extension;

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
