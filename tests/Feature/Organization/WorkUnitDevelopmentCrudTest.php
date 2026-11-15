<?php

use App\Models\Organization\EmployeePositionAssignment;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\UserDevelopmentRecord;
use App\Models\Organization\WorkUnit;
use App\Models\Settings\System;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function workUnitDevelopmentSetup(): User
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

function workUnitDevelopmentPermission(string ...$names): void
{
    $role = Role::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
}

function workUnitDevelopmentActing(User $user)
{
    return test()->actingAs($user)->withSession(['active_role' => 'admin']);
}

it('menampilkan daftar unit kerja dan riwayat pengembangan pegawai', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('work-unit.viewAny', 'user-development-record.viewAny');

    $unit = WorkUnit::create([
        'name' => 'Biro Administrasi Akademik',
        'code' => 'BAAK',
        'is_active' => true,
    ]);

    $member = User::factory()->create();
    $unit->members()->attach($member->id, ['position' => 'head', 'is_active' => true]);

    UserDevelopmentRecord::create([
        'user_id' => $member->id,
        'type' => 'certification',
        'title' => 'Sertifikasi Auditor Internal',
        'start_date' => '2026-01-10',
        'is_verified' => false,
    ]);

    workUnitDevelopmentActing($user)
        ->get('/admin/organization/work-units')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/WorkUnits/Index')
            ->where('stats.total', 1)
            ->where('stats.members', 1)
            ->has('data.rows', 1));

    workUnitDevelopmentActing($user)
        ->get('/admin/organization/user-development-records')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Organization/UserDevelopmentRecords/Index')
            ->where('stats.total', 1)
            ->where('stats.pending', 1)
            ->has('data.rows', 1));
});

it('membuat unit kerja beserta anggota dan memperbarui keanggotaan', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('work-unit.create', 'work-unit.update');

    $head = User::factory()->create();
    $staff = User::factory()->create();

    workUnitDevelopmentActing($user)
        ->post('/admin/organization/work-units', [
            'name' => 'Biro Administrasi Akademik',
            'code' => 'baak',
            'description' => 'Melayani administrasi akademik mahasiswa.',
            'is_active' => true,
            'members' => [
                ['user_id' => $head->id, 'position' => 'head', 'is_active' => true],
                ['user_id' => $staff->id, 'position' => 'member', 'is_active' => false],
            ],
        ])
        ->assertRedirect('/admin/organization/work-units')
        ->assertSessionHas('success');

    $unit = WorkUnit::where('code', 'BAAK')->firstOrFail();

    expect($unit->members)->toHaveCount(2)
        ->and($unit->members->firstWhere('id', $head->id)->pivot->position)->toBe('head')
        ->and((bool) $unit->members->firstWhere('id', $staff->id)->pivot->is_active)->toBeFalse();

    workUnitDevelopmentActing($user)
        ->put("/admin/organization/work-units/{$unit->id}", [
            'name' => 'Biro Administrasi Akademik & Kemahasiswaan',
            'code' => 'BAAK',
            'description' => 'Unit gabungan akademik dan kemahasiswaan.',
            'is_active' => true,
            'members' => [
                ['user_id' => $staff->id, 'position' => 'coordinator', 'is_active' => true],
            ],
        ])
        ->assertRedirect('/admin/organization/work-units')
        ->assertSessionHas('success');

    $unit->refresh();

    expect($unit->name)->toBe('Biro Administrasi Akademik & Kemahasiswaan')
        ->and($unit->members()->count())->toBe(1)
        ->and($unit->members->firstWhere('id', $staff->id)->pivot->position)->toBe('coordinator')
        ->and($unit->members()->whereKey($head->id)->exists())->toBeFalse();

    workUnitDevelopmentActing($user)
        ->post("/admin/organization/work-units/{$unit->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect($unit->fresh()->is_active)->toBeFalse();
});

