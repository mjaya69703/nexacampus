<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Reminder Deadline Tugas</title>
</head>
<body style="margin:0;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif;">
    <div style="max-width:640px;margin:0 auto;padding:32px 18px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;">
            <p style="margin:0 0 12px;color:#64748b;">Halo {{ $studentProfile->user?->name ?? 'Mahasiswa' }},</p>
            <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;">Deadline tugas sudah dekat</h1>
            <p style="margin:0 0 20px;line-height:1.6;color:#334155;">
                Tugas <strong>{{ $assignment->title }}</strong> untuk
                <strong>{{ $assignment->courseOffering?->course?->code }} {{ $assignment->courseOffering?->course?->name }}</strong>
                perlu dikirim sebelum {{ $assignment->due_at?->format('d M Y H:i') }}.
            </p>

            <div style="background:#eef2ff;border-radius:12px;padding:16px;margin-bottom:22px;color:#3730a3;">
                Pastikan jawaban dan lampiran sudah final sebelum submit. Kalau tugas menerima file, cek ulang format file yang diminta dosen.
            </div>

            <a href="{{ $actionUrl }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:700;border-radius:10px;padding:12px 18px;">
                Buka Tugas
            </a>
        </div>
    </div>
</body>
</html>
