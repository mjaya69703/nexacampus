<?php

namespace App\Support\Alumni;

use App\Enums\EmploymentStatus;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyResult;
use App\Models\Academic\TranscriptEntry;
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

        // Idempotency: pulihkan profil yang pernah dihapus agar NIM unik tidak
        // memicu error, dan jangan buat duplikat bila konversi diulang.
        $existing = AlumniProfile::withTrashed()->where('nim', $studentProfile->nim)->first();
        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $existing->setAttribute('restored_during_conversion', true);
            }

            return $existing;
        }

        $facultyId = $studentProfile->studyProgram?->faculty_id;

        if (! $facultyId) {
            throw new \RuntimeException("Student profile #{$studentProfile->id} has no study program faculty.");
        }

        return DB::transaction(function () use ($application, $studentProfile, $facultyId): AlumniProfile {
            $period = $application->academicPeriod ?? $application->graduationBatch?->academicPeriod;
            $graduationDate = $application->graduation_date
                ?? $studentProfile->graduation_date
                ?? $period?->end_at
                ?? now();

            $gender = match ($studentProfile->user?->gender) {
                'Laki-laki' => 'L',
                'Perempuan' => 'P',
                default => null,
            };

            return AlumniProfile::create([
                'user_id' => $studentProfile->user_id,
                'student_profile_id' => $studentProfile->id,
                'nim' => $studentProfile->nim,
                'full_name' => $studentProfile->user?->name ?? $studentProfile->nim,
                'graduation_date' => $graduationDate,
                'graduation_year' => (int) $graduationDate->year,
                'study_program_id' => $studentProfile->study_program_id,
                'faculty_id' => $facultyId,
                'gpa' => $this->resolveGpa($studentProfile),
                'email' => $studentProfile->user?->email ?? '',
                'phone' => $studentProfile->user?->phone,
                'gender' => $gender,
                'birth_date' => $studentProfile->user?->date_of_birth,
                'employment_status' => EmploymentStatus::Unemployed->value,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Ambil IPK akhir dari transkrip, lalu jatuh ke snapshot StudyResult.
     */
    private function resolveGpa(StudentProfile $studentProfile): ?float
    {
        $entries = TranscriptEntry::query()
            ->where('student_profile_id', $studentProfile->id)
            ->where('is_counted_in_gpa', true)
            ->whereNotNull('grade_point')
            ->get(['credits', 'grade_point']);

        $credits = (float) $entries->sum(fn (TranscriptEntry $entry) => (int) ($entry->credits ?? 0));

        if ($credits > 0) {
            $weightedPoints = $entries->sum(
                fn (TranscriptEntry $entry) => ((float) $entry->grade_point) * ((int) ($entry->credits ?? 0)),
            );

            return round($weightedPoints / $credits, 2);
        }

        $cumulative = StudyResult::query()
            ->where('student_profile_id', $studentProfile->id)
            ->whereNotNull('cumulative_gpa')
            ->orderByDesc('semester_no')
            ->value('cumulative_gpa');

        return $cumulative !== null ? round((float) $cumulative, 2) : null;
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

                if ($profile->wasRecentlyCreated || $profile->getAttribute('restored_during_conversion')) {
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
