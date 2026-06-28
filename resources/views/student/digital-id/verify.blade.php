<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Kartu Mahasiswa</title>
    <link rel="stylesheet" href="{{ asset('assets/dist/css/tabler.min.css') }}">
</head>
<body class="bg-light">
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="card card-md">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl bg-green-lt text-green mb-3 fw-bold">OK</span>
                    <h1 class="h2 mb-1">Kartu Mahasiswa Valid</h1>
                    <p class="text-secondary mb-4">Data ini cocok dengan profil mahasiswa aktif di NexaCampus.</p>

                    <div class="text-start border rounded p-3 mb-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="text-secondary small">Nama</div>
                                <div class="fw-bold">{{ $studentProfile->user?->name ?? '-' }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary small">NIM</div>
                                <div class="fw-bold">{{ $studentProfile->nim }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary small">Status</div>
                                <div class="fw-bold">{{ $studentProfile->academic_status ?? '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-secondary small">Program Studi</div>
                                <div class="fw-bold">{{ $studentProfile->studyProgram?->name ?? '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-secondary small">Fakultas</div>
                                <div class="fw-bold">{{ $studentProfile->studyProgram?->faculty?->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <span class="badge {{ $studentProfile->is_active ? 'bg-green-lt text-green' : 'bg-red-lt text-red' }}">
                        {{ $studentProfile->is_active ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