it('menolak menghapus unit kerja yang masih memiliki anggota atau penugasan', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('work-unit.delete');

    $withMembers = WorkUnit::create(['name' => 'Unit Anggota', 'code' => 'UNIT-MEMBER', 'is_active' => true]);
    $withMembers->members()->attach(User::factory()->create()->id, ['position' => 'member', 'is_active' => true]);

    workUnitDevelopmentActing($user)
        ->delete("/admin/organization/work-units/{$withMembers->id}")
        ->assertSessionHas('error');

    expect(WorkUnit::find($withMembers->id))->not->toBeNull();

    $withAssignment = WorkUnit::create(['name' => 'Unit Penugasan', 'code' => 'UNIT-ASSIGN', 'is_active' => true]);
    $employee = EmployeeProfile::create([
        'user_id' => User::factory()->create()->id,
        'employee_number' => 'EMP-WU-1',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    EmployeePositionAssignment::create([
        'employee_profile_id' => $employee->id,
        'organizational_position_id' => OrganizationalPosition::firstOrFail()->id,
        'work_unit_id' => $withAssignment->id,
        'is_active' => true,
    ]);

    workUnitDevelopmentActing($user)
        ->delete("/admin/organization/work-units/{$withAssignment->id}")
        ->assertSessionHas('error');

    expect(WorkUnit::find($withAssignment->id))->not->toBeNull();

    $withPrimary = WorkUnit::create(['name' => 'Unit Utama', 'code' => 'UNIT-PRIMARY', 'is_active' => true]);
    EmployeeProfile::create([
        'user_id' => User::factory()->create()->id,
        'employee_number' => 'EMP-WU-2',
        'employment_type' => 'staff',
        'employment_status' => 'active',
        'primary_work_unit_id' => $withPrimary->id,
        'is_active' => true,
    ]);

    workUnitDevelopmentActing($user)
        ->delete("/admin/organization/work-units/{$withPrimary->id}")
        ->assertSessionHas('error');

    expect(WorkUnit::find($withPrimary->id))->not->toBeNull();
});

it('menghapus unit kerja tanpa anggota dan menghapus massal unit kerja', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('work-unit.delete');

    $empty = WorkUnit::create(['name' => 'Unit Kosong', 'code' => 'UNIT-EMPTY', 'is_active' => true]);

    workUnitDevelopmentActing($user)
        ->delete("/admin/organization/work-units/{$empty->id}")
        ->assertRedirect('/admin/organization/work-units')
        ->assertSessionHas('success');

    expect(WorkUnit::find($empty->id))->toBeNull();

    $first = WorkUnit::create(['name' => 'Unit Massal A', 'code' => 'UNIT-MASS-A', 'is_active' => true]);
    $second = WorkUnit::create(['name' => 'Unit Massal B', 'code' => 'UNIT-MASS-B', 'is_active' => true]);
    $second->members()->attach(User::factory()->create()->id, ['position' => 'member', 'is_active' => true]);

    workUnitDevelopmentActing($user)
        ->post('/admin/organization/work-units/bulk-destroy', ['ids' => [$first->id, $second->id]])
        ->assertSessionHas('success');

    expect(WorkUnit::whereIn('id', [$first->id, $second->id])->count())->toBe(0);
});

it('mencari pegawai untuk picker anggota unit kerja dan mengecualikan anggota terpilih', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('work-unit.create');

    $candidate = User::factory()->create(['first_name' => 'Zulkifli', 'last_name' => 'Pencarian']);
    $excluded = User::factory()->create(['first_name' => 'Zulkifli', 'last_name' => 'Dikecualikan']);

    $response = workUnitDevelopmentActing($user)
        ->get('/admin/organization/work-units/search-users?q=Zulkifli&exclude='.$excluded->id)
        ->assertOk();

    $ids = collect($response->json('options'))->pluck('id')->all();

    expect($ids)->toContain($candidate->id)
        ->and($ids)->not->toContain($excluded->id);
});

it('membuat riwayat pengembangan dengan lampiran dan memverifikasinya', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('user-development-record.create', 'user-development-record.update');

    $owner = User::factory()->create();

    workUnitDevelopmentActing($user)
        ->post('/admin/organization/user-development-records', [
            'user_id' => $owner->id,
            'type' => 'certification',
            'title' => 'Sertifikasi Auditor Internal ISO 9001',
            'organizer' => 'Badan Standardisasi Nasional',
            'credential_number' => 'ISO/IA/2026/00123',
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
            'expires_at' => '2029-01-12',
            'cost' => 1500000,
            'description' => 'Pelatihan dan uji kompetensi auditor internal.',
            'is_verified' => true,
            'verification_notes' => 'Berkas lengkap dan valid.',
            'attachment' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect('/admin/organization/user-development-records')
        ->assertSessionHas('success');

    $record = UserDevelopmentRecord::with('attachments')->where('title', 'Sertifikasi Auditor Internal ISO 9001')->firstOrFail();

    expect($record->user_id)->toBe($owner->id)
        ->and($record->is_verified)->toBeTrue()
        ->and($record->verified_by)->toBe($user->id)
        ->and($record->verified_at)->not->toBeNull()
        ->and($record->attachments)->toHaveCount(1);

    Storage::disk('local')->assertExists($record->attachments->first()->file_path);

    workUnitDevelopmentActing($user)
        ->post("/admin/organization/user-development-records/{$record->id}/unverify")
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->is_verified)->toBeFalse()
        ->and($record->verified_by)->toBeNull()
        ->and($record->verified_at)->toBeNull();

    workUnitDevelopmentActing($user)
        ->post("/admin/organization/user-development-records/{$record->id}/verify")
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->is_verified)->toBeTrue()
        ->and($record->verified_by)->toBe($user->id)
        ->and($record->verified_at)->not->toBeNull();
});

