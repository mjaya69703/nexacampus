<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    public function admin(Payment $payment): Response
    {
        $payment->load(['invoice', 'studentProfile.user', 'studentProfile.studyProgram', 'verifiedBy']);

        abort_unless($payment->status === 'verified', 404);

        return $this->pdf($payment);
    }

    public function student(Payment $payment): Response
    {
        $studentProfile = auth()->user()?->studentProfile;

        abort_unless($studentProfile && (int) $payment->student_profile_id === (int) $studentProfile->id, 404);
        abort_unless($payment->status === 'verified', 404);

        $payment->load(['invoice', 'studentProfile.user', 'studentProfile.studyProgram', 'verifiedBy']);

        return $this->pdf($payment);
    }

    private function pdf(Payment $payment): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.financial.payment-receipt', [
            'payment' => $payment,
        ])->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$payment->payment_number.'.pdf"',
        ]);
    }
}
