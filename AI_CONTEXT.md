# NexaCampus - AI Development Context
> **Baca file ini di awal setiap sesi.** File ini berisi seluruh konteks yang diperlukan AI untuk langsung memahami arsitektur, pola kode, dan konvensi proyek NexaCampus tanpa penjelasan ulang dari user.

---

## 1. Overview Proyek

**NexaCampus** adalah Sistem Informasi Akademik (SIAKAD) perguruan tinggi terpadu berbasis **Laravel 12** + **Livewire 4** + **Flux UI** + **PowerGrid 6**. Proyek ini full-stack single-monolith dengan multi-role access (superuser, admin, lecturer, student, academic-leader, alumni).

### Tech Stack
| Layer | Teknologi |
|---|---|
| **Backend Framework** | Laravel 12 (PHP 8.2+) |
| **Frontend Reactivity** | Livewire 4 (Full-Page Components + Anonymous Blade Components) |
| **UI Kit** | Livewire Flux v2 + Bootstrap 5 (Tabler Admin Theme) |
| **CSS** | TailwindCSS 4 via Vite plugin + custom CSS variables |
| **DataTable** | PowerGrid 6 (Laravel Livewire PowerGrid) |
| **Authorization** | Spatie Laravel Permission v7 (multi-role, session-based active role) |
| **Activity Log** | Spatie Laravel Activity Log v4 |
| **Build Tool** | Vite 7 + laravel-vite-plugin |
| **Database** | MySQL (DB: `nexacampus_test`) |
| **Queue** | Database driver |
| **Session/Cache** | Database driver |
| **Rich Text Editor** | Mantix Jodit Text Editor |
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
Proyek ini mengorganisir kode berdasarkan **domain bisnis**, BUKAN berdasarkan tipe file. Setiap domain memiliki mirror structure di Models, Livewire, Views, Controllers, Mail, Support:

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
│   │   ├── Controllers/           # Thin controllers (file downloads, PDF, exports only)
│   │   │   ├── Academic/
│   │   │   ├── Admin/
│   │   │   ├── Alumni/
│   │   │   ├── Financial/
│   │   │   ├── Lecturer/
│   │   │   ├── Notifications/
│   │   │   ├── Organization/
│   │   │   ├── Student/
│   │   │   └── StudentService/
│   │   └── Middleware/
│   │       ├── Authenticate.php
│   │       ├── EnsureRoleIsActive.php          # middleware: active_role / active_role:student
│   │       ├── EnsureActiveRoleHasPermission.php # middleware: active_permission:resource.action
│   │       ├── EnsureFinancialClearance.php     # middleware: financial_clearance
│   │       └── EnsureSystemIsInstalled.php      # middleware: is_installed
│   ├── Livewire/
│   │   ├── BasePowerGridTable.php              # ⭐ BASE CLASS untuk semua PowerGrid table
│   │   ├── Concerns/
│   │   │   └── ExportsPowerGridWithPhpSpreadsheet.php  # Trait export XLS/CSV
│   │   ├── Academic/                           # PowerGrid tables untuk modul akademik
│   │   ├── Access/
│   │   ├── Admission/
│   │   ├── Alumni/
│   │   ├── Campus/
│   │   ├── Financial/
│   │   ├── Lecturer/                           # Sub-components (CourseMaterials/, StudentGrades/)
│   │   ├── Organization/
│   │   ├── Publication/
│   │   ├── StudentService/
│   │   └── System/
│   ├── Mail/                                   # Mailable classes per domain
│   │   ├── Academic/
│   │   ├── Financial/
│   │   └── StudentService/
│   ├── Models/                                 # Eloquent models per domain
│   │   ├── User.php                            # Root user model (multi-profile)
│   │   ├── Academic/   (43 models)
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
│   ├── css/app.css                             # Custom CSS + CSS variables + PowerGrid theming
│   ├── js/app.js                               # Flatpickr, Leaflet, PowerGrid, TomSelect init
│   ├── saas/app.scss                           # SCSS (minimal)
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php                   # ⭐ Main admin layout (Tabler sidebar)
│       │   ├── home.blade.php                  # Public/home layout
│       │   ├── setup.blade.php                 # Setup wizard layout
│       │   └── errors.blade.php                # Error pages layout
│       ├── components/                         # ⭐ Livewire anonymous Blade components
│       │   ├── admin/{domain}/{resource}/      # Admin CRUD views
│       │   ├── student/{feature}/              # Student portal views
│       │   ├── lecturer/{feature}/             # Lecturer portal views
│       │   ├── academic-leader/{feature}/      # Academic leader views
│       │   ├── alumni/{feature}/               # Alumni portal views
│       │   ├── employee/{feature}/             # Employee self-service views
│       │   ├── admission/                      # Public admission views
│       │   ├── auth/                           # Login/select-role views
│       │   └── powergrid/                      # Custom PowerGrid templates
│       ├── templates/                          # Reusable templates
│       ├── exports/                            # PDF/export templates
│       ├── pdf/                                # DomPDF templates
│       └── vendor/                             # Published vendor views
└── routes/
    └── web.php                                 # ⭐ All routes (admin, student, lecturer, etc.)
