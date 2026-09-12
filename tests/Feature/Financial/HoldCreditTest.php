<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\FinancialHold;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function holdCreditSetup(): array
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
    foreach ([
        'financial-hold.viewAny', 'financial-hold.view', 'financial-hold.update',
        'student-credit.viewAny', 'student-credit.create',
    ] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FH'.$suffix, 'code' => 'FH'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PH'.$suffix, 'code' => 'PH'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YH'.$suffix, 'code' => 'YH'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    $studentUser = App\Models\User::factory()->create();
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $program->id,
        'nim' => 'H'.$suffix,
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'entry_date' => '2026-08-01',
        'current_semester' => 1,
        'is_active' => true,
    ]);

    $invoice = app(App\Support\Financial\InvoiceGenerationService::class)->createCustomInvoice(
        $student,
        [['item_type' => 'fee', 'description' => 'SPP', 'amount' => 1000000]],
        'tuition',
        now()->subMonth()->format('Y-m-d'),
        $year,
        1,
        null,
        $admin->id,
        true,
    );

    return compact('admin', 'student', 'invoice');
}

function holdCreditActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

it('evaluasi clearance membuat hold untuk tunggakan', function () {
    $setup = holdCreditSetup();

    $result = app(App\Support\Financial\FinancialClearanceService::class)->evaluate($setup['student']);

    expect(FinancialHold::where('student_profile_id', $setup['student']->id)->where('status', 'active')->exists())->toBeTrue();
});

it('menampilkan daftar dan detail hold', function () {
    $setup = holdCreditSetup();
    app(App\Support\Financial\FinancialClearanceService::class)->evaluate($setup['student']);

    holdCreditActingAs($setup['admin'])
        ->get(route('admin.financial.financial-holds.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/FinancialHold/Index'));

    $hold = FinancialHold::where('student_profile_id', $setup['student']->id)->first();

    holdCreditActingAs($setup['admin'])
        ->get(route('admin.financial.financial-holds.show', $hold))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/FinancialHold/Show'));
});

it('melepas dan menangguhkan hold', function () {
    $setup = holdCreditSetup();
    app(App\Support\Financial\FinancialClearanceService::class)->evaluate($setup['student']);
    $hold = FinancialHold::where('student_profile_id', $setup['student']->id)->first();

    holdCreditActingAs($setup['admin'])
        ->post(route('admin.financial.financial-holds.waive', $hold), [
            'waived_until' => now()->addWeek()->format('Y-m-d'),
            'release_notes' => 'Dispensasi wisuda',
        ])
        ->assertRedirect();

    expect($hold->fresh()->status)->toBe('waived');

    holdCreditActingAs($setup['admin'])
        ->post(route('admin.financial.financial-holds.release', $hold), [
            'release_notes' => 'Sudah lunas manual',
        ])
        ->assertRedirect();

    expect($hold->fresh()->status)->toBe('released');
});

it('mencatat refund dan koreksi deposit', function () {
    $setup = holdCreditSetup();

    holdCreditActingAs($setup['admin'])
        ->post(route('admin.financial.student-credits.record'), [
            'student_profile_ids' => [$setup['student']->id],
            'transaction_type' => 'manual_adjustment',
            'amount' => 500000,
            'notes' => 'Koreksi saldo awal',
        ])
        ->assertRedirect();

    expect((float) StudentCreditBalance::where('student_profile_id', $setup['student']->id)->first()->balance)->toBe(500000.0);

    holdCreditActingAs($setup['admin'])
        ->post(route('admin.financial.student-credits.record'), [
            'student_profile_ids' => [$setup['student']->id],
            'transaction_type' => 'refund',
            'amount' => 200000,
        ])
        ->assertRedirect();

    expect((float) StudentCreditBalance::where('student_profile_id', $setup['student']->id)->first()->balance)->toBe(300000.0);
});

it('menolak refund melebihi saldo', function () {
    $setup = holdCreditSetup();

    holdCreditActingAs($setup['admin'])
        ->post(route('admin.financial.student-credits.record'), [
            'student_profile_ids' => [$setup['student']->id],
            'transaction_type' => 'refund',
            'amount' => 100000,
        ])
        ->assertRedirect();

    expect((float) (StudentCreditBalance::where('student_profile_id', $setup['student']->id)->first()->balance ?? 0))->toBe(0.0);
});

it('menampilkan daftar saldo deposit', function () {
    $setup = holdCreditSetup();
    app(App\Support\Financial\StudentCreditService::class)->manualAdjustment($setup['student'], 250000, $setup['admin']->id);

    holdCreditActingAs($setup['admin'])
        ->get(route('admin.financial.student-credits.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/StudentCredit/Index')
            ->where('stats.students', 1));
});
