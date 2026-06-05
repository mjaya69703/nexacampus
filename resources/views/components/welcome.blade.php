@php
    $loginUrl = Route::has('auth.signin-index') ? route('auth.signin-index') : url('/auth/login');
    $admissionUrl = Route::has('admission.apply') ? route('admission.apply') : url('/admission/apply');
    $statusUrl = Route::has('admission.status') ? route('admission.status') : url('/admission/status');
    $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
    $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute)
        ? route($dashboardRoute)
        : (Route::has('auth.select-role') ? route('auth.select-role') : $loginUrl);

    $modules = [
        ['icon' => 'fa-layer-group', 'color' => 'primary', 'title' => 'Master Akademik', 'text' => 'Tahun akademik, fakultas, program studi, kurikulum, mata kuliah, gedung, dan ruangan.'],
        ['icon' => 'fa-file-signature', 'color' => 'green', 'title' => 'Admission / PMB', 'text' => 'Form publik, dokumen pendaftaran, seleksi, ranking, dan konversi calon mahasiswa.'],
        ['icon' => 'fa-user-graduate', 'color' => 'azure', 'title' => 'Portal Mahasiswa', 'text' => 'Registrasi, KRS, jadwal, materi, presensi, nilai, transkrip, layanan, dan invoice.'],
        ['icon' => 'fa-chalkboard-user', 'color' => 'indigo', 'title' => 'Portal Dosen', 'text' => 'Kelas, materi kuliah, presensi, tugas, diskusi, grade book, dan pengumuman dosen.'],
        ['icon' => 'fa-wallet', 'color' => 'yellow', 'title' => 'Keuangan Mahasiswa', 'text' => 'Tagihan, bukti bayar, cicilan, receipt PDF, financial hold, dan clearance policy.'],
        ['icon' => 'fa-headset', 'color' => 'orange', 'title' => 'Layanan Mahasiswa', 'text' => 'Surat layanan, cuti akademik, transfer, kelulusan, dokumen, dan komplain mahasiswa.'],
    ];

    $roles = [
        ['icon' => 'fa-user-shield', 'color' => 'primary', 'title' => 'Admin dan Operator', 'text' => 'Mengelola master data, permission, resource, admission, publikasi, layanan mahasiswa, dan finansial.'],
        ['icon' => 'fa-person-chalkboard', 'color' => 'green', 'title' => 'Dosen', 'text' => 'Mengajar dari portal khusus untuk kelas, jadwal, presensi, materi, diskusi, tugas, dan penilaian.'],
        ['icon' => 'fa-id-card', 'color' => 'azure', 'title' => 'Mahasiswa', 'text' => 'Mengikuti perjalanan akademik dari registrasi, KRS, pembelajaran, layanan, tagihan, sampai transkrip.'],
    ];

    $flow = [
        ['title' => 'Setup Akademik', 'text' => 'Admin menyiapkan tahun akademik, program studi, kurikulum, mata kuliah, dan kelas.'],
        ['title' => 'Registrasi dan KRS', 'text' => 'Mahasiswa melakukan registrasi periode dan menyusun rencana studi semester.'],
        ['title' => 'Pembelajaran', 'text' => 'Dosen membagikan materi, mengelola presensi, membuka diskusi, memberi tugas, dan mengisi nilai.'],
        ['title' => 'Evaluasi', 'text' => 'Nilai diproses menjadi hasil studi, transkrip, statistik kelas, dan dasar keputusan akademik.'],
    ];
@endphp