it('memperbarui riwayat pengembangan tanpa menghapus jejak verifikasi lama', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('user-development-record.update');

    $owner = User::factory()->create();
    $verifier = User::factory()->create();
    $verifiedAt = now()->subDays(3)->startOfSecond();

    $record = UserDevelopmentRecord::create([
        'user_id' => $owner->id,
        'type' => 'training',
        'title' => 'Pelatihan Manajemen Mutu',
        'start_date' => '2026-02-01',
        'is_verified' => true,
        'verified_by' => $verifier->id,
        'verified_at' => $verifiedAt,
    ]);

    workUnitDevelopmentActing($user)
        ->put("/admin/organization/user-development-records/{$record->id}", [
            'type' => 'training',
            'title' => 'Pelatihan Manajemen Mutu Terpadu',
            'start_date' => '2026-02-01',
            'is_verified' => true,
            'verification_notes' => 'Catatan diperbarui.',
        ])
        ->assertRedirect('/admin/organization/user-development-records')
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->title)->toBe('Pelatihan Manajemen Mutu Terpadu')
        ->and($record->is_verified)->toBeTrue()
        ->and($record->verified_by)->toBe($verifier->id)
        ->and($record->verified_at->equalTo($verifiedAt))->toBeTrue();

    workUnitDevelopmentActing($user)
        ->put("/admin/organization/user-development-records/{$record->id}", [
            'type' => 'training',
            'title' => 'Pelatihan Manajemen Mutu Terpadu',
            'start_date' => '2026-02-01',
            'is_verified' => false,
        ])
        ->assertRedirect('/admin/organization/user-development-records')
        ->assertSessionHas('success');

    $record->refresh();

    expect($record->is_verified)->toBeFalse()
        ->and($record->verified_by)->toBeNull()
        ->and($record->verified_at)->toBeNull();
});

it('menghapus dan menghapus massal riwayat pengembangan', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('user-development-record.delete');

    $owner = User::factory()->create();

    $first = UserDevelopmentRecord::create([
        'user_id' => $owner->id,
        'type' => 'seminar',
        'title' => 'Seminar Nasional Pendidikan',
        'start_date' => '2026-03-01',
    ]);

    workUnitDevelopmentActing($user)
        ->delete("/admin/organization/user-development-records/{$first->id}")
        ->assertRedirect('/admin/organization/user-development-records')
        ->assertSessionHas('success');

    expect(UserDevelopmentRecord::find($first->id))->toBeNull();

    $second = UserDevelopmentRecord::create([
        'user_id' => $owner->id,
        'type' => 'workshop',
        'title' => 'Workshop Kurikulum OBE',
        'start_date' => '2026-03-05',
    ]);
    $third = UserDevelopmentRecord::create([
        'user_id' => $owner->id,
        'type' => 'award',
        'title' => 'Penghargaan Dosen Berprestasi',
        'start_date' => '2026-03-10',
    ]);

    workUnitDevelopmentActing($user)
        ->post('/admin/organization/user-development-records/bulk-destroy', ['ids' => [$second->id, $third->id]])
        ->assertSessionHas('success');

    expect(UserDevelopmentRecord::whereIn('id', [$second->id, $third->id])->count())->toBe(0);
});

it('mencari pegawai untuk picker pemilik riwayat pengembangan', function () {
    $user = workUnitDevelopmentSetup();
    workUnitDevelopmentPermission('user-development-record.create');

    $candidate = User::factory()->create(['first_name' => 'Yohana', 'last_name' => 'Pencarian']);

    $response = workUnitDevelopmentActing($user)
        ->get('/admin/organization/user-development-records/search-users?q=Yohana')
        ->assertOk();

    $option = collect($response->json('options'))->firstWhere('id', $candidate->id);

    expect($option)->not->toBeNull()
        ->and($option['label'])->toContain('Yohana Pencarian');
});
