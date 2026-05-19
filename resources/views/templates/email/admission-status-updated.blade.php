<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admission Status Updated</title>
</head>
<body style="margin:0;background:#f6f3ff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ff;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e8ddff;">
                    <tr>
                        <td style="background:#6842f4;background:linear-gradient(135deg,#6842f4,#3f249c);padding:32px;color:#ffffff;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Admission</div>
                            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">Status updated</h1>
                            <p style="margin:12px 0 0;color:#e7ddff;">There is a new update on your admission application.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin-top:0;">Hi {{ $application->full_name }},</p>
                            <p>Your application status has changed.</p>

                            <div style="background:#f1f5f9;border-radius:12px;padding:18px;margin:22px 0;">
                                <div style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">{{ $application->application_number }}</div>
                                <div style="font-size:22px;font-weight:800;color:#334155;margin-top:6px;">
                                    {{ str($fromStatus)->replace('_', ' ')->title() }} → {{ str($toStatus)->replace('_', ' ')->title() }}
                                </div>
                            </div>

                            @if($application->review_notes)
                                <div style="border-left:4px solid #6842f4;background:#faf8ff;padding:14px 16px;margin-bottom:22px;">
                                    <div style="font-weight:700;margin-bottom:4px;">Review notes</div>
                                    <div>{{ $application->review_notes }}</div>
                                </div>
                            @endif

                            <p>Open the applicant portal to view details and update documents if requested.</p>
                            <p style="margin:28px 0;">
                                <a href="{{ $portalUrl }}" style="display:inline-block;background:#6842f4;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;">Open Applicant Portal</a>
                            </p>

                            <p style="color:#64748b;font-size:13px;margin-bottom:0;">If the button does not work, open this link: <br>{{ $portalUrl }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
