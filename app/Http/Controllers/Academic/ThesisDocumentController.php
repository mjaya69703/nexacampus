<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\ThesisDocument;
use App\Support\ActivePermission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Akses berkas skripsi.
 *
 * Berkas tidak pernah disajikan lewat URL publik langsung: pemilik,
 * pembimbing, penguji, dan admin diperiksa per permintaan.
 */
class ThesisDocumentController extends Controller
{
    public function admin(Request $request, ThesisDocument $document): Response
    {
        abort_unless(ActivePermission::check('thesis.view'), 403);

        return $this->stream($document);
    }

    public function student(Request $request, ThesisDocument $document): Response
    {
        $studentProfile = $request->user()?->studentProfile;

        abort_unless(
            $studentProfile && (int) $document->thesis?->student_profile_id === (int) $studentProfile->id,
            404,
        );

        return $this->stream($document);
    }

    public function lecturer(Request $request, ThesisDocument $document): Response
    {
        $lecturerProfile = $request->user()?->lecturerProfile;
        $thesis = $document->thesis;

        abort_unless($lecturerProfile && $thesis, 404);

        $isSupervisor = $thesis->supervisors()
            ->where('lecturer_profile_id', $lecturerProfile->id)
            ->exists();

        abort_unless($isSupervisor || $thesis->hasExaminer($lecturerProfile->id), 404);

        return $this->stream($document);
    }

    private function stream(ThesisDocument $document): Response
    {
        $path = $document->file_path;

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        $mime = Storage::disk('public')->mimeType($path) ?: ($document->mime_type ?: 'application/octet-stream');
        $filename = $document->file_name ?: basename($path);

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
        ]);
    }
}
