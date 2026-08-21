---
name: verification-workflow
description: Checklist verifikasi wajib setelah selesai mengubah kode NexaCampus. Gunakan setiap kali selesai membuat fitur, memperbaiki bug, atau sebelum menganggap pekerjaan selesai (kata kunci: verifikasi, selesai, checklist, jalankan test, build).
---

# Checklist Verifikasi NexaCampus

Jalankan sesuai jenis perubahan. Jangan anggap pekerjaan selesai sebelum checklist relevan lolos.

## Setiap Selesai Ngoding (wajib)

```bash
php artisan test          # seluruh suite harus tetap hijau
```

Jika ada test gagal → perbaiki dulu, jangan lewati. Untuk bug fix: pastikan ada regression test.

## Jika Menyentuh Resource CRUD (`config/resources.php`, model baru, menu)

```bash
php artisan resources:sync
```

## Jika Menyentuh CSS / Aset Frontend (`resources/css/app.css`, js, scss)

```bash
npm run build
```

## Jika Membuat/Mengubah Migration

```bash
php artisan migrate           # pastikan jalan tanpa error
php artisan migrate:rollback  # pastikan down() juga benar (di env dev)
php artisan test              # RefreshDatabase akan re-run semua migrasi
```

## Jika Menyentuh Alur Keuangan / Nilai

- Wajib tulis/update test di `tests/Feature/Financial/` atau `tests/Feature/Academic/`
- Perhatikan counter denormalis (`paid_amount`, `outstanding_amount`) — harus konsisten setelah operasi

## Sanity Check Manual (opsional, untuk perubahan UI besar)

```bash
composer dev   # serve + queue:listen + vite dev concurrent
```

Cek: dark mode tidak "bocor putih", tidak ada tombol oversize/mini sembarangan, flash message muncul.
