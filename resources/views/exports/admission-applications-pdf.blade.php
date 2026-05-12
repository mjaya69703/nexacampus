<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admission Applications</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #4338ca; }
        .meta { color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eef2ff; color: #3730a3; font-weight: 700; }
        th, td { border: 1px solid #dbe3ef; padding: 6px 7px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>Admission Applications</h1>
    <div class="meta">Generated at {{ $generatedAt->format('d M Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
