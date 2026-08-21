<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\InvoiceItem;
use App\Models\Financial\Payment;
use App\Models\Financial\StudentInvoice;
use App\Models\User;
use App\Support\Dashboard\DashboardConfig;
use App\Support\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function dashboardTestSetup(): array
{
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('finance');

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'code' => 'TI',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $user->assignRole('student');

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

    return compact('admin', 'faculty', 'program', 'user', 'student');
}

function createDashboardInvoice(array $setup, float $amount, string $status, ?string $dueDate = null): StudentInvoice
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
        'created_by' => $setup['admin']->id,
    ]);

    InvoiceItem::create([
        'student_invoice_id' => $invoice->id,
        'item_type' => 'fee',
        'description' => 'UKT',
        'amount' => $amount,
    ]);

    return $invoice;
}

it('menghitung pembayaran terverifikasi dengan status verified', function () {
    $setup = dashboardTestSetup();
    $invoice = createDashboardInvoice($setup, 5_000_000, 'paid');

    Payment::create([
        'payment_number' => 'PAY-001',
        'student_invoice_id' => $invoice->id,
        'student_profile_id' => $setup['student']->id,
        'amount' => 5_000_000,
        'payment_method' => 'bank_transfer',
        'status' => 'verified',
        'verified_by' => $setup['admin']->id,
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

it('menghitung mahasiswa aktif dengan status Aktif (Title Case)', function () {
    dashboardTestSetup();

    $stats = app(DashboardService::class)->academic();

    expect($stats['active_students_count'])->toBe(1);
});

it('menghitung tagihan jatuh tempo dari outstanding amount', function () {
    $setup = dashboardTestSetup();

    createDashboardInvoice($setup, 3_000_000, 'overdue', now()->subDays(2)->toDateString());
    createDashboardInvoice($setup, 1_500_000, 'pending', now()->subDays(1)->toDateString());
    createDashboardInvoice($setup, 2_000_000, 'pending', now()->addDays(7)->toDateString());

    $stats = app(DashboardService::class)->financial();

    expect($stats['overdue_count'])->toBe(2)
        ->and($stats['overdue_amount'])->toBe(4_500_000.0);
});

it('menghitung tren pendaftaran bulanan untuk admission', function () {
    dashboardTestSetup();

    $stats = app(DashboardService::class)->admission();

    expect($stats['monthly_trend'])->toHaveCount(6)
        ->and($stats['applicants_count'])->toBe(0);
});

it('menyimpan dan memuat konfigurasi widget per user', function () {
    $setup = dashboardTestSetup();

    $config = DashboardConfig::defaults();
    $config['admission']['trend_chart'] = false;

    $setup['user']->forceFill(['dashboard_widget_config' => $config])->save();

    $loaded = DashboardConfig::forUser($setup['user']->fresh());

    expect($loaded['admission']['trend_chart'])->toBeFalse()
        ->and($loaded['financial']['stat_card'])->toBeTrue();

    $setup['user']->forceFill(['dashboard_widget_config' => null])->save();
    expect(DashboardConfig::forUser($setup['user']->fresh()))->toBe(DashboardConfig::defaults());
});

it('merender halaman dashboard admin tanpa error', function () {
    $setup = dashboardTestSetup();

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    foreach (['admission-application.viewAny', 'student-invoice.viewAny'] as $permissionName) {
        $permission = Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
        // ActivePermission membaca permission dari ROLE, bukan dari user
        $financeRole = Spatie\Permission\Models\Role::where('name', 'finance')->first();
        $financeRole->givePermissionTo($permission);
        $setup['admin']->givePermissionTo($permission);
    }

    $response = $this->actingAs($setup['admin'])
        ->withSession(['active_role' => 'finance'])
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('Control Center Dashboard');

    // Snapshot komponen anak WAJIB ada di HTML (regresi: dynamic livewire tag tidak terkompilasi)
    $response->assertSee('admin.dashboard.module-admission', false)
        ->assertSee('admin.dashboard.module-financial', false);
});

it('merender dashboard lengkap untuk superuser dengan semua modul', function () {
    $setup = dashboardTestSetup();
    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superuser', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    // Superuser mendapat seluruh permission via resources:sync — replikasi minimal
    // (ActivePermission membaca permission dari ROLE, bukan dari user)
    $superRole = Spatie\Permission\Models\Role::where('name', 'superuser')->first();
    $superRole->syncPermissions(collect([
        'admission-application.viewAny', 'student-invoice.viewAny', 'payment.viewAny',
        'course-offering.viewAny', 'study-plan.viewAny', 'service-letter-request.viewAny',
        'student-complaint.viewAny', 'employee-profile.viewAny', 'tridharma-record.viewAny',
        'announcement.viewAny', 'alumni-profile.viewAny', 'dashboard.manage',
    ])->map(fn ($name) => Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ])));
    $setup['admin']->assignRole('superuser');

    $this->actingAs($setup['admin'])
        ->withSession(['active_role' => 'superuser'])
        ->get('/admin/dashboard')
        ->assertOk();

    $html = $this->actingAs($setup['admin'])
        ->withSession(['active_role' => 'superuser'])
        ->get('/admin/dashboard')->getContent();

    foreach ([
        'admin.dashboard.module-system-health',
        'admin.dashboard.module-admission',
        'admin.dashboard.module-financial',
        'admin.dashboard.module-academic',
        'admin.dashboard.module-student-services',
        'admin.dashboard.module-organization',
        'admin.dashboard.module-publication-alumni',
    ] as $componentName) {
        expect($html)->toContain($componentName);
    }
});

it('child modul dapat dirender langsung via Livewire tanpa error', function () {
    $setup = dashboardTestSetup();
    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superuser', 'guard_name' => 'web']);
    $setup['admin']->assignRole('superuser');

    $this->withSession(['active_role' => 'superuser']);

    \Livewire\Livewire::actingAs($setup['admin'])
        ->test('admin.dashboard.module-admission')
        ->assertStatus(200)
        ->assertSee('Modul PMB');
});
