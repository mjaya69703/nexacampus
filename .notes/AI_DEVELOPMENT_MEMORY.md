# NexaCampus - AI Development Memory & Structural Patterns

> **Tujuan Dokumen:** Referensi struktural kilat (`Memory & Skills`) untuk AI yang baru memulai sesi percakapan agar langsung memahami spesifikasi teknis, pola pemanggilan layanan, dan konvensi penataan file di proyek NexaCampus.

---

## 1. Golden Rules untuk AI Development
1. **Source of Truth Utama:** `AI_CONTEXT.md` (di root) adalah dokumen induk. `.notes/has-been-implemented.md` dan `.notes/will-be-implemented.md` adalah tracker status pengerjaan fitur.
2. **Modular by Domain:** Jangan pernah mencampur file lintas domain tanpa alasan arsitektural yang jelas.
   - **Domain Aktif:** `Academic`, `Access`, `Admission`, `Alumni`, `Campus`, `Financial`, `Organization`, `Publication`, `Settings`, `StudentService`, `System`.
   - **Mirror Directory Structure:** Setiap domain memiliki folder terdedikasi di:
     - Models: `app/Models/[Domain]/[Model].php`
     - Livewire Components: `app/Livewire/[Domain]/[Resource]Table.php` (ekstensi `BasePowerGridTable`)
     - Services & Support: `app/Support/[Domain]/[Service].php`
     - Controllers (hanya download/export/API): `app/Http/Controllers/[Domain]/[Controller].php`
     - Automated Tests: `tests/Feature/[Domain]/[TestName]Test.php`
     - Views: `resources/views/components/admin/[domain]/[resource]/⚡[action].blade.php`

---

## 2. Pola Layanan & Workflow Otomatis (Polymorphic Loops)

### A. Alur Penerimaan Mahasiswa Baru (Domain: `Admission`)
- **Generate NIM:** `app(NimGenerationService::class)->generateNim($application)`
  - Menggunakan token lowercase: `{year}`, `{yy}`, `{period_code}`, `{faculty_code}`, `{program_code}`, `{class_type}`, `{sequence}`.
  - Case-Sensitive: pastikan array `$tokens` di dalam `NimGenerationService` tidak dipanggil menggunakan huruf besar.
- **Konversi Maba:** `app(AdmissionConversionService::class)->convert($application, $actorId)`
  - Otomatis membuat `User` baru, menetapkan role `'student'` via Spatie Permission, membuat `StudentProfile`, `StudentRegistration`, dan memperbarui status aplikasi menjadi `'converted'`.

### B. Alur Tagihan & Pemblokiran Akademik (Domain: `Financial`)
- **Penghitungan Total Invoice:** `app(InvoiceAdjustmentService::class)->totalWithAdjustments($invoice)`
  - **PENTING:** Selalu hitung dari `$invoice->items()->sum('amount') + $invoice->adjustments()->sum('amount')`. Jika membuat invoice untuk pengujian, wajib membuat minimal satu `InvoiceItem`.
- **Evaluasi Hold & Release Otomatis:** `app(FinancialClearanceService::class)->evaluate($studentProfile)`
  - Memeriksa keterlambatan (`due_date < now()`) dan masa tenggang (`grace_days`). Jika menunggak, membuat `FinancialHold` aktif dengan `isBlocking() == true`.
  - Ketika pembayaran diverifikasi oleh finance (`app(PaymentProcessingService::class)->verify()`), sistem memanggil `refresh()` status invoice dan menjalankan `evaluate()` yang **secara otomatis melepas (release) hold keuangan** yang aktif.

### C. Alur Layanan Mahasiswa & Aktivasi Cuti (Domain: `StudentService`)
- **Pengajuan & Approval Engine:** Pengajuan cuti (`StudentLeaveApplicationService::create()`) otomatis memicu alur persetujuan via `ApprovalEngine` (`template code: STUDENT_LEAVE_REVIEW`).
- **Integrasi Tagihan Cuti:** Saat disetujui bersama biaya administrasi (`approveFromApproval()`), sistem membuat invoice khusus bertipe `'leave'`. Saat dibayar (`markFeePaid()`), status berganti menjadi `'approved'`.
- **Aktivasi Akademik Otomatis:** Ketika admin memanggil `activate($application)`, sistem secara otomatis mengubah `StudentProfile->academic_status` menjadi `'Cuti'`, menyetel `is_active = false`, dan membuat/memperbarui rekam jejak di `StudentRegistration`.

---

## 3. Konvensi Pengujian Otomatis (Pest Testing Suite)
- **Lokasi:** `tests/Feature/[Domain]/[TestName]Test.php`
- **Konfigurasi Pest (`tests/Pest.php`):** Sudah diatur untuk mengikat `uses(TestCase::class, RefreshDatabase::class)->in('Feature');` secara rekursif ke seluruh folder domain.
- **Tanpa Namespace Ekstra:** Jangan tambahkan `namespace Tests\Feature\[Domain];` pada file pengujian untuk menghindari konflik *namespace binding* dari Pest.
- **Setup Role & Integrity:** Di dalam fungsi `setup()` setiap test, selalu jalankan `Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);` dan lengkapi kolom non-null (seperti `phone`, `gender`, `birth_date`).

---

## 4. Perintah Kilat AI
- **Run All Tests:** `php artisan test` (Lulus 100% pada 73 test / 242 assertions)
- **Run Single Domain Test:** `php artisan test --filter=[TestName]`
- **Sync CRUD Resource Menu:** `php artisan app:sync-resources`
- **Check Migrations Status:** `php artisan migrate:status`
