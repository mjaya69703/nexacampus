<?php

namespace App\Support\Admission;

use App\Mail\AdmissionConvertedToStudent;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudentRegistration;
use App\Models\Admission\AdmissionApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AdmissionConversionService
{
    public function convert(AdmissionApplication $application, int $userId): StudentProfile
    {
        if ($application->status !== 'accepted') {
            throw new RuntimeException('Hanya applicant dengan status accepted yang bisa dikonversi.');
        }

        if ($application->converted_at || $application->user_id) {
            throw new RuntimeException('Applicant ini sudah pernah dikonversi.');
        }

        if (! $application->study_program_id) {
            throw new RuntimeException('Study program wajib diisi sebelum konversi.');
        }

        $plainPassword = Str::password(10);
        $nimService = app(NimGenerationService::class);

        $studentProfile = DB::transaction(function () use ($application, $userId, $plainPassword, $nimService) {
            $application->loadMissing(['period.academicYear', 'studyProgram']);

            $user = User::withTrashed()->where('email', $application->email)->first();

            if ($user?->trashed()) {
                $user->restore();
            }

            if (! $user) {
                $nameParts = preg_split('/\s+/', trim($application->full_name), 2);
                $user = User::create([
                    'first_name' => $nameParts[0] ?? $application->full_name,
                    'last_name' => $nameParts[1] ?? '',
                    'photo' => 'default.jpg',
                    'username' => $this->uniqueUsername($application),
                    'phone' => $application->phone,
                    'gender' => $this->userGender($application->gender),
                    'date_of_birth' => $application->birth_date,
                    'code' => Str::random(6),
                    'email' => $application->email,
                    'password' => Hash::make($plainPassword),
                    'is_active' => true,
                ]);
            } else {
                $user->update([
                    'phone' => $application->phone,
                    'gender' => $this->userGender($application->gender),
                    'date_of_birth' => $application->birth_date,
                    'is_active' => true,
                ]);
            }

            if (! $user->hasRole('student')) {
                $user->assignRole('student');
            }

            $nim = $nimService->generate($application);

            $studentProfile = StudentProfile::create([
                'user_id' => $user->id,
                'study_program_id' => $application->study_program_id,
                'entry_academic_year_id' => $application->period?->academic_year_id,
                'nim' => $nim,
                'entry_year' => $application->period?->academic_year ?: now()->year,
                'academic_status' => 'Aktif',
                'entry_date' => now()->toDateString(),
                'current_semester' => 1,
                'is_active' => true,
                'desc' => 'Converted from admission application '.$application->application_number.'.',
                'created_by' => $userId,
            ]);

            if ($application->period?->academic_year_id) {
                StudentRegistration::create([
                    'student_profile_id' => $studentProfile->id,
                    'academic_year_id' => $application->period->academic_year_id,
                    'semester_no' => 1,
                    'registration_status' => 'Approved',
                    'academic_status' => 'Aktif',
                    'registration_date' => now()->toDateString(),
                    'submitted_at' => now(),
                    'approved_at' => now(),
                    'approved_by' => $userId,
                    'notes' => 'Initial registration from admission conversion.',
                    'is_active' => true,
                    'created_by' => $userId,
                ]);
            }

            $application->update([
                'user_id' => $user->id,
                'converted_at' => now(),
                'updated_by' => $userId,
            ]);

            return $studentProfile->load('user', 'studyProgram', 'entryAcademicYear');
        });

        $this->sendWelcomeEmail($application->fresh(['period', 'studyProgram', 'user']), $studentProfile, $plainPassword);

        return $studentProfile;
    }

    private function uniqueUsername(AdmissionApplication $application): string
    {
        $base = Str::slug($application->full_name, '.')
            ?: Str::before($application->email, '@')
            ?: 'student';

        $username = $base;
        $counter = 1;

        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = $base.'.'.$counter;
            $counter++;
        }

        return $username;
    }

    private function userGender(?string $gender): ?string
    {
        return match ($gender) {
            'male' => 'Laki-laki',
            'female' => 'Perempuan',
            default => null,
        };
    }

    private function sendWelcomeEmail(AdmissionApplication $application, StudentProfile $studentProfile, string $plainPassword): void
    {
        try {
            Mail::to($application->email)->send(
                new AdmissionConvertedToStudent($application, $studentProfile, $plainPassword),
            );
        } catch (Throwable $exception) {
            Log::warning('Admission conversion welcome email failed.', [
                'application_id' => $application->id,
                'email' => $application->email,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
