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
            margin: 28px 30px 40px;
        }

        /* ── Kop ── */
        .kop { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .kop td { border: 0; vertical-align: middle; padding: 0; }
        .kop-logo { width: 76px; }
        .kop-logo img { width: 68px; }
        .kop-text { text-align: center; }
        .kop-text .instansi { font-size: 17px; font-weight: 700; margin: 0; }
        .kop-text .alamat { font-size: 10px; color: #334155; margin: 3px 0 0; }
        .kop-rule { border: 0; border-top: 3px double #0f172a; margin: 0 0 14px; }

        /* ── Judul ── */
        h1 { font-size: 15px; text-align: center; text-transform: uppercase; margin: 0 0 2px; letter-spacing: .04em; }
        .subtitle { text-align: center; color: #475569; margin-bottom: 14px; }

        /* ── Tabel ── */
        table.data { border-collapse: collapse; width: 100%; }
        table.data th {
            background: #eef2ff;
            color: #312e81;
            font-weight: 700;
            text-align: left;
        }
        table.data th,
        table.data td {
            border: 1px solid #94a3b8;
            padding: 6px 8px;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; }

        /* ── Penutup ── */
        .closing { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .closing td { border: 0; vertical-align: top; padding: 0; font-size: 11px; }
        .sign { text-align: left; width: 55%; color: #475569; }
        .ttd { text-align: center; width: 45%; }
        .ttd .space { height: 64px; }
        .ttd .nama { font-weight: 700; text-decoration: underline; }

        /* ── Nomor halaman ── */
        .pagenum:before { content: 'Halaman ' counter(page) ' dari ' counter(pages); }
        .footer { position: fixed; bottom: -28px; left: 0; right: 0; text-align: center; font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="kop">
        <tr>
            @if (! empty($logoBase64))
                <td class="kop-logo"><img src="{{ $logoBase64 }}" alt="Logo"></td>
            @endif
            <td class="kop-text">
                <p class="instansi">{{ $campusName ?? config('app.name') }}</p>
                @if (! empty($campusAddress))
                    <p class="alamat">{{ $campusAddress }}</p>
                @endif
                @if (! empty($campusContact))
                    <p class="alamat">{{ $campusContact }}</p>
                @endif
            </td>
        </tr>
    </table>
    <hr class="kop-rule">

    <h1>{{ $title ?? 'Laporan Akademik' }}</h1>
    <div class="subtitle">
        @if (! empty($subtitle)){{ $subtitle }} &middot; @endif
        Dicetak {{ $generatedAt ?? now()->format('d M Y H:i') }}
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 34px;">No</th>
                @foreach ($headers ?? [] as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows ?? [] as $index => $row)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    @foreach ($row as $value)
                        <td>{!! is_scalar($value) ? strip_tags((string) $value) : '' !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($headers ?? []) + 1, 1) }}" style="text-align: center;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="closing">
        <tr>
            <td class="sign">
                Dicetak oleh: {{ $generatedBy ?? '-' }}<br>
                Total data: {{ count($rows ?? []) }} baris
            </td>
            <td class="ttd">
                {{ $signCity ?? '' }}, {{ $signDate ?? now()->translatedFormat('d F Y') }}<br>
                Penanggung jawab,
                <div class="space"></div>
                <span class="nama">( ........................................ )</span><br>
                NIP.
            </td>
        </tr>
    </table>

    <div class="footer"><span class="pagenum"></span></div>
</body>
</html>
