# NexaCampus - AI Development Context
> **Baca file ini di awal setiap sesi.** File ini berisi seluruh konteks yang diperlukan AI untuk langsung memahami arsitektur, pola kode, dan konvensi proyek NexaCampus tanpa penjelasan ulang dari user.

---

## 1. Overview Proyek

**NexaCampus** adalah Sistem Informasi Akademik (SIAKAD) perguruan tinggi terpadu berbasis **Laravel 12** + **Inertia.js** + **React 19** + **Bootstrap 5 (Tabler)**. Proyek ini full-stack single-monolith dengan multi-role access (superuser, admin, lecturer, student, academic-leader, alumni).

> [!IMPORTANT]
> **STATUS MIGRASI (per November 2026):** Seluruh UI — admin, portal, auth, halaman publik, dan setup wizard — sudah menggunakan **Inertia.js 3 + React 19** (`resources/js/pages/**`). Tidak ada lagi halaman Livewire/Blade/PowerGrid milik aplikasi. Paket `livewire/livewire` + `livewire/flux` hanya dipakai oleh UI admin bawaan package WhatsApp (opsional, di luar navigasi aplikasi). Untuk modul/fitur baru, **WAJIB memakai pola Inertia React di §3.1**.

### Tech Stack
| Layer | Teknologi |
|---|---|
| **Backend Framework** | Laravel 12 (PHP 8.2+) |
| **Frontend Reactivity** | **Inertia.js 3 + React 19** (seluruh halaman aplikasi; Livewire/Flux hanya untuk UI admin bawaan package WhatsApp) |
| **UI Kit** | Bootstrap 5 (Tabler Admin Theme) + komponen React shared |
| **CSS** | TailwindCSS 4 via Vite plugin + `dashboard.css`/`crud.css` + custom CSS variables |
| **DataTable** | Komponen React `CrudTable` |
| **Form & Navigasi** | Inertia `useForm`, `router`, `Link`/`Head` |
| **Ikon** | Lucide React (halaman React); Font Awesome 6 (Blade legacy) |
| **Type Checking** | TypeScript (`npm run typecheck`) |
| **Authorization** | Spatie Laravel Permission v7 (multi-role, session-based active role) |
| **Activity Log** | Spatie Laravel Activity Log v4 |
| **Build Tool** | Vite 7 + laravel-vite-plugin + @vitejs/plugin-react |
| **Database** | MySQL (DB: `nexacampus`) |
| **Queue** | Database driver |
| **Session/Cache** | Database driver |
| **Rich Text Editor** | Jodit (`jodit-react`) |
| **Export** | PhpSpreadsheet 5 + DomPDF 3 + OpenSpout 5 |
| **Notification** | Email (SMTP) + WhatsApp (kstmostofa/laravel-whatsapp) + Web Push |
| **PWA** | erag/laravel-pwa |
| **Maps** | Leaflet.js |
| **Date Picker** | Flatpickr |
| **QR Code** | SimpleSoftwareIO QR Code |

### Dev Command
```bash
composer dev  # Runs: php artisan serve + queue:listen + npm run dev (concurrent)
```

---

## 2. Arsitektur & Struktur Direktori

### Domain Modules (Modular-by-Domain)
Proyek ini mengorganisir kode berdasarkan **domain bisnis**, BUKAN berdasarkan tipe file. Setiap domain memiliki mirror structure di Models, Controllers, React pages, Support, Mail, dan Tests.

```
Domain Modules:
├── Academic       → Akademik (mata kuliah, KRS, nilai, jadwal, absensi, kurikulum)
├── Access         → Manajemen akses (user, role, permission)
├── Admission      → PMB / Penerimaan Mahasiswa Baru
├── Alumni         → Alumni, tracer study, lowongan kerja, event
├── Campus         → Fasilitas kampus (gedung, ruangan)
├── Financial      → Keuangan (tagihan, pembayaran, beasiswa)
├── Organization   → Kepegawaian & organisasi (pegawai, approval, absensi pegawai, BKD, EDOM, tridharma)
├── Publication    → Pengumuman
├── Settings       → Pengaturan sistem (campus, menu, notification, system)
├── StudentService → Layanan mahasiswa (surat, cuti, pindah, yudisium, pengaduan)
└── System         → Sistem (log aktivitas, menu)
```