<style>
    .nexa-home {
        width: 100vw;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
        background: var(--tblr-bg-surface-secondary);
        overflow: hidden;
    }

    .nexa-hero {
        position: relative;
        min-height: 76vh;
        display: flex;
        align-items: end;
        padding: 6.5rem 0 4rem;
        color: #fff;
        background-image:
            linear-gradient(90deg, rgba(15, 23, 42, .94) 0%, rgba(15, 23, 42, .78) 48%, rgba(15, 23, 42, .38) 100%),
            url("{{ asset('assets/static/photos/a-visit-to-the-bookstore.jpg') }}");
        background-size: cover;
        background-position: center;
    }

    .nexa-hero::after {
        content: "";
        position: absolute;
        inset: auto 0 0;
        height: 6rem;
        background: linear-gradient(180deg, rgba(246, 248, 251, 0), var(--tblr-bg-surface-secondary));
        pointer-events: none;
    }

    .nexa-hero .container-xl {
        position: relative;
        z-index: 1;
    }

    .nexa-hero-copy {
        max-width: 42rem;
        color: rgba(255, 255, 255, .82);
        font-size: 1.15rem;
        line-height: 1.7;
    }

    .nexa-hero-title {
        max-width: 46rem;
        color: #fff;
        font-size: clamp(2.75rem, 5vw, 5.25rem);
        line-height: .98;
        letter-spacing: 0;
        font-weight: 800;
    }

    .nexa-overlap {
        position: relative;
        z-index: 2;
        margin-top: -2rem;
    }

    .nexa-section {
        padding: 5rem 0;
    }

    .nexa-section-white {
        background: var(--tblr-bg-surface);
    }

    .nexa-photo-card {
        min-height: 32rem;
        border-radius: var(--tblr-border-radius);
        background-image:
            linear-gradient(180deg, rgba(6, 111, 209, .04), rgba(6, 111, 209, .22)),
            url("{{ asset('assets/static/photos/a-woman-works-at-a-desk-with-a-laptop-and-a-cup-of-coffee.jpg') }}");
        background-size: cover;
        background-position: center;
    }

    .nexa-cta {
        color: #fff;
        background:
            linear-gradient(90deg, rgba(15, 23, 42, .96), rgba(6, 111, 209, .82)),
            url("{{ asset('storage/images/gallery/album-a.jpg') }}");
        background-size: cover;
        background-position: center;
    }

    @media (max-width: 575.98px) {
        .nexa-hero {
            min-height: 82vh;
            padding: 5rem 0 3rem;
            background-position: 62% center;
        }

        .nexa-hero-copy {
            font-size: 1rem;
        }

        .nexa-section {
            padding: 3.5rem 0;
        }

        .nexa-photo-card {
            min-height: 20rem;
        }
    }
</style>

