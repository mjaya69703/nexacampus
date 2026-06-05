<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .stats { margin: 12px 0; display: table; width: 100%; }
        .stat { display: table-cell; border: 1px solid #e5e7eb; padding: 8px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Rekap BKD Dosen</h1>
    <div class="muted">{{ $context['scope'] ?? '-' }} / Dicetak {{ $context['generated_at'] ?? '-' }} oleh {{ $context['generated_by'] ?? '-' }}</div>

    <div class="stats">
        <div class="stat"><strong>{{ $stats['total'] }}</strong><br><span class="muted">Total Data</span></div>
        <div class="stat"><strong>{{ $stats['approved'] }}</strong><br><span class="muted">Disetujui</span></div>
        <div class="stat"><strong>{{ number_format($stats['avg_sks'], 2) }}</strong><br><span class="muted">Rata-rata SKS</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Dosen</th><th>Program Studi</th><th>Fakultas</th><th>Periode</th><th>Status</th>
                <th class="right">Mengajar</th><th class="right">Jabatan</th><th class="right">Tridharma</th><th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['lecturer'] }}</td>
                    <td>{{ $row['study_program'] }}</td>
                    <td>{{ $row['faculty'] }}</td>
                    <td>{{ $row['period'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="right">{{ number_format($row['teaching_sks'], 2) }}</td>
                    <td class="right">{{ number_format($row['structural_sks'], 2) }}</td>
                    <td class="right">{{ number_format($row['tridharma_sks'], 2) }}</td>
                    <td class="right"><strong>{{ number_format($row['total_sks'], 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