### Full Directory Map
```
nexacampus/
├── app/
│   ├── Console/Commands/          # Artisan commands (SyncPermissions, SyncMenus, etc.)
│   ├── Enums/                     # PHP 8.1+ backed enums (AnnouncementPriority, etc.)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                          # ⭐ Controller CRUD admin Inertia React (per area)
│   │   │   │   ├── Academic/
│   │   │   │   ├── Access/
│   │   │   │   ├── Admission/
│   │   │   │   ├── Alumni/
│   │   │   │   ├── Campus/
│   │   │   │   ├── Organization/
│   │   │   │   ├── Publication/
│   │   │   │   ├── StudentService/
│   │   │   │   └── System/
│   │   │   ├── Academic/                       # Portal controller (download/export/PDF)
│   │   │   ├── Alumni/
│   │   │   ├── Financial/                      # Export & receipt (bukan CRUD admin)
│   │   │   ├── Lecturer/
│   │   │   ├── Notifications/
│   │   │   ├── Organization/                   # Attachment/export controller
│   │   │   ├── Student/
│   │   │   └── StudentService/
│   │   └── Middleware/
│   │       ├── Authenticate.php
│   │       ├── EnsureRoleIsActive.php          # middleware: active_role / active_role:student
│   │       ├── EnsureActiveRoleHasPermission.php # middleware: active_permission:resource.action
│   │       ├── EnsureFinancialClearance.php     # middleware: financial_clearance
│   │       └── EnsureSystemIsInstalled.php      # middleware: is_installed
│   ├── Mail/                                   # Mailable classes per domain
│   │   ├── Academic/
│   │   ├── Financial/
│   │   └── StudentService/
│   ├── Models/                                 # Eloquent models per domain
│   │   ├── User.php                            # Root user model (multi-profile)
│   │   ├── Academic/   (49 models)
│   │   ├── Access/     (Role, Permission)
│   │   ├── Admission/  (11 models)
│   │   ├── Alumni/     (7 models)
│   │   ├── Campus/     (Building, Room)
│   │   ├── Financial/  (14 models)
│   │   ├── Organization/ (34 models)
│   │   ├── Publication/  (Announcement, AnnouncementRead)
│   │   ├── Settings/   (Campus, Menu, NotificationSetting, System, etc.)
│   │   └── StudentService/ (18 models)
│   ├── Providers/
│   │   └── AppServiceProvider.php              # ⭐ Boot: View composers, Blade directives, Route macros
│   └── Support/                                # ⭐ Service layer & helpers
│       ├── ActivePermission.php                # Permission checker (session-based active role)
│       ├── ResourceRegistry.php                # Config-driven CRUD resource registry
│       ├── SidebarMenu.php                     # Dynamic sidebar menu builder
│       ├── Inertia/ShellProps.php              # ⭐ Props AdminShell React (sidebar/topbar/user)
│       ├── AcademicAdvisorService.php
│       ├── StudentGradeCalculator.php
│       ├── TranscriptSyncService.php
│       ├── StudentProgressAnalyticsService.php
│       ├── GradeBookExportService.php
│       ├── Notifications/                      # Multi-channel notification dispatch
│       │   ├── NotificationDispatchService.php
│       │   ├── WebPushNotificationService.php
│       │   └── WhatsAppProviderManager.php
│       ├── Financial/
│       ├── Admission/
│       ├── Alumni/
│       ├── Organization/
│       ├── Student/
│       ├── StudentService/
│       └── WhatsApp/
├── config/
│   └── resources.php                           # ⭐ Master resource registry (CRUD definitions)
├── database/
│   ├── migrations/                             # 101 migration files
│   ├── seeders/                                # Domain-based seeders
│   └── factories/
├── resources/
│   ├── css/
│   │   ├── app.css                             # Global CSS + CSS variables + dark mode
│   │   ├── dashboard.css                       # Styling dashboard/portal React
│   │   ├── crud.css                            # Styling CRUD React (table, form, hero, filter)
│   │   └── admin-shell.css                     # Sidebar/topbar AdminShell
│   ├── js/
│   │   ├── app.tsx                             # ⭐ Entry Inertia React (createInertiaApp)
│   │   ├── app.js                              # Init global legacy (Leaflet, Flatpickr, dsb.)
│   │   ├── components/Shared/                  # ⭐ AdminShell, FlashAlert, Pagination, StatStrip
│   │   │   └── Crud/                           # ⭐ CrudTable, FilterPanel, fields, ConfirmModal,
│   │   │                                       #    AsyncSelect, CrudHero, ImportModal, Checklist
│   │   ├── pages/Admin/{Area}/{Resource}/      # ⭐ Halaman admin React (Index/Form/Show.tsx)
│   │   ├── pages/{Role}/                       # Portal student/lecturer/alumni/academic-leader
│   │   ├── lib/                                # Helper fetch/format
│   │   └── types/                              # Deklarasi TypeScript
│   ├── saas/app.scss                           # SCSS (minimal)
│   └── views/
│       ├── app.blade.php                       # ⭐ Root Inertia (div#app + @vite)
│       ├── layouts/                            # Layout Blade legacy (PDF, error)
│       ├── components/                         # Komponen Blade legacy (alert, dll.)
│       ├── templates/                          # Reusable template
│       ├── exports/                            # PDF/export templates
│       ├── pdf/                                # DomPDF templates
│       └── vendor/                             # Published vendor views
├── routes/
    ├── web.php                                 # ⭐ Entry route (memuat semua file di routes/web/)
    └── web/
        ├── admin.php                           # ⭐ Route admin per area + skip list ResourceRegistry
        ├── student.php, lecturer.php           # Route portal
        ├── alumni.php, academic-leader.php
        ├── guest.php, public.php, shared.php
├── tests/
    ├── Feature/                                # ⭐ Automated Domain E2E & Feature Tests
    │   ├── Academic/                           # KRS, Nilai, Kalender, QR Attendance
    │   ├── Access/                             # User, role, permission (Inertia)
    │   ├── Admission/                          # PMB, NIM Generator, Konversi Maba
    │   ├── Alumni/                             # Tracer Study, Job Board, Event
    │   ├── Campus/                             # Gedung, ruangan, aset, reservasi
    │   ├── Financial/                          # Invoice, Clearance Policy, Payment Verification
    │   ├── Organization/                       # Approval Engine, Kepegawaian, Workload EDOM
    │   ├── Publication/                        # Pengumuman, berita, agenda, galeri
    │   ├── StudentService/                     # Cuti Akademik, Fee Integration, Digital ID
    │   └── System/                             # Uji sistem umum + smoke Inertia
    ├── Unit/                                   # Uji unit murni
    ├── Pest.php                                # ⭐ Konfigurasi Pest bind TestCase->in('Feature')
    └── TestCase.php
```

---

## 3. Pola Kode Inti (Core Patterns)

### 3.1 CRUD Admin Resource Pattern (Inertia React) ⭐

**Ini adalah pola paling penting.** Semua CRUD admin baru memakai **Inertia.js 3 + React 19**. `config/resources.php` tetap menjadi sumber menu & permission, tetapi route dan halaman dibuat eksplisit per area.

> [!NOTE]
> Pola **Blade + Livewire + PowerGrid** di §3.1b **tidak dipakai lagi** dan hanya disimpan sebagai referensi historis. Jangan memakainya untuk modul baru.

#### Step 1: Registrasi resource di `config/resources.php`
```php
[
    'resource' => 'your-resource',      // Singular kebab-case (dipakai untuk permission: your-resource.viewAny)
    'plural' => 'your-resources',       // Plural (dipakai untuk URL)
    'area' => 'academic',               // Domain area (academic/admission/financial/etc.)
    'component' => 'admin.academic.your-resources',  // Legacy: prefix komponen (tidak dipakai route Inertia)
    'actions' => ['index', 'create', 'edit', 'show', 'delete'],  // Available actions
    'permissions' => ['viewAny', 'create', 'update', 'delete', 'view'], // Optional: custom permissions
    'menu' => [
        'title' => 'Your Resources',
        'icon' => 'fas fa-icon-name',
        'group' => 'Akademik',          // Menu group name
        'group_icon' => 'fas fa-graduation-cap', // Optional group icon
        'group_order' => 10,            // Group sort priority
        'order' => 1,                   // Item sort within group
    ],
]
```

#### Step 2: Migration & Model (`app/Models/{Domain}/YourResource.php`)

```php
// database/migrations/xxxx_xx_xx_xxxxxx_create_your_resources_table.php
Schema::create('your_resources', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 30)->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
});
```

```php
<?php
namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class YourResource extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('your_resource')
            ->logOnly(['field1', 'field2'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'name', 'code', 'is_active',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function relatedModel(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }
}
```

#### Step 3: Route eksplisit (`routes/web/admin.php`)

Resource Inertia **tetap terdaftar** di `config/resources.php` (untuk menu & permission), lalu route-nya ditulis eksplisit di `routes/web/admin.php`:

