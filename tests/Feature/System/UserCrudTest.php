<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function userCrudSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = User::factory()->create([
        'first_name' => 'Operator',
        'last_name' => 'Test',
        'username' => 'operator',
    ]);
    $operator->assignRole('operator');

    return compact('operator');
}

function giveUserPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingUserOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function userCrudPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Budi',
        'last_name' => 'Santoso',
        'username' => 'budi'.fake()->unique()->numerify('###'),
        'phone' => '08'.fake()->unique()->numerify('##########'),
        'email' => fake()->unique()->safeEmail(),
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
        'is_active' => true,
        'fst_setup' => false,
        'tfa_setup' => false,
    ], $overrides);
}

it('menampilkan daftar pengguna dengan status akun sendiri', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.viewAny']);

    actingUserOperator($setup['operator'])
        ->get('/admin/access/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Access/User/Index')
            ->has('shell')
            ->where('can.toggle', false)
            ->where('stats.total', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.isSelf', true)
            ->where('data.rows.0.username', 'operator'));
});

it('memfilter pengguna berdasarkan peran dan status', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.viewAny']);

    $role = Role::create(['name' => 'petugas', 'guard_name' => 'web']);
    $user = User::factory()->create(['first_name' => 'Andi', 'last_name' => 'Tester', 'is_active' => false]);
    $user->syncRoles([$role]);

    actingUserOperator($setup['operator'])
        ->get("/admin/access/users?role={$role->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.name', 'Andi Tester'));

    actingUserOperator($setup['operator'])
        ->get('/admin/access/users?is_active=0')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.name', 'Andi Tester'));
});

it('menyimpan pengguna baru dengan password ter-hash dan mapping role', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.create']);

    $role = Role::create(['name' => 'petugas', 'guard_name' => 'web']);
    $payload = userCrudPayload(['role_ids' => [$role->id]]);

    actingUserOperator($setup['operator'])
        ->post('/admin/access/users', $payload)
        ->assertRedirect('/admin/access/users')
        ->assertSessionHas('success');

    $user = User::where('username', $payload['username'])->firstOrFail();

    expect(Hash::check('rahasia123', $user->password))->toBeTrue()
        ->and($user->roles->pluck('id')->all())->toBe([$role->id])
        ->and($user->photo)->toContain('default.jpg');
});

it('mengunggah foto profil saat tambah pengguna', function () {
    Storage::fake('public');

    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.create']);

    $payload = userCrudPayload();
    $payload['photo'] = UploadedFile::fake()->image('foto.jpg');

    actingUserOperator($setup['operator'])
        ->post('/admin/access/users', $payload)
        ->assertRedirect('/admin/access/users');

    $user = User::where('username', $payload['username'])->firstOrFail();

    expect($user->getRawOriginal('photo'))->toStartWith('profile_');
    Storage::disk('public')->assertExists('images/profile/'.$user->getRawOriginal('photo'));
});

it('memperbarui pengguna tanpa mengubah password yang dikosongkan', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.update']);

    $role = Role::create(['name' => 'petugas', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->syncRoles([$role]);
    $oldHash = $user->password;

    actingUserOperator($setup['operator'])
        ->put("/admin/access/users/{$user->id}", array_merge(userCrudPayload([
            'username' => $user->username,
            'phone' => $user->phone,
            'email' => $user->email,
        ]), [
            'new_password' => '',
            'new_password_confirmation' => '',
            'role_ids' => [],
        ]))
        ->assertRedirect('/admin/access/users')
        ->assertSessionHas('success');

    expect($user->fresh()->password)->toBe($oldHash)
        ->and($user->fresh()->roles)->toHaveCount(0);
});

it('membuat profil mahasiswa saat role student dipilih beserta datanya', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.update']);

    $studentRole = Role::create(['name' => 'student', 'guard_name' => 'web']);
    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'Teknik Informatika',
        'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
    ]);
    $year = AcademicYear::create([
        'name' => '2026/2027 Ganjil', 'code' => '2627G', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);

    $user = User::factory()->create();

    actingUserOperator($setup['operator'])
        ->put("/admin/access/users/{$user->id}", array_merge(userCrudPayload([
            'username' => $user->username,
            'phone' => $user->phone,
            'email' => $user->email,
        ]), [
            'new_password' => '',
            'new_password_confirmation' => '',
            'role_ids' => [$studentRole->id],
            'student' => [
                'study_program_id' => $program->id,
                'entry_academic_year_id' => $year->id,
                'nim' => '2026TI0001',
                'entry_year' => 2026,
                'academic_status' => 'Aktif',
                'entry_date' => '2026-08-01',
                'current_semester' => 1,
                'is_active' => true,
            ],
        ]))
        ->assertRedirect('/admin/access/users')
        ->assertSessionHas('success');

    expect($user->fresh()->studentProfile)->not->toBeNull()
        ->and($user->fresh()->studentProfile->nim)->toBe('2026TI0001');
});

it('melindungi akun sendiri dari hapus dan nonaktif', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.delete', 'user.update']);

    actingUserOperator($setup['operator'])
        ->delete("/admin/access/users/{$setup['operator']->id}")
        ->assertSessionHas('error');

    expect(User::find($setup['operator']->id))->not->toBeNull();

    actingUserOperator($setup['operator'])
        ->post("/admin/access/users/{$setup['operator']->id}/toggle", ['is_active' => false])
        ->assertSessionHas('error');

    expect((bool) $setup['operator']->fresh()->is_active)->toBeTrue();
});

