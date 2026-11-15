<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\Inertia\ShellProps;
use App\Support\Student\DigitalStudentIdService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentDigitalIdController extends Controller
{
    public function index(Request $request, DigitalStudentIdService $service): Response
    {
        $user = $request->user();

        $profile = $user->studentProfile()
            ->with(['user', 'studyProgram.faculty', 'entryAcademicYear'])
            ->first();

        $card = null;
        $verificationUrl = null;
        $qrSvg = null;

        if ($profile !== null) {
            $verificationUrl = $service->verificationUrl($profile);
            $qrSvg = $service->qrSvg($profile, 230);

            $card = [
                'name' => $profile->user?->name ?? '-',
                'nim' => $profile->nim,
                'studyProgram' => $profile->studyProgram?->name ?? '-',
                'faculty' => $profile->studyProgram?->faculty?->name ?? '-',
                'entryYear' => $profile->entry_year ?? $profile->entryAcademicYear?->name ?? '-',
                'semester' => $profile->current_semester ?? '-',
                'status' => $profile->academic_status ?? '-',
                'active' => (bool) $profile->is_active,
                'photo' => $profile->user?->photo ?: asset('storage/images/profile/default.jpg'),
            ];
        }

        return Inertia::render('Student/DigitalId/Index', [
            'shell' => ShellProps::make($user, 'Kartu Mahasiswa', 'Kartu Mahasiswa'),
            'hasProfile' => $profile !== null,
            'card' => $card,
            'verificationUrl' => $verificationUrl,
            // SVG berasal dari library QR (bukan input pengguna) dan dirender
            // sebagai markup agar tetap tajam saat dicetak.
            'qrSvg' => $qrSvg,
        ]);
    }
}
