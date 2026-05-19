# NexaCampus

NexaCampus adalah Sistem Informasi Akademik Perguruan Tinggi (SIAKAD PT) berbasis Laravel 12 dan Livewire 4 untuk mengelola operasional kampus dari sisi akademik, pembelajaran, publikasi, penerimaan mahasiswa baru, dan keuangan mahasiswa.

Platform ini dirancang sebagai aplikasi SIAKAD modern untuk universitas, sekolah tinggi, institut, akademi, politeknik, dan lembaga pendidikan tinggi yang membutuhkan sistem kampus terpadu. NexaCampus membantu kampus mengelola data mahasiswa, dosen, program studi, tahun akademik, kurikulum, mata kuliah, KRS, jadwal kuliah, absensi, nilai, transkrip, materi pembelajaran, pengumuman, admission/PMB, invoice, pembayaran, cicilan, dan financial clearance dalam satu aplikasi web.

Dengan arsitektur modular, NexaCampus dapat digunakan sebagai starter sistem informasi akademik, sistem administrasi kampus, aplikasi manajemen perguruan tinggi, portal mahasiswa, portal dosen, dan dashboard admin kampus. Fokus utama proyek ini adalah menyediakan fondasi SIAKAD PT yang rapi, mudah dikembangkan, dan cocok untuk kebutuhan digitalisasi proses akademik maupun operasional kampus di Indonesia.

Demo tersedia di:

https://nexacampus.idev-fun.org

## Akun Demo

| Role | Email | Password |
| --- | --- | --- |
| Superuser | `superuser@example.com` | `admin123` |
| Dosen | `lecturer@example.com` | `lecturer123` |
| Mahasiswa | `student@example.com` | `student123` |

Setelah login, pilih role yang ingin digunakan bila akun memiliki lebih dari satu role.

## Fitur Utama

### Akademik

- Setup wizard untuk konfigurasi awal sistem, kampus, dan admin.
- Manajemen pengguna, role, permission, menu, pengaturan sistem, dan activity log.
- Master data akademik: tahun akademik, fakultas, program studi, mata kuliah, kurikulum, gedung, dan ruangan.
- Registrasi mahasiswa per tahun akademik dan periode akademik.
- KRS/study plan, detail mata kuliah, validasi SKS, dan status persetujuan.
- Penawaran kelas, dosen pengampu, jadwal kuliah, dan sesi pertemuan.
- Absensi mahasiswa per sesi kuliah.
- Penilaian mahasiswa dengan komponen nilai, bobot, final score, grade, dan status finalisasi.
- Transkrip, hasil studi semester, IPS/IPK, dan sinkronisasi snapshot nilai terbaik.

### Pembelajaran

- Manajemen materi kuliah untuk dosen.
- Upload banyak file per materi dengan kategori lampiran.
- Preview PDF, gambar, dan link eksternal.
- YouTube/video embed pada materi.
- Bookmark materi oleh mahasiswa.
- Tracking download berbasis student profile.
- Diskusi materi dengan komentar bertingkat.
- Like pada materi dan komentar.
- Halaman materi khusus mahasiswa dan dosen dengan tampilan modern.

### Pengumuman

- Pengumuman global dan targeted berdasarkan fakultas, program studi, course offering, dosen, atau mahasiswa.
- Level prioritas normal, penting, dan urgent.
- Rich text editor untuk konten pengumuman.
- Auto mark-as-read dan unread badge.
- Halaman pengumuman untuk admin, dosen, dan mahasiswa.
- Widget pengumuman pada dashboard dosen dan mahasiswa.

### Grade Book & Export

- Halaman grade book khusus dosen.
- Statistik nilai: total mahasiswa, rata-rata, pass rate, nilai tertinggi, nilai terendah, dan jumlah nilai yang sudah diisi.
- Filter berdasarkan course offering, academic year, semester, dan pencarian mahasiswa/NIM/mata kuliah.
- Admin grade table berbasis PowerGrid.
- Export nilai ke CSV, Excel, dan PDF.
- PDF report dengan ringkasan statistik.

### Admission