<main class="nexa-home">
    <section class="nexa-hero">
        <div class="container-xl">
            <div class="row">
                <div class="col-lg-8">
                    <span class="badge bg-primary-lt text-primary mb-3">
                        <i class="fa-solid fa-building-columns me-2"></i>
                        SIAKAD modern untuk perguruan tinggi Indonesia
                    </span>
                    <h1 class="nexa-hero-title mb-3">NexaCampus</h1>
                    <p class="nexa-hero-copy mb-4">
                        Sistem informasi akademik terpadu untuk mengelola PMB, perkuliahan, pembelajaran, layanan mahasiswa,
                        pengumuman, nilai, transkrip, dan keuangan mahasiswa dalam satu portal kampus.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        @auth
                            <a href="{{ $dashboardUrl }}" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-chart-line me-2"></i>
                                Masuk Dashboard
                            </a>
                        @else
                            <a href="{{ $loginUrl }}" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-right-to-bracket me-2"></i>
                                Login Portal
                            </a>
                        @endauth
                        <a href="{{ $admissionUrl }}" class="btn btn-outline-light btn-lg">
                            <i class="fa-solid fa-file-signature me-2"></i>
                            Daftar Mahasiswa Baru
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="nexa-overlap">
        <div class="container-xl">
            <div class="card">
                <div class="row g-0">
                    <div class="col-sm-6 col-lg-3 border-end">
                        <div class="card-body">
                            <div class="subheader">Admission</div>
                            <div class="h1 mb-2">PMB</div>
                            <div class="text-secondary">Form publik, dokumen, seleksi, dan konversi mahasiswa.</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 border-end">
                        <div class="card-body">
                            <div class="subheader">Akademik</div>
                            <div class="h1 mb-2">KRS</div>
                            <div class="text-secondary">Registrasi akademik, rencana studi, jadwal, dan presensi.</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 border-end">
                        <div class="card-body">
                            <div class="subheader">Pembelajaran</div>
                            <div class="h1 mb-2">LMS</div>
                            <div class="text-secondary">Materi kuliah, lampiran, diskusi, bookmark, dan tracking.</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card-body">
                            <div class="subheader">Keuangan</div>
                            <div class="h1 mb-2">Finance</div>
                            <div class="text-secondary">Tagihan, bukti bayar, cicilan, receipt, dan financial hold.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="nexa-section">
        <div class="container-xl">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <div class="subheader text-primary">Satu Fondasi Operasional</div>
                    <h2 class="display-6 fw-bold mb-2">Ruang kerja kampus yang rapi dari pendaftaran sampai kelulusan.</h2>
                    <p class="text-secondary fs-3 mb-0">
                        NexaCampus menyatukan proses akademik utama agar admin, dosen, dan mahasiswa bekerja dari data yang sama.
                    </p>
                </div>
            </div>

            <div class="row row-cards">
                @foreach ($modules as $module)
                    <div class="col-md-6 col-xl-4">
                        <div class="card card-stacked h-100">
                            <div class="card-body">
                                <span class="avatar avatar-lg bg-{{ $module['color'] }}-lt text-{{ $module['color'] }} mb-3">
                                    <i class="fa-solid {{ $module['icon'] }}"></i>
                                </span>
                                <h3 class="card-title mb-2">{{ $module['title'] }}</h3>
                                <p class="text-secondary mb-0">{{ $module['text'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="nexa-section nexa-section-white">
        <div class="container-xl">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-5">
                    <div class="nexa-photo-card h-100"></div>
                </div>
                <div class="col-lg-7">
                    <div class="mb-4">
                        <div class="subheader text-primary">Untuk Banyak Peran</div>
                        <h2 class="display-6 fw-bold mb-2">Admin fleksibel, dosen fokus mengajar, mahasiswa punya portal yang jelas.</h2>
                        <p class="text-secondary fs-3 mb-0">
                            Hak akses dibangun dengan role dan permission, sementara pengalaman pengguna dipisah sesuai kebutuhan kerja masing-masing peran.
                        </p>
                    </div>

                    <div class="row row-cards">
                        @foreach ($roles as $role)
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body d-flex gap-3">
                                        <span class="avatar avatar-lg bg-{{ $role['color'] }}-lt text-{{ $role['color'] }} flex-shrink-0">
                                            <i class="fa-solid {{ $role['icon'] }}"></i>
                                        </span>
                                        <div>
                                            <h3 class="card-title mb-1">{{ $role['title'] }}</h3>
                                            <p class="text-secondary mb-0">{{ $role['text'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="nexa-section">
        <div class="container-xl">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <div class="subheader text-primary">Alur Kampus</div>
                    <h2 class="display-6 fw-bold mb-2">Didesain untuk ritme kerja semesteran.</h2>
                    <p class="text-secondary fs-3 mb-0">
                        Dari setup tahun akademik sampai laporan nilai, setiap modul mengikuti alur operasional kampus yang familiar.
                    </p>
                </div>
            </div>

            <div class="row row-cards">
                @foreach ($flow as $index => $step)
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <span class="badge bg-primary mb-4">{{ $index + 1 }}</span>
                                <h3 class="card-title mb-2">{{ $step['title'] }}</h3>
                                <p class="text-secondary mb-0">{{ $step['text'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="nexa-section nexa-cta">
        <div class="container-xl">
            <div class="row align-items-center g-4">
                <div class="col-lg">
                    <span class="badge bg-white-lt text-white mb-3">Portal siap dipakai</span>
                    <h2 class="display-6 fw-bold text-white mb-2">Mulai dari akses login dan PMB publik.</h2>
                    <p class="text-white-50 fs-3 mb-0">
                        Akses login untuk admin, dosen, dan mahasiswa, atau arahkan calon mahasiswa ke formulir pendaftaran online.
                    </p>
                </div>
                <div class="col-lg-auto">
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="{{ $statusUrl }}" class="btn btn-outline-light btn-lg">
                            <i class="fa-solid fa-magnifying-glass me-2"></i>
                            Cek Status PMB
                        </a>
                        <a href="{{ $loginUrl }}" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>
                            Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
