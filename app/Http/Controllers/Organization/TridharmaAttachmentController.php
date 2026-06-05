<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization\TridharmaAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TridharmaAttachmentController extends Controller
{
    public function adminPreview(TridharmaAttachment $attachment): BinaryFileResponse
    {
        return $this->inlineFile($attachment);
    }

    public function selfPreview(TridharmaAttachment $attachment): BinaryFileResponse
    {
        abort_unless($attachment->record?->userCanAccess(auth()->user()), Response::HTTP_FORBIDDEN);

        return $this->inlineFile($attachment);
    }

    private function inlineFile(TridharmaAttachment $attachment): BinaryFileResponse
    {
        abort_unless(Storage::exists($attachment->file_path), Response::HTTP_NOT_FOUND);

        $mime = Storage::mimeType($attachment->file_path) ?: 'application/octet-stream';

        return response()->file(Storage::path($attachment->file_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$attachment->file_name.'"',
        ]);
    }
}
