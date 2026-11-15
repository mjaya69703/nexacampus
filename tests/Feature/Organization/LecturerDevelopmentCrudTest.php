<?php

use App\Models\Organization\LecturerPerformanceRubric;
use App\Models\Organization\LecturerWorkloadRule;
use App\Models\Settings\System;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function lecturerDevCrudSetup(): User
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

function lecturerDevCrudPermission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function lecturerDevCrudActing(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

it('menampilkan daftar aturan SKS BKD', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-workload-rule.viewAny');

    LecturerWorkloadRule::create([
        'category' => 'structural', 'source_code' => 'DEKAN', 'name' => 'Dekan',
        'sks_value' => 4, 'maximum_sks' => 4, 'is_active' => true,
    ]);
    LecturerWorkloadRule::create([
        'category' => 'tridharma', 'source_code' => 'RESEARCH', 'name' => 'Penelitian',
        'sks_value' => 3, 'maximum_sks' => 6, 'is_active' => false,
    ]);

    lecturerDevCrudActing($user)
        ->get('/admin/organization/lecturer-workload-rules')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerWorkloadRules/Index')
            ->where('stats.total', 2)
            ->where('stats.active', 1)
            ->where('stats.teaching', 0)
            ->where('stats.tridharma', 1)
            ->has('data.rows', 2));
});

it('membuat, memperbarui, dan menghapus aturan SKS BKD', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission(
        'lecturer-workload-rule.create',
        'lecturer-workload-rule.update',
        'lecturer-workload-rule.delete',
    );

    lecturerDevCrudActing($user)
        ->post('/admin/organization/lecturer-workload-rules', [
            'category' => 'tridharma',
            'source_code' => 'community_service',
            'name' => 'Pengabdian Masyarakat',
            'sks_value' => 2,
            'maximum_sks' => '',
            'is_active' => true,
            'description' => 'Pengabdian terverifikasi.',
        ])
        ->assertRedirect('/admin/organization/lecturer-workload-rules')
        ->assertSessionHas('success');

    $rule = LecturerWorkloadRule::where('source_code', 'COMMUNITY_SERVICE')->firstOrFail();

    expect($rule)
        ->category->toBe('tridharma')
        ->maximum_sks->toBeNull()
        ->is_active->toBeTrue();

    lecturerDevCrudActing($user)
        ->put("/admin/organization/lecturer-workload-rules/{$rule->id}", [
            'category' => 'tridharma',
            'source_code' => 'COMMUNITY_SERVICE',
            'name' => 'Pengabdian Masyarakat Terverifikasi',
            'sks_value' => 2.5,
            'maximum_sks' => 5,
            'is_active' => false,
        ])
        ->assertRedirect('/admin/organization/lecturer-workload-rules')
        ->assertSessionHas('success');

    expect($rule->fresh())
        ->name->toBe('Pengabdian Masyarakat Terverifikasi')
        ->sks_value->toBe('2.50')
        ->maximum_sks->toBe('5.00')
        ->is_active->toBeFalse();

    lecturerDevCrudActing($user)
        ->delete("/admin/organization/lecturer-workload-rules/{$rule->id}")
        ->assertRedirect('/admin/organization/lecturer-workload-rules')
        ->assertSessionHas('success');

    expect(LecturerWorkloadRule::find($rule->id))->toBeNull();
});

it('menolak kode sumber duplikat dalam kategori yang sama', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-workload-rule.create');

    LecturerWorkloadRule::create([
        'category' => 'structural', 'source_code' => 'DEKAN', 'name' => 'Dekan',
        'sks_value' => 4, 'is_active' => true,
    ]);

    lecturerDevCrudActing($user)
        ->post('/admin/organization/lecturer-workload-rules', [
            'category' => 'structural',
            'source_code' => 'dekan',
            'name' => 'Duplikat Dekan',
            'sks_value' => 4,
        ])
        ->assertSessionHasErrors('source_code');

    lecturerDevCrudActing($user)
        ->post('/admin/organization/lecturer-workload-rules', [
            'category' => 'tridharma',
            'source_code' => 'dekan',
            'name' => 'Dekan pada Kategori Berbeda',
            'sks_value' => 4,
        ])
        ->assertRedirect('/admin/organization/lecturer-workload-rules');
});

