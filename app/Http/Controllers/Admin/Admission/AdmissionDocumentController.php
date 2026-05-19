<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdmissionDocumentController extends Controller
{
    public function adminPreview(AdmissionDocument $document): BinaryFileResponse
    {
        return $this->inlineFile($document);
    }

    public function portalPreview(string $applicationNumber, string $token, AdmissionDocument $document): BinaryFileResponse
    {
        $application = AdmissionApplication::query()
            ->where('application_number', $applicationNumber)
            ->where('access_token', $token)
            ->firstOrFail();

        abort_unless($document->admission_application_id === $application->id, Response::HTTP_FORBIDDEN);

        return $this->inlineFile($document);
    }

    private function inlineFile(AdmissionDocument $document): BinaryFileResponse
    {
        abort_unless(Storage::disk('public')->exists($document->file_path), Response::HTTP_NOT_FOUND);

        $path = Storage::disk('public')->path($document->file_path);
        $mime = Storage::disk('public')->mimeType($document->file_path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
        ]);
    }
}
