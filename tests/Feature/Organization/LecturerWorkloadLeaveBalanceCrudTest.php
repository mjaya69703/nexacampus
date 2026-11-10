<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadSubmission;
use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function workloadLeaveSetup(): User
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

function workloadLeavePermission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function workloadLeaveActing(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

function workloadLeaveAcademic(): array
{
    $year = AcademicYear::create([
        'name' => '2025/2026',
        'code' => '2025-2026',
        'semester' => 'Ganjil',
        'start_date' => '2025-08-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    $period = AcademicPeriod::create([
        'academic_year_id' => $year->id,
        'name' => 'Semester Ganjil 2025/2026',
        'code' => '2025-2026-GANJIL',
        'type' => 'Custom',
        'start_at' => '2025-08-01',
        'end_at' => '2025-12-31',
        'is_active' => true,
    ]);

    return [$year, $period];
}

function workloadLeavePeriod(array $attributes = []): LecturerWorkloadPeriod
{
    return LecturerWorkloadPeriod::create(array_merge([
        'name' => 'BKD Semester Ganjil 2025/2026',
        'code' => 'BKD-20251',
        'starts_at' => '2025-12-01',
        'ends_at' => '2025-12-31',
        'status' => 'open',
        'minimum_sks' => 12,
        'maximum_sks' => 16,
    ], $attributes));
}

it('menampilkan daftar periode BKD beserta statistik dan detail laporan', function () {
    $user = workloadLeaveSetup();
    workloadLeavePermission('lecturer-workload-period.viewAny', 'lecturer-workload-period.view');

    [$year, $period] = workloadLeaveAcademic();

    $first = workloadLeavePeriod([
        'academic_year_id' => $year->id,
        'academic_period_id' => $period->id,
    ]);

    workloadLeavePeriod([
        'name' => 'BKD Semester Genap 2025/2026',
        'code' => 'BKD-20252',
        'starts_at' => '2026-05-01',
        'ends_at' => '2026-05-31',
        'status' => 'draft',
    ]);

    $approvedOwner = User::factory()->create();
    $submittedOwner = User::factory()->create();

    LecturerWorkloadSubmission::create([
        'lecturer_workload_period_id' => $first->id,
        'user_id' => $approvedOwner->id,
        'status' => 'approved',
        'teaching_sks' => 10,
        'structural_sks' => 2,
        'tridharma_sks' => 2.5,
        'total_sks' => 14.5,
    ]);

    LecturerWorkloadSubmission::create([
        'lecturer_workload_period_id' => $first->id,
        'user_id' => $submittedOwner->id,
        'status' => 'submitted',
        'teaching_sks' => 12,
        'structural_sks' => 2,
        'tridharma_sks' => 1.5,
        'total_sks' => 15.5,
    ]);

    workloadLeaveActing($user)
        ->get('/admin/organization/lecturer-workload-periods')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerWorkloadPeriods/Index')
            ->where('stats.total', 2)
            ->where('stats.open', 1)
            ->where('stats.review', 0)
            ->where('stats.closed', 0)
            ->has('data.rows', 2)
            ->has('academicYears', 1));

    workloadLeaveActing($user)
        ->get("/admin/organization/lecturer-workload-periods/{$first->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerWorkloadPeriods/Show')
            ->where('period.code', 'BKD-20251')
            ->where('period.submissionsCount', 2)
            ->where('period.approvedCount', 1)
            ->where('period.averageSks', 15)
            ->has('submissions', 2));
});

