<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            line-height: 1.65;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #111827;
            padding-bottom: 14px;
            margin-bottom: 28px;
        }
        .campus-name {
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .number {
            text-align: center;
            margin-bottom: 28px;
        }
        table.identity {
            width: 100%;
            margin: 16px 0;
            border-collapse: collapse;
        }
        table.identity td {
            padding: 3px 0;
            vertical-align: top;
        }
        table.identity td:first-child {
            width: 160px;
        }
        .signature {
            margin-top: 52px;
            width: 260px;
            float: right;
            text-align: center;
        }
        .signature-space {
            height: 72px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="campus-name">{{ config('app.name', 'NexaCampus') }}</div>
        <div>Sistem Informasi Akademik Perguruan Tinggi</div>
    </div>

    <div class="title">{{ strtoupper($letterType->name) }}</div>
    <div class="number">Nomor: {{ $request->request_number }}</div>

    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

    <table class="identity">
        <tr>
            <td>Nama</td>
            <td>: {{ $student->name }}</td>
        </tr>
        <tr>
            <td>NIM</td>
            <td>: {{ $studentProfile->nim }}</td>
        </tr>
        <tr>
            <td>Program Studi</td>
            <td>: {{ $studentProfile->studyProgram?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Semester</td>
            <td>: {{ $studentProfile->current_semester ?? '-' }}</td>
        </tr>
        <tr>
            <td>Status Akademik</td>
            <td>: {{ str($studentProfile->academic_status ?? 'active')->replace('_', ' ')->title() }}</td>
        </tr>
    </table>

    @if ($letterType->template_key === 'internship_recommendation')
        <p>
            Dengan ini direkomendasikan untuk mengikuti kegiatan magang/PKL pada
            <strong>{{ $data['company_name'] ?? 'instansi tujuan' }}</strong>
            @if (! empty($data['company_address']))
                yang beralamat di {{ $data['company_address'] }}
            @endif
            @if (! empty($data['internship_period']))
                selama periode {{ $data['internship_period'] }}
            @endif
            .
        </p>
    @else
        <p>
            Adalah benar mahasiswa aktif pada {{ config('app.name', 'NexaCampus') }}.
            Surat keterangan ini diterbitkan untuk keperluan {{ $request->purpose }}.
        </p>
    @endif

    @if (! empty($data))
        <p>Informasi tambahan:</p>
        <ul>
            @foreach ($data as $key => $value)
                <li>{{ str($key)->replace('_', ' ')->title() }}: {{ is_array($value) ? json_encode($value) : $value }}</li>
            @endforeach
        </ul>
    @endif

    <p>Demikian surat ini dibuat agar dapat dipergunakan sebagaimana mestinya.</p>

    <div class="signature">
        <div>{{ now()->translatedFormat('d F Y') }}</div>
        <div>{{ $letterType->signer_position ?: 'Pejabat Berwenang' }}</div>
        <div class="signature-space"></div>
        <strong>{{ $letterType->signer_name ?: 'Pejabat Berwenang' }}</strong>
    </div>
</body>
</html>
