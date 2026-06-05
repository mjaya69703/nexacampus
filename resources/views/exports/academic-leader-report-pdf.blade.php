<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #312e81; color: #fff; padding: 7px; text-align: left; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>{{ $context['title'] ?? 'Laporan Pimpinan Akademik' }}</h1>
    <div class="meta">
        Dibuat oleh {{ $context['generated_by'] ?? '-' }} pada {{ $context['generated_at'] ?? '-' }}.
        Total {{ $rows->count() }} data.
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ max(1, count($headings)) }}">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