it('membuat dan memperbarui periode BKD serta menolak kode duplikat', function () {
    $user = workloadLeaveSetup();
    workloadLeavePermission('lecturer-workload-period.create', 'lecturer-workload-period.update');

    [$year, $period] = workloadLeaveAcademic();

    workloadLeaveActing($user)
        ->post('/admin/organization/lecturer-workload-periods', [
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'name' => 'BKD Semester Ganjil 2025/2026',
            'code' => 'bkd-20251',
            'starts_at' => '2025-12-01',
            'ends_at' => '2025-12-31',
            'status' => 'open',
            'minimum_sks' => 12,
            'maximum_sks' => 16,
            'notes' => 'Periode pengajuan BKD dosen.',
        ])
        ->assertRedirect('/admin/organization/lecturer-workload-periods')
        ->assertSessionHas('success');

    $created = LecturerWorkloadPeriod::where('code', 'BKD-20251')->firstOrFail();

    expect($created->academic_year_id)->toBe($year->id)
        ->and($created->academic_period_id)->toBe($period->id)
        ->and($created->status)->toBe('open')
        ->and((float) $created->minimum_sks)->toBe(12.0)
        ->and((float) $created->maximum_sks)->toBe(16.0)
        ->and($created->created_by)->toBe($user->id);

    workloadLeaveActing($user)
        ->put("/admin/organization/lecturer-workload-periods/{$created->id}", [
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'name' => 'BKD Ganjil 2025/2026 (Revisi)',
            'code' => 'BKD-20251',
            'starts_at' => '2025-12-02',
            'ends_at' => '2026-01-05',
            'status' => 'review',
            'minimum_sks' => 12,
            'maximum_sks' => 18,
            'notes' => 'Masa review asesor.',
        ])
        ->assertRedirect('/admin/organization/lecturer-workload-periods')
        ->assertSessionHas('success');

    $created->refresh();

    expect($created->name)->toBe('BKD Ganjil 2025/2026 (Revisi)')
        ->and($created->status)->toBe('review')
        ->and((float) $created->maximum_sks)->toBe(18.0)
        ->and($created->updated_by)->toBe($user->id);

    workloadLeaveActing($user)
        ->post('/admin/organization/lecturer-workload-periods', [
            'name' => 'Periode Duplikat',
            'code' => 'BKD-20251',
            'status' => 'draft',
            'minimum_sks' => 12,
            'maximum_sks' => 16,
        ])
        ->assertSessionHasErrors('code');

    workloadLeaveActing($user)
        ->post('/admin/organization/lecturer-workload-periods', [
            'name' => 'Periode Salah Rentang',
            'code' => 'BKD-20253',
            'status' => 'draft',
            'minimum_sks' => 16,
            'maximum_sks' => 12,
        ])
        ->assertSessionHasErrors('maximum_sks');
});

it('menjaga hapus periode BKD yang sudah memiliki laporan dan menghapus massal terpilih', function () {
    $user = workloadLeaveSetup();
    workloadLeavePermission('lecturer-workload-period.delete');

    $withSubmission = workloadLeavePeriod();
    LecturerWorkloadSubmission::create([
        'lecturer_workload_period_id' => $withSubmission->id,
        'user_id' => User::factory()->create()->id,
        'status' => 'submitted',
        'total_sks' => 12,
    ]);

    workloadLeaveActing($user)
        ->delete("/admin/organization/lecturer-workload-periods/{$withSubmission->id}")
        ->assertSessionHas('error');

    expect(LecturerWorkloadPeriod::find($withSubmission->id))->not->toBeNull();

    $empty = workloadLeavePeriod([
        'name' => 'Periode Tanpa Laporan',
        'code' => 'BKD-EMPTY',
    ]);

    workloadLeaveActing($user)
        ->delete("/admin/organization/lecturer-workload-periods/{$empty->id}")
        ->assertRedirect('/admin/organization/lecturer-workload-periods')
        ->assertSessionHas('success');

    expect(LecturerWorkloadPeriod::find($empty->id))->toBeNull();

    $first = workloadLeavePeriod(['name' => 'Periode Massal A', 'code' => 'BKD-MASS-A']);
    $second = workloadLeavePeriod(['name' => 'Periode Massal B', 'code' => 'BKD-MASS-B']);

    workloadLeaveActing($user)
        ->post('/admin/organization/lecturer-workload-periods/bulk-destroy', [
            'ids' => [$first->id, $second->id],
        ])
        ->assertSessionHas('success');

    expect(LecturerWorkloadPeriod::find($first->id))->toBeNull()
        ->and(LecturerWorkloadPeriod::find($second->id))->toBeNull();
});

