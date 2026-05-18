<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice Terbit</title>
</head>
<body style="margin:0;background:#f6f3ff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ff;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e8ddff;">
                    <tr>
                        <td style="background:#6842f4;background:linear-gradient(135deg,#6842f4,#3f249c);padding:32px;color:#ffffff;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Financial</div>
                            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">Invoice baru sudah terbit</h1>
                            <p style="margin:12px 0 0;color:#e7ddff;">Silakan cek rincian tagihan dan lakukan pembayaran sebelum jatuh tempo.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin-top:0;">Halo {{ $invoice->studentProfile?->user?->name ?? 'Mahasiswa' }},</p>
                            <p>Invoice <strong>{{ $invoice->invoice_number }}</strong> sudah diterbitkan.</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;">
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;color:#64748b;">Jenis Invoice</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;text-align:right;font-weight:700;">{{ str($invoice->invoice_type)->replace('_', ' ')->title() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;color:#64748b;">Total</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;text-align:right;font-weight:700;">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;color:#64748b;">Jatuh Tempo</td>
                                    <td style="padding:10px 0;text-align:right;font-weight:700;">{{ $invoice->due_date?->format('d M Y') }}</td>
                                </tr>
                            </table>

                            <p style="margin:28px 0;">
                                <a href="{{ route('student.financial.invoices.show', ['id' => $invoice->id]) }}" style="display:inline-block;background:#6842f4;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;">Lihat Invoice</a>
                            </p>
                            <p style="color:#64748b;font-size:13px;margin-bottom:0;">Email ini dikirim otomatis oleh sistem keuangan kampus.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
