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

it('merender halaman admin kepegawaian dan organisasi', function (string $routeName, string $permission, ?string $component = null) {
    $user = organizationAdminSmokeSetup();
    organizationAdminSmokePermission($permission);

    $response = $this->actingAs($user)
        ->withSession(['active_role' => 'admin'])
        ->get(route($routeName))
        ->assertOk();

    if ($component !== null) {
        $response->assertInertia(fn ($page) => $page->component($component));
    }
})->with([
    ['admin.organization.user-development-records.index', 'user-development-record.viewAny', 'Admin/Organization/UserDevelopmentRecords/Index'],
    ['admin.organization.user-development-records.create', 'user-development-record.create', 'Admin/Organization/UserDevelopmentRecords/Form'],
    ['admin.organization.tridharma-records.index', 'tridharma-record.viewAny', 'Admin/Organization/TridharmaRecords/Index'],
    ['admin.organization.tridharma-records.create', 'tridharma-record.create', 'Admin/Organization/TridharmaRecords/Form'],
    ['admin.organization.lecturer-workload-periods.index', 'lecturer-workload-period.viewAny', 'Admin/Organization/LecturerWorkloadPeriods/Index'],
    ['admin.organization.lecturer-workload-periods.create', 'lecturer-workload-period.create', 'Admin/Organization/LecturerWorkloadPeriods/Form'],
    ['admin.organization.lecturer-workload-rules.index', 'lecturer-workload-rule.viewAny', 'Admin/Organization/LecturerWorkloadRules/Index'],
    ['admin.organization.lecturer-workload-rules.create', 'lecturer-workload-rule.create', 'Admin/Organization/LecturerWorkloadRules/Form'],
    ['admin.organization.lecturer-workload-submissions.index', 'lecturer-workload-submission.viewAny', 'Admin/Organization/LecturerWorkloadSubmissions/Index'],
    ['admin.organization.edom-periods.index', 'edom-period.viewAny', 'Admin/Organization/EdomPeriods/Index'],
    ['admin.organization.edom-periods.create', 'edom-period.create', 'Admin/Organization/EdomPeriods/Form'],
    ['admin.organization.edom-questions.index', 'edom-question.viewAny', 'Admin/Organization/EdomQuestions/Index'],
    ['admin.organization.edom-questions.create', 'edom-question.create', 'Admin/Organization/EdomQuestions/Form'],
    ['admin.organization.lecturer-performance-reviews.index', 'lecturer-performance-review.viewAny', 'Admin/Organization/LecturerPerformanceReviews/Index'],
    ['admin.organization.lecturer-performance-rubrics.index', 'lecturer-performance-rubric.viewAny', 'Admin/Organization/LecturerPerformanceRubrics/Index'],
    ['admin.organization.lecturer-performance-rubrics.create', 'lecturer-performance-rubric.create', 'Admin/Organization/LecturerPerformanceRubrics/Form'],
    ['admin.organization.employee-profiles.index', 'employee-profile.viewAny'],
    ['admin.organization.employee-profiles.create', 'employee-profile.create'],
    ['admin.organization.organizational-positions.index', 'organizational-position.viewAny', 'Admin/Organization/OrganizationalPositions/Index'],
    ['admin.organization.organizational-positions.create', 'organizational-position.create', 'Admin/Organization/OrganizationalPositions/Form'],
    ['admin.organization.employee-position-assignments.index', 'employee-position-assignment.viewAny'],
    ['admin.organization.employee-position-assignments.create', 'employee-position-assignment.create'],
    ['admin.organization.work-units.index', 'work-unit.viewAny', 'Admin/Organization/WorkUnits/Index'],
    ['admin.organization.work-units.create', 'work-unit.create', 'Admin/Organization/WorkUnits/Form'],
    ['admin.organization.approval-templates.index', 'approval-template.viewAny'],
    ['admin.organization.approval-templates.create', 'approval-template.create'],
    ['admin.organization.approval-requests.index', 'approval-request.viewAny'],
    ['admin.organization.approval-requests.create', 'approval-request.create'],
    ['admin.organization.employee-leave-types.index', 'employee-leave-type.viewAny', 'Admin/Organization/EmployeeLeaveTypes/Index'],
    ['admin.organization.employee-leave-types.create', 'employee-leave-type.create', 'Admin/Organization/EmployeeLeaveTypes/Form'],
    ['admin.organization.employee-leave-requests.index', 'employee-leave-request.viewAny'],
    ['admin.organization.employee-leave-requests.create', 'employee-leave-request.create'],
    ['admin.organization.employee-attendance-records.index', 'employee-attendance-record.viewAny'],
    ['admin.organization.employee-attendance-records.create', 'employee-attendance-record.create'],
    ['admin.organization.employee-leave-balances.index', 'employee-leave-balance.viewAny', 'Admin/Organization/EmployeeLeaveBalances/Index'],
]);
