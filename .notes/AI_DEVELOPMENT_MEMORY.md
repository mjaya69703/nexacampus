# NexaCampus - AI Development Memory & Structural Patterns

> **Tujuan Dokumen:** Referensi struktural kilat (`Memory & Skills`) untuk AI yang baru memulai sesi percakapan agar langsung memahami spesifikasi teknis, pola pemanggilan layanan, dan konvensi penataan file di proyek NexaCampus.

---

## 1. Golden Rules untuk AI Development
1. **Source of Truth Utama:** `AI_CONTEXT.md` (di root) adalah dokumen induk. `.notes/has-been-implemented.md` dan `.notes/will-be-implemented.md` adalah tracker status pengerjaan fitur.
2. **Frontend Utama = Inertia React:** Semua modul admin — termasuk Financial — memakai **Inertia.js 3 + React 19** (`resources/js/pages/Admin/**`). **Livewire 4 + Blade + PowerGrid hanya tersisa untuk setup wizard** (legacy). Jangan membuat PowerGrid/Blade untuk fitur admin baru.
3. **Modular by Domain:** Jangan pernah mencampur file lintas domain tanpa alasan arsitektural yang jelas.
   - **Domain Aktif:** `Academic`, `Access`, `Admission`, `Alumni`, `Campus`, `Financial`, `Organization`, `Publication`, `Settings`, `StudentService`, `System`.
   - **Mirror Directory Structure:** Setiap domain memiliki folder terdedikasi di:
     - Models: `app/Models/[Domain]/[Model].php`
     - Controller admin: `app/Http/Controllers/Admin/[Domain]/[Resource]Controller.php`
     - Halaman React: `resources/js/pages/Admin/[Domain]/[Resources]/Index.tsx` & `Form.tsx`
     - Services & Support: `app/Support/[Domain]/[Service].php`
     - Controllers portal/export: `app/Http/Controllers/[Domain]/[Controller].php`
     - Automated Tests: `tests/Feature/[Domain]/[TestName]Test.php`
     - Legacy Blade (auth/setup wizard): `resources/views/components/{auth,admission}/⚡[page].blade.php`

---

## 1b. Pola CRUD Admin Inertia React (Ringkas)
1. **Config** → entry di `config/resources.php` (menu + permission).
2. **Route** → skip plural di loop `ResourceRegistry` (`routes/web/admin.php`), lalu route eksplisit dengan `active_permission:*` dan nama `admin.[area].[plural].[action]`.
3. **Controller** → `Inertia::render('Admin/[Area]/[Resources]/Index', [...])` + `ShellProps::make($user, $pretitle, $pageTitle)`.
   - Kirim `can` via `ActivePermission::check()`, `data.rows` yang sudah dimapping, `filters`, dan `urls`.
   - Aksi tulis mengembalikan `redirect()->route(...)->with('success', ...)`.
   - `destroy`/`bulkDestroy` wajib punya guard relasi bisnis.
4. **React** → gunakan `AdminShell`, `CrudHero`, `CrudTable`, `FilterPanel`, `ConfirmModal`, dan field dari `components/Shared/Crud/fields.tsx`.
   - Form memakai `useForm`; submit `post`/`put`; batal lewat `FormActions`.
   - Jangan hardcode warna terang; pakai variabel `--db-*`.
5. **Test** → `assertInertia(...)` untuk komponen, data, filter, permission, create/update, guard delete.
6. **Sync** → `php artisan resources:sync`.
7. **Verifikasi** → `npm run typecheck`, `npm run build`, lalu test domain.

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
- **Test Halaman Inertia:** Gunakan `assertInertia(fn (Assert $page) => $page->component('Admin/[Area]/[Resources]/Index')->has('data.rows', ...))`; sertakan assertion `can`, filter, create/update, guard delete, dan bulk delete.

---

## 4. Perintah Kilat AI
- **Run All Tests:** `php artisan test` (Lulus 100% pada **798 test / 7.973 assertions**)
- **Run Single Domain Test:** `php artisan test tests/Feature/[Domain]`
- **Run Single Test:** `php artisan test --filter=[TestName]`
- **Type Check React:** `npm run typecheck`
- **Build Asset:** `npm run build`
- **Sync CRUD Resource Menu:** `php artisan resources:sync`
- **Check Migrations Status:** `php artisan migrate:status`

---

## 5. Arsitektur Global Dark Mode & UI Styling Rules
1. **Source of Truth Dark Mode:** `resources/css/app.css` (Section 1 - 5) menangani normalisasi otomatis dark mode untuk atribut `[data-bs-theme=dark]`.
2. **Standard UI Card Classes:** Gunakan `.card`, `.modern-card`, `.stat-card`, `.course-card`, `.material-card`, `.grade-card`, `.advisor-card`, `.service-card` untuk elemen kontainer agar otomatis mengikuti aturan CSS global.
3. **Halaman React:** Gunakan `db-*`/`crud-*` dan variabel `var(--db-card)`, `var(--db-line)`, `var(--db-heading)`, `var(--db-muted)`, `var(--db-brand)`. Jangan menulis background/hex terang inline.
4. **Larangan Keras:** **DILARANG KERAS** menggunakan *hardcoded background* terang (`background: white;`, `#fff`, `#f8fafc`) pada `<style>` atau `@push('styles')` di dalam komponen Blade tanpa menambahkan aturan override `[data-bs-theme=dark]` dengan `!important`.
5. **Asset Build:** Setiap modifikasi CSS/UI wajib dikompilasi dengan `npm run build`.
