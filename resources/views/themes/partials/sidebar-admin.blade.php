<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.*') ? 'show' : '' }}" href="#navbar-referensi" data-bs-toggle="dropdown"
        data-bs-auto-close="outside" role="button" aria-expanded="{{ request()->routeIs($activeRole . '.referensi*') ? 'true' : 'false' }}">
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-1">
                <path d="M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0" />
                <path d="M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0" />
                <path d="M3 6l0 13" />
                <path d="M12 6l0 13" />
                <path d="M21 6l0 13" />
            </svg>
        </span>
        <span class="nav-link-title">Dashboard</span>
    </a>
    <div class="dropdown-menu {{ request()->routeIs('dashboard.*') ? 'show' : '' }}">
        <div class="dropdown-menu-columns">
            <div class="dropdown-menu-column">
                <a class="dropdown-item {{ request()->routeIs($activeRole.'.dashboard-infra') ? 'active' : '' }}" href="{{ route($activeRole.'.dashboard-infra') }}">Dashboard Infrastruktur</a>
                <a class="dropdown-item {{ request()->routeIs($activeRole.'.dashboard-referensi') ? 'active' : '' }}" href="{{ route($activeRole.'.dashboard-referensi') }}">Dashboard Referensi</a>
            </div>
        </div>
    </div>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs($activeRole.'.profile-index') ? 'active' : '' }}" href="{{ route($activeRole.'.profile-index') }}">
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-1">
                <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                <path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                <path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" />
            </svg>
        </span>
        <span class="nav-link-title">Profile</span>
    </a>
