---
name: permissions-auth
description: Sistem otorisasi active-role berbasis session NexaCampus. Gunakan saat bekerja dengan permission, role, middleware akses, ActivePermission, @activecan, atau proteksi route/halaman (kata kunci: permission, role, akses, authorize, middleware, activecan).
---

# Sistem Otorisasi NexaCampus (Active Role Berbasis Session)

## Konsep Kunci

User bisa punya banyak role. Saat login user memilih **active role** yang disimpan di `session('active_role')`. **Semua pengecekan permission dilakukan terhadap active role ini**, BUKAN semua role user. Jangan gunakan `$user->hasPermissionTo()` / `@can` bawaan Spatie untuk logic aplikasi — gunakan helper di bawah.

## API Pengecekan Permission

```php
use App\Support\ActivePermission;

ActivePermission::check('faculty.create');          // bool — cek single
ActivePermission::any(['a.create', 'a.update']);    // bool — salah satu
ActivePermission::all(['a.create', 'a.delete']);    // bool — semua
```

## Blade Directives

```blade
@activecan('resource.create') ... @endactivecan
@activecanany(['resource.create', 'resource.update']) ... @endactivecanany
@activecanall(['resource.create', 'resource.delete']) ... @endactivecanall
```

## Middleware (sudah terdaftar)

```php
Route::middleware('active_role')->...              // wajib punya active role
Route::middleware('active_role:student')->...      // active role harus = student
Route::middleware('active_permission:resource.viewAny')->...  // cek permission
Route::middleware('financial_clearance')->...      // blokir jika ada financial hold aktif
Route::middleware('is_installed')->...             // cek sistem sudah ter-install
```

File: `app/Http/Middleware/Ensure{RoleIsActive,ActiveRoleHasPermission,FinancialClearance,SystemIsInstalled}.php`

## Naming Permission

Format: `{resource-kebab-case}.{action}`

```
user.viewAny, user.create, user.update, user.delete, user.view
admission-application.viewAny, admission-application.update
```

Action standar: `viewAny`, `view`, `create`, `update`, `delete`.

## Roles Bawaan

`superuser`, `admin`, `lecturer`, `student`, `academic-leader`, `alumni`

## Aturan Praktis

1. **PowerGrid action button** → selalu guard dengan `ActivePermission::check()` sebelum menambahkan button
2. **Blade tombol/link** → bungkus dengan `@activecan`
3. **Bulk action PowerGrid** → set `protected ?string $bulkActionPermissionPrefix = 'your-resource';` di table class
4. **Route admin CRUD** → otomatis ter-guard via macro `crudLivewire()` dari `config/resources.php`; jangan duplikasi middleware manual
5. Setelah tambah resource baru → `php artisan resources:sync` untuk generate permission + menu
