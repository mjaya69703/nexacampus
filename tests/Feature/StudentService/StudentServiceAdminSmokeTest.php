<?php

use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function studentServiceAdminSmokeSetup(): User
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

function studentServiceAdminSmokePermission(string $name): void
{
    $role = Role::where('name', 'admin')->firstOrFail();
    $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
}

it('merender halaman admin layanan mahasiswa', function (string $routeName, string $permission) {
    $user = studentServiceAdminSmokeSetup();
    studentServiceAdminSmokePermission($permission);

    $this->actingAs($user)
        ->withSession(['active_role' => 'admin'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    ['admin.student-services.dashboard.index', 'student-service-dashboard.viewAny'],
    ['admin.student-services.letter-types.index', 'service-letter-type.viewAny'],
    ['admin.student-services.letter-types.create', 'service-letter-type.create'],
    ['admin.student-services.letter-requests.index', 'service-letter-request.viewAny'],
    ['admin.student-services.leave-applications.index', 'leave-application.viewAny'],
    ['admin.student-services.transfer-requests.index', 'transfer-request.viewAny'],
    ['admin.student-services.graduation-batches.index', 'graduation-batch.viewAny'],
    ['admin.student-services.graduation-batches.create', 'graduation-batch.create'],
    ['admin.student-services.graduation-applications.index', 'graduation-application.viewAny'],
    ['admin.student-services.graduation-policies.index', 'graduation-policy.viewAny'],
    ['admin.student-services.graduation-policies.create', 'graduation-policy.create'],
    ['admin.student-services.graduation-document-requirements.index', 'graduation-document-requirement.viewAny'],
    ['admin.student-services.graduation-document-requirements.create', 'graduation-document-requirement.create'],
    ['admin.student-services.complaint-categories.index', 'student-complaint-category.viewAny'],
    ['admin.student-services.complaint-categories.create', 'student-complaint-category.create'],
    ['admin.student-services.complaints.index', 'student-complaint.viewAny'],
]);
