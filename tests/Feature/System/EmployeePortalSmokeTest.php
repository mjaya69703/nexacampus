<?php

use App\Models\Organization\EmployeeProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function employeePortalSmokeSetup(): User
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
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('admin');
    EmployeeProfile::create(['user_id' => $user->id, 'is_active' => true]);

    return $user;
}

it('merender seluruh halaman self-service pegawai', function (string $routeName) {
    $user = employeePortalSmokeSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'admin'])
        ->get(route($routeName))
        ->assertOk();
})->with([
    'kehadiran' => ['employee.attendance.index'],
    'tridharma' => ['employee.tridharma.index'],
    'tambah tridharma' => ['employee.tridharma.create'],
]);
