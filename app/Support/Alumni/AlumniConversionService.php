<?php

namespace App\Support\Alumni;

use App\Models\Alumni\AlumniProfile;
use App\Models\StudentService\GraduationApplication;
use Illuminate\Support\Facades\DB;

class AlumniConversionService
{
    /**
     * Convert a single GraduationApplication into an AlumniProfile.
     * Returns the existing profile if already converted (idempotent).
     */
    public function convertFromGraduation(GraduationApplication $application): AlumniProfile
    {
        $this->ensureFinalized($application);

        $studentProfile = $application->studentProfile;

        if (! $studentProfile) {
            throw new \RuntimeException('Graduation application has no linked student profile.');
        }

        // Idempotency: return existing profile if already converted
        $existing = AlumniProfile::where('nim', $studentProfile->nim)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($application, $studentProfile): AlumniProfile {
            $period = $application->academicPeriod ?? $application->graduationBatch?->academicPeriod;

            return AlumniProfile::create([
                'user_id' => $studentProfile->user_id,
                'student_profile_id' => $studentProfile->id,
                'nim' => $studentProfile->nim,
                'full_name' => $studentProfile->user?->name ?? $studentProfile->nim,
                'graduation_date' => $period?->end_date ?? now(),
                'graduation_year' => $period?->end_date?->year ?? now()->year,
                'study_program_id' => $studentProfile->study_program_id,
                'faculty_id' => $studentProfile->studyProgram?->faculty_id,
                'gpa' => $studentProfile->gpa ?? $application->gpa ?? null,
                'email' => $studentProfile->user?->email ?? '',
                'gender' => $studentProfile->gender ?? null,
                'birth_date' => $studentProfile->birth_date ?? null,
                'employment_status' => 'unemployed',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Batch convert all finalized GraduationApplications from a specific batch.
     * Returns array with 'converted' count and 'skipped' count.
     */
    public function batchConvert(int $graduationBatchId): array
    {
        $applications = GraduationApplication::query()
            ->where('graduation_batch_id', $graduationBatchId)
            ->where('status', 'finalized')
            ->with(['studentProfile.user', 'studentProfile.studyProgram', 'academicPeriod', 'graduationBatch.academicPeriod'])
            ->get();

        $converted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($applications as $application) {
            try {
                $profile = $this->convertFromGraduation($application);

                if ($profile->wasRecentlyCreated) {
                    $converted++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Application #{$application->id}: {$e->getMessage()}";
            }
        }

        return [
            'converted' => $converted,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function ensureFinalized(GraduationApplication $application): void
    {
        if ($application->status !== 'finalized') {
            throw new \RuntimeException('Only finalized graduation applications can be converted to alumni profiles.');
        }
    }
}
