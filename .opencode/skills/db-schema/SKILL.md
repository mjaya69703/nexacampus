---
name: db-schema
description: Konvensi skema database & migrasi NexaCampus. Gunakan saat membuat/mengubah migration, tabel, kolom, foreign key, index, atau relasi antar tabel (kata kunci: migration, migrasi, schema, tabel, kolom, FK, foreign key, index).
---

# Konvensi Database & Migration NexaCampus

## Struktur File

- Anonymous class: `return new class extends Migration`
- Multi-table per file untuk fitur besar: `create_{fitur}_phase_one/two_tables.php`
- `down()` = kebalikan persis `up()`; drop tabel **terbalik urutan** (child dulu)
- Migrasi alter: selalu `->after('kolom')`, drop index dulu baru `dropColumn([...])` batch
- Untuk fitur yang mungkin sudah ada (idempotent): bungkus dengan `if (! Schema::hasTable('...'))`

## Aturan Wajib Kolom

| Pattern | Aturan |
|---|---|
| PK | `$table->id()` |
| FK | `$table->foreignId('x_id')->constrained('tabel')->...` — JANGAN unsignedBigInteger terpisah |
| Audit trail | `created_by`, `updated_by`, (`deleted_by`) sebagai `foreignId()->nullable()->constrained('users')->nullOnDelete()` |
| Soft delete | `$table->softDeletes()` untuk semua tabel master/transaksi |
| Uang | `decimal('amount', 12, 2)` — konsisten di seluruh project |
| Boolean flag | `is_active` default true/false eksplisit |
| Timestamps | `$table->timestamps()` selalu |

## Semantik Delete FK (PILIH SESUAI MAKNANYA)

```php
->restrictOnDelete()   // data referensi/master (course, study_program) — jangan biarkan terhapus
->cascadeOnDelete()    // data anak milik parent (invoice_items, student_profiles→users)
->nullOnDelete()       // relasi opsional/historis (created_by, referensi tahun ajaran lama)
```

⚠️ **KEUANGAN:** tabel pembayaran/riwayat transaksi (`payments`, dll) HARUS `restrictOnDelete` dari invoice — riwayat pembayaran adalah dokumen legal, jangan cascade.

## Indexing

Selalu beri **nama eksplisit** dan ikuti access path query:

```php
$table->unique(['a', 'b', 'c'], 'nama_tabel_konteks_unique');
$table->index(['status', 'due_date'], 'nama_tabel_status_due_idx');  // suffix _idx
```

Pola umum: `(student_id, status)`, `(status, due_date)`, `(invoice_type, status)`.

## Status & Enum

- Modul lama pakai `enum` Title Case Indonesia (`'Aktif'`, `'Cuti'`) — pertahankan saat edit
- Modul baru cenderung `string` lowercase (`'draft'`, `'pending'`) + nilai validasi di level aplikasi
- Untuk status baru: gunakan PHP Enum backed string (lihat pattern `AnnouncementPriority`) + cast di model

## Counter Denormalis

Kolom seperti `registered_count`, `likes_count`, `paid_amount` rawan drift. Saat menulis kode yang mengubah data sumber, WAJIB update counter-nya juga (atau gunakan `withCount()` saat baca).

## Contoh Lengkap

Lihat file nyata sebagai referensi:
- Create multi-table: `database/migrations/2026_05_13_010000_create_financial_phase_one_tables.php`
- Alter + down rapi: `2026_06_04_020000_modernize_academic_attendance_for_qr_checkin.php`
- Idempotent guard: `2026_05_11_030000_create_admission_phase_two_tables.php`