it('menampilkan halaman saldo cuti dan menyimpan penyesuaian saldo pegawai', function () {
    $user = workloadLeaveSetup();
    workloadLeavePermission('employee-leave-balance.viewAny', 'employee-leave-balance.create', 'employee-leave-balance.update');

    $leaveType = EmployeeLeaveType::create([
        'name' => 'Cuti Tahunan',
        'code' => 'CT',
        'default_days_per_year' => 12,
        'requires_approval' => true,
        'is_paid' => true,
        'is_active' => true,
    ]);

    $employee = EmployeeProfile::create([
        'user_id' => User::factory()->create()->id,
        'employee_number' => 'EMP-001',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    workloadLeaveActing($user)
        ->get('/admin/organization/employee-leave-balances')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/EmployeeLeaveBalances/Index')
            ->where('stats.totalEmployees', 0)
            ->where('stats.totalBalances', 0)
            ->has('leaveTypes')
            ->has('employees', 1)
            ->has('balances', 0));

    workloadLeaveActing($user)
        ->from('/admin/organization/employee-leave-balances')
        ->post('/admin/organization/employee-leave-balances', [
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated_days' => 12,
            'carried_over_days' => 2,
            'notes' => 'Saldo awal tahun 2026.',
        ])
        ->assertRedirect('/admin/organization/employee-leave-balances')
        ->assertSessionHas('success');

    $balance = EmployeeLeaveBalance::where('employee_profile_id', $employee->id)
        ->where('employee_leave_type_id', $leaveType->id)
        ->where('year', 2026)
        ->firstOrFail();

    expect((float) $balance->allocated_days)->toBe(12.0)
        ->and((float) $balance->carried_over_days)->toBe(2.0)
        ->and($balance->notes)->toBe('Saldo awal tahun 2026.')
        ->and($balance->created_by)->toBe($user->id)
        ->and($balance->updated_by)->toBe($user->id);

    workloadLeaveActing($user)
        ->from('/admin/organization/employee-leave-balances')
        ->post('/admin/organization/employee-leave-balances', [
            'employee_profile_id' => $employee->id,
            'employee_leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated_days' => 15,
            'carried_over_days' => 0,
            'notes' => 'Penyesuaian kuota cuti.',
        ])
        ->assertRedirect('/admin/organization/employee-leave-balances')
        ->assertSessionHas('success');

    expect(EmployeeLeaveBalance::count())->toBe(1);

    $balance->refresh();

    expect((float) $balance->allocated_days)->toBe(15.0)
        ->and((float) $balance->carried_over_days)->toBe(0.0)
        ->and($balance->notes)->toBe('Penyesuaian kuota cuti.');

    $balance->update(['used_days' => 3, 'pending_days' => 1]);

    workloadLeaveActing($user)
        ->get('/admin/organization/employee-leave-balances')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.totalEmployees', 1)
            ->where('stats.totalBalances', 1)
            ->where('stats.totalAllocated', 15)
            ->where('stats.totalUsed', 3)
            ->where('balances.0.availableDays', 11)
            ->has('balances', 1));
});

it('mencari pegawai aktif untuk picker saldo cuti', function () {
    $user = workloadLeaveSetup();
    workloadLeavePermission('employee-leave-balance.create');

    $active = EmployeeProfile::create([
        'user_id' => User::factory()->create(['first_name' => 'Budi', 'last_name' => 'Aktif'])->id,
        'employee_number' => 'EMP-BUDI',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    $inactive = EmployeeProfile::create([
        'user_id' => User::factory()->create(['first_name' => 'Budi', 'last_name' => 'Nonaktif'])->id,
        'employee_number' => 'EMP-BUDI-OFF',
        'employment_type' => 'staff',
        'employment_status' => 'inactive',
        'is_active' => false,
    ]);

    EmployeeProfile::create([
        'user_id' => User::factory()->create(['first_name' => 'Siti', 'last_name' => 'Aktif'])->id,
        'employee_number' => 'EMP-SITI',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    $response = workloadLeaveActing($user)
        ->get('/admin/organization/employee-leave-balances/search-employees?q=Budi')
        ->assertOk();

    $ids = collect($response->json('options'))->pluck('id')->all();

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);

    $all = workloadLeaveActing($user)
        ->get('/admin/organization/employee-leave-balances/search-employees')
        ->assertOk();

    expect($all->json('options'))->toHaveCount(2);
});
