<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudentProfile;
use App\Models\Settings\Campus;
use App\Support\Student\DigitalStudentIdService;
use Inertia\Inertia;
use Inertia\Response;

class DigitalStudentIdVerificationController extends Controller
{
    public function __invoke(
        StudentProfile $studentProfile,
        string $token,
        DigitalStudentIdService $service
    ): Response {
        abort_unless($service->isValid($studentProfile, $token), 404);

        $studentProfile->load(['user', 'studyProgram.faculty', 'entryAcademicYear']);

        return Inertia::render('Home/DigitalIdVerify', [
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
            ],
            'student' => [
                'name' => $studentProfile->user?->name ?? '-',
                'nim' => $studentProfile->nim,
                'academicStatus' => $studentProfile->academic_status ?? '-',
                'programName' => $studentProfile->studyProgram?->name ?? '-',
                'facultyName' => $studentProfile->studyProgram?->faculty?->name ?? '-',
                'isActive' => (bool) $studentProfile->is_active,
            ],
        ]);
    }
}