```

---

## 3. Pola Kode Inti (Core Patterns)

### 3.1 CRUD Admin Resource Pattern (Config-Driven)

**Ini adalah pola paling penting.** Semua CRUD admin dikelola via `config/resources.php` dan di-route secara otomatis.

#### Step 1: Registrasi di `config/resources.php`
```php
[
    'resource' => 'your-resource',      // Singular kebab-case (dipakai untuk permission: your-resource.viewAny)
    'plural' => 'your-resources',       // Plural (dipakai untuk URL)
    'area' => 'academic',               // Domain area (academic/admission/financial/etc.)
    'component' => 'admin.academic.your-resources',  // Livewire component prefix
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

#### Step 2: Model (`app/Models/{Domain}/YourResource.php`)
```php
<?php
namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Model;
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

#### Step 3: PowerGrid Table (`app/Livewire/{Domain}/YourResourceTable.php`)
```php
<?php
namespace App\Livewire\Academic;

use App\Livewire\BasePowerGridTable;
use App\Livewire\Concerns\ExportsPowerGridWithPhpSpreadsheet;
use App\Models\Academic\YourResource;
use App\Support\ActivePermission;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class YourResourceTable extends BasePowerGridTable
{
    use ExportsPowerGridWithPhpSpreadsheet;

    public string $tableName = 'yourResourceTable';
    protected ?string $bulkActionModel = YourResource::class;
    protected ?string $bulkActionPermissionPrefix = 'your-resource';
    protected string $bulkActionItemLabel = 'data';

    public function setUp(): array
    {
        return $this->powerGridSetUp(showToggleColumns: true, showExport: true);
    }

    public function datasource(): Builder
    {
        return YourResource::query()->withCount('relatedModel');
    }

    public function fields(): PowerGridFields { /* ... */ }
    public function columns(): array { /* ... */ }
    public function filters(): array { /* ... */ }

    // Edit action → redirect to edit page
    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirectRoute('admin.academic.your-resources.edit', ['id' => $rowId]);
    }

    // Delete action → SweetAlert confirmation → Livewire dispatch
    #[On('delete')]
    public function delete($id): void { /* SweetAlert + dispatch('deleteItem') */ }

    #[On('deleteItem')]
    public function deleteItem($id = null): void { /* ActivePermission check + delete */ }

    // Action buttons (per-row)
    public function actions(YourResource $row): array
    {
        $actions = [];
        if (ActivePermission::check('your-resource.update')) {
            $actions[] = Button::add('edit')->slot('<i class="fa fa-edit"></i>')->class('btn btn-primary')->dispatch('edit', ['rowId' => $row->id]);
        }
        if (ActivePermission::check('your-resource.delete')) {
            $actions[] = Button::add('delete')->slot('<i class="fa fa-trash"></i>')->class('btn btn-danger')->dispatch('delete', ['id' => $row->id]);
        }
        return $actions;
    }
}
```

#### Step 4: Views (Anonymous Blade Components)
**PENTING:** Views ditulis sebagai **anonymous Blade Livewire components** dengan `⚡` prefix di nama file.

**Index**: `resources/views/components/admin/academic/your-resources/⚡index.blade.php`
```blade
<?php
use Livewire\Component;