it('menolak batas maksimum SKS di bawah nilai konversi', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-workload-rule.create');

    lecturerDevCrudActing($user)
        ->post('/admin/organization/lecturer-workload-rules', [
            'category' => 'tridharma',
            'source_code' => 'PUBLICATION',
            'name' => 'Publikasi',
            'sks_value' => 4,
            'maximum_sks' => 2,
        ])
        ->assertSessionHasErrors('maximum_sks');
});

it('menghapus massal aturan SKS BKD', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-workload-rule.delete');

    $first = LecturerWorkloadRule::create([
        'category' => 'structural', 'source_code' => 'TEMP_RULE_A', 'name' => 'Aturan A',
        'sks_value' => 1, 'is_active' => true,
    ]);
    $second = LecturerWorkloadRule::create([
        'category' => 'structural', 'source_code' => 'TEMP_RULE_B', 'name' => 'Aturan B',
        'sks_value' => 1, 'is_active' => true,
    ]);

    lecturerDevCrudActing($user)
        ->post('/admin/organization/lecturer-workload-rules/bulk-destroy', [
            'ids' => [$first->id, $second->id],
        ])
        ->assertSessionHas('success');

    expect(LecturerWorkloadRule::whereIn('id', [$first->id, $second->id])->count())->toBe(0);
});

it('menampilkan daftar rubrik performa dosen', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-performance-rubric.viewAny');

    LecturerPerformanceRubric::create([
        'code' => 'STANDAR', 'name' => 'Rubrik Standar', 'is_active' => true,
        'edom_weight' => 40, 'teaching_weight' => 30, 'attendance_weight' => 20, 'workload_weight' => 10,
        'minimum_responses' => 3, 'target_workload_sks' => 12,
    ]);

    lecturerDevCrudActing($user)
        ->get('/admin/organization/lecturer-performance-rubrics')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/LecturerPerformanceRubrics/Index')
            ->where('stats.total', 1)
            ->where('stats.activeCode', 'STANDAR')
            ->where('stats.targetSks', 12)
            ->has('data.rows', 1));
});

it('membuat rubrik aktif dan menonaktifkan rubrik sebelumnya', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-performance-rubric.create');

    $previous = LecturerPerformanceRubric::create([
        'code' => 'LAMA', 'name' => 'Rubrik Lama', 'is_active' => true,
        'edom_weight' => 40, 'teaching_weight' => 30, 'attendance_weight' => 20, 'workload_weight' => 10,
        'minimum_responses' => 3, 'target_workload_sks' => 12,
    ]);

    lecturerDevCrudActing($user)
        ->post('/admin/organization/lecturer-performance-rubrics', [
            'code' => 'standar-2026',
            'name' => 'Rubrik Standar 2026',
            'edom_weight' => 45,
            'teaching_weight' => 25,
            'attendance_weight' => 20,
            'workload_weight' => 10,
            'minimum_responses' => 5,
            'target_workload_sks' => 12,
            'is_active' => true,
            'notes' => 'Berlaku mulai semester ganjil.',
        ])
        ->assertRedirect('/admin/organization/lecturer-performance-rubrics')
        ->assertSessionHas('success');

    $created = LecturerPerformanceRubric::where('code', 'STANDAR-2026')->firstOrFail();

    expect($created)
        ->name->toBe('Rubrik Standar 2026')
        ->is_active->toBeTrue();

    expect($created->totalWeight())->toBe(100.0);

    expect($previous->fresh()->is_active)->toBeFalse();
});