</li>
    <li class="nav-item">
        <a class="nav-link" href="#">
            <span class="nav-link-title">Master Data</span>
        </a>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ request()->routeIs($activeRole . '.referensi*') ? 'show' : '' }}" href="#navbar-referensi" data-bs-toggle="dropdown"
            data-bs-auto-close="outside" role="button" aria-expanded="{{ request()->routeIs($activeRole . '.referensi*') ? 'true' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-replace-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 11v-3c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-6m0 0l3 3m-3 -3l3 -3" /><path d="M3 13.013v3c0 .53 .211 1.039 .586 1.414c.375 .375 .884 .586 1.414 .586h6m0 0l-3 -3m3 3l-3 3" /><path d="M16 16.502c0 .53 .211 1.039 .586 1.414c.375 .375 .884 .586 1.414 .586c.53 0 1.039 -.211 1.414 -.586c.375 -.375 .586 -.884 .586 -1.414c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414z" /><path d="M4 4.502c0 .53 .211 1.039 .586 1.414c.375 .375 .884 .586 1.414 .586c.53 0 1.039 -.211 1.414 -.586c.375 -.375 .586 -.884 .586 -1.414c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414z" /><path d="M21 21.499c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414" /><path d="M9 9.499c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414" /></svg>
            </span>
            <span class="nav-link-title">Data Pengguna</span>
        </a>
        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.referensi*') ? 'show' : '' }}">
            <div class="dropdown-menu-columns">
                <div class="dropdown-menu-column">
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.users.user*') ? 'active' : '' }}" href="{{ route($activeRole . '.users.user-index') }}">User</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.users.role*') ? 'active' : '' }}" href="{{ route($activeRole . '.users.role-index') }}">Role</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.users.subrole*') ? 'active' : '' }}" href="#">Subrole ( Soon )</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.users.alamat*') ? 'active' : '' }}" href="{{ route($activeRole . '.users.alamat-index') }}">Alamat</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.users.keluarga*') ? 'active' : '' }}" href="{{ route($activeRole . '.users.keluarga-index') }}">Keluarga</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.users.pendidikan*') ? 'active' : '' }}" href="{{ route($activeRole . '.users.pendidikan-index') }}">Pendidikan</a>
                </div>
            </div>
        </div>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ request()->routeIs($activeRole . '.referensi*') ? 'show' : '' }}" href="#navbar-referensi" data-bs-toggle="dropdown"
            data-bs-auto-close="outside" role="button" aria-expanded="{{ request()->routeIs($activeRole . '.referensi*') ? 'true' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-1">
                    <path d="M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0" />
                    <path d="M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0" />
                    <path d="M3 6l0 13" />
                    <path d="M12 6l0 13" />
                    <path d="M21 6l0 13" />
                </svg>
            </span>
            <span class="nav-link-title">Data Referensi</span>
        </a>
        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.referensi*') ? 'show' : '' }}">
            <div class="dropdown-menu-columns">
                <div class="dropdown-menu-column">
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.referensi.agama*') ? 'active' : '' }}" href="{{ route($activeRole . '.referensi.agama-index') }}">Agama</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.referensi.golongan-darah*') ? 'active' : '' }}" href="{{ route($activeRole . '.referensi.golongan-darah-index') }}">Golongan Darah</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.referensi.jenis-kelamin*') ? 'active' : '' }}" href="{{ route($activeRole . '.referensi.jenis-kelamin-index') }}">Jenis Kelamin</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.referensi.kewarganegaraan*') ? 'active' : '' }}" href="{{ route($activeRole . '.referensi.kewarganegaraan-index') }}">Kewarganegaraan</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.referensi.semester*') ? 'active' : '' }}" href="{{ route($activeRole . '.referensi.semester-index') }}">Semester</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.referensi.status-mahasiswa*') ? 'active' : '' }}" href="{{ route($activeRole . '.referensi.status-mahasiswa-index') }}">Status Mahasiswa</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.referensi.jabatan*') ? 'active' : '' }}" href="{{ route($activeRole . '.referensi.jabatan-index') }}">Jabatan</a>
                </div>
            </div>
        </div>
    </li>

    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ request()->routeIs($activeRole . '.infra*', $activeRole . '.inventaris*', $activeRole . '.transaksi-barang*', $activeRole . '.perawatan*') ? 'show' : '' }}" href="#navbar-infra" data-bs-toggle="dropdown" data-bs-auto-close="outside"
            role="button" aria-expanded="{{ request()->routeIs($activeRole . '.infra*', $activeRole . '.inventaris*', $activeRole . '.transaksi-barang*', $activeRole . '.perawatan*') ? 'true' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" class="icon icon-1">
                    <path d="M3 12h1l3 8l3 -16l3 16l3 -8h4" />
                    <path d="M4 4l1 1" />
                    <path d="M18 5l1 -1" />
                    <path d="M17 10l1 1" />
                    <path d="M13 10l1 1" />
                    <path d="M9 10l1 1" />
                    <path d="M5 10l1 1" />
                </svg>
            </span>
            <span class="nav-link-title">Master Infrastruktur</span>
        </a>
        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.infra*', $activeRole . '.inventaris*', $activeRole . '.transaksi-barang*', $activeRole . '.perawatan*') ? 'show' : '' }}">
            <div class="dropdown-menu-columns">
                <div class="dropdown-menu-column">
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.infra.gedung*') ? 'active' : '' }}" href="{{ route($activeRole . '.infra.gedung-index') }}">Data Gedung</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.infra.ruangan*') ? 'active' : '' }}" href="{{ route($activeRole . '.infra.ruangan-index') }}">Data Ruangan</a>
                    <div class="dropend">
                        <a class="dropdown-item dropdown-toggle {{ request()->routeIs($activeRole . '.inventaris*') ? 'show' : '' }}" href="#sidebar-inventaris"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button"
                            aria-expanded="{{ request()->routeIs($activeRole . '.inventaris*') ? 'true' : 'false' }}">
                            Data Inventaris
                        </a>
                        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.inventaris*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.inventaris.kategori-barang*') ? 'active' : '' }}" href="{{ route($activeRole . '.inventaris.kategori-barang-index') }}">Kategori Barang</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.inventaris.barang*') ? 'active' : '' }}" href="{{ route($activeRole . '.inventaris.barang-index') }}">Barang</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.inventaris.barang-inventaris*') ? 'active' : '' }}" href="{{ route($activeRole . '.inventaris.barang-inventaris-index') }}">Barang Inventaris</a>
                        </div>
                    </div>
                    <div class="dropend">
                        <a class="dropdown-item dropdown-toggle {{ request()->routeIs($activeRole . '.transaksi-barang*') ? 'show' : '' }}" href="#sidebar-transaksi-barang"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button"
                            aria-expanded="{{ request()->routeIs($activeRole . '.transaksi-barang*') ? 'true' : 'false' }}">
                            Data Transaksi Barang
                        </a>
                        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.transaksi-barang*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.transaksi-barang.peminjaman*') ? 'active' : '' }}" href="{{ route($activeRole . '.transaksi-barang.peminjaman-index') }}">Peminjaman</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.transaksi-barang.pengecekan*') ? 'active' : '' }}" href="{{ route($activeRole . '.transaksi-barang.pengecekan-index') }}">Pengecekan</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.transaksi-barang.pengajuan*') ? 'active' : '' }}" href="{{ route($activeRole . '.transaksi-barang.pengajuan-index') }}">Pengajuan Perbaikan</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.transaksi-barang.riwayat*') ? 'active' : '' }}" href="{{ route($activeRole . '.transaksi-barang.riwayat-index') }}">Riwayat Perbaikan</a>
                        </div>
                    </div>
                    <div class="dropend">
                        <a class="dropdown-item dropdown-toggle {{ request()->routeIs($activeRole . '.perawatan*') ? 'show' : '' }}" href="#sidebar-perawatan"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button"
                            aria-expanded="{{ request()->routeIs($activeRole . '.perawatan*') ? 'true' : 'false' }}">
                            Data Perawatan
                        </a>
                        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.perawatan*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.perawatan.jadwal*') ? 'active' : '' }}" href="{{ route($activeRole . '.perawatan.jadwal-index') }}">Jadwal Pemeliharaan</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.perawatan.histori*') ? 'active' : '' }}" href="{{ route($activeRole . '.perawatan.histori-index') }}">Histori Pemeliharaan</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </li>



    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ request()->routeIs('akademik*') ? 'show' : '' }}" href="#navbar-akademik" data-bs-toggle="dropdown" data-bs-auto-close="outside"
            role="button" aria-expanded="{{ request()->routeIs('akademik*') ? 'true' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" class="icon icon-1">
                    <path d="M3 12h1l3 8l3 -16l3 16l3 -8h4" />
                    <path d="M4 4l1 1" />
                    <path d="M18 5l1 -1" />
                    <path d="M17 10l1 1" />
                    <path d="M13 10l1 1" />
                    <path d="M9 10l1 1" />
                    <path d="M5 10l1 1" />
                </svg>
            </span>
            <span class="nav-link-title">Master Akademik</span>
        </a>
        <div class="dropdown-menu {{ request()->routeIs('akademik*') ? 'show' : '' }}">
            <div class="dropdown-menu-columns">
                <div class="dropdown-menu-column">
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.tahun-akademik*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.tahun-akademik-index') }}">Tahun Akademik</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.fakultas*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.fakultas-index') }}">Fakultas</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.program-studi*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.program-studi-index') }}">Program Studi</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.kurikulum-*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.kurikulum-index') }}">Kurikulum</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.matakuliah-*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.matakuliah-index') }}">Mata Kuliah</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.kelas-perkuliahan*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.kelas-perkuliahan-index') }}">Kelas Perkuliahan</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.jadwal-perkuliahan*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.jadwal-perkuliahan-index') }}">Jadwal Perkuliahan</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole. '.akademik.kelas-mahasiswa*') ? 'active' : '' }}" href="{{ route($activeRole. '.akademik.kelas-mahasiswa-index') }}">Kelas Mahasiswa</a>
                </div>
            </div>
        </div>
    </li>

    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ request()->routeIs($activeRole . '.akademik.komponen-penilaian*', $activeRole . '.akademik.jenis-tugas*', $activeRole . '.akademik.penugasan*', $activeRole . '.akademik.pengumpulan-tugas*', $activeRole . '.akademik.nilai-akhir*', $activeRole . '.akademik.ipk*', $activeRole . '.akademik.konversi-nilai*') ? 'show' : '' }}" href="#navbar-penilaian" data-bs-toggle="dropdown" data-bs-auto-close="outside"
            role="button" aria-expanded="{{ request()->routeIs($activeRole . '.akademik.komponen-penilaian*', $activeRole . '.akademik.jenis-tugas*', $activeRole . '.akademik.penugasan*', $activeRole . '.akademik.pengumpulan-tugas*', $activeRole . '.akademik.nilai-akhir*', $activeRole . '.akademik.ipk*', $activeRole . '.akademik.konversi-nilai*') ? 'true' : 'false' }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" class="icon icon-1">
                    <path d="M9 11l3 3L22 4" />
                    <path d="M21 12v7a2 2 0 0 1 -2 2H5a2 2 0 0 1 -2 -2V5a2 2 0 0 1 2 -2h11" />
                </svg>
            </span>
            <span class="nav-link-title">Tugas & Penilaian</span>
        </a>
        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.akademik.komponen-penilaian*', $activeRole . '.akademik.jenis-tugas*', $activeRole . '.akademik.penugasan*', $activeRole . '.akademik.pengumpulan-tugas*', $activeRole . '.akademik.nilai-akhir*', $activeRole . '.akademik.ipk*', $activeRole . '.akademik.konversi-nilai*') ? 'show' : '' }}">
            <div class="dropdown-menu-columns">
                <div class="dropdown-menu-column">
                    <div class="dropend">
                        <a class="dropdown-item dropdown-toggle {{ request()->routeIs($activeRole . '.akademik.komponen-penilaian*', $activeRole . '.akademik.jenis-tugas*', $activeRole . '.akademik.konversi-nilai*') ? 'show' : '' }}" href="#sidebar-setup-penilaian"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button"
                            aria-expanded="{{ request()->routeIs($activeRole . '.akademik.komponen-penilaian*', $activeRole . '.akademik.jenis-tugas*', $activeRole . '.akademik.konversi-nilai*') ? 'true' : 'false' }}">
                            Setup Penilaian
                        </a>
                        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.akademik.komponen-penilaian*', $activeRole . '.akademik.jenis-tugas*', $activeRole . '.akademik.konversi-nilai*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.akademik.komponen-penilaian*') ? 'active' : '' }}" href="{{ route($activeRole . '.akademik.komponen-penilaian-index') }}">Komponen Penilaian</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.akademik.jenis-tugas*') ? 'active' : '' }}" href="{{ route($activeRole . '.akademik.jenis-tugas-index') }}">Jenis Tugas</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.akademik.konversi-nilai*') ? 'active' : '' }}" href="{{ route($activeRole . '.akademik.konversi-nilai-index') }}">Konversi Nilai</a>
                        </div>
                    </div>
                    <div class="dropend">
                        <a class="dropdown-item dropdown-toggle {{ request()->routeIs($activeRole . '.akademik.penugasan*', $activeRole . '.akademik.pengumpulan-tugas*') ? 'show' : '' }}" href="#sidebar-tugas"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button"
                            aria-expanded="{{ request()->routeIs($activeRole . '.akademik.penugasan*', $activeRole . '.akademik.pengumpulan-tugas*') ? 'true' : 'false' }}">
                            Manajemen Tugas
                        </a>
                        <div class="dropdown-menu {{ request()->routeIs($activeRole . '.akademik.penugasan*', $activeRole . '.akademik.pengumpulan-tugas*') ? 'show' : '' }}">
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.akademik.penugasan*') ? 'active' : '' }}" href="{{ route($activeRole . '.akademik.penugasan-index') }}">Penugasan</a>
                            <a class="dropdown-item {{ request()->routeIs($activeRole . '.akademik.pengumpulan-tugas*') ? 'active' : '' }}" href="{{ route($activeRole . '.akademik.pengumpulan-tugas-index') }}">Pengumpulan Tugas</a>
                        </div>
                    </div>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.akademik.nilai-akhir*') ? 'active' : '' }}" href="{{ route($activeRole . '.akademik.nilai-akhir-index') }}">Nilai Akhir</a>
                    <a class="dropdown-item {{ request()->routeIs($activeRole . '.akademik.ipk*') ? 'active' : '' }}" href="{{ route($activeRole . '.akademik.ipk-index') }}">IPK & Transkrip</a>
                </div>
            </div>
        </div>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="#">
            <span class="nav-link-title">Lainnya</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs($activeRole . '.pengaturan-index') ? 'active' : '' }}" href="{{ route($activeRole . '.pengaturan-index') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" class="icon icon-1">
                    <path
                        d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" />
                    <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                </svg>
            </span>
            <span class="nav-link-title">Pengaturan</span>
        </a>
    </li>