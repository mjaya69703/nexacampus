<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\InvoiceItem;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use App\Models\User;
use App\Support\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function adminDashboardSetup(): array
{
    Role::firstOrCreate(['name' => 'superuser', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $superuser = User::factory()->create();
    $superuser->assignRole('superuser');

    return compact('operator', 'superuser');
}

function giveOperatorPermissions(array $names): void
{
    $role = Role::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function giveSuperuserPermissions(array $names): void
{
    $role = Role::where('name', 'superuser')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function adminDashboardStudentSetup(): array
{
    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'code' => 'TI',
        'degree' => 'S1',
        'is_active' => true,
    ]);
    $user = User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'nim' => '2026TI0001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => now()->toDateString(),
        'current_semester' => 1,
        'is_active' => true,
    ]);

    return compact('user', 'student');
}

function createAdminDashboardInvoice(array $setup, float $amount, string $status, ?string $dueDate = null): StudentInvoice
{
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-'.fake()->unique()->numerify('#####'),
        'student_profile_id' => $setup['student']->id,
        'semester' => 1,
        'invoice_type' => 'tuition',
        'total_amount' => $amount,
        'paid_amount' => $status === 'paid' ? $amount : 0,
        'outstanding_amount' => $status === 'paid' ? 0 : $amount,
        'status' => $status,
        'due_date' => $dueDate ?? now()->addDays(10)->toDateString(),
        'issued_at' => now(),
        'created_by' => $setup['user']->id,
    ]);

    InvoiceItem::create([
        'student_invoice_id' => $invoice->id,
        'item_type' => 'fee',
        'description' => 'UKT',
        'amount' => $amount,
    ]);

    return $invoice;
}

it('menampilkan hanya section sesuai izin peran', function () {
    $setup = adminDashboardSetup();
    giveOperatorPermissions(['admission-application.viewAny', 'student-invoice.viewAny']);

    $this->actingAs($setup['operator'])
        ->withSession(['active_role' => 'operator'])
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('shell')
            ->has('hero')
            ->where('hero.sectionCount', 2)
            ->has('sections', 2)
            ->where('sections.0.key', 'admission')
            ->where('sections.1.key', 'financial'));
});

it('mengembalikan sections kosong untuk peran tanpa izin operasional', function () {
    $setup = adminDashboardSetup();

    $this->actingAs($setup['operator'])
        ->withSession(['active_role' => 'operator'])
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('hero.sectionCount', 0)
            ->has('sections', 0));
});

it('menjaga system-health dengan permission dashboard.manage bukan nama role', function () {
    $setup = adminDashboardSetup();

    // Peran biasa (bukan superuser) dengan dashboard.manage BOLEH melihat.
    giveOperatorPermissions(['dashboard.manage']);

    $this->actingAs($setup['operator'])
        ->withSession(['active_role' => 'operator'])
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('sections', 1)
            ->where('sections.0.key', 'system-health'));

    // Superuser TANPA dashboard.manage TIDAK melihat (tidak ada cabang nama role).
    giveSuperuserPermissions(['admission-application.viewAny']);

    $keys = $this->actingAs($setup['superuser'])
        ->withSession(['active_role' => 'superuser'])
        ->get('/admin/dashboard')
        ->assertOk()
        ->viewData('page')['props']['sections'];

    expect(collect($keys)->pluck('key')->all())->not->toContain('system-health');
});

it('menampilkan aktivitas sistem untuk pemegang activity-log.viewAny', function () {
    $setup = adminDashboardSetup();
    giveOperatorPermissions(['activity-log.viewAny']);

    Activity::create(['log_name' => 'default', 'description' => 'Uji aktivitas']);

    $response = $this->actingAs($setup['operator'])
        ->withSession(['active_role' => 'operator'])
        ->get('/admin/dashboard')
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->has('sections', 1)
        ->where('sections.0.key', 'activity')
        ->has('sections.0.rows', 1));

    // Urutan aktivitas sedetik bisa seri; cukup pastikan catatan kita ikut terkirim.
    $items = $response->viewData('page')['props']['sections'][0]['rows'][0]['items'];

    expect(collect($items)->pluck('title')->all())->toContain('Uji aktivitas');
});