it('memperbarui rubrik tanpa konflik kode sendiri', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-performance-rubric.update');

    $rubric = LecturerPerformanceRubric::create([
        'code' => 'STANDAR', 'name' => 'Rubrik Standar', 'is_active' => true,
        'edom_weight' => 40, 'teaching_weight' => 30, 'attendance_weight' => 20, 'workload_weight' => 10,
        'minimum_responses' => 3, 'target_workload_sks' => 12,
    ]);

    lecturerDevCrudActing($user)
        ->put("/admin/organization/lecturer-performance-rubrics/{$rubric->id}", [
            'code' => 'standar',
            'name' => 'Rubrik Standar Revisi',
            'edom_weight' => 40,
            'teaching_weight' => 30,
            'attendance_weight' => 20,
            'workload_weight' => 10,
            'minimum_responses' => 4,
            'target_workload_sks' => 14,
            'is_active' => true,
        ])
        ->assertRedirect('/admin/organization/lecturer-performance-rubrics')
        ->assertSessionHas('success');

    expect($rubric->fresh())
        ->code->toBe('STANDAR')
        ->name->toBe('Rubrik Standar Revisi')
        ->minimum_responses->toBe(4)
        ->target_workload_sks->toBe('14.00');
});

it('mengaktifkan rubrik melalui toggle dan menonaktifkan rubrik lain', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-performance-rubric.update');

    $active = LecturerPerformanceRubric::create([
        'code' => 'AKTIF', 'name' => 'Rubrik Aktif', 'is_active' => true,
        'edom_weight' => 40, 'teaching_weight' => 30, 'attendance_weight' => 20, 'workload_weight' => 10,
        'minimum_responses' => 3, 'target_workload_sks' => 12,
    ]);
    $inactive = LecturerPerformanceRubric::create([
        'code' => 'CADANGAN', 'name' => 'Rubrik Cadangan', 'is_active' => false,
        'edom_weight' => 50, 'teaching_weight' => 20, 'attendance_weight' => 20, 'workload_weight' => 10,
        'minimum_responses' => 3, 'target_workload_sks' => 12,
    ]);

    lecturerDevCrudActing($user)
        ->post("/admin/organization/lecturer-performance-rubrics/{$inactive->id}/toggle", ['is_active' => true])
        ->assertSessionHas('success');

    expect($inactive->fresh()->is_active)->toBeTrue();
    expect($active->fresh()->is_active)->toBeFalse();
});

it('menolak hapus rubrik aktif dan mengizinkan hapus rubrik nonaktif', function () {
    $user = lecturerDevCrudSetup();
    lecturerDevCrudPermission('lecturer-performance-rubric.delete');

    $active = LecturerPerformanceRubric::create([
        'code' => 'AKTIF', 'name' => 'Rubrik Aktif', 'is_active' => true,
        'edom_weight' => 40, 'teaching_weight' => 30, 'attendance_weight' => 20, 'workload_weight' => 10,
        'minimum_responses' => 3, 'target_workload_sks' => 12,
    ]);
    $inactive = LecturerPerformanceRubric::create([
        'code' => 'NONAKTIF', 'name' => 'Rubrik Nonaktif', 'is_active' => false,
        'edom_weight' => 40, 'teaching_weight' => 30, 'attendance_weight' => 20, 'workload_weight' => 10,
        'minimum_responses' => 3, 'target_workload_sks' => 12,
    ]);

    lecturerDevCrudActing($user)
        ->delete("/admin/organization/lecturer-performance-rubrics/{$active->id}")
        ->assertSessionHas('error');

    expect(LecturerPerformanceRubric::find($active->id))->not->toBeNull();

    lecturerDevCrudActing($user)
        ->delete("/admin/organization/lecturer-performance-rubrics/{$inactive->id}")
        ->assertRedirect('/admin/organization/lecturer-performance-rubrics')
        ->assertSessionHas('success');

    expect(LecturerPerformanceRubric::find($inactive->id))->toBeNull();
});