- Manajemen admission period/intake dengan academic year binding.
- Public application form untuk calon mahasiswa.
- Applicant portal berbasis token tanpa membuat role applicant.
- Status check menggunakan application number dan email.
- Upload dan update dokumen pendaftaran.
- Konfigurasi document requirement per period/program.
- Secure document preview untuk admin dan applicant portal.
- Review aplikasi oleh admin, verifikasi dokumen, status history, final score, dan review notes.
- Email notification untuk submit aplikasi dan perubahan status.
- Exam/interview schedule, participant assignment, attendance, dan score input.
- Selection dashboard dengan ranking, quota usage, waitlist, accept/reject, dan bulk decision.
- Konversi applicant diterima menjadi user dan student profile.
- Flexible NIM generation rule dengan token dan sequence scope.
- Acceptance letter PDF dan welcome notification.

### Keuangan Mahasiswa

- Tuition fee template per academic year, study program, dan semester.
- Student invoice berbasis `student_profile_id`.
- Invoice item sebagai snapshot rincian tagihan.
- Custom/manual invoice untuk satu mahasiswa, pilihan mahasiswa tertentu, atau bulk mahasiswa aktif.
- Invoice type: tuition, custom, admission, registration, graduation, exam, library fine, certificate, dan other.
- Draft/issued/partially paid/paid/overdue/cancelled invoice lifecycle.
- Student invoice list dan detail di sisi mahasiswa.
- Manual payment proof upload dari mahasiswa.
- Admin payment verification dan rejection.
- Inline preview bukti pembayaran.
- Payment history dan receipt PDF.
- Pengajuan cicilan dari mahasiswa dengan simulasi tenor.
- Approval/rejection cicilan oleh finance/admin.
- Jadwal cicilan dan pembayaran sesuai installment.
- Financial clearance policies dari dashboard admin.
- Financial holds untuk memblokir atau memberi warning pada workflow akademik tertentu.
- Global warning banner pada halaman mahasiswa jika ada tagihan overdue.
- Temporary dispensation/waiver dan release hold oleh admin.
- Middleware financial clearance yang membaca mapping route dari konfigurasi.
- Dashboard financial khusus admin untuk memantau revenue, outstanding, pending payment, overdue invoice, financial hold, invoice schedule, student credit, dan scholarship dari satu halaman.
- Invoice schedule untuk menerbitkan invoice otomatis pada waktu yang ditentukan admin.
- Scheduled overdue refresh dan financial hold evaluation agar status tagihan/hold tetap sinkron walau tidak sedang dibuka manual.
- Email notification untuk invoice terbit, payment verified/rejected, invoice overdue, dan cicilan approved/rejected.

## Peran dan Akses

- **Superuser/Admin**: mengelola data akademik, publikasi, admission, financial, permission, menu, dan sistem.
- **Dosen**: mengelola kelas, materi, absensi, nilai, grade book, dan pengumuman dosen.
- **Mahasiswa**: melihat dashboard, registrasi, KRS, jadwal, materi, pengumuman, nilai, transkrip, invoice, pembayaran, dan cicilan.

## Arsitektur Singkat

- Laravel 12 dan Livewire 4.
- PowerGrid untuk tabel admin.
- Spatie Permission untuk role dan permission.
- Activity Log untuk audit perubahan data.
- Dompdf untuk PDF report, acceptance letter, dan receipt.
- PhpSpreadsheet untuk export Excel.
- PWA support via erag/laravel-pwa.
- Vite dan Tailwind CSS untuk build frontend.
- Resource registry di `config/resources.php` untuk menyatukan CRUD, permission, dan menu.

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

### Scheduler Production

Untuk menjalankan automation keuangan seperti invoice schedule, overdue refresh, financial hold evaluation, dan email reminder, pastikan Laravel Scheduler aktif di server:

```bash
* * * * * cd /path/to/nexacampus && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Command automation yang dijalankan scheduler:

```bash
php artisan financial:run-invoice-schedules
php artisan financial:refresh-overdue
php artisan financial:evaluate-holds
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

- Setup wizard tersedia di `/welcome`.
- Login tersedia di `/auth/login` setelah sistem terpasang.
- Public admission form tersedia di `/admission/apply`.
- Applicant status check tersedia di `/admission/status`.
