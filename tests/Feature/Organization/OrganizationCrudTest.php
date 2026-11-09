<?php

use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeePositionAssignment;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function organizationCrudSetup(): User
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

function organizationCrudPermission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function organizationCrudActing(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

it('menampilkan daftar jabatan hasil seed migrasi', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('organizational-position.viewAny');

    organizationCrudActing($user)
        ->get('/admin/organization/organizational-positions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/OrganizationalPositions/Index')
            ->where('stats.total', 10)
            ->has('data.rows', 10));
});

it('membuat, memperbarui, dan menonaktifkan jabatan', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission(
        'organizational-position.create',
        'organizational-position.update',
    );

    organizationCrudActing($user)
        ->post('/admin/organization/organizational-positions', [
            'name' => 'Koordinator Laboratorium',
            'code' => 'koordinator_lab',
            'category' => 'work_unit',
            'scope_type' => 'work_unit',
            'description' => 'Koordinator laboratorium fakultas.',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/organization/organizational-positions')
        ->assertSessionHas('success');

    $position = OrganizationalPosition::where('code', 'KOORDINATOR_LAB')->firstOrFail();

    organizationCrudActing($user)
        ->put("/admin/organization/organizational-positions/{$position->id}", [
            'name' => 'Koordinator Laboratorium Terpadu',
            'code' => 'KOORDINATOR_LAB',
            'category' => 'work_unit',
            'scope_type' => 'work_unit',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/organization/organizational-positions')
        ->assertSessionHas('success');

    expect($position->fresh()->name)->toBe('Koordinator Laboratorium Terpadu');

    organizationCrudActing($user)
        ->post("/admin/organization/organizational-positions/{$position->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect($position->fresh()->is_active)->toBeFalse();
});

it('menolak kode jabatan duplikat', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('organizational-position.create');

    organizationCrudActing($user)
        ->post('/admin/organization/organizational-positions', [
            'name' => 'Duplikat Rektor',
            'code' => 'REKTOR',
            'category' => 'executive',
            'scope_type' => 'none',
        ])
        ->assertSessionHasErrors('code');
});

it('menolak hapus jabatan yang masih memiliki penugasan', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('organizational-position.delete');

    $position = OrganizationalPosition::where('code', 'REKTOR')->firstOrFail();
    $employee = EmployeeProfile::create([
        'user_id' => User::factory()->create()->id,
        'employee_number' => 'EMP-ORG-1',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    EmployeePositionAssignment::create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => $position->id,
        'is_active' => true,
    ]);

    organizationCrudActing($user)
        ->delete("/admin/organization/organizational-positions/{$position->id}")
        ->assertSessionHas('error');

    expect(OrganizationalPosition::find($position->id))->not->toBeNull();
});

it('menghapus massal jabatan tanpa penugasan', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('organizational-position.delete');

    $first = OrganizationalPosition::create([
        'name' => 'Jabatan Sementara A', 'code' => 'TEMP_A', 'category' => 'staff', 'scope_type' => 'none', 'is_active' => true,
    ]);
    $second = OrganizationalPosition::create([
        'name' => 'Jabatan Sementara B', 'code' => 'TEMP_B', 'category' => 'staff', 'scope_type' => 'none', 'is_active' => true,
    ]);

    organizationCrudActing($user)
        ->post('/admin/organization/organizational-positions/bulk-destroy', [
            'ids' => [$first->id, $second->id],
        ])
        ->assertSessionHas('success');

    expect(OrganizationalPosition::whereIn('id', [$first->id, $second->id])->count())->toBe(0);
});

it('menampilkan daftar jenis cuti hasil seed migrasi', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('employee-leave-type.viewAny');

    organizationCrudActing($user)
        ->get('/admin/organization/employee-leave-types')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeeLeaveTypes/Index')
            ->where('stats.total', 3)
            ->has('data.rows', 3));
});

it('membuat, memperbarui, dan menonaktifkan jenis cuti', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission(
        'employee-leave-type.create',
        'employee-leave-type.update',
    );

    organizationCrudActing($user)
        ->post('/admin/organization/employee-leave-types', [
            'name' => 'Cuti Melahirkan',
            'code' => 'maternity_leave',
            'default_days_per_year' => 90,
            'requires_approval' => true,
            'is_paid' => true,
            'is_active' => true,
            'description' => 'Cuti melahirkan pegawai.',
        ])
        ->assertRedirect('/admin/organization/employee-leave-types')
        ->assertSessionHas('success');

    $leaveType = EmployeeLeaveType::where('code', 'MATERNITY_LEAVE')->firstOrFail();

    organizationCrudActing($user)
        ->put("/admin/organization/employee-leave-types/{$leaveType->id}", [
            'name' => 'Cuti Melahirkan & Menyusui',
            'code' => 'MATERNITY_LEAVE',
            'default_days_per_year' => 90,
            'requires_approval' => true,
            'is_paid' => true,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/organization/employee-leave-types')
        ->assertSessionHas('success');

    expect($leaveType->fresh()->name)->toBe('Cuti Melahirkan & Menyusui');

    organizationCrudActing($user)
        ->post("/admin/organization/employee-leave-types/{$leaveType->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect($leaveType->fresh()->is_active)->toBeFalse();
});

it('menolak kode jenis cuti duplikat', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('employee-leave-type.create');

    organizationCrudActing($user)
        ->post('/admin/organization/employee-leave-types', [
            'name' => 'Duplikat Cuti Tahunan',
            'code' => 'ANNUAL_LEAVE',
        ])
        ->assertSessionHasErrors('code');
});

it('menolak hapus jenis cuti yang masih dipakai saldo cuti', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('employee-leave-type.delete');

    $leaveType = EmployeeLeaveType::where('code', 'ANNUAL_LEAVE')->firstOrFail();
    $employee = EmployeeProfile::create([
        'user_id' => User::factory()->create()->id,
        'employee_number' => 'EMP-ORG-2',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    EmployeeLeaveBalance::create([
        'employee_profile_id' => $employee->id,
        'employee_leave_type_id' => $leaveType->id,
        'year' => (int) now()->year,
        'allocated_days' => 12,
    ]);

    organizationCrudActing($user)
        ->delete("/admin/organization/employee-leave-types/{$leaveType->id}")
        ->assertSessionHas('error');

    expect(EmployeeLeaveType::find($leaveType->id))->not->toBeNull();
});

it('menghapus massal jenis cuti tanpa pemakaian', function () {
    $user = organizationCrudSetup();
    organizationCrudPermission('employee-leave-type.delete');

    $first = EmployeeLeaveType::create([
        'name' => 'Cuti Sementara A', 'code' => 'TEMP_LEAVE_A', 'default_days_per_year' => 0, 'is_active' => true,
    ]);
    $second = EmployeeLeaveType::create([
        'name' => 'Cuti Sementara B', 'code' => 'TEMP_LEAVE_B', 'default_days_per_year' => 0, 'is_active' => true,
    ]);

    organizationCrudActing($user)
        ->post('/admin/organization/employee-leave-types/bulk-destroy', [
            'ids' => [$first->id, $second->id],
        ])
        ->assertSessionHas('success');

    expect(EmployeeLeaveType::whereIn('id', [$first->id, $second->id])->count())->toBe(0);
});
