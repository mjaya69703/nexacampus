<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f6f3ff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ff;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e8ddff;">
                    <tr>
                        <td style="background:#6842f4;background:linear-gradient(135deg,#6842f4,#3f249c);padding:32px;color:#ffffff;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Student Services</div>
                            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">{{ $title }}</h1>
                            <p style="margin:12px 0 0;color:#e7ddff;">Status layanan mahasiswa kamu sudah diperbarui.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin-top:0;">Halo {{ $studentName }},</p>
                            <p>{{ $requestLabel }} <strong>{{ $requestNumber }}</strong> sekarang berstatus <strong>{{ $statusLabel }}</strong>.</p>

                            @if ($notes)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                                    <tr>
                                        <td style="padding:16px;">
                                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:6px;">Catatan</div>
                                            <div style="line-height:1.6;">{{ $notes }}</div>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if ($actionUrl)
                                <p style="margin:28px 0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#6842f4;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;">{{ $actionLabel }}</a>
                                </p>
                            @endif

                            <p style="color:#64748b;font-size:13px;margin-bottom:0;">Email ini dikirim otomatis oleh sistem layanan mahasiswa kampus.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
