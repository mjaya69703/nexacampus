<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Pembayaran Ditolak</title></head>
<body style="margin:0;background:#fef2f2;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #fecaca;">
                <tr><td style="background:#dc2626;background:linear-gradient(135deg,#ef4444,#991b1b);padding:32px;color:#ffffff;">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Financial</div>
                    <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">Pembayaran perlu diperbaiki</h1>
                    <p style="margin:12px 0 0;color:#fee2e2;">Finance menolak bukti pembayaran yang dikirim.</p>
                </td></tr>
                <tr><td style="padding:28px 32px;">
                    <p style="margin-top:0;">Halo {{ $payment->studentProfile?->user?->name ?? 'Mahasiswa' }},</p>
                    <p>Pembayaran <strong>{{ $payment->payment_number }}</strong> untuk invoice <strong>{{ $payment->invoice?->invoice_number }}</strong> ditolak.</p>
                    @if($payment->verification_notes)
                        <div style="background:#fef2f2;border-radius:12px;padding:18px;margin:22px 0;color:#991b1b;">{{ $payment->verification_notes }}</div>
                    @endif
                    <p style="margin:28px 0;"><a href="{{ route('student.financial.invoices.show', ['id' => $payment->student_invoice_id]) }}" style="display:inline-block;background:#dc2626;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;">Upload Ulang Bukti Bayar</a></p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
