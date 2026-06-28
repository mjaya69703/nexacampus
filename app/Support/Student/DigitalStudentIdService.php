<?php

namespace App\Support\Student;

use App\Models\Academic\StudentProfile;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DigitalStudentIdService
{
    public function verificationToken(StudentProfile $studentProfile): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                $studentProfile->id,
                $studentProfile->user_id,
                $studentProfile->nim,
                $studentProfile->created_at?->timestamp,
            ]),
            config('app.key')
        );
    }

    public function verificationUrl(StudentProfile $studentProfile): string
    {
        return URL::route('student.digital-id.verify', [
            'studentProfile' => $studentProfile->id,
            'token' => $this->verificationToken($studentProfile),
        ]);
    }

    public function isValid(StudentProfile $studentProfile, string $token): bool
    {
        return hash_equals($this->verificationToken($studentProfile), $token);
    }

    public function qrSvg(StudentProfile $studentProfile, int $size = 220): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->generate($this->verificationUrl($studentProfile));
    }
}
