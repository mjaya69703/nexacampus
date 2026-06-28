<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Academic\StudentProfile;
use App\Support\Student\DigitalStudentIdService;
use Illuminate\Http\Response;

class DigitalStudentIdVerificationController extends Controller
{
    public function __invoke(
        StudentProfile $studentProfile,
        string $token,
        DigitalStudentIdService $service
    ): Response {
        abort_unless($service->isValid($studentProfile, $token), 404);

        $studentProfile->load(['user', 'studyProgram.faculty', 'entryAcademicYear']);

        return response()->view('student.digital-id.verify', [
            'studentProfile' => $studentProfile,
            'pages' => 'Verifikasi Kartu Mahasiswa',
        ]);
    }
}