```php
// Route eksplisit (nama route WAJIB sama dengan yang dipakai sidebar/menu)
Route::get('/academic/your-resources', [YourResourceController::class, 'index'])
    ->middleware('active_permission:your-resource.viewAny')
    ->name('academic.your-resources.index');
Route::get('/academic/your-resources/create', [YourResourceController::class, 'create'])
    ->middleware('active_permission:your-resource.create')
    ->name('academic.your-resources.create');
Route::post('/academic/your-resources', [YourResourceController::class, 'store'])
    ->middleware('active_permission:your-resource.create')
    ->name('academic.your-resources.store');
Route::post('/academic/your-resources/bulk-destroy', [YourResourceController::class, 'bulkDestroy'])
    ->middleware('active_permission:your-resource.delete')
    ->name('academic.your-resources.bulk-destroy');
Route::get('/academic/your-resources/{id}/edit', [YourResourceController::class, 'edit'])
    ->middleware('active_permission:your-resource.update')
    ->name('academic.your-resources.edit');
Route::put('/academic/your-resources/{id}', [YourResourceController::class, 'update'])
    ->middleware('active_permission:your-resource.update')
    ->name('academic.your-resources.update');
Route::delete('/academic/your-resources/{id}', [YourResourceController::class, 'destroy'])
    ->middleware('active_permission:your-resource.delete')
    ->name('academic.your-resources.destroy');
```

> Route admin tinggal di `routes/web/admin.php` (dimuat oleh `routes/web.php`), bukan di satu file monolitik.

#### Step 4: Controller Inertia (`app/Http/Controllers/Admin/{Area}/{Resource}Controller.php`)

```php
<?php

namespace App\Http\Controllers\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\YourResource;
use App\Support\ActivePermission;
use App\Support\Inertia\ShellProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class YourResourceController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|in:name,code,is_active,created_at',
            'direction' => 'nullable|in:asc,desc',
            'perPage' => 'nullable|in:10,15,25,50,100',
        ]);

        $query = YourResource::query();

        if (filled($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%"));
        }

        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $rows = $query->paginate((int) ($validated['perPage'] ?? 10))->withQueryString();

        return Inertia::render('Admin/Academic/YourResources/Index', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Daftar Your Resources'),
            'can' => [
                'create' => ActivePermission::check('your-resource.create'),
                'update' => ActivePermission::check('your-resource.update'),
                'delete' => ActivePermission::check('your-resource.delete'),
            ],
            'stats' => [
                'total' => YourResource::count(),
                'active' => YourResource::where('is_active', true)->count(),
            ],
            'data' => [
                'rows' => collect($rows->items())->values()->map(fn (YourResource $row, int $i) => [
                    'id' => $row->id,
                    'no' => ($rows->firstItem() ?? 0) + $i,
                    'name' => $row->name,
                    'code' => $row->code,
                    'isActive' => (bool) $row->is_active,
                    'createdAt' => $row->created_at?->format('d M Y'),
                    'editUrl' => route('admin.academic.your-resources.edit', $row->id),
                    'deleteUrl' => route('admin.academic.your-resources.destroy', $row->id),
                ])->all(),
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'is_active' => $validated['is_active'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => (int) ($validated['perPage'] ?? 10),
            ],
            'urls' => [
                'index' => route('admin.academic.your-resources.index'),
                'create' => route('admin.academic.your-resources.create'),
                'bulkDestroy' => route('admin.academic.your-resources.bulk-destroy'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Academic/YourResources/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Tambah Your Resource'),
            'mode' => 'create',
            'resource' => ['name' => '', 'code' => '', 'is_active' => true],
            'urls' => [
                'index' => route('admin.academic.your-resources.index'),
                'submit' => route('admin.academic.your-resources.store'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        YourResource::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.your-resources.index')
            ->with('success', 'Data berhasil ditambahkan.');
    }

    public function edit(Request $request, int $id): Response
    {
        $row = YourResource::findOrFail($id);

        return Inertia::render('Admin/Academic/YourResources/Form', [
            'shell' => ShellProps::make($request->user(), 'Akademik', 'Edit Your Resource'),
            'mode' => 'edit',
            'resource' => [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'is_active' => (bool) $row->is_active,
            ],
            'urls' => [
                'index' => route('admin.academic.your-resources.index'),
                'submit' => route('admin.academic.your-resources.update', $row->id),
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $row = YourResource::findOrFail($id);
        $row->update([
            ...$this->validatePayload($request, $row),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.academic.your-resources.index')
            ->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $row = YourResource::findOrFail($id);

        // Guard relasi bisnis: kembalikan back()->with('error', '...') bila masih dipakai.

        $row->update(['deleted_by' => $request->user()->id]);
        $row->delete();

        return redirect()->route('admin.academic.your-resources.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?YourResource $row = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', Rule::unique('your_resources', 'code')->ignore($row?->id)],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
```

**Aturan controller:**
- `ShellProps::make($user, $pretitle, $pageTitle)` wajib agar sidebar/topbar React tampil.
- Kirim `can` dari `ActivePermission::check(...)` — jangan mengandalkan `auth()->user()->can()` karena role aktif berbasis session.
- Map baris tabel secara eksplisit (jangan kirim model Eloquent mentah) + sertakan `editUrl`/`deleteUrl`.
- `bulkDestroy` wajib punya guard per baris bila model punya relasi bisnis (jangan soft-delete buta).
- Semua pesan flash & label dalam Bahasa Indonesia.

#### Step 5: Halaman React (`resources/js/pages/Admin/{Area}/{Resources}/`)

**`Index.tsx`** — pola tabel + filter + aksi massal:

```tsx
import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = { id: number; no: number; name: string; code: string; isActive: boolean; editUrl: string; deleteUrl: string };

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; is_active: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    urls: { index: string; create: string; bulkDestroy: string };
};

export default function YourResourcesIndex({ shell, can, data, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [pending, setPending] = useState<Row | null>(null);

    const visit = (overrides: Record<string, string | number>) => {
        router.get(urls.index, { ...filters, q: search, ...overrides }, { preserveState: true, replace: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Your Resources · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Plus}
                        eyebrow="Akademik"
                        title="Daftar Your Resources"
                        description="Kelola data master …"
                        actions={can.create ? <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah</a> : undefined}
                    />
                    <section className="db-card">
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    { key: 'name', label: 'Nama', sortable: true, render: (row) => row.name },
                                    { key: 'code', label: 'Kode', sortable: true, render: (row) => <span className="db-badge gray">{row.code}</span> },
                                    { key: 'is_active', label: 'Status', sortable: true, render: (row) => (
                                        <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                    ) },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={(key) => visit({ sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 })}
                                filterBar={<FilterPanel count={filters.is_active !== '' ? 1 : 0} onReset={() => visit({ is_active: '', page: 1 })}>
                                    <select className="db-input" value={filters.is_active} onChange={(e) => visit({ is_active: e.target.value, page: 1 })}>
                                        <option value="">Semua status</option>
                                        <option value="1">Aktif</option>
                                        <option value="0">Nonaktif</option>
                                    </select>
                                </FilterPanel>}
                                canUpdate={can.update}
                                canDelete={can.delete}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={(row) => setPending(row)}
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
                <ConfirmModal
                    open={pending !== null}
                    title="Hapus data?"
                    message={pending ? `"${pending.name}" akan dihapus.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    onConfirm={() => { if (pending) router.delete(pending.deleteUrl, { onFinish: () => setPending(null) }); }}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
