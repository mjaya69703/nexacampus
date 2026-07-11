# NexaCampus

NexaCampus adalah Sistem Informasi Akademik Perguruan Tinggi (SIAKAD PT) berbasis Laravel 12 dan Livewire 4 untuk mengelola operasional kampus dari sisi akademik, pembelajaran, publikasi, penerimaan mahasiswa baru, keuangan mahasiswa, layanan mahasiswa, kepegawaian, dan pengawasan akademik.

Platform ini dirancang sebagai aplikasi SIAKAD modern untuk universitas, sekolah tinggi, institut, akademi, politeknik, dan lembaga pendidikan tinggi yang membutuhkan sistem kampus terpadu. NexaCampus membantu kampus mengelola data mahasiswa, dosen, pegawai, program studi, tahun akademik, kurikulum, mata kuliah, KRS, jadwal kuliah, absensi QR, nilai, transkrip, materi pembelajaran, tugas, pengumuman, admission/PMB, invoice, pembayaran, cicilan, layanan akademik mahasiswa, Tridharma, BKD, EDOM, dan financial clearance dalam satu aplikasi web.

Dengan arsitektur modular, NexaCampus dapat digunakan sebagai starter sistem informasi akademik, sistem administrasi kampus, aplikasi manajemen perguruan tinggi, portal mahasiswa, portal dosen, portal pegawai, portal pimpinan akademik, dan dashboard admin kampus. Fokus utama proyek ini adalah menyediakan fondasi SIAKAD PT yang rapi, mudah dikembangkan, dan cocok untuk kebutuhan digitalisasi proses akademik maupun operasional kampus di Indonesia.

Demo tersedia di:

https://nexacampus.idev-fun.org

## Akun Demo

| Role | Email | Password |
| --- | --- | --- |
| Superuser | `superuser@example.com` | `admin123` |
| Staff Kepegawaian | `staff@example.com` | `staff123` |
| Pimpinan Akademik Dekan | `dekan@example.com` | `academic123` |
| Pimpinan Akademik Kaprodi | `kaprodi@example.com` | `academic123` |
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
- Absensi mahasiswa per sesi kuliah dengan dukungan QR dinamis, check-in mandiri, dan rekap status hadir/izin/sakit/alpa.
- Penilaian mahasiswa dengan komponen nilai, bobot, final score, grade, dan status finalisasi.
- Transkrip, hasil studi semester, IPS/IPK, dan sinkronisasi snapshot nilai terbaik.
- Bimbingan akademik, catatan pembimbing, dan analitik progres studi mahasiswa.

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
- Tugas kuliah, lampiran tugas, pengumpulan mahasiswa, penilaian dosen, status history, dan export laporan tugas.
- Reminder deadline tugas melalui scheduler dan email.

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

### Layanan Mahasiswa

- Pengajuan surat layanan akademik dengan nomor otomatis, dokumen pendukung, status history, dan unduhan surat.
- Pengajuan cuti mahasiswa dengan integrasi invoice biaya layanan bila dibutuhkan.
- Pengajuan pindah program studi, kelas, atau fakultas dengan workflow persetujuan.
- Pengajuan yudisium/graduation dengan batch, policy, requirement dokumen, dan status review.
- Sistem pengaduan mahasiswa dengan kategori, lampiran, pesan tindak lanjut, assignment staff, dan dashboard operasional.
- Email notification untuk perubahan status layanan mahasiswa.

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
- Scholarship, student credit, invoice adjustment, dan export laporan keuangan.
- Scheduled overdue refresh dan financial hold evaluation agar status tagihan/hold tetap sinkron walau tidak sedang dibuka manual.
- Email notification untuk invoice terbit, payment verified/rejected, invoice overdue, dan cicilan approved/rejected.

### Notifikasi

- Template dan log notifikasi terpusat untuk event akademik, keuangan, admission, dan layanan mahasiswa.
- WhatsApp notification channel dengan provider official Meta Cloud API atau web session sidecar.
- Web Push/PWA notification channel berbasis VAPID, browser subscription per user, service worker, dan delivery log.
- Tombol opt-in notifikasi browser di topbar untuk perangkat yang mendukung Push API.
- Command generator VAPID key untuk konfigurasi environment production.

### Kepegawaian dan Organisasi

- Struktur organisasi kampus: unit kerja, jabatan organisasi, profil pegawai, dan penugasan jabatan.
- Scope jabatan berbasis fakultas, program studi, atau unit kerja untuk membatasi akses operasional.
- Approval engine dengan template, step approval, request, action, dan riwayat keputusan.
- Presensi pegawai dengan lokasi, sumber absensi, foto, jarak lokasi, check-in/check-out, dan peta lokasi.
- Cuti pegawai dengan tipe cuti, saldo, lampiran, approval, dan status request.
- Pengembangan pengguna/pegawai: training, sertifikasi, kegiatan pengembangan, lampiran, dan status validasi.
- Tridharma dosen: penelitian, pengabdian, penunjang, anggota, milestone, anggaran, output, lampiran, dan workflow validasi.

### BKD, EDOM, dan Pengawasan Akademik

