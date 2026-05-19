<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admission Application Received</title>
</head>
<body style="margin:0;background:#f6f3ff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ff;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e8ddff;">
                    <tr>
                        <td style="background:#6842f4;background:linear-gradient(135deg,#6842f4,#3f249c);padding:32px;color:#ffffff;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.78;font-weight:700;">NexaCampus Admission</div>
                            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">Application received</h1>
                            <p style="margin:12px 0 0;color:#e7ddff;">Your admission application has been submitted successfully.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin-top:0;">Hi {{ $application->full_name }},</p>
                            <p>We have received your admission application. Keep this application number for tracking:</p>

                            <div style="background:#f1f5f9;border-radius:12px;padding:18px;margin:22px 0;text-align:center;">
                                <div style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Application Number</div>
                                <div style="font-size:26px;font-weight:800;color:#334155;margin-top:4px;">{{ $application->application_number }}</div>
                            </div>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px;">
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;color:#64748b;">Admission Period</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;text-align:right;font-weight:700;">{{ $application->period?->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;color:#64748b;">Study Program</td>
                                    <td style="padding:10px 0;border-bottom:1px solid #edf2f7;text-align:right;font-weight:700;">{{ $application->studyProgram?->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;color:#64748b;">Status</td>
                                    <td style="padding:10px 0;text-align:right;font-weight:700;">{{ str($application->status)->replace('_', ' ')->title() }}</td>
                                </tr>
                            </table>

                            <p>You can track your application and update required documents from the applicant portal.</p>
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