```

**`Form.tsx`** — pola form create/edit:

```tsx
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextField } from '../../../../components/Shared/Crud/fields';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    resource: { id?: number; name: string; code: string; is_active: boolean };
    urls: { index: string; submit: string };
};

export default function YourResourceForm({ shell, mode, resource, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({ name: resource.name, code: resource.code, is_active: resource.is_active });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        isCreate ? form.post(urls.submit) : form.put(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Your Resource · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Plus} eyebrow="Akademik" title={isCreate ? 'Tambah Your Resource' : `Edit ${resource.name}`} description="…" />
                    <form onSubmit={submit}>
                        <section className="db-card">
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <TextField label="Nama" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                                    <TextField label="Kode" required value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} error={form.errors.code} />
                                    <SwitchField label="Status aktif" checked={form.data.is_active} onChange={(value) => form.setData('is_active', value)} />
                                </div>
                            </div>
                        </section>
                        <FormActions cancelHref={urls.index} submitLabel={isCreate ? 'Simpan' : 'Simpan Perubahan'} processing={form.processing} />
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
```

**Komponen shared wajib diketahui:**

| Komponen | Fungsi |
|---|---|
| `components/Shared/AdminShell.tsx` | Sidebar + topbar + flash alert + layout portal |
| `components/Shared/Crud/CrudTable.tsx` | Tabel generik: sort, search, pagination, checkbox, bulk delete, actions |
| `components/Shared/Crud/FilterPanel.tsx` | Panel filter collapsible + badge jumlah filter aktif |
| `components/Shared/Crud/fields.tsx` | `TextField`, `TextareaField`, `SelectField`, `SwitchField`, `FormActions` |
| `components/Shared/Crud/AsyncSelect.tsx` | Pencarian server-side (debounce + endpoint JSON) |
| `components/Shared/Crud/ConfirmModal.tsx` | Konfirmasi hapus/aksi destruktif |
| `components/Shared/Crud/CrudHero.tsx` | Header halaman (ikon, eyebrow, judul, deskripsi, badges, actions) |
| `components/Shared/Crud/ImportModal.tsx` | Modal impor spreadsheet |
| `components/Shared/FlashAlert.tsx` | Render flash `success/error/warning/info` |
| `pages/Admin/{Area}/shared.tsx` | Helper lokal area (mis. `buildQuery`, `OrgStats`) |

#### Step 6: Sync permissions & menus
```bash
php artisan resources:sync   # Runs SyncPermissions + SyncMenus
```

---

### 3.1b Legacy: Blade + Livewire + PowerGrid (historis)

> [!NOTE]
> Seluruh modul admin, termasuk Financial, sudah memakai Inertia React. Pola Blade + Livewire + PowerGrid di bagian ini **tidak dipakai lagi** dan hanya disimpan sebagai referensi historis. Jangan membuat resource baru dengan pola ini.

### 3.2 Authorization System (Session-Based Active Role)

**Konsep Kunci:** User bisa punya multiple roles. Saat login, user memilih **active role** yang disimpan di session (`session('active_role')`). Semua permission check dilakukan terhadap active role ini, BUKAN semua role user.

#### Komponen:
- **`ActivePermission::check($permission)`** → Cek permission terhadap active role
- **`ActivePermission::any($permissions)`** → Cek salah satu permission
- **`ActivePermission::all($permissions)`** → Cek semua permission

#### Blade Directives:
```blade
@activecan('resource.create')       {{-- Cek single permission --}}
@endactivecan

@activecanany(['resource.create', 'resource.update'])
@endactivecanany

@activecanall(['resource.create', 'resource.delete'])
@endactivecanall
```

#### Middleware:
```php
Route::middleware('active_role')->...           // Cek user punya active role
Route::middleware('active_role:student')->...   // Cek active role = student
Route::middleware('active_permission:resource.viewAny')->...  // Cek permission
Route::middleware('financial_clearance')->...   // Cek financial holds
Route::middleware('is_installed')->...          // Cek system installed
```

#### Permission Naming: `{resource-kebab-case}.{action}`
```
user.viewAny, user.create, user.update, user.delete
faculty.viewAny, faculty.create, faculty.update, faculty.delete
admission-application.viewAny, admission-application.view, admission-application.update
```

#### Roles: `superuser`, `admin`, `lecturer`, `student`, `academic-leader`, `alumni`

---

### 3.3 Route Patterns

#### Admin Routes (Inertia React — primary)
Route admin ditulis eksplisit di `routes/web/admin.php`. Tidak ada lagi loop `ResourceRegistry` atau macro `crudLivewire`; `config/resources.php` hanya menjadi sumber menu & permission.

```php
// routes/web/admin.php
Route::get('/academic/faculties', [FacultyController::class, 'index'])
    ->middleware('active_permission:faculty.viewAny')
    ->name('academic.faculties.index');
```

#### Route Name Conventions:
```
Admin:    admin.{area}.{resources}.{action}     → admin.academic.faculties.index
Student:  student.{feature}.{action}            → student.study-plan.index
Lecturer: lecturer.{feature}.{action}           → lecturer.course-offerings.index
Alumni:   alumni.{feature}.{action}             → alumni.jobs.index
Academic Leader: academic-leader.{feature}      → academic-leader.dashboard.index
Employee: employee.{feature}.{action}           → employee.attendance.index
```

#### Inertia Page Route:
```php
Route::get('/path', [FeatureController::class, 'index'])->name('route.name');
// Controller: return Inertia::render('Role/Feature/Index', [...]);
```

---

### 3.4 User Model & Multi-Profile System

User memiliki **multiple profile types** via HasOne relationships:
```php
$user->studentProfile     // → StudentProfile (NIM, prodi, angkatan)
$user->lecturerProfile    // → LecturerProfile (NIDN, expertise)
$user->employeeProfile    // → EmployeeProfile (NIP, jabatan)
$user->alumniProfile      // → AlumniProfile
```

Computed attributes:
```php
$user->name       // first_name + last_name
$user->role       // Comma-separated role names
$user->prefix     // Route prefix berdasarkan active role ('admin.', 'student.', etc.)
$user->photo      // Auto-resolve ke storage path
```

---

### 3.5 Layout & Shell System

#### Inertia React (utama)
Setiap controller admin mengirim props `shell` lewat `ShellProps::make($user, $pretitle, $pageTitle)`:

```php
return Inertia::render('Admin/Organization/WorkUnits/Index', [
    'shell' => ShellProps::make($request->user(), 'Kepegawaian', 'Unit Kerja'),
    // ...
]);
```

Halaman React membungkus konten dengan `<AdminShell shell={shell}>`. Shell menyediakan sidebar, topbar, command palette, flash alert, dan data user — **tidak perlu** memanggil layout Blade.

#### Blade legacy (historis)
```php
return $this->view()->layout('layouts.app', [
    'menus' => 'Menu Category Name',    // Breadcrumb/sidebar context
    'pages' => 'Page Title',            // Page title in header
]);
```

#### Global View Variables (dari AppServiceProvider, Blade legacy):
- `$campus` → Campus model (nama, logo, alamat)
- `$system` → System model (app_name, app_description)
- `$user` → Auth::user()
- `$activeRole` → session('active_role')

#### Alert Component:
- React: `<FlashAlert />` sudah terpasang di dalam `AdminShell`.
- Blade legacy: `<x-alert />` (session flash `success/error/warning/info`).

---

### 3.6 Sidebar Menu System

Menu dibangun dari **dua sumber**:
1. **Database** (`menus` table) → Admin menus yang di-sync dari `config/resources.php`
2. **Hard-coded** (di `SidebarMenu.php`) → Role-specific menus (student, lecturer, alumni, academic-leader)

`SidebarMenu::get()` menggabungkan:
- `commonMenus()` → Dashboard + Profile (semua role)
- `employeeMenus()` → Employee self-service (jika punya EmployeeProfile)
- Database menus → Admin CRUD menus (permission-filtered)
- `roleMenus()` → Role-specific menus

---

### 3.7 Enum Pattern

```php
enum AnnouncementPriority: string
{
    case NORMAL = 'normal';
    case IMPORTANT = 'important';
    case URGENT = 'urgent';

    public function label(): string { /* Indonesian labels */ }
    public function badgeClass(): string { /* Bootstrap CSS class */ }
    public function icon(): string { /* FontAwesome icon class */ }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
```

---

### 3.8 Notification System

Multi-channel notification via `NotificationDispatchService`:
- **Email** (Laravel Mail)
- **WhatsApp** (via `WhatsAppProviderManager` → Official Cloud API or Unofficial Web Session)
- **Web Push** (via `WebPushNotificationService` + VAPID keys)

Notification settings per-user stored in `notification_settings` table.
Templates stored in `notification_templates` table.

---

### 3.9 Approval Engine

Generic approval workflow via:
- `ApprovalTemplate` → Defines multi-step approval flow
- `ApprovalTemplateStep` → Individual steps with role-based approvers
- `ApprovalRequest` → Instance of an approval process
- `ApprovalStep` → Individual step execution
- `ApprovalAction` → Actions taken (approve/reject/revise)

Used by: Employee leaves, financial adjustments, student service requests.

### 3.10 Modul Skripsi / Tugas Akhir

Domain `Academic` — alur: mahasiswa mengajukan proposal → admin menyetujui/menolak + menetapkan pembimbing → milestone bimbingan (mahasiswa submit, dosen review) → admin menjadwalkan sidang → dosen/penguji memberi nilai → admin finalisasi nilai & publikasi repositori.

**Tabel:** `theses`, `thesis_supervisors`, `thesis_milestones`, `thesis_documents`, `thesis_defenses`, `thesis_defense_examiners`.

**Status:**
- `theses.status`: `draft`, `submitted`, `in_progress`, `defense_ready`, `defended`, `completed`, `rejected`, `cancelled`
- `thesis_milestones.status`: `pending`, `in_progress`, `submitted`, `revision`, `approved`
- `thesis_defenses.status`: `scheduled`, `completed`, `cancelled`, `postponed`; tipe `proposal` / `result`; hasil `passed` / `passed_with_revision` / `failed`

**Service utama:** `app(ThesisService::class)` di `app/Support/Academic/ThesisService.php`:
- `createProposal()`, `approve()`, `reject()`, `submitMilestone()`, `reviewMilestone()`
- `scheduleDefense()`, `completeDefense()`, `finalize()`, `storeDocument()`
- `ensureNoActiveThesis()` — mahasiswa hanya boleh punya satu thesis aktif
- Konversi skor → huruf mutu (`gradeFromScore`) dan sinkronisasi status thesis dari milestone/sidang

**Route (28):**
- Admin: `admin.academic.theses.*` (`index/create/store/show/edit/update/destroy`, `approve`, `reject`, `milestones.store/update/destroy`, `defenses.store/update/complete/cancel`, `documents.store/destroy/download`, `finalize`, `bulk-destroy`, `search-lecturers`, `search-students`)
- Mahasiswa: `student.thesis.*` (`index/store/update/cancel`, `milestones.submit`, `documents.store/destroy/download`)
- Dosen: `lecturer.theses.*` (`index/show`, `milestones.review`, `defenses.score`, `documents.download`)

**Halaman React:**
- Admin: `resources/js/pages/Admin/Academic/Theses/{Index,Form,Show}.tsx`
- Mahasiswa: `resources/js/pages/Student/Thesis/Index.tsx`
- Dosen: `resources/js/pages/Lecturer/Theses/{Index,Show}.tsx`

**Permission:** `thesis.viewAny`, `thesis.view`, `thesis.create`, `thesis.update`, `thesis.delete`, `thesis.approve`, `thesis.defense`, `thesis.grade`.

**Dokumen:** disk `public`, folder `thesis/{thesis_id}`. Akses unduh dibatasi pemilik, pembimbing, penguji, dan admin ber-permission `thesis.view` (`Academic\ThesisDocumentController`).

**Test:** `tests/Feature/Academic/ThesisManagementTest.php`.

---

## 4. Naming Conventions

### PHP Naming:
| Type | Convention | Example |
|---|---|---|
| Model | PascalCase singular | `StudentProfile`, `CourseOffering` |
| Table | snake_case plural | `student_profiles`, `course_offerings` |
| Migration | timestamp + descriptive | `2026_03_21_171532_create_faculties_table.php` |
| Enum | PascalCase | `AnnouncementPriority` |
| Service | `{Name}Service` | `TranscriptSyncService` |
| Seeder | `{Domain}Seeder` | `AcademicSeeder` |
| Middleware | `Ensure{Condition}` | `EnsureRoleIsActive` |
| Command | Imperative verb | `SyncPermissions`, `PruneNotificationLogs` |
| Admin Controller | `{Resource}Controller` | `FacultyController`, `WorkUnitController` |

### Frontend File Naming:
| Type | Path Pattern |
|---|---|
| Admin CRUD page | `resources/js/pages/Admin/{Area}/{Resources}/{Index,Form,Show}.tsx` |
| Portal page | `resources/js/pages/{Role}/{Feature}/{Page}.tsx` |
| Shared CRUD component | `resources/js/components/Shared/Crud/{Component}.tsx` |
| Shared layout/UI | `resources/js/components/Shared/{Component}.tsx` |
| Halaman publik | `resources/js/pages/Home/{Page}.tsx` |
| Halaman auth | `resources/js/pages/Auth/{Login,SelectRole}.tsx` |
| Setup wizard | `resources/js/pages/Setup/Wizard.tsx` |

### Route & Permission Naming:
| Type | Pattern | Example |
|---|---|---|
| Route (admin) | `admin.{area}.{resources}.{action}` | `admin.academic.faculties.create` |
| Permission | `{resource-kebab}.{action}` | `faculty.viewAny` |
| Resource key | kebab-case singular | `student-registration` |

---

## 5. UI/Design System

### Color Palette
```css
--app-primary:        #6842f4   /* Primary Purple */
--app-primary-bright: #8b6cff   /* Bright Purple */
--app-primary-deep:   #3f249c   /* Deep Purple */
--app-shell-bg:       linear-gradient(165deg, #8b6cff 0%, #5d3fd3 48%, #372083 100%)
--app-page-bg:        #f7f3ff   /* Light lavender background */
--app-surface-bg:     #ffffff   /* Card/surface background */
```

### CSS Framework: Bootstrap 5 (Tabler Admin) + TailwindCSS 4 + React
- Halaman **React**: class `db-*`/`crud-*` + CSS variables; Bootstrap tersedia global untuk komponen legacy.
- Halaman **Blade legacy**: `card`, `form-control`, `btn`, `row/col-*`.
- TailwindCSS untuk utility tambahan; custom CSS variables di `resources/css/app.css`.

### Icon: Lucide React (utama) + Font Awesome 6 (legacy)
```tsx
import { Plus, Pencil, Trash2 } from 'lucide-react';
<Plus size={15} />
```
```html
<!-- Blade legacy -->
<i class="fas fa-plus"></i>
```

### Button Styles & Sizing Rules (MANDATORY):
```tsx
// React (utama)
<button className="db-btn primary">Aksi Utama</button>
<a className="db-btn light" href={urls.create}>Tambah</a>
<button className="db-btn danger">Hapus</button>
```
```html
<!-- Blade legacy -->
<button class="btn btn-primary">Primary Action</button>
<a class="btn btn-ghost-primary">Ghost/Outline</a>
<button class="btn btn-secondary">Cancel</button>
<button class="btn btn-danger">Delete</button>
```
> [!IMPORTANT]
> **ATURAN PROPORSI & UKURAN ELEMEN (ANTI-OVERSIZE & ANTI-MINI):**
> 1. **DILARANG OVERSIZE:** Jangan pernah menggunakan ukuran besar/oversize (`btn-lg`, `form-control-lg`, padding raksasa `-lg`, font super besar). User sangat tidak menyukai desain yang gembrot/oversized.
> 2. **DILARANG BTN-SM SEMBARANGAN:** Jangan sembarangan memakai `btn-sm` atau tombol mini pada navbar/toggle/action utama yang merusak estetika dan keterbacaan. Gunakan ukuran standar (`btn`, `form-control`) yang proporsional, compact, dan elegan.

### Dark Mode & Background Architecture Rules (MANDATORY):
> [!WARNING]
> **ATURAN TEMA & DARK MODE GLOBAL:**
> 1. **DILARANG HARDCODED BG-WHITE/BG-LIGHT:** Hindari penggunaan `bg-white`, `bg-light`, atau `style="background: #ffffff"` pada container/card di file Blade. Karena di Tabler/Bootstrap class tersebut memaksa `#ffffff !important` dan membuat tampilan bocor/putih menyilaukan saat mode gelap (`[data-bs-theme=dark]`). Gunakan class `card`, `var(--tblr-bg-surface)`, atau biarkan mengikuti styling tema global.
> 2. **GLOBAL FIRST:** Pengaturan warna latar dan border untuk dark mode diatur secara terpusat dan global melalui `resources/css/app.css` (`[data-bs-theme=dark]`). Jangan membuat custom blok CSS dark mode di setiap komponen blade yang membebani maintainability.

### Confirmation Dialog: `ConfirmModal` (React, utama)
```tsx
<ConfirmModal
    open={pending !== null}
    title="Hapus data?"
    message="Data tidak bisa dikembalikan!"
    confirmLabel="Ya, hapus"
    danger
    processing={processing}
    onConfirm={confirmPending}
    onCancel={() => setPending(null)}
/>
```

**Legacy historis (tidak dipakai modul aktif):** SweetAlert2 via Livewire `$this->js()`:
```php
$this->js('Swal.fire({ title: "Hapus data?", icon: "warning", showCancelButton: true })
    .then((result) => { if (result.isConfirmed) { $wire.deleteItem('.$id.') } });');
```

### Flash Messages:
```php
session()->flash('success', 'Data berhasil disimpan.');   // Green
session()->flash('error', 'Terjadi kesalahan.');           // Red
session()->flash('warning', 'Perhatian!');                  // Yellow
session()->flash('info', 'Informasi.');                     // Blue
```

---

## 6. Database Schema Overview

### Core Tables:
- `users` (first_name, last_name, username, email, phone, code, photo, identity_number, etc.)
- `roles`, `permissions`, `model_has_roles`, `role_has_permissions` (Spatie)
- `activity_log` (Spatie)
- `sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs` (Laravel)
- `settings` (campus info, system config)
- `menus` (dynamic sidebar menu, parent_id for nesting)

### Academic Module:
`academic_years` → `academic_periods` → `course_offerings` (per period)
`faculties` → `study_programs` → `curriculums` → `curriculum_courses`
`courses` → `course_prerequisites`, `course_scopes`
`course_offerings` → `course_offering_lecturers`, `course_schedules`, `attendance_sessions`
`student_profiles` → `student_registrations`, `study_plans` → `study_plan_details`
`student_grades` → `student_grade_components`
`study_results`, `transcript_entries`
`assignments` → `assignment_submissions` → `assignment_grades`
`course_materials` → `course_material_files`, `course_material_versions`
`consultation_slots` → `consultation_appointments`
`grade_appeals` → `grade_appeal_attachments`
`theses` → `thesis_supervisors`, `thesis_milestones`, `thesis_documents`, `thesis_defenses` → `thesis_defense_examiners`

### Financial Module:
`tuition_fees` → `invoice_schedules` → `student_invoices` → `invoice_items`
`payments`, `invoice_adjustments`, `invoice_installments`, `invoice_installment_requests`
`scholarships` → `student_scholarships`
`student_credit_balances`, `student_credit_transactions`
`financial_clearance_policies`, `financial_holds`

### Common Model Traits:
```php
use SoftDeletes;        // Semua model utama
use LogsActivity;       // Semua model utama
```

### Common Fields:
- `created_by`, `updated_by`, `deleted_by` → user ID tracking
- `is_active` → boolean status toggle
- `created_at`, `updated_at`, `deleted_at` → timestamps

---

## 7. Seeding Order

```php
// DatabaseSeeder.php - ORDER MATTERS (foreign key dependencies)
$this->call([
    SettingsSeeder::class,              // Campus & System settings
    NotificationTemplateSeeder::class,  // Notification templates
    UserSeeder::class,                  // Users, Roles, Permissions
    MenuSeeder::class,                  // Sidebar menus
    AcademicSeeder::class,              // Academic data (faculties, prodi, etc.)
    OrganizationSeeder::class,          // Employee data
    AdmissionSeeder::class,             // PMB data
    FinancialSeeder::class,             // Financial data
    StudentServiceSeeder::class,        // Student services
    AlumniSeeder::class,                // Alumni data
]);
```

---

## 8. Artisan Commands

> Command signatures menggunakan namespace per-domain (BUKAN `app:*`).

```bash
# Resources / Permissions / Menus
php artisan resources:sync              # Sync permissions + menus dari config/resources.php
php artisan permissions:sync            # Sync permissions saja ({--prune} = hapus permission web yang tidak terdaftar)
php artisan menus:sync                  # Sync menus saja

# Financial
php artisan financial:evaluate-holds    # Evaluate student financial holds
php artisan financial:refresh-overdue   # Mark overdue invoices
php artisan financial:run-invoice-schedules {--dry-run} {--limit=}  # Generate invoices dari schedules

# Academic
php artisan academic:send-assignment-reminders {--hours=24} {--dry-run}  # Reminder deadline tugas

# Notifications
php artisan notifications:prune-logs    # Bersihkan notification logs lama
php artisan notifications:web-push-vapid # Generate Web Push VAPID keys

# WhatsApp Sidecar
php artisan nexacampus:whatsapp-sidecar-start  # Spawn WhatsApp sidecar background
php artisan whatsapp:health             # Cek health sidecar & Cloud API backends
php artisan whatsapp:sidecar:status     # Status instalasi/sesi sidecar
php artisan whatsapp:sidecar:install    # Install Node dependencies sidecar
php artisan whatsapp:sidecar:stop       # Stop sidecar session
php artisan whatsapp:web:listen         # Subscribe event stream sidecar → Laravel events
```

---

## 9. Key Integration Points

### Menambah Modul/Resource Admin Baru (Inertia React):
1. **Migration** → `database/migrations/`
2. **Model** → `app/Models/{Domain}/`
3. **Config** → Tambah entry di `config/resources.php` (menu + permission)
4. **Route** → Tambahkan plural ke skip list `ResourceRegistry`, lalu route eksplisit di `routes/web/admin.php`
5. **Controller** → `app/Http/Controllers/Admin/{Area}/{Resource}Controller.php` (`Inertia::render` + `ShellProps::make`)
6. **Halaman React** → `resources/js/pages/Admin/{Area}/{Resources}/Index.tsx` + `Form.tsx` (+ `Show.tsx` bila perlu)
7. **Test** → `tests/Feature/{Domain}/{Resource}CrudTest.php` dengan `assertInertia`
8. **Sync** → `php artisan resources:sync`

> Detail pola lengkap ada di §3.1. Jangan membuat PowerGrid/Blade untuk modul admin baru.

### Menambah Fitur Student/Lecturer/Alumni/Employee:
1. **Route** → Tambah di `routes/web/{portal}.php` (`student.php`, `lecturer.php`, `alumni.php`, `academic-leader.php`)
2. **Controller** → `app/Http/Controllers/{Portal}/{Feature}Controller.php` → `Inertia::render('{Role}/{Feature}/Index', [...])`
3. **Halaman React** → `resources/js/pages/{Role}/{Feature}/` (gunakan `AdminShell`/layout portal + komponen `Shared`)
4. **Menu** → Tambah di `SidebarMenu::roleMenus()` atau `SidebarMenu::lecturerMenus()`
5. **Service** (opsional) → `app/Support/{Domain}/`

### Menambah Controller (untuk file downloads/exports/PDF only):
```
app/Http/Controllers/{Domain}/{Resource}Controller.php
```
Controller terpisah hanya dipakai untuk operasi non-Inertia: file download, PDF generation, export spreadsheet, webhook/API.

---

## 10. Locale & Language

- **UI Language:** Bahasa Indonesia (semua label, flash message, menu title)
- **Code Language:** English (class names, variable names, method names)
- **Timezone:** Asia/Jakarta
- **Date Format:** Menggunakan Carbon default (Y-m-d H:i:s)

---

## 11. File yang Paling Sering Diubah

| File | Kapan Diubah |
|---|---|
| `config/resources.php` | Setiap tambah CRUD resource baru (menu + permission) |
| `routes/web/admin.php` | Setiap tambah route admin + skip list resource Inertia |
| `app/Http/Controllers/Admin/{Area}/` | Setiap buat/modifikasi CRUD admin Inertia |
| `resources/js/pages/Admin/{Area}/{Resources}/` | Setiap buat halaman admin React |
| `resources/js/components/Shared/Crud/` | Saat mengubah perilaku tabel/form/filter bersama |
| `app/Support/SidebarMenu.php` | Setiap tambah menu untuk role tertentu |
| `app/Support/Inertia/ShellProps.php` | Saat menambah data global sidebar/topbar |
| `database/migrations/` | Setiap tambah/ubah schema |
| `database/seeders/` | Setiap tambah data seeder |

---

## 12. Automated Testing & Domain QA Suite (Pest / PHPUnit)

**Semua pengujian otomatis di NexaCampus dikelola menggunakan Pest PHP dan diorganisir secara modular berdasarkan Domain Bisnis di dalam folder `tests/Feature/[Domain]/`.**

### 12.1 Struktur Domain Test Suite
```
tests/Feature/
├── Academic/       # KRS, Nilai, Kalender, QR Attendance, registrasi, Skripsi/Tugas Akhir
├── Access/         # User, role, permission (Inertia CRUD)
├── Admission/      # PMB, NIM Generator, Konversi Maba
├── Alumni/         # Tracer Study, Job Board, Event
├── Campus/         # Gedung, ruangan, aset, maintenance, reservasi
├── Financial/      # Invoice, Clearance Policy, Payment Verification
├── Organization/   # Approval Engine, Kepegawaian, Workload EDOM, CRUD OR-1..OR-8
├── Publication/    # Pengumuman, berita, agenda, galeri
├── StudentService/ # Cuti Akademik, Fee Integration, Digital ID
└── System/         # Uji sistem umum, smoke render Inertia
```

**Status saat ini:** **818 test / 8.300 assertion** lulus (`php artisan test`, ±185 detik).

### 12.2 Konvensi Penulisan Pest Test (`*Test.php`)
1. **Direct Namespace/Uses:** File pengujian di `tests/Feature/[Domain]/` ditulis langsung dengan `<?php \n\n use App\Models\...` tanpa deklarasi `namespace Tests\Feature\[Domain];` karena `tests/Pest.php` telah dikonfigurasikan dengan `uses(TestCase::class, RefreshDatabase::class)->in('Feature');` yang mengikat seluruh subdirektori secara rekursif.
2. **Setup Helper Function:** Setiap file test domain disarankan memiliki fungsi helper setup lokal (misal: `admissionTestSetup()`, `financialTestSetup()`, `studentServiceTestSetup()`) yang mendaftarkan *roles* (`Role::firstOrCreate(['name' => '...', 'guard_name' => 'web']);`), membuat entitas inti (`Faculty`, `StudyProgram`, `AcademicYear`), dan mengembalikan array asosiatif via `compact()`.
3. **Database Constraints Integrity:** Saat membuat data dummy dalam test (misal `AdmissionApplication` atau `StudentInvoice`), selalu pastikan kolom *NOT NULL* (`phone`, `birth_date`, `gender`, `address`) terisi lengkap dan sertakan `InvoiceItem` agar metode penghitungan seperti `InvoiceAdjustmentService::totalWithAdjustments($invoice)` menghasilkan nilai akurat.
4. **Test CRUD Inertia:** Gunakan `assertInertia(fn (Assert $page) => $page->component('Admin/{Area}/{Resources}/Index')->has('data.rows', ...))`; uji juga `can` permission, filter, create/update, guard delete, dan bulk delete. Contoh: `tests/Feature/Organization/OrganizationOr8CrudTest.php`.
5. **Menjalankan Pengujian:**
   ```bash
   php artisan test                                      # Seluruh 818 test (semua domain)
   php artisan test tests/Feature/Organization           # Satu domain
   php artisan test --filter=AdmissionEndToEndTest       # Satu file/kelas test
   php artisan test --filter=FinancialClearanceEndToEndTest
   ```
6. **Verifikasi wajib setelah perubahan UI:**
   ```bash
   npm run typecheck   # TypeScript
   npm run build       # Bundle Vite
   php artisan view:cache  # Bila menyentuh Blade legacy
   ```

---

## 13. Notes & Existing Documentation

- `.notes/AI_DEVELOPMENT_MEMORY.md` → ⭐ Referensi struktural kilat & memori arsitektur untuk sesi AI development baru
- `.notes/PATTERN-QUICK-REFERENCE.md` → Quick reference untuk pola kode (created for Grade Book feature)
- `.notes/has-been-implemented.md` → Catatan fitur yang sudah diimplementasi
- `.notes/will-be-implemented.md` → Catatan fitur yang akan diimplementasi

---

## 14. Arsitektur & Aturan Ketat Dark Mode (Global Dark Mode & Mandatory UI Rules)

**NexaCampus menggunakan sistem Global Dark Mode Interceptor (`[data-bs-theme=dark]`) di `resources/css/app.css` (Section 1 - 5). Untuk menjaga konsistensi visual modern & premium di seluruh portal (Admin, Lecturer, Student, Alumni), seluruh developer & AI WAJIB mematuhi aturan berikut:**

### 14.1 Larangan Keras (Strict Prohibitions)
1. ❌ **DILARANG KERAS** menggunakan *hardcoded background* terang (`background: white;`, `background: #fff;`, `background: #ffffff;`, `background: #f8fafc;`, `background: #f9fafb;`) di dalam blok `<style>` atau `@push('styles')` tanpa disertai override `[data-bs-theme=dark]` yang sesuai.
2. ❌ **DILARANG KERAS** menulis *inline style* statis berwarna terang pada container/panel, contoh: `<div style="background: white; color: #1f2937">` atau `<div style="background: linear-gradient(...)">` yang tidak memiliki override dark mode di `app.css`.

### 14.2 Standard UI Component Classes
Gunakan class card yang telah terdaftar & ter-normalize secara otomatis di `app.css` agar langsung beradaptasi dengan Dark Mode secara sempurna:
- **Kartu Utama:** `.card`, `.modern-card`, `.stat-card`, `.course-card`, `.material-card`, `.grade-card`, `.advisor-card`, `.service-card`, `.finance-widget`
- **Sub-Panel & Inner Items:** `.section-header`, `.soft-list-item`, `.stat-item`, `.info-item`, `.score-display`, `.advisor-metric`, `.note-preview`, `.selected-file-chip`, `.upload-panel`, `.form-section`

### 14.3 Pola Pembuatan Custom Style Baru (Mandatory Pattern)
Untuk halaman **React**, gunakan variabel tema (`var(--db-card)`, `var(--db-line)`, `var(--db-heading)`, `var(--db-muted)`, `var(--db-brand)`) dan class `db-*`/`crud-*` yang sudah ada — jangan menulis warna hex terang secara inline. Untuk file **Blade legacy**, jika Anda perlu membuat class custom baru di dalam `@push('styles')`, **WAJIB** menyertakan blok override dark mode langsung di bawahnya:

```css
.my-custom-panel {
    background: white;
    border: 2px solid #e2e8f0;
    color: #1f2937;
}

/* WAJIB diserahkan untuk adaptasi Dark Mode */
[data-bs-theme=dark] .my-custom-panel,
body[data-bs-theme=dark] .my-custom-panel {
    background: rgba(43, 28, 67, 0.85) !important;
    border-color: rgba(167, 139, 255, 0.22) !important;
    color: #f3edff !important;
}
```

### 14.4 Kompilasi Asset (`npm run build`)
Setiap kali melakukan perubahan pada `resources/css/app.css` atau aset frontend global, selalu jalankan perintah berikut agar bundle CSS terperbarui di seluruh environment:
```bash
npm run build
```

