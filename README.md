# NexaCampus

NexaCampus adalah Sistem Informasi Akademik berbasis Laravel 12 dan Livewire 4. Aplikasi ini fokus pada pengelolaan data kampus, akademik, pembelajaran, absensi, penilaian, dan transkrip dalam satu platform terintegrasi.

## Ringkasan Fitur

- Setup wizard untuk konfigurasi awal sistem, kampus, dan admin.
- Manajemen akses: pengguna, role, dan permission.
- Master data akademik: tahun akademik, fakultas, program studi, mata kuliah, dan kurikulum.
- Registrasi mahasiswa per tahun akademik + periode akademik.
- KRS (study plan) dan detail mata kuliah yang diambil.
- Penawaran kelas, jadwal kuliah, dan dosen pengampu.
- Sesi absensi dan pencatatan absensi mahasiswa.
- Penilaian mahasiswa dengan komponen bobot dan status nilai.
- Transkrip dan snapshot hasil studi (IPS/IPK) per semester.
- Pengaturan sistem, menu, dan activity log.
- Data kampus seperti gedung dan ruangan.

## Peran dan Akses

- Admin/Superuser: akses penuh ke resource akademik, sistem, dan akses.
- Dosen: kelola kelas, absensi, dan input nilai mahasiswa.
- Mahasiswa: registrasi, KRS, jadwal, absensi, nilai, dan transkrip.

## Arsitektur Singkat

- Laravel 12 + Livewire 4 (UI reaktif tanpa JavaScript berat).
- PowerGrid untuk tabel data, Spatie Permission untuk otorisasi, dan Activity Log untuk audit.
- PWA aktif via erag/laravel-pwa.
- Vite + Tailwind CSS untuk build frontend.
- Resource registry di config/resources.php mengatur CRUD, permission, dan menu sekaligus.
- Menu sidebar tersusun dinamis dari database dan registry.

## Alur Akademik Inti

1. Admin menyiapkan tahun akademik, prodi, kurikulum, dan penawaran mata kuliah.
2. Mahasiswa melakukan registrasi dan mengisi KRS.
3. Dosen mengajar sesuai jadwal, membuka sesi absensi, dan mengisi nilai.
4. Sistem membangun snapshot hasil studi dan transkrip terbaik per mata kuliah.

## Menjalankan Proyek

### Setup awal

```bash
composer run setup
```

### Development

```bash
composer run dev
```

Atau jalankan manual:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

### Testing

```bash
composer run test
```

## Sinkronisasi Permission dan Menu

```bash
php artisan permissions:sync
php artisan menus:sync
php artisan resources:sync
```

## Catatan

- Setup wizard tersedia di /welcome.
- Login tersedia di /auth/login setelah sistem terpasang.
