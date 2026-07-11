<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Laporan Akademik' }}</title>
    <style>
        body {
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            margin: 28px;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        .meta {
            color: #64748b;
            margin-bottom: 18px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            background: #eef2ff;
            color: #312e81;
            font-weight: 700;
            text-align: left;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            vertical-align: top;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Laporan Akademik' }}</h1>
    <div class="meta">
        Dicetak {{ $generatedAt ?? now()->format('d M Y H:i') }}
        @if (! empty($subtitle))
            &middot; {{ $subtitle }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headers ?? [] as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows ?? [] as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{!! is_scalar($value) ? strip_tags((string) $value) : '' !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($headers ?? []), 1) }}">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