- Periode BKD, aturan SKS, submission BKD, item breakdown, snapshot total beban kerja, dan export laporan.
- Portal dosen untuk menyiapkan draft BKD otomatis dari mengajar, jabatan, dan Tridharma; submit review; serta revisi saat diminta.
- Periode EDOM, pertanyaan evaluasi, survey mahasiswa per kelas/dosen, response unik, dan agregasi anonim.
- Lecturer performance review berbasis EDOM, kepatuhan mengajar, rubrik performa, dan catatan review.
- Portal academic leader dengan dashboard, daftar dosen dalam scope, kelas dan kehadiran, BKD dosen, EDOM dan performa, alert akademik, serta export laporan.
- Akses academic leader memakai role `academic-leader` dengan scope dari penugasan jabatan seperti Dekan, Kaprodi, atau Sekprodi.

## Peran dan Akses

- **Superuser/Admin**: mengelola data akademik, publikasi, admission, financial, layanan mahasiswa, kepegawaian/organisasi, permission, menu, dan sistem.
- **Staff/Employee**: mengelola self-service kepegawaian, presensi pegawai, cuti, dan data pengembangan sesuai permission.
- **Academic Leader**: memantau dosen, kelas, kehadiran, BKD, EDOM, performa, dan laporan sesuai scope jabatan akademik.
- **Dosen**: mengelola kelas, materi, tugas, absensi, nilai, grade book, Tridharma, BKD, EDOM agregat, dan pengumuman dosen.
- **Mahasiswa**: melihat dashboard, registrasi, KRS, jadwal, materi, tugas, pengumuman, nilai, transkrip, invoice, pembayaran, cicilan, layanan mahasiswa, dan survey EDOM.
- **Applicant**: mengakses formulir admission dan status aplikasi melalui portal token tanpa role login terpisah.

## Arsitektur Singkat

- Laravel 12 dan Livewire 4.
- PowerGrid untuk tabel admin.
- Spatie Permission untuk role dan permission.
- Activity Log untuk audit perubahan data.
- Approval engine internal untuk workflow review lintas modul.
- Position scope resolver untuk akses pimpinan akademik berbasis penugasan jabatan.
- Dompdf untuk PDF report, acceptance letter, receipt, service letter, dan workload report.
- PhpSpreadsheet untuk export Excel.
- Leaflet untuk tampilan peta dan validasi lokasi absensi.
- Private file preview/download controller untuk dokumen sensitif.
- PWA support via erag/laravel-pwa.
- Browser push notification via `minishlink/web-push` dan service worker di `public/sw.js`.
- Vite dan Tailwind CSS untuk build frontend.
- Resource registry di `config/resources.php` untuk menyatukan CRUD, permission, dan menu.
- Seeder modular per domain: academic, admission, financial, student service, dan organization.

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

Untuk menjalankan automation seperti invoice schedule, overdue refresh, financial hold evaluation, dan reminder tugas, pastikan Laravel Scheduler aktif di server:

```bash
* * * * * cd /path/to/nexacampus && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

Command automation yang dijalankan scheduler:

```bash
php artisan financial:run-invoice-schedules
php artisan financial:refresh-overdue
php artisan financial:evaluate-holds
php artisan academic:send-assignment-reminders
```

### Web Push Notifications

Generate VAPID keys:

```bash
php artisan notifications:web-push-vapid
```

Tambahkan hasilnya ke `.env`:

```dotenv
WEB_PUSH_VAPID_SUBJECT="${APP_URL}"
WEB_PUSH_VAPID_PUBLIC_KEY=
WEB_PUSH_VAPID_PRIVATE_KEY=
```

Lalu refresh konfigurasi:

```bash
php artisan optimize:clear
```

Aktifkan kanal Web Push dari `System Management > Pengaturan Sistem > Notifikasi`, kemudian user perlu klik tombol notifikasi di topbar dan memberi izin browser. Browser push membutuhkan `localhost` atau HTTPS; setelah update service worker, reload halaman atau unregister service worker lama dari DevTools bila notifikasi belum muncul.

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

## Lisensi

NexaCampus dilisensikan di bawah **GNU Affero General Public License v3.0 only** (`AGPL-3.0-only`).

Anda dapat menggunakan, mempelajari, memodifikasi, dan mendistribusikan proyek ini sesuai ketentuan AGPL v3. Jika Anda memodifikasi NexaCampus dan menyediakan akses kepada pengguna melalui jaringan, Anda wajib menawarkan source code yang sesuai kepada pengguna tersebut sebagaimana diatur oleh lisensi.

Copyright (C) 2026 NexaCampus contributors. Dependensi pihak ketiga tetap mengikuti lisensinya masing-masing.

Teks lisensi lengkap tersedia di [LICENSE](LICENSE).

## Catatan

- Setup wizard tersedia di `/welcome`.
- Login tersedia di `/auth/login` setelah sistem terpasang.
- Public admission form tersedia di `/admission/apply`.
- Applicant status check tersedia di `/admission/status`.
- Portal academic leader tersedia di `/academic-leader/dashboard`.
