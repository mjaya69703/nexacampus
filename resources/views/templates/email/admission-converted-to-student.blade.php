<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to NexaCampus</title>
</head>
<body style="font-family: Arial, sans-serif; color:#1f2937; background:#f8fafc; padding:24px;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:14px;padding:28px;border:1px solid #e5e7eb;">
        <h1 style="margin-top:0;color:#4338ca;">Welcome, {{ $application->full_name }}</h1>
        <p>Your admission application has been converted into an active student account.</p>

        <div style="background:#f1f5f9;border-radius:12px;padding:18px;margin:20px 0;">
            <p style="margin:0 0 8px;"><strong>NIM:</strong> {{ $studentProfile->nim }}</p>
            <p style="margin:0 0 8px;"><strong>Study Program:</strong> {{ $studentProfile->studyProgram?->name ?? '-' }}</p>
            <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $application->email }}</p>
            <p style="margin:0;"><strong>Temporary Password:</strong> {{ $plainPassword }}</p>
        </div>

        <p>Please login and change your password after your first sign in.</p>
        <p style="margin-bottom:0;color:#64748b;">NexaCampus Admission Team</p>
    </div>
</body>
</html>
