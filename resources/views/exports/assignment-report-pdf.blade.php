<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        .stats { margin: 18px 0; width: 100%; }
        .stats td { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #4338ca; color: #fff; padding: 8px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; padding: 7px; vertical-align: top; }
    </style>
</head>
<body>
    <h1>Assignment Report</h1>
    <div class="muted">
        {{ $assignment->title }} -
        {{ $assignment->courseOffering?->course?->code }} {{ $assignment->courseOffering?->course?->name }}
        / {{ $assignment->courseOffering?->label }}
    </div>
    <div class="muted">
        Deadline: {{ $assignment->due_at?->format('d M Y H:i') ?? '-' }}
        | Generated: {{ $context['generated_at'] ?? now()->format('d M Y H:i') }}
        | By: {{ $context['generated_by'] ?? '-' }}
    </div>

    <table class="stats">
        <tr>
            <td><strong>Total</strong><br>{{ $statistics['total_students'] }}</td>
            <td><strong>Submitted</strong><br>{{ $statistics['submitted_count'] }} ({{ $statistics['submission_rate'] }}%)</td>
            <td><strong>Graded</strong><br>{{ $statistics['graded_count'] }}</td>
            <td><strong>Returned</strong><br>{{ $statistics['returned_count'] }}</td>
            <td><strong>Missing</strong><br>{{ $statistics['missing_count'] }}</td>
            <td><strong>Average</strong><br>{{ $statistics['average_score'] ?? '-' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>NIM</th>
                <th>Mahasiswa</th>
                <th>Status</th>
                <th>Submitted At</th>
                <th>File</th>
                <th>Skor</th>
                <th>Skor 100</th>
                <th>Feedback / Revisi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['nim'] }}</td>
                    <td>{{ $row['student_name'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['submitted_at'] }}</td>
                    <td>{{ $row['file_count'] }}</td>
                    <td>{{ $row['raw_score'] ?? '-' }}</td>
                    <td>{{ $row['score_100'] ?? '-' }}</td>
                    <td>{{ $row['feedback'] ?: $row['return_note'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
