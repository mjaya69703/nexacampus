<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialDownload;
use App\Models\Academic\CourseMaterialFile;
use App\Models\Academic\StudyPlan;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseMaterialController extends Controller
{
    public function download(int $id, ?int $fileId = null): BinaryFileResponse|RedirectResponse
    {
        // If fileId is provided, download specific file from course_material_files table
        if ($fileId) {
            return $this->downloadSpecificFile($id, $fileId);
        }

        // Legacy: download from course_materials table (for backward compatibility)
        return $this->downloadLegacyFile($id);
    }

    private function downloadSpecificFile(int $materialId, int $fileId): BinaryFileResponse|RedirectResponse
    {
        $file = CourseMaterialFile::with(['courseMaterial.courseOffering.lecturers', 'courseMaterial.courseOffering.studyProgram'])
            ->where('course_material_id', $materialId)
            ->find($fileId);

        if (! $file) {
            abort(404, 'File tidak ditemukan');
        }

        $material = $file->courseMaterial;

        $user = auth()->user();

        if (! $user || ! $this->canAccessMaterial($material, $user)) {
            abort(403, 'Anda tidak memiliki akses ke materi ini');
        }

        if ($file->file_type === 'link') {
            return $this->redirectToLink($file, $user);
        }

        // Get file path
        $filePath = storage_path('app/public/'.$file->file_path);

        if (! file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di server');
        }

        // Update download count
        $file->increment('download_count');

        // Download the file
        return response()->download($filePath, $file->file_name);
    }

    private function downloadLegacyFile(int $id): BinaryFileResponse
    {
        $material = CourseMaterial::with(['courseOffering.course'])->find($id);

        if (! $material) {
            abort(404, 'Materi tidak ditemukan');
        }

        $user = auth()->user();

        if (! $user || ! $this->canAccessMaterial($material, $user)) {
            abort(403, 'Anda tidak memiliki akses ke materi ini');
        }

        // Get file path
        $filePath = storage_path('app/public/'.$material->file_path);

        if (! file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di server');
        }

        // Update last accessed timestamp for students
        if ($user->hasRole('student')) {
            $material->update(['last_accessed_at' => now()]);
        }

        // Download the file
        return response()->download($filePath, $material->file_name);
    }

    public function preview(int $id, ?int $fileId = null): BinaryFileResponse
    {
        $user = auth()->user();

        if ($fileId) {
            $file = CourseMaterialFile::with('courseMaterial.courseOffering.lecturers')
                ->where('course_material_id', $id)
                ->find($fileId);

            if (! $file) {
                abort(404, 'File tidak ditemukan');
            }

            $material = $file->courseMaterial;

            if (! $user || ! $this->canAccessMaterial($material, $user)) {
                abort(403, 'Anda tidak memiliki akses ke materi ini');
            }

            if ($file->file_type !== 'pdf') {
                abort(400, 'Preview hanya untuk PDF');
            }

            $filePath = storage_path('app/public/'.$file->file_path);

            if (! file_exists($filePath)) {
                abort(404, 'File tidak ditemukan di server');
            }

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$file->file_name.'"',
            ]);
        }

        $material = CourseMaterial::with(['courseOffering.course'])->find($id);

        if (! $material) {
            abort(404, 'Materi tidak ditemukan');
        }

        if (! $user || ! $this->canAccessMaterial($material, $user)) {
            abort(403, 'Anda tidak memiliki akses ke materi ini');
        }

        if ($material->file_type !== 'pdf') {
            abort(400, 'Preview hanya untuk PDF');
        }

        $filePath = storage_path('app/public/'.$material->file_path);

        if (! file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di server');
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$material->file_name.'"',
        ]);
    }

    public function openLink(int $id, int $fileId): RedirectResponse
    {
        $file = CourseMaterialFile::with('courseMaterial.courseOffering.lecturers')
            ->where('course_material_id', $id)
            ->find($fileId);

        if (! $file) {
            abort(404, 'Link tidak ditemukan');
        }

        $material = $file->courseMaterial;
        $user = auth()->user();

        if (! $user || ! $this->canAccessMaterial($material, $user)) {
            abort(403, 'Anda tidak memiliki akses ke materi ini');
        }

        if ($file->file_type !== 'link') {
            abort(400, 'File ini bukan link');
        }

        $this->trackStudentDownload($material, $user);
        $file->increment('download_count');
        $material->update(['last_accessed_at' => now()]);

        return redirect()->away($file->file_path);
    }

    private function canAccessMaterial(CourseMaterial $material, $user): bool
    {
        $isLecturer = $user->hasRole('lecturer') &&
            $material->courseOffering->lecturers()
                ->where('lecturer_profile_id', $user->lecturerProfile?->id)
                ->exists();

        $studentProfileId = $user->studentProfile?->id;
        $isStudent = $user->hasRole('student') && $studentProfileId
            ? StudyPlan::query()
                ->where('student_profile_id', $studentProfileId)
                ->whereHas('details', function ($query) use ($material) {
                    $query->where('course_offering_id', $material->course_offering_id);
                })
                ->exists()
            : false;

        return $isLecturer || $isStudent;
    }

    private function redirectToLink(CourseMaterialFile $file, $user): RedirectResponse
    {
        $this->trackStudentDownload($file->courseMaterial, $user);
        $file->increment('download_count');
        $file->courseMaterial->update(['last_accessed_at' => now()]);

        return redirect()->away($file->file_path);
    }

    private function trackStudentDownload(CourseMaterial $material, $user): void
    {
        if (! $user || ! $user->hasRole('student')) {
            return;
        }

        CourseMaterialDownload::updateOrCreate(
            [
                'course_material_id' => $material->id,
                'student_id' => $user->id,
            ],
            [
                'downloaded_at' => now(),
                'ip_address' => request()->ip(),
            ]
        );
    }
}