new class extends Component {
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Daftar Your Resources',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Your Resources</h3>
            <div class="card-tools d-flex gap-2 flex-wrap justify-content-end">
                @activecan('your-resource.create')
                    <a href="{{ route('admin.academic.your-resources.create') }}" class="btn btn-ghost-primary">
                        <i class="fa fa-plus me-2"></i> Tambah
                    </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:academic.your-resource-table />
        </div>
    </div>
</div>
```

**Create**: `resources/views/components/admin/academic/your-resources/⚡create.blade.php`
```blade
<?php
use App\Models\Academic\YourResource;
use Livewire\Component;

new class extends Component {
    public array $form = [];

    public function mount(): void
    {
        $this->form = ['name' => '', 'code' => '', 'is_active' => true, 'desc' => ''];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:20|unique:your_resources,code',
            'form.is_active' => 'boolean',
            'form.desc' => 'nullable|string',
        ]);

        YourResource::create([...$validated['form'], 'created_by' => auth()->id()]);
        session()->flash('success', 'Data berhasil ditambahkan.');
        $this->redirectRoute('admin.academic.your-resources.index');
    }

    public function cancel(): void { $this->redirectRoute('admin.academic.your-resources.index'); }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Academic Management',
            'pages' => 'Tambah Your Resource',
        ]);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tambah Your Resource</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label>Nama <span class="text-danger">*</span></label>
                <input type="text" class="form-control" wire:model.defer="form.name">
                @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <!-- ... more fields ... -->
            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="save">
                    <i class="fas fa-save me-2"></i> Simpan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
```

#### Step 5: Sync permissions & menus
```bash
php artisan app:sync-resources   # Runs SyncPermissions + SyncMenus
```

---

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

#### Admin Routes (Auto-generated via `Route::crudLivewire()` macro)
```php
// Defined in AppServiceProvider::boot()
Route::macro('crudLivewire', function($resource, $componentPrefix, $only, $uriPrefix) {
    // Generates: admin/{area}/{resources}, admin/{area}/{resources}/create, etc.
    // With middleware: active_permission:{singular}.{action}
    // Named: {baseName}.index, {baseName}.create, etc.
});

// Usage in web.php:
Route::middleware('active_role')->prefix('admin')->as('admin.')->group(function () {
    foreach (ResourceRegistry::all() as $resource) {
        Route::crudLivewire($resource['plural'], $resource['component'], $resource['actions'], $resource['area']);
    }
});
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

#### Livewire Full-Page Route:
```php
Route::livewire('/path', 'component.name')->name('route.name');
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

### 3.5 View Layout System

#### Layout: `layouts.app`
```php
return $this->view()->layout('layouts.app', [
    'menus' => 'Menu Category Name',    // Breadcrumb/sidebar context
    'pages' => 'Page Title',            // Page title in header
]);
```

#### Global View Variables (dari AppServiceProvider):
- `$campus` → Campus model (nama, logo, alamat)
- `$system` → System model (app_name, app_description)
- `$user` → Auth::user()
- `$activeRole` → session('active_role')

#### Alert Component:
```blade
<x-alert />  {{-- Renders session flash messages (success/error/warning/info) --}}
```

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

---

## 4. Naming Conventions

### PHP Naming:
| Type | Convention | Example |
|---|---|---|
| Model | PascalCase singular | `StudentProfile`, `CourseOffering` |
| Table | snake_case plural | `student_profiles`, `course_offerings` |
| Migration | timestamp + descriptive | `2026_03_21_171532_create_faculties_table.php` |
| Livewire Table | `{Resource}Table` | `FacultyTable`, `CourseOfferingTable` |
| Enum | PascalCase | `AnnouncementPriority` |
| Service | `{Name}Service` | `TranscriptSyncService` |
| Seeder | `{Domain}Seeder` | `AcademicSeeder` |
| Middleware | `Ensure{Condition}` | `EnsureRoleIsActive` |
| Command | Imperative verb | `SyncPermissions`, `PruneNotificationLogs` |

### View File Naming:
| Type | Path Pattern |
|---|---|
| Admin CRUD view | `components/admin/{domain}/{resources}/⚡{action}.blade.php` |
| Student view | `components/student/{feature}/⚡{page}.blade.php` |
| Lecturer view | `components/lecturer/{feature}/⚡{page}.blade.php` |
| Alumni view | `components/alumni/{feature}/⚡{page}.blade.php` |
| Auth view | `components/auth/⚡{page}.blade.php` |

**⚡ prefix** = Livewire anonymous component (inline PHP class + Blade template in one file).

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

### CSS Framework: Bootstrap 5 (Tabler Admin) + TailwindCSS 4
- Bootstrap classes untuk layout utama: `card`, `form-control`, `btn`, `row/col-*`
- TailwindCSS untuk utility tambahan: `w-full`, flex utilities
- Custom CSS variables di `resources/css/app.css` untuk theming

### Icon: Font Awesome 6
```html
<i class="fas fa-plus"></i>     <!-- Solid -->
<i class="fa fa-edit"></i>      <!-- Regular -->
```

### Button Styles:
```html
<button class="btn btn-primary">Primary Action</button>
<a class="btn btn-ghost-primary">Ghost/Outline</a>
<button class="btn btn-secondary">Cancel</button>
<button class="btn btn-danger">Delete</button>
```

### Confirmation Dialog: SweetAlert2 (via Livewire `$this->js()`)
```php
$this->js('
    Swal.fire({
        title: "Hapus data?",
        text: "Data tidak bisa dikembalikan!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Ya hapus",
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            $wire.deleteItem('.$id.')
        }
    });
