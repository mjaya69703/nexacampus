<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Cicilan Disetujui</title></head>
<body style="margin:0;background:#eff6ff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;padding:32px 12px;">
        <tr><td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #bfdbfe;">
                <tr><td style="background:#2563eb;background:linear-gradient(135deg,#3b82f6,#1d4ed8);padding:32px;color:#ffffff;">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Financial</div>
                    <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">Pengajuan cicilan disetujui</h1>
                    <p style="margin:12px 0 0;color:#dbeafe;">Silakan bayar sesuai jadwal cicilan yang sudah disetujui.</p>
                </td></tr>
                <tr><td style="padding:28px 32px;">
                    <p style="margin-top:0;">Halo {{ $request->invoice?->studentProfile?->user?->name ?? 'Mahasiswa' }},</p>
                    <p>Pengajuan cicilan untuk invoice <strong>{{ $request->invoice?->invoice_number }}</strong> disetujui dengan tenor <strong>{{ $request->requested_tenor }}x</strong>.</p>
                    @if($request->finance_notes)
                        <div style="background:#eff6ff;border-radius:12px;padding:18px;margin:22px 0;color:#1e40af;">{{ $request->finance_notes }}</div>
                    @endif
                    <p style="margin:28px 0;"><a href="{{ route('student.financial.invoices.show', ['id' => $request->student_invoice_id]) }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;">Lihat Jadwal Cicilan</a></p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
