---
name: pest-testing
description: Konvensi automated testing Pest PHP NexaCampus per domain bisnis. Gunakan saat membuat, mengubah, atau menjalankan test/feature test (kata kunci: test, testing, pest, phpunit, feature test, unit test).
---

# Testing NexaCampus (Pest PHP, Modular per Domain)

## Struktur

```
tests/Feature/
├── Academic/        # KRS, Nilai, QR Attendance, GradeAppeal
├── Admission/       # AdmissionEndToEndTest (NIM Generator, kuota, konversi maba)
├── Alumni/          # Tracer Study, Job Board, Event
├── Financial/       # FinancialClearanceEndToEndTest (invoice, hold, payment verification)
├── Organization/    # ApprovalEngine, Kepegawaian, Workload EDOM
├── StudentService/  # Cuti akademik, fee integration, status transition
└── System/
```

## Konvensi WAJIB

1. **Tanpa namespace deklarasi.** `tests/Pest.php` sudah bind `uses(TestCase::class, RefreshDatabase::class)->in('Feature')` rekursif. Cukup `<?php` + `use App\Models\...` langsung.
2. **Helper setup lokal** per file test — daftarkan roles + entitas inti, return via `compact()`:

```php
function financialTestSetup(): array
{
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', ...]);
    // ...
    return compact('faculty', ...);
}
```

3. **Integritas constraint DB:** kolom NOT NULL (`phone`, `birth_date`, `gender`, `address`) wajib terisi saat bikin data dummy. Untuk invoice: **wajib sertakan minimal satu `InvoiceItem`** agar `InvoiceAdjustmentService::totalWithAdjustments($invoice)` akurat.
4. Nama file: `{NamaFitur}Test.php`.

## Menjalankan Test

```bash
php artisan test                                        # semua domain
php artisan test --filter=AdmissionEndToEndTest         # satu test
php artisan test --filter=FinancialClearanceEndToEndTest
```

## Kapan Wajib Nulis Test

- Alur uang (invoice, payment, adjustment, hold/release) — prioritas tertinggi
- Transisi status penting (KRS, approval engine, konversi maba)
- Service baru di `app/Support/{Domain}/`
- Bug fix → tulis regression test yang gagal sebelum fix

## Referensi Contoh Nyata

Lihat test existing di `tests/Feature/{Domain}/` sebagai template gaya penulisan sebelum membuat test baru.
