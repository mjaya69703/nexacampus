<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionApplication;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class AcceptanceLetterController extends Controller
{
    public function show(AdmissionApplication $application): Response
    {
        abort_unless($application->status === 'accepted', 404);

        $application->load(['period.academicYear', 'faculty', 'studyProgram', 'user.studentProfile']);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.admission-acceptance-letter', [
            'application' => $application,
            'studentProfile' => $application->user?->studentProfile,
            'generatedAt' => now(),
        ])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="acceptance-letter-'.$application->application_number.'.pdf"',
        ]);
    }
}
