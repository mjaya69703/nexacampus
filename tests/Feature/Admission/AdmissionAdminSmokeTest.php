<?php

use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function admissionAdminSmokeSetup(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function admissionAdminSmokePermission(string $name): void
{
    $role = Role::where('name', 'admin')->firstOrFail();
    $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
}

it('merender halaman admin penerimaan mahasiswa', function (string $routeName, string $permission) {
    $user = admissionAdminSmokeSetup();
    admissionAdminSmokePermission($permission);

    $this->actingAs($user)
        ->withSession(['active_role' => 'admin'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    ['admin.admission.admission-periods.index', 'admission-period.viewAny'],
    ['admin.admission.admission-periods.create', 'admission-period.create'],
    ['admin.admission.admission-applications.index', 'admission-application.viewAny'],
    ['admin.admission.admission-exam-schedules.index', 'admission-exam-schedule.viewAny'],
    ['admin.admission.admission-exam-schedules.create', 'admission-exam-schedule.create'],
    ['admin.admission.admission-quotas.index', 'admission-quota.viewAny'],
    ['admin.admission.admission-quotas.create', 'admission-quota.create'],
    ['admin.admission.admission-selection.index', 'admission-selection.viewAny'],
    ['admin.admission.nim-generation-rules.index', 'nim-generation-rule.viewAny'],
    ['admin.admission.nim-generation-rules.create', 'nim-generation-rule.create'],
]);
