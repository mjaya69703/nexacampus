<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function financialOverviewSetup(): array
{
    DB::table('systems')->updateOrInsert(['id' => 1], [
        'app_name' => 'NexaCampus Test',
        'app_version' => 'test',
        'app_description' => 'NexaCampus Test',
        'app_url' => 'https://example.test',
        'app_email' => 'campus@example.test',
        'is_installed' => true,
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    $role = Role::findOrCreate('admin', 'web');
    foreach (['financial-dashboard.viewAny', 'financial-report.viewAny'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FO'.$suffix, 'code' => 'FO'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PO'.$suffix, 'code' => 'PO'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YO'.$suffix, 'code' => 'YO'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'O'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);

    $invoice = app(App\Support\Financial\InvoiceGenerationService::class)->createCustomInvoice(
        $student,
        [['item_type' => 'fee', 'description' => 'SPP', 'amount' => 4000000]],
        'tuition',
        now()->subWeek()->format('Y-m-d'),
        $year,
        1,
        null,
        $admin->id,
        true,
    );

    $payment = app(App\Support\Financial\PaymentProcessingService::class)->submitProof(
        $invoice->fresh(),
        1000000,
        'bukti/test.jpg',
        'bank_transfer',
        null,
        null,
        $studentUser->id,
    );
    app(App\Support\Financial\PaymentProcessingService::class)->verify($payment, $admin->id);

    return compact('admin', 'invoice');
}

function financialOverviewActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

it('menampilkan dashboard keuangan dengan agregat benar', function () {
    $setup = financialOverviewSetup();

    financialOverviewActingAs($setup['admin'])
        ->get(route('admin.financial.dashboard.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/Dashboard')
            ->where('stats.verifiedPayments', 1000000)
            ->where('stats.outstanding', 3000000)
            ->where('collection.paidPercent', 25)
            ->where('collection.outstandingPercent', 75)
            ->has('trend', 6)
            ->has('aging', 5)
            ->has('recentPayments', 1));
});

it('menampilkan laporan keuangan dengan rekap jenis', function () {
    $setup = financialOverviewSetup();

    financialOverviewActingAs($setup['admin'])
        ->get(route('admin.financial.financial-reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/Report')
            ->where('stats.verifiedPayments', 1000000)
            ->where('stats.outstanding', 3000000)
            ->has('typeRows', 1));
});

it('mengunduh ekspor laporan csv dan pdf', function () {
    $setup = financialOverviewSetup();

    financialOverviewActingAs($setup['admin'])
        ->get(route('admin.financial.reports.export.csv'))
        ->assertOk();

    financialOverviewActingAs($setup['admin'])
        ->get(route('admin.financial.reports.export.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
