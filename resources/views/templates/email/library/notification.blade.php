<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;background:#f5f0ff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0ff;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5dbff;">
                    <tr>
                        <td style="background:#6842f4;background:linear-gradient(135deg,#8b6cff,#4c2bb8);padding:30px 32px;color:#ffffff;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.8;font-weight:700;">NexaCampus Perpustakaan</div>
                            <h1 style="margin:8px 0 0;font-size:26px;line-height:1.25;">{{ $heading }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin-top:0;line-height:1.6;color:#334155;">{!! nl2br(e($intro)) !!}</p>

                            @if (count($details) > 0)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f3ff;border-radius:12px;padding:6px 18px;margin:20px 0;">
                                    @foreach ($details as $detail)
                                        <tr>
                                            <td style="padding:9px 0;font-size:13px;color:#6b5b95;width:44%;vertical-align:top;">{{ $detail['label'] }}</td>
                                            <td style="padding:9px 0;font-size:14px;color:#2f2158;font-weight:700;vertical-align:top;">{{ $detail['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if ($actionUrl)
                                <p style="margin:26px 0 8px;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#6842f4;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:700;">{{ $actionLabel }}</a>
                                </p>
                            @endif

                            <p style="color:#64748b;font-size:13px;line-height:1.6;margin-bottom:0;">
                                {{ $footer ?? 'Email ini dikirim otomatis oleh sistem perpustakaan NexaCampus.' }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
