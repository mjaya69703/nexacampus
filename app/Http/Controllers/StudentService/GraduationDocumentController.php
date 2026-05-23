<?php

namespace App\Http\Controllers\StudentService;

use App\Http\Controllers\Controller;
use App\Models\StudentService\GraduationDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GraduationDocumentController extends Controller
{
    public function adminPreview(GraduationDocument $document): BinaryFileResponse
    {
        return $this->inlineFile($document);
    }

    public function studentPreview(GraduationDocument $document): BinaryFileResponse
    {
        abort_unless($document->application?->student_profile_id === auth()->user()?->studentProfile?->id, 403);

        return $this->inlineFile($document);
    }

    private function inlineFile(GraduationDocument $document): BinaryFileResponse
    {
        $path = storage_path('app/public/'.$document->file_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
        ]);
    }
}
