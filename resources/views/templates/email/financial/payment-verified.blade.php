<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Pembayaran Terverifikasi</title></head>
<body style="margin:0;background:#f0fdf4;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #bbf7d0;">
                <tr><td style="background:#16a34a;background:linear-gradient(135deg,#22c55e,#15803d);padding:32px;color:#ffffff;">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Financial</div>
                    <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">Pembayaran terverifikasi</h1>
                    <p style="margin:12px 0 0;color:#dcfce7;">Bukti pembayaran kamu sudah disetujui finance.</p>
                </td></tr>
                <tr><td style="padding:28px 32px;">
                    <p style="margin-top:0;">Halo {{ $payment->studentProfile?->user?->name ?? 'Mahasiswa' }},</p>
                    <p>Pembayaran <strong>{{ $payment->payment_number }}</strong> untuk invoice <strong>{{ $payment->invoice?->invoice_number }}</strong> sudah terverifikasi.</p>
                    <div style="background:#f0fdf4;border-radius:12px;padding:18px;margin:22px 0;">
                        <div style="font-size:12px;color:#166534;text-transform:uppercase;font-weight:700;">Nominal Pembayaran</div>
                        <div style="font-size:26px;font-weight:800;color:#15803d;margin-top:4px;">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</div>
                    </div>
                    <p style="margin:28px 0;"><a href="{{ route('student.financial.invoices.show', ['id' => $payment->student_invoice_id]) }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;">Lihat Invoice</a></p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
