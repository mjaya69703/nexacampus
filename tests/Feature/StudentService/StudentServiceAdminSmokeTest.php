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

it('merender halaman admin layanan mahasiswa', function (string $routeName, string $permission, string $component) {
    $user = studentServiceAdminSmokeSetup();
    studentServiceAdminSmokePermission($permission);

    $this->actingAs($user)
        ->withSession(['active_role' => 'admin'])
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['admin.student-services.dashboard.index', 'student-service-dashboard.viewAny', 'Admin/StudentServices/Dashboard'],
    ['admin.student-services.letter-types.index', 'service-letter-type.viewAny', 'Admin/StudentServices/LetterTypes/Index'],
    ['admin.student-services.letter-types.create', 'service-letter-type.create', 'Admin/StudentServices/LetterTypes/Form'],
    ['admin.student-services.letter-requests.index', 'service-letter-request.viewAny', 'Admin/StudentServices/LetterRequests/Index'],
    ['admin.student-services.leave-applications.index', 'leave-application.viewAny', 'Admin/StudentServices/LeaveApplications/Index'],
    ['admin.student-services.transfer-requests.index', 'transfer-request.viewAny', 'Admin/StudentServices/TransferRequests/Index'],
    ['admin.student-services.graduation-batches.index', 'graduation-batch.viewAny', 'Admin/StudentServices/GraduationBatches/Index'],
    ['admin.student-services.graduation-batches.create', 'graduation-batch.create', 'Admin/StudentServices/GraduationBatches/Form'],
    ['admin.student-services.graduation-applications.index', 'graduation-application.viewAny', 'Admin/StudentServices/GraduationApplications/Index'],
    ['admin.student-services.graduation-policies.index', 'graduation-policy.viewAny', 'Admin/StudentServices/GraduationPolicies/Index'],
    ['admin.student-services.graduation-policies.create', 'graduation-policy.create', 'Admin/StudentServices/GraduationPolicies/Form'],
    ['admin.student-services.graduation-document-requirements.index', 'graduation-document-requirement.viewAny', 'Admin/StudentServices/GraduationDocumentRequirements/Index'],
    ['admin.student-services.graduation-document-requirements.create', 'graduation-document-requirement.create', 'Admin/StudentServices/GraduationDocumentRequirements/Form'],
    ['admin.student-services.complaint-categories.index', 'student-complaint-category.viewAny', 'Admin/StudentServices/ComplaintCategories/Index'],
    ['admin.student-services.complaint-categories.create', 'student-complaint-category.create', 'Admin/StudentServices/ComplaintCategories/Form'],
    ['admin.student-services.complaints.index', 'student-complaint.viewAny', 'Admin/StudentServices/Complaints/Index'],
]);