it('menampilkan seluruh 8 section untuk peran berizin penuh', function () {
    $setup = adminDashboardSetup();
    giveSuperuserPermissions([
        'admission-application.viewAny', 'student-invoice.viewAny', 'payment.viewAny',
        'course-offering.viewAny', 'study-plan.viewAny', 'service-letter-request.viewAny',
        'student-complaint.viewAny', 'employee-profile.viewAny', 'tridharma-record.viewAny',
        'announcement.viewAny', 'alumni-profile.viewAny', 'dashboard.manage', 'activity-log.viewAny',
    ]);

    $this->actingAs($setup['superuser'])
        ->withSession(['active_role' => 'superuser'])
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('hero.sectionCount', 8)
            ->has('sections', 8)
            ->where('sections.0.key', 'admission')
            ->where('sections.1.key', 'financial')
            ->where('sections.2.key', 'academic')
            ->where('sections.3.key', 'student-services')
            ->where('sections.4.key', 'organization')
            ->where('sections.5.key', 'publication-alumni')
            ->where('sections.6.key', 'system-health')
            ->where('sections.7.key', 'activity'));

    expect(route('admin.dashboard.index'))->toBe(url('/admin/dashboard'));
});

it('mengarahkan menu dashboard role baru ke kokpit admin adaptif', function () {
    $setup = adminDashboardSetup();

    // Role 'operator' tidak punya route operator.dashboard.index.
    $response = $this->actingAs($setup['operator'])
        ->withSession(['active_role' => 'operator'])
        ->get('/admin/dashboard')
        ->assertOk();

    $menus = $response->viewData('page')['props']['shell']['menus'];
    $dashboard = collect($menus)->firstWhere('id', 'common-dashboard');

    expect($dashboard['url'])->toBe(route('admin.dashboard.index'));
});

it('menghitung pembayaran terverifikasi dengan status verified', function () {
    adminDashboardSetup();
    $setup = adminDashboardStudentSetup();
    $invoice = createAdminDashboardInvoice($setup, 5_000_000, 'paid');

    Payment::create([
        'payment_number' => 'PAY-001',
        'student_invoice_id' => $invoice->id,
        'student_profile_id' => $setup['student']->id,
        'amount' => 5_000_000,
        'payment_method' => 'bank_transfer',
        'status' => 'verified',
        'verified_by' => $setup['user']->id,
        'verified_at' => now(),
    ]);

    Payment::create([
        'payment_number' => 'PAY-002',
        'student_invoice_id' => $invoice->id,
        'student_profile_id' => $setup['student']->id,
        'amount' => 9_999_999,
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
    ]);

    $stats = app(DashboardService::class)->financial();

    expect($stats['paid_sum'])->toBe(5_000_000.0)
        ->and($stats['recent_payments'])->toHaveCount(1)
        ->and($stats['recent_payments'][0]['reference_number'])->toBe('PAY-001')
        ->and($stats['recent_payments'][0]['student_name'])->not->toBe('-');
});

it('menghitung tagihan jatuh tempo dari outstanding amount', function () {
    adminDashboardSetup();
    $setup = adminDashboardStudentSetup();

    createAdminDashboardInvoice($setup, 3_000_000, 'overdue', now()->subDays(2)->toDateString());
    createAdminDashboardInvoice($setup, 1_500_000, 'pending', now()->subDays(1)->toDateString());
    createAdminDashboardInvoice($setup, 2_000_000, 'pending', now()->addDays(7)->toDateString());

    $stats = app(DashboardService::class)->financial();

    expect($stats['overdue_count'])->toBe(2)
        ->and($stats['overdue_amount'])->toBe(4_500_000.0);
});

it('menghitung tren pendaftaran bulanan untuk admission', function () {
    adminDashboardSetup();

    $stats = app(DashboardService::class)->admission();

    expect($stats['monthly_trend'])->toHaveCount(6)
        ->and($stats['applicants_count'])->toBe(0);
});