it('mengubah status dan menghapus pengguna lain', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.delete', 'user.update']);

    $user = User::factory()->create(['is_active' => true]);

    actingUserOperator($setup['operator'])
        ->post("/admin/access/users/{$user->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect((bool) $user->fresh()->is_active)->toBeFalse();

    actingUserOperator($setup['operator'])
        ->delete("/admin/access/users/{$user->id}")
        ->assertRedirect('/admin/access/users');

    expect(User::find($user->id))->toBeNull()
        ->and(User::onlyTrashed()->find($user->id))->not->toBeNull();
});

it('melewati akun sendiri pada hapus massal dan mendukung pulihkan', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.delete']);

    $other = User::factory()->create();

    actingUserOperator($setup['operator'])
        ->post('/admin/access/users/bulk-destroy', ['ids' => [$setup['operator']->id, $other->id]])
        ->assertSessionHas('warning');

    expect(User::find($setup['operator']->id))->not->toBeNull()
        ->and(User::find($other->id))->toBeNull();

    giveUserPermissionsToOperator(['user.viewAny']);

    actingUserOperator($setup['operator'])
        ->get('/admin/access/users?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.mode', 'trash')
            ->has('data.rows', 1));

    actingUserOperator($setup['operator'])
        ->post("/admin/access/users/{$other->id}/restore")
        ->assertSessionHas('success');

    expect(User::find($other->id))->not->toBeNull();
});

it('menghapus permanen user sampah beserta profilnya dan menolak akun sendiri', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.delete']);

    $other = User::factory()->create();
    $other->studentProfile()->create([
        'study_program_id' => StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'FT', 'code' => 'FT', 'is_active' => true])->id,
            'name' => 'TI', 'code' => 'TI', 'degree' => 'S1', 'is_active' => true,
        ])->id,
        'entry_academic_year_id' => AcademicYear::create([
            'name' => '2026/2027', 'code' => '2627', 'semester' => 'Ganjil',
            'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
        ])->id,
        'nim' => '2026TI0099',
        'created_by' => $setup['operator']->id,
    ]);
    $other->delete();

    actingUserOperator($setup['operator'])
        ->delete("/admin/access/users/{$other->id}/force")
        ->assertSessionHas('success');

    expect(User::withTrashed()->find($other->id))->toBeNull()
        ->and(App\Models\Academic\StudentProfile::withTrashed()->where('nim', '2026TI0099')->count())->toBe(0);

    actingUserOperator($setup['operator'])
        ->delete("/admin/access/users/{$setup['operator']->id}/force")
        ->assertSessionHas('error');

    expect(User::find($setup['operator']->id))->not->toBeNull();
});

function makeUserImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['first_name', 'last_name', 'username', 'email', 'phone', 'password', 'role_names', 'gender', 'is_active'],
        ...$rows,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'import-users').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('mengunduh template impor pengguna', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.create']);

    $response = actingUserOperator($setup['operator'])
        ->get('/admin/access/users/import/template')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('template-import-users.xlsx');
});

it('mengimpor pengguna valid beserta role-nya', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.create']);

    Role::create(['name' => 'petugas', 'guard_name' => 'web']);

    $file = makeUserImportFile([
        ['Budi', 'Santoso', 'budi.s', 'budi@kampus.ac.id', '0811111111', 'rahasia123', 'petugas', 'Laki-laki', '1'],
        ['Siti', 'Aminah', 'siti.a', 'siti@kampus.ac.id', '0822222222', 'rahasia123', '', 'Perempuan', '1'],
    ]);

    actingUserOperator($setup['operator'])
        ->post('/admin/access/users/import', ['file' => $file])
        ->assertRedirect('/admin/access/users')
        ->assertSessionHas('success');

    expect(User::where('username', 'budi.s')->firstOrFail()->roles->pluck('name')->all())->toBe(['petugas'])
        ->and(User::where('username', 'siti.a')->firstOrFail()->roles)->toHaveCount(0);
});

it('menolak seluruh file bila ada baris tidak valid', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.create']);

    $existing = User::factory()->create(['username' => 'sudah.ada']);

    $file = makeUserImportFile([
        ['Budi', 'Santoso', 'budi.s', 'budi@kampus.ac.id', '0811111111', 'rahasia123', '', '', ''],
        ['Joko', 'Li', 'sudah.ada', 'bukan-email', '0833333333', 'pendek', 'role-siluman', '', ''],
    ]);

    $response = actingUserOperator($setup['operator'])
        ->post('/admin/access/users/import', ['file' => $file])
        ->assertRedirect('/admin/access/users');

    expect(User::where('username', 'budi.s')->count())->toBe(0);

    $result = $response->getSession()->get('import_result');

    expect($result['success'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('row')->all())->toContain(3);
});

it('menolak file bukan spreadsheet', function () {
    $setup = userCrudSetup();
    giveUserPermissionsToOperator(['user.create']);

    $path = tempnam(sys_get_temp_dir(), 'import-users').'.txt';
    file_put_contents($path, 'bukan spreadsheet');

    actingUserOperator($setup['operator'])
        ->post('/admin/access/users/import', [
            'file' => new UploadedFile($path, 'users.txt', 'text/plain', null, true),
        ])
        ->assertSessionHasErrors('file');
});
