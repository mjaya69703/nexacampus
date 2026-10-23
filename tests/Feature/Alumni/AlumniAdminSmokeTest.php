<?php

use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function alumniAdminSmokeSetup(): User
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

function alumniAdminSmokePermission(string $name): void
{
    $role = Role::where('name', 'admin')->firstOrFail();
    $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
}

it('merender halaman admin alumni', function (string $routeName, string $permission) {
    $user = alumniAdminSmokeSetup();
    alumniAdminSmokePermission($permission);

    $this->actingAs($user)
        ->withSession(['active_role' => 'admin'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    ['admin.alumni.alumni-profiles.index', 'alumni-profile.viewAny'],
    ['admin.alumni.alumni-profiles.create', 'alumni-profile.create'],
    ['admin.alumni.employer-partners.index', 'employer-partner.viewAny'],
    ['admin.alumni.employer-partners.create', 'employer-partner.create'],
    ['admin.alumni.job-postings.index', 'job-posting.viewAny'],
    ['admin.alumni.job-postings.create', 'job-posting.create'],
    ['admin.alumni.alumni-events.index', 'alumni-event.viewAny'],
    ['admin.alumni.alumni-events.create', 'alumni-event.create'],
    ['admin.alumni.events.index', 'alumni-event.viewAny'],
    ['admin.alumni.events.create', 'alumni-event.create'],
    ['admin.alumni.tracer-study-campaigns.index', 'tracer-study-campaign.viewAny'],
    ['admin.alumni.tracer-study-campaigns.create', 'tracer-study-campaign.create'],
    ['admin.alumni.tracer-study.index', 'tracer-study-campaign.viewAny'],
    ['admin.alumni.tracer-study.create', 'tracer-study-campaign.create'],
    ['admin.alumni.tracer-study-responses.index', 'tracer-study-response.viewAny'],
]);
