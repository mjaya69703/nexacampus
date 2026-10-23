<?php

use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function organizationAdminSmokeSetup(): User
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

function organizationAdminSmokePermission(string $name): void
{
    $role = Role::where('name', 'admin')->firstOrFail();
    $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
}

it('merender halaman admin kepegawaian dan organisasi', function (string $routeName, string $permission) {
    $user = organizationAdminSmokeSetup();
    organizationAdminSmokePermission($permission);

    $this->actingAs($user)
        ->withSession(['active_role' => 'admin'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    ['admin.organization.user-development-records.index', 'user-development-record.viewAny'],
    ['admin.organization.user-development-records.create', 'user-development-record.create'],
    ['admin.organization.tridharma-records.index', 'tridharma-record.viewAny'],
    ['admin.organization.tridharma-records.create', 'tridharma-record.create'],
    ['admin.organization.lecturer-workload-periods.index', 'lecturer-workload-period.viewAny'],
    ['admin.organization.lecturer-workload-periods.create', 'lecturer-workload-period.create'],
    ['admin.organization.lecturer-workload-rules.index', 'lecturer-workload-rule.viewAny'],
    ['admin.organization.lecturer-workload-rules.create', 'lecturer-workload-rule.create'],
    ['admin.organization.lecturer-workload-submissions.index', 'lecturer-workload-submission.viewAny'],
    ['admin.organization.edom-periods.index', 'edom-period.viewAny'],
    ['admin.organization.edom-periods.create', 'edom-period.create'],
    ['admin.organization.edom-questions.index', 'edom-question.viewAny'],
    ['admin.organization.edom-questions.create', 'edom-question.create'],
    ['admin.organization.lecturer-performance-reviews.index', 'lecturer-performance-review.viewAny'],
    ['admin.organization.lecturer-performance-rubrics.index', 'lecturer-performance-rubric.viewAny'],
    ['admin.organization.lecturer-performance-rubrics.create', 'lecturer-performance-rubric.create'],
    ['admin.organization.employee-profiles.index', 'employee-profile.viewAny'],
    ['admin.organization.employee-profiles.create', 'employee-profile.create'],
    ['admin.organization.organizational-positions.index', 'organizational-position.viewAny'],
    ['admin.organization.organizational-positions.create', 'organizational-position.create'],
    ['admin.organization.employee-position-assignments.index', 'employee-position-assignment.viewAny'],
    ['admin.organization.employee-position-assignments.create', 'employee-position-assignment.create'],
    ['admin.organization.work-units.index', 'work-unit.viewAny'],
    ['admin.organization.work-units.create', 'work-unit.create'],
    ['admin.organization.approval-templates.index', 'approval-template.viewAny'],
    ['admin.organization.approval-templates.create', 'approval-template.create'],
    ['admin.organization.approval-requests.index', 'approval-request.viewAny'],
    ['admin.organization.approval-requests.create', 'approval-request.create'],
    ['admin.organization.employee-leave-types.index', 'employee-leave-type.viewAny'],
    ['admin.organization.employee-leave-types.create', 'employee-leave-type.create'],
    ['admin.organization.employee-leave-requests.index', 'employee-leave-request.viewAny'],
    ['admin.organization.employee-leave-requests.create', 'employee-leave-request.create'],
    ['admin.organization.employee-attendance-records.index', 'employee-attendance-record.viewAny'],
    ['admin.organization.employee-attendance-records.create', 'employee-attendance-record.create'],
    ['admin.organization.employee-leave-balances.index', 'employee-leave-balance.viewAny'],
]);