');
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

```bash
php artisan app:sync-resources          # Sync permissions + menus from config/resources.php
php artisan app:sync-permissions        # Sync permissions only
php artisan app:sync-menus              # Sync menus only
php artisan app:evaluate-financial-holds # Evaluate student financial holds
php artisan app:refresh-financial-overdue # Mark overdue invoices
php artisan app:run-invoice-schedules   # Generate invoices from schedules
php artisan app:send-assignment-reminders # Send assignment due reminders
php artisan app:prune-notification-logs  # Clean old notification logs
php artisan app:generate-vapid-keys     # Generate Web Push VAPID keys
```

---

## 9. Key Integration Points

### Menambah Modul/Resource Baru:
1. **Migration** → `database/migrations/`
2. **Model** → `app/Models/{Domain}/`
3. **Config** → Tambah entry di `config/resources.php`
4. **PowerGrid Table** → `app/Livewire/{Domain}/{Resource}Table.php` extends `BasePowerGridTable`
5. **Views** → `resources/views/components/admin/{domain}/{resources}/⚡{action}.blade.php`
6. **Sync** → `php artisan app:sync-resources`

### Menambah Fitur Student/Lecturer/Alumni:
1. **Route** → Tambah di `routes/web.php` di group yang sesuai
2. **View** → `resources/views/components/{role}/{feature}/⚡{page}.blade.php`
3. **Menu** → Tambah di `SidebarMenu::roleMenus()` atau `SidebarMenu::lecturerMenus()`
4. **Service** (opsional) → `app/Support/{Domain}/`

### Menambah Controller (untuk file downloads/exports only):
```
app/Http/Controllers/{Domain}/{Resource}Controller.php
```
Controllers hanya dipakai untuk non-Livewire operations: file downloads, PDF generation, API endpoints.

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
| `config/resources.php` | Setiap tambah CRUD resource baru |
| `routes/web.php` | Setiap tambah route baru |
| `app/Support/SidebarMenu.php` | Setiap tambah menu untuk role tertentu |
| `app/Livewire/{Domain}/` | Setiap buat PowerGrid table baru |
| `resources/views/components/` | Setiap buat view/page baru |
| `database/migrations/` | Setiap tambah/ubah schema |
| `database/seeders/` | Setiap tambah data seeder |

---

## 12. Notes & Existing Documentation

- `.notes/PATTERN-QUICK-REFERENCE.md` → Quick reference untuk pola kode (created for Grade Book feature)
- `.notes/has-been-implemented.md` → Catatan fitur yang sudah diimplementasi
- `.notes/will-be-implemented.md` → Catatan fitur yang akan diimplementasi
