<ul class="navbar-nav">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('root.home-index') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                    <path d="M5 12l-2 0l9 -9l9 9l-2 0" />
                    <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" />
                    <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                </svg>
            </span>
            <span class="nav-link-title">Beranda</span>
        </a>
    </li>
    {{-- ─── Akademik ─────────────────────────────────────────── --}}
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#navbar-akademik" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                    <path d="M22 9l-10 -4l-10 4l10 4l10 -4v6" />
                    <path d="M6 10.6v5.4a6 3 0 0 0 12 0v-5.4" />
                </svg>
            </span>
            <span class="nav-link-title">Akademik</span>
        </a>
        <div class="dropdown-menu">
            <div class="dropdown-menu-columns">
                <div class="dropdown-menu-column">
                    <a class="dropdown-item" href="{{ route('landing.prodi') }}">
                        <span class="dropdown-item-icon"><i class="fa-solid fa-graduation-cap text-primary"></i></span>
                        Program Studi
                    </a>
                    <a class="dropdown-item" href="{{ route('root.akademik.kalender') }}">
                        <span class="dropdown-item-icon"><i class="fa-solid fa-calendar-days text-info"></i></span>
                        Kalender Akademik
                    </a>
                    <a class="dropdown-item" href="{{ route('root.akademik.jadwal') }}">
                        <span class="dropdown-item-icon"><i class="fa-solid fa-clock text-warning"></i></span>
                        Jadwal Kuliah
                    </a>
                    <a class="dropdown-item" href="{{ route('root.akademik.kurikulum') }}">
                        <span class="dropdown-item-icon"><i class="fa-solid fa-book-open text-success"></i></span>
                        Silabus &amp; Kurikulum
                    </a>
                    <a class="dropdown-item" href="{{ route('root.akademik.elearning') }}">
                        <span class="dropdown-item-icon"><i class="fa-solid fa-laptop text-secondary"></i></span>
                        E-Learning
                    </a>
                </div>
            </div>
        </div>
    </li>
    {{-- ─── Penerimaan Mahasiswa Baru ────────────────────────── --}}
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#navbar-pmb" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                    <path d="M12 3a3 3 0 0 0 -3 3v12a3 3 0 0 0 3 3" />
                    <path d="M6 3a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3" />
                    <path d="M13 7h7a1 1 0 0 1 1 1v8a1 1 0 0 1 -1 1h-7" />
                    <path d="M5 7h-1a1 1 0 0 0 -1 1v8a1 1 0 0 0 1 1h1" />
                    <path d="M17 12h.01" />
                    <path d="M13 12h4" />
                </svg>
            </span>
            <span class="nav-link-title">Penerimaan</span>
        </a>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('root.admission.apply') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-file-signature text-primary"></i></span>
                Daftar Sekarang (PMB Online)
            </a>
            <a class="dropdown-item" href="{{ route('root.admission.status') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-magnifying-glass text-info"></i></span>
                Cek Status Pendaftaran
            </a>
            <a class="dropdown-item" href="{{ route('root.admission.requirements') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-list-check text-warning"></i></span>
                Jalur Masuk &amp; Syarat
            </a>
            <a class="dropdown-item" href="{{ route('root.admission.tuition') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-money-bill-wave text-success"></i></span>
                Biaya Pendidikan (UKT)
            </a>
            <a class="dropdown-item" href="{{ route('root.admission.faq') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-circle-question text-secondary"></i></span>
                FAQ Penerimaan
            </a>
        </div>
    </li>
    {{-- ─── Kemahasiswaan ────────────────────────────────────── --}}
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#navbar-kemahasiswaan" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                    <path d="M5 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                    <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    <path d="M21 21v-2a4 4 0 0 0 -3 -3.85" />
                </svg>
            </span>
            <span class="nav-link-title">Kemahasiswaan</span>
        </a>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('root.kemahasiswaan.organisasi') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-people-group text-primary"></i></span>
                Organisasi Mahasiswa
            </a>
            <a class="dropdown-item" href="{{ route('landing.beasiswa') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-award text-warning"></i></span>
                Beasiswa
            </a>
            <a class="dropdown-item" href="{{ route('root.kemahasiswaan.prestasi') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-trophy text-success"></i></span>
                Prestasi Mahasiswa
            </a>
            <a class="dropdown-item" href="{{ route('root.kemahasiswaan.layanan') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-hands-helping text-info"></i></span>
                Layanan Mahasiswa
            </a>
            <a class="dropdown-item" href="{{ route('root.alumni.index') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-user-graduate text-secondary"></i></span>
                Alumni
            </a>
        </div>
    </li>
    {{-- ─── Institusi ────────────────────────────────────────── --}}
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#navbar-institusi" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                    <path d="M3 21h18" />
                    <path d="M19 21v-4" />
                    <path d="M19 17a2 2 0 0 0 2 -2v-2a2 2 0 1 0 -4 0v2a2 2 0 0 0 2 2z" />
                    <path d="M14 21v-14a3 3 0 0 0 -3 -3h-4a3 3 0 0 0 -3 3v14" />
                    <path d="M9 17v4" />
                    <path d="M8 13h2" />
                    <path d="M8 9h2" />
                </svg>
            </span>
            <span class="nav-link-title">Institusi</span>
        </a>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('root.institusi.profil') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-building-columns text-primary"></i></span>
                Profil Kampus
            </a>
            <a class="dropdown-item" href="{{ route('root.institusi.visi-misi') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-bullseye text-danger"></i></span>
                Visi &amp; Misi
            </a>
            <a class="dropdown-item" href="{{ route('root.institusi.struktur') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-sitemap text-info"></i></span>
                Struktur Organisasi
            </a>
            <a class="dropdown-item" href="{{ route('root.institusi.fasilitas') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-door-open text-success"></i></span>
                Fasilitas &amp; Sarana
            </a>
            <a class="dropdown-item" href="{{ route('root.institusi.akreditasi') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-certificate text-warning"></i></span>
                Akreditasi
            </a>
            <a class="dropdown-item" href="{{ route('root.institusi.kerjasama') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-handshake text-secondary"></i></span>
                Kerjasama
            </a>
        </div>
    </li>
    {{-- ─── Publikasi ────────────────────────────────────────── --}}
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#navbar-publikasi" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                    <path d="M6 4h11a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-11a1 1 0 0 1 -1 -1v-14a1 1 0 0 1 1 -1m3 0v18" />
                    <path d="M13 8l2 0" />
                    <path d="M13 12l2 0" />
                </svg>
            </span>
            <span class="nav-link-title">Publikasi</span>
        </a>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('root.publication.announcements') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-bullhorn text-danger"></i></span>
                Pengumuman
            </a>
            <a class="dropdown-item" href="{{ route('root.faq') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-circle-question text-info"></i></span>
                Pusat Bantuan &amp; FAQ
            </a>
            <a class="dropdown-item" href="{{ route('root.publication.news') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-newspaper text-primary"></i></span>
                Berita Kampus
            </a>
            <a class="dropdown-item" href="{{ route('root.publication.galeri') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-images text-success"></i></span>
                Galeri
            </a>
            <a class="dropdown-item" href="{{ route('root.publication.agenda') }}">
                <span class="dropdown-item-icon"><i class="fa-solid fa-calendar-check text-warning"></i></span>
                Agenda &amp; Kegiatan
            </a>
        </div>
    </li>
    {{-- ─── Kontak ───────────────────────────────────────────── --}}
    <li class="nav-item">
        <a class="nav-link" href="{{ route('root.kontak') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                    <path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z" />
                    <path d="M3 7l9 6l9 -6" />
                </svg>
            </span>
            <span class="nav-link-title">Kontak</span>
        </a>
    </li>
</ul>