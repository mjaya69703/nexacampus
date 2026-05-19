<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice Overdue</title>
</head>
<body style="margin:0;background:#fff7ed;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff7ed;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #fed7aa;">
                    <tr>
                        <td style="background:#ea580c;background:linear-gradient(135deg,#f97316,#c2410c);padding:32px;color:#ffffff;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Financial</div>
                            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">Invoice melewati jatuh tempo</h1>
                            <p style="margin:12px 0 0;color:#ffedd5;">Mohon selesaikan pembayaran atau hubungi finance jika memerlukan dispensasi.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin-top:0;">Halo {{ $invoice->studentProfile?->user?->name ?? 'Mahasiswa' }},</p>
                            <p>Invoice <strong>{{ $invoice->invoice_number }}</strong> sudah melewati jatuh tempo.</p>
                            <div style="background:#fff7ed;border-radius:12px;padding:18px;margin:22px 0;">
                                <div style="font-size:12px;color:#9a3412;text-transform:uppercase;font-weight:700;">Sisa Pembayaran</div>
                                <div style="font-size:26px;font-weight:800;color:#c2410c;margin-top:4px;">Rp {{ number_format((float) $invoice->outstanding_amount, 0, ',', '.') }}</div>
                                <div style="font-size:13px;color:#9a3412;margin-top:8px;">Jatuh tempo: {{ $invoice->due_date?->format('d M Y') }}</div>
                            </div>
                            <p style="margin:28px 0;">
                                <a href="{{ route('student.financial.invoices.show', ['id' => $invoice->id]) }}" style="display:inline-block;background:#ea580c;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;">Lihat Tagihan</a>
                            </p>
                            <p style="color:#64748b;font-size:13px;margin-bottom:0;">Jika pembayaran sudah dilakukan, abaikan email ini setelah finance memverifikasi bukti pembayaran.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
