<?php

use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function financialSmokeSetup(): User
{
    Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $user = User::factory()->create();
    $user->assignRole('finance');

    return $user;
}

function financialSmokePermission(string $name): void
{
    $role = Role::where('name', 'finance')->firstOrFail();
    $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
}

it('merender seluruh halaman admin financial', function (string $routeName, string $permission) {
    $user = financialSmokeSetup();
    financialSmokePermission($permission);

    $this->actingAs($user)
        ->withSession(['active_role' => 'finance'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    ['admin.financial.dashboard.index', 'financial-dashboard.viewAny'],
    ['admin.financial.tuition-fees.index', 'tuition-fee.viewAny'],
    ['admin.financial.tuition-fees.create', 'tuition-fee.create'],
    ['admin.financial.student-invoices.index', 'student-invoice.viewAny'],
    ['admin.financial.student-invoices.create', 'student-invoice.create'],
    ['admin.financial.payments.index', 'payment.viewAny'],
    ['admin.financial.installment-requests.index', 'installment-request.viewAny'],
    ['admin.financial.financial-holds.index', 'financial-hold.viewAny'],
    ['admin.financial.clearance-policies.index', 'clearance-policy.viewAny'],
    ['admin.financial.clearance-policies.create', 'clearance-policy.create'],
    ['admin.financial.invoice-schedules.index', 'invoice-schedule.viewAny'],
    ['admin.financial.invoice-schedules.create', 'invoice-schedule.create'],
    ['admin.financial.scholarships.index', 'scholarship.viewAny'],
    ['admin.financial.scholarships.create', 'scholarship.create'],
    ['admin.financial.student-scholarships.index', 'student-scholarship.viewAny'],
    ['admin.financial.student-scholarships.create', 'student-scholarship.create'],
    ['admin.financial.invoice-adjustments.index', 'invoice-adjustment.viewAny'],
    ['admin.financial.student-credits.index', 'student-credit.viewAny'],
    ['admin.financial.financial-reports.index', 'financial-report.viewAny'],
]);
